"""Exercise real PHP handlers with private data using CLI, without network sockets.

HTTP integration tests remain required for routing, headers, and cookie transport.
OEM_TEST_ROOT can point this suite at the original source to reproduce defects.
"""
import json
import os
from pathlib import Path
import re
import sqlite3
import subprocess
import tempfile
import time
import unittest

ROOT = Path(os.environ.get('OEM_TEST_ROOT', Path(__file__).resolve().parents[1]))
PHP = json.loads(os.environ.get('PHP_COMMAND', '["php"]'))
HARNESS = r'''
$request = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$_SERVER['DOCUMENT_ROOT'] = getenv('OEM_TEST_ROOT');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_POST = $request['post'] ?? [];
ob_start();
register_shutdown_function(function () {
    $body = ob_get_clean();
    echo json_encode(['status' => http_response_code() ?: 200,
        'session' => session_id(), 'body' => $body], JSON_THROW_ON_ERROR);
});
require getenv('OEM_TEST_ROOT') . '/app.php';
if (!empty($request['session'])) {
    session_id($request['session']);
}
eval($request['code']);
'''


class QuoteRegressionTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.data = Path(self.temp.name) / 'data'
        self.data.mkdir(mode=0o700)
        self.sessions = Path(self.temp.name) / 'sessions'
        self.sessions.mkdir(mode=0o700)
        self.env = os.environ.copy()
        for key in ['TELEGRAM_BOT_TOKEN', 'TELEGRAM_CHAT_ID', 'OEM_ADMIN_USER',
                    'OEM_ADMIN_PASSWORD_HASH', 'OEM_DATA_DIR', 'OEM_TEST_HTTP']:
            self.env.pop(key, None)
        self.env.update(OEM_DATA_DIR=str(self.data), OEM_TEST_ROOT=str(ROOT))
        self.run_php('oem_db();')
        with self.db() as db:
            db.execute('INSERT INTO market_rate VALUES (1, ?, ?)', (5000000, int(time.time())))

    def db(self):
        return sqlite3.connect(self.data / 'orders.sqlite')

    def run_php(self, code, session=None, post=None, options=(), env=None):
        result = subprocess.run(
            PHP + ['-d', 'session.save_path=' + str(self.sessions), *options, '-r', HARNESS],
            input=json.dumps({'code': code, 'session': session, 'post': post}),
            text=True, capture_output=True, env=env or self.env, check=True)
        return json.loads(result.stdout)

    def form(self, service='bootloader'):
        result = self.run_php(
            'echo json_encode(["csrf" => oem_csrf(), "quote" => '
            'oem_quote(oem_db(), ' + json.dumps(service) + ')]);')
        body = json.loads(result['body'])
        return result['session'], {
            'csrf': body['csrf'], 'quote_id': body['quote']['id'], 'service': service,
            'email': 'owner@example.com', 'model': 'Motorola test',
            'txid': os.urandom(32).hex(), 'ownership': '1',
        }

    def submit(self, session, form):
        return self.run_php('require getenv("OEM_TEST_ROOT") . "/process_order.php";',
                            session=session, post=form)

    def test_leaked_quote_rejected_without_consuming_owner_quote(self):
        owner, form = self.form()
        attacker, stolen = self.form()
        stolen['quote_id'] = form['quote_id']
        self.assertEqual(self.submit(attacker, stolen)['status'], 409)
        with self.db() as db:
            self.assertEqual(db.execute('SELECT COUNT(*) FROM orders').fetchone()[0], 0)
        self.assertEqual(self.submit(owner, form)['status'], 201)
        form['txid'] = os.urandom(32).hex()
        self.assertEqual(self.submit(owner, form)['status'], 409)

    def test_pruning_preserves_late_payment_and_order_audit_evidence(self):
        owner, form = self.form()
        self.assertEqual(self.submit(owner, form)['status'], 201)
        late_session, late_form = self.form('frp')
        now = int(time.time())
        with self.db() as db:
            saved = db.execute('SELECT * FROM orders').fetchall()
            order_id = db.execute('SELECT id FROM orders').fetchone()[0]
            db.execute('INSERT INTO order_events (order_id,created_at,actor,status,notes) '
                       'VALUES (?, ?, ?, ?, ?)', (order_id, 'test', 'reviewer', 'pending_review', 'test'))
            events = db.execute('SELECT * FROM order_events').fetchall()
            db.execute('UPDATE quotes SET created_at = ? WHERE id = ?',
                       (now - 86401, form['quote_id']))
            db.execute('UPDATE quotes SET created_at = ?, expires_at = ? WHERE id = ?',
                       (now - 86340, now - 85440, late_form['quote_id']))
            db.execute('INSERT INTO quotes VALUES (?, ?, ?, ?, ?, ?, ?)',
                       ('abandoned', 'frp', 3000, 5000000, '0.00060000', now - 86401, now - 85501))
        # Reuse a still-current session quote: cleanup must run before returning it.
        self.run_php('oem_quote(oem_db(), "frp");', session=late_session)
        with self.db() as db:
            remaining = {row[0] for row in db.execute('SELECT id FROM quotes')}
            self.assertNotIn('abandoned', remaining)
            self.assertNotIn(form['quote_id'], remaining)
            self.assertIn(late_form['quote_id'], remaining)
            self.assertEqual(db.execute('SELECT * FROM orders').fetchall(), saved)
            self.assertEqual(db.execute('SELECT * FROM order_events').fetchall(), events)
        result = self.submit(late_session, late_form)
        self.assertEqual(result['status'], 201)
        with self.db() as db:
            self.assertEqual(db.execute('SELECT amount_btc FROM orders WHERE quote_id = ?',
                                       (late_form['quote_id'],)).fetchone()[0], '0.00060000')

    def test_quotes_past_reporting_window_still_rejected(self):
        session, form = self.form()
        with self.db() as db:
            db.execute('UPDATE quotes SET created_at = ?', (int(time.time()) - 86401,))
        self.assertEqual(self.submit(session, form)['status'], 409)

    def test_secure_cookie_default_ignores_forwarded_header_and_cli_optout(self):
        for https in ['', 'off', 'on']:
            with self.subTest(https=https):
                result = self.run_php(
                    '$_SERVER["HTTPS"] = ' + json.dumps(https) + ';'
                    '$_SERVER["HTTP_X_FORWARDED_PROTO"] = "http";'
                    'oem_session(); echo json_encode(session_get_cookie_params());',
                    env={**self.env, 'OEM_TEST_HTTP': '1'})
                cookie = json.loads(result['body'])
                self.assertTrue(cookie['secure'])
                self.assertTrue(cookie['httponly'])
                self.assertEqual(cookie['samesite'], 'Strict')

    def test_preflight_cannot_claim_live_pricing_from_cache(self):
        # A fresh cache is available, but live requests deliberately cannot work.
        self.assertEqual(self.run_php('echo oem_rate(oem_db())["usd_cents"];',
                                     options=['-d', 'disable_functions=curl_init'])['body'], '5000000')
        result = subprocess.run(
            PHP + ['-d', 'session.save_path=' + str(self.sessions), '-d',
                   'disable_functions=curl_init', str(ROOT / 'tools/preflight.php')],
            env=self.env, text=True, capture_output=True)
        self.assertEqual(result.returncode, 1)
        self.assertNotIn('PASS: Current BTC/USD rate available', result.stdout)
        self.assertNotIn('PASS: Live Coinbase', result.stdout)
        self.assertIn('FAIL: Storage and live pricing ready', result.stdout)
        with self.db() as db:
            self.assertEqual(db.execute('SELECT usd_cents FROM market_rate').fetchone()[0], 5000000)

    def test_wallet_is_identical_to_fixed_original_bytes(self):
        wallet = re.search(rb"const OEM_WALLET = '([^']+)'", (ROOT / 'app.php').read_bytes()).group(1)
        self.assertEqual(wallet, b'bc1qtema35w6qeeqmwnuxr3u9f3nx3gvqc79cl7dxv')


if __name__ == '__main__':
    unittest.main(verbosity=2)
