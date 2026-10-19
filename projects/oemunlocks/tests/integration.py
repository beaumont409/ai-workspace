"""Isolated HTTP integration tests. No real payments or Telegram messages are sent."""
import base64
import http.cookiejar
import json
import os
from pathlib import Path
import re
import socket
import sqlite3
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = json.loads(os.environ.get("PHP_COMMAND", '["php"]'))
WALLET = "bc1qtema35w6qeeqmwnuxr3u9f3nx3gvqc79cl7dxv"
PASSWORD = "integration-test-only-password"


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


class Client:
    def __init__(self, base):
        self.base = base
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect()
        )

    def request(self, path, data=None, auth=False, json_response=False, method=None):
        headers = {}
        if auth:
            raw = ("reviewer:" + PASSWORD).encode()
            headers["Authorization"] = "Basic " + base64.b64encode(raw).decode()
        if json_response:
            headers["Accept"] = "application/json"
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        request = urllib.request.Request(self.base + path, body, headers, method=method)
        try:
            response = self.opener.open(request, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        return response.code, response.read().decode(), response.headers

    def form(self, service="bootloader"):
        code, html, _ = self.request("/payment.php?service=" + service)
        if code != 200:
            raise AssertionError((code, html))
        fields = dict(re.findall(r'name="([^"]+)" value="([^"]*)"', html))
        fields.update(email="owner@example.com", model="Motorola test device",
                      txid=os.urandom(32).hex(), ownership="1")
        return fields, html


class Server:
    def __init__(self, directory, configure=True, admin=True, curl=True):
        self.directory = Path(directory)
        self.directory.mkdir()
        self.env = os.environ.copy()
        for key in ["TELEGRAM_BOT_TOKEN", "TELEGRAM_CHAT_ID", "OEM_DATA_DIR",
                    "OEM_ADMIN_USER", "OEM_ADMIN_PASSWORD_HASH"]:
            self.env.pop(key, None)
        if configure:
            self.env["OEM_DATA_DIR"] = str(self.directory)
        if admin:
            self.env["OEM_ADMIN_USER"] = "reviewer"
            self.env["OEM_ADMIN_PASSWORD_HASH"] = subprocess.check_output(
                PHP + ["-r", "echo password_hash(getenv('TEST_PASSWORD'), PASSWORD_DEFAULT);"],
                env={**self.env, "TEST_PASSWORD": PASSWORD}, text=True
            )
        if configure:
            subprocess.run(
                PHP + ["-r", "require " + repr(str(ROOT / "app.php")) + "; oem_db();"],
                env=self.env, check=True, capture_output=True
            )
            self.seed_rate()
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        self.base = "http://127.0.0.1:" + str(port)
        self.log = tempfile.TemporaryFile()
        sessions = self.directory / "test-sessions"
        sessions.mkdir(mode=0o700)
        command = PHP + ["-d", "session.save_path=" + str(sessions)]
        command += [] if curl else ["-d", "disable_functions=curl_init"]
        self.process = subprocess.Popen(
            command + ["-S", "127.0.0.1:" + str(port), "-t", str(ROOT)],
            env=self.env, stdout=self.log, stderr=self.log
        )
        for _ in range(100):
            try:
                urllib.request.urlopen(self.base + "/index.html", timeout=.1).close()
                break
            except OSError:
                time.sleep(.02)
        else:
            self.close()
            raise RuntimeError("Test server did not start")

    def seed_rate(self, timestamp=None):
        with self.db() as db:
            db.execute("INSERT OR REPLACE INTO market_rate VALUES (1, ?, ?)",
                       (5000000, int(time.time()) if timestamp is None else timestamp))

    def db(self):
        connection = sqlite3.connect(self.directory / "orders.sqlite")
        connection.row_factory = sqlite3.Row
        return connection

    def close(self):
        self.process.terminate()
        self.process.wait(timeout=5)
        self.log.close()


class OEMTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.server = Server(Path(self.temp.name) / "data")
        self.client = Client(self.server.base)

    def tearDown(self):
        self.server.close()
        self.temp.cleanup()

    def submit(self, client=None):
        client = client or self.client
        form, _ = client.form()
        return form, client.request("/process_order.php", form, json_response=True)

    def order(self):
        with self.server.db() as db:
            return dict(db.execute("SELECT * FROM orders LIMIT 1").fetchone())

    def review(self, status, version=0, **extra):
        code, html, _ = self.client.request("/orders.php", auth=True)
        self.assertEqual(code, 200)
        csrf = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
        return self.client.request("/orders.php", {
            "id": self.order()["id"], "csrf": csrf, "version": str(version),
            "status": status, "notes": extra.pop("notes", ""), **extra
        }, auth=True)

    def test_01_homepage_prices_and_original_links(self):
        code, html, _ = self.client.request("/index.html")
        self.assertEqual(code, 200)
        for service, price in [("bootloader", "45"), ("frp", "30")]:
            self.assertIn('href="payment.php?service=' + service + '"', html)
            self.assertIn('data-button-price>$' + price + '.00', html)
        self.assertEqual(self.client.request("/oem-preview.html")[1], html)

    def test_02_live_prices_match_catalog(self):
        code, body, _ = self.client.request("/prices.php")
        self.assertEqual(code, 200)
        services = json.loads(body)["services"]
        self.assertEqual(services["bootloader"], {
            "name": "Bootloader Unlock", "usd": "45.00", "btc": "0.00090000"
        })
        self.assertEqual(services["frp"]["btc"], "0.00060000")
        self.assertEqual(services["frp"]["usd"], "30.00")

    def test_03_wallet_and_quote_consistency(self):
        first, html = self.client.form()
        second, _ = self.client.form()
        self.assertIn(WALLET, html)
        self.assertIn("0.00090000 BTC", html)
        self.assertEqual(first["quote_id"], second["quote_id"])
        _, frp_html = self.client.form("frp")
        self.assertIn("$30.00 USD", frp_html)
        self.assertIn("0.00060000 BTC", frp_html)

    def test_04_persistent_pending_order_and_ignored_client_prices(self):
        form, _ = self.client.form()
        form.update(amount_btc="0", usd_cents="1", wallet="attacker")
        code, body, _ = self.client.request("/process_order.php", form, json_response=True)
        self.assertEqual(code, 201, body)
        self.assertIn("not yet been verified", json.loads(body)["message"])
        order = self.order()
        self.assertEqual(order["status"], "pending_review")
        self.assertEqual(order["wallet"], WALLET)
        self.assertEqual(order["amount_btc"], "0.00090000")
        self.assertEqual(order["usd_cents"], 4500)
        self.assertEqual(order["notification"], "not_configured")

    def test_05_invalid_fields_rejected(self):
        form, _ = self.client.form()
        cases = [
            ("csrf", "", 403), ("service", "unknown", 422),
            ("email", "not-an-email", 422), ("model", "", 422),
            ("model", "a\nb", 422), ("txid", "bad", 422),
            ("ownership", "", 422), ("quote_id", "unknown", 409),
        ]
        for key, value, expected in cases:
            with self.subTest(field=key, value=value):
                code, _, _ = self.client.request(
                    "/process_order.php", {**form, key: value}, json_response=True)
                self.assertEqual(code, expected)
        with self.server.db() as db:
            self.assertEqual(db.execute("SELECT COUNT(*) FROM orders").fetchone()[0], 0)

    def test_06_wrong_service_quote_rejected(self):
        form, _ = self.client.form()
        form["service"] = "frp"
        self.assertEqual(self.client.request("/process_order.php", form, json_response=True)[0], 409)

    def test_07_duplicate_txid_and_quote_rejected(self):
        form, (code, _, _) = self.submit()
        self.assertEqual(code, 201)
        other = Client(self.server.base)
        another, _ = other.form()
        another["txid"] = form["txid"].upper()
        self.assertEqual(other.request("/process_order.php", another, json_response=True)[0], 409)
        form["txid"] = os.urandom(32).hex()
        self.assertEqual(self.client.request("/process_order.php", form, json_response=True)[0], 409)
        with self.server.db() as db:
            self.assertEqual(db.execute("SELECT COUNT(*) FROM orders").fetchone()[0], 1)

    def test_08_post_redirect_get_and_receipt(self):
        form, _ = self.client.form()
        code, _, headers = self.client.request("/process_order.php", form)
        self.assertEqual(code, 303)
        code, receipt, _ = self.client.request("/" + headers["Location"])
        self.assertEqual(code, 200)
        self.assertIn("Order saved for review", receipt)
        self.assertIn(self.order()["id"], receipt)

    def test_09_method_and_unknown_service_errors(self):
        self.assertEqual(self.client.request("/process_order.php", json_response=True)[0], 405)
        self.assertEqual(self.client.request("/payment.php?service=bad")[0], 400)
        self.assertEqual(self.client.request("/payment.php?service[]=bad")[0], 400)
        self.assertEqual(self.client.request("/prices.php", {})[0], 405)

    def test_10_admin_authentication(self):
        code, body, headers = self.client.request("/orders.php")
        self.assertEqual(code, 401)
        self.assertIn("WWW-Authenticate", headers)
        self.assertNotIn("owner@example.com", body)
        self.assertEqual(self.client.request("/orders.php", auth=True)[0], 200)

    def test_11_admin_validation_and_audited_transitions(self):
        self.submit()
        self.assertEqual(self.review("completed")[0], 422)
        self.assertEqual(self.review("payment_verified")[0], 422)
        self.assertEqual(self.review("payment_verified", verification="1")[0], 200)
        self.assertEqual(self.order()["status"], "payment_verified")
        self.assertEqual(self.review("completed", version=0)[0], 409)
        self.assertEqual(self.review("completed", version=1)[0], 200)
        self.assertEqual(self.order()["status"], "completed")
        with self.server.db() as db:
            self.assertEqual(db.execute("SELECT COUNT(*) FROM order_events").fetchone()[0], 2)

    def test_12_admin_csrf_and_escaping(self):
        form, _ = self.client.form()
        form["model"] = '<script>alert("test")</script>'
        self.assertEqual(self.client.request("/process_order.php", form, json_response=True)[0], 201)
        code, html, _ = self.client.request("/orders.php", auth=True)
        self.assertEqual(code, 200)
        self.assertIn("&lt;script&gt;", html)
        self.assertNotIn('<script>alert("test")</script>', html)
        self.assertEqual(self.client.request("/orders.php", {
            "id": self.order()["id"], "csrf": "", "status": "completed"
        }, auth=True)[0], 403)

    def test_13_rate_limit(self):
        self.submit()
        with self.server.db() as db:
            db.execute("UPDATE rate_limits SET attempts = 5")
        client = Client(self.server.base)
        form, _ = client.form()
        code, _, headers = client.request("/process_order.php", form, json_response=True)
        self.assertEqual(code, 429)
        self.assertEqual(headers["Retry-After"], "600")

    def test_14_database_private_and_not_web_accessible(self):
        self.assertEqual((self.server.directory / "orders.sqlite").stat().st_mode & 0o777, 0o600)
        self.assertEqual(self.server.directory.stat().st_mode & 0o777, 0o700)
        self.assertEqual(self.client.request("/orders.sqlite")[0], 404)

    def test_15_missing_storage_never_reports_success(self):
        other = Server(Path(self.temp.name) / "missing", configure=False)
        try:
            client = Client(other.base)
            code, html, _ = client.request("/payment.php")
            self.assertEqual(code, 503)
            self.assertNotIn(WALLET, html)
            form, _ = self.client.form()
            session = next((other.directory / "test-sessions").glob("sess_*")).read_text()
            form["csrf"] = re.search(r'csrf\|s:64:"([^"]+)"', session).group(1)
            status, body, _ = client.request("/process_order.php", form, json_response=True)
            self.assertEqual(status, 503)
            self.assertEqual(json.loads(body)["status"], "error")
            self.assertFalse((other.directory / "orders.sqlite").exists())
        finally:
            other.close()

    def test_16_price_outage_does_not_show_false_conversion(self):
        other = Server(Path(self.temp.name) / "outage", curl=False)
        try:
            other.seed_rate(timestamp=0)
            client = Client(other.base)
            self.assertEqual(client.request("/prices.php")[0], 503)
            code, body, _ = client.request("/payment.php")
            self.assertEqual(code, 503)
            self.assertNotIn(WALLET, body)
            self.assertNotIn('name="quote_id"', body)
        finally:
            other.close()

    def test_17_quote_survives_price_change_and_expiry_for_reporting(self):
        form, _ = self.client.form()
        with self.server.db() as db:
            db.execute("UPDATE quotes SET expires_at = ?", (int(time.time()) - 1,))
            db.execute("UPDATE market_rate SET usd_cents = 6000000")
        self.assertEqual(self.client.request("/process_order.php", form, json_response=True)[0], 201)
        self.assertEqual(self.order()["amount_btc"], "0.00090000")
        self.assertEqual(self.order()["rate_usd_cents"], 5000000)

    def test_18_unconfigured_admin_is_closed(self):
        other = Server(Path(self.temp.name) / "noadmin", admin=False)
        try:
            self.assertEqual(Client(other.base).request("/orders.php")[0], 503)
        finally:
            other.close()

    def test_19_legacy_payment_post_uses_same_handler(self):
        form, _ = self.client.form("frp")
        code, body, _ = self.client.request("/payment.php", form, json_response=True)
        self.assertEqual(code, 201, body)
        self.assertEqual(self.order()["service"], "frp")
        self.assertEqual(self.order()["usd_cents"], 3000)

    def test_20_notification_missing_curl_is_recorded_as_failure(self):
        code = "require " + repr(str(ROOT / "app.php")) + "; echo oem_notify(['id'=>'TEST','service'=>'frp']);"
        output = subprocess.check_output(
            PHP + ["-d", "disable_functions=curl_init", "-r", code],
            env={**self.server.env, "TELEGRAM_BOT_TOKEN": "test-only", "TELEGRAM_CHAT_ID": "test-only"},
            text=True,
        )
        self.assertEqual(output, "failed")

    def test_21_round_up_to_satoshi(self):
        code = "require " + repr(str(ROOT / "app.php")) + "; echo oem_btc(4500, 8201543);"
        output = subprocess.check_output(PHP + ["-r", code], text=True)
        sats = round(float(output) * 100000000)
        self.assertGreaterEqual(sats * 8201543, 4500 * 100000000)
        self.assertLess((sats - 1) * 8201543, 4500 * 100000000)

    def test_22_deploy_manifest_only_oem_runtime(self):
        manifest = (ROOT / "deploy-files.txt").read_text().splitlines()
        for name in manifest:
            self.assertTrue((ROOT / name).is_file(), name)
            self.assertNotIn("..", name)
            self.assertNotIn("landing", name)
            self.assertNotIn(".env", name)


if __name__ == "__main__":
    unittest.main(verbosity=2)
