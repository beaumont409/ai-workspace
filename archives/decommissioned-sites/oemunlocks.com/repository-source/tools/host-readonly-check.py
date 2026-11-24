#!/usr/bin/env python3
"""Run with sudo on the actual host. No secrets, customer data, or config dumps.

Only creates/removes a temporary diagnostic script outside nginx roots. Does not
load the OEM app, create a session/database, send Telegram, or reload services.
"""
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import tempfile

REQUIRED = ['OEM_DATA_DIR', 'OEM_ADMIN_USER', 'OEM_ADMIN_PASSWORD_HASH',
            'TELEGRAM_BOT_TOKEN', 'TELEGRAM_CHAT_ID']

print('Host effective uid:', os.geteuid())
processes = []
for item in Path('/proc').glob('[0-9]*/comm'):
    try:
        name = item.read_text().strip()
        if name not in ['php-fpm8.3', 'nginx']:
            continue
        status = (item.parent / 'status').read_text()
        uid = re.search(r'^Uid:\s+(.*)$', status, re.M).group(1).split()
        gid = re.search(r'^Gid:\s+(.*)$', status, re.M).group(1).split()
        processes.append({'pid': int(item.parent.name), 'name': name,
                          'effective_uid': int(uid[1]), 'effective_gid': int(gid[1])})
    except (OSError, AttributeError):
        pass
print('Visible nginx/FPM process identities:', json.dumps(processes))
print('NOTE: /proc/PID/environ is not used as proof of FPM pool env[] values.')

def capture(command, **kwargs):
    try:
        return subprocess.run(command, capture_output=True, text=True, timeout=15, **kwargs)
    except (OSError, subprocess.TimeoutExpired) as error:
        print('BLOCKED:', command[0], type(error).__name__)
        return None

# Parse privately: nginx -T and FPM -tt can contain values that must never be printed.
nginx = capture(['/usr/sbin/nginx', '-T'])
roots = []
if nginx is None or nginx.returncode:
    print('Nginx configuration test/dump unavailable; raw diagnostics withheld.')
else:
    print('Nginx on-disk configuration test: PASS (not proof of loaded configuration)')
    roots = re.findall(r'^\s*(?:root|alias)\s+([^;]+);', nginx.stdout, re.M)
    roots = [p.strip().strip('\"\'') for p in roots]
    print('Served roots/aliases inspected:', len(roots))
    # Print only whitelisted routing directives from the exact OEM config section.
    match = re.search(r'# configuration file /etc/nginx/sites-enabled/oemunlocks.com:\n(.*?)(?=\n# configuration file |\Z)',
                      nginx.stdout, re.S)
    if match:
        routes = re.findall(r'^\s*(?:listen|server_name|root|index|location|return|proxy_pass|fastcgi_pass)\b[^\n]*',
                            match.group(1), re.M)
        print('OEM on-disk route directives:')
        for line in routes:
            # Only the known static OEM route values can be printed.
            if re.search(r'(?i)token|password|secret|\?', line):
                print('[directive with potentially private value withheld]')
            else:
                print(line.strip())
    else:
        print('OEM section not found under expected enabled path; inspect routing privately.')

fpm = capture(['/usr/sbin/php-fpm8.3', '-tt'])
if fpm is not None:
    print('FPM on-disk configuration test exit:', fpm.returncode, '(raw dump withheld)')
    if fpm.returncode == 0:
        dump = fpm.stdout + fpm.stderr
        for key in REQUIRED:
            entries = re.findall(r'env\[' + re.escape(key) + r'\]\s*=\s*([^\r\n]*)', dump)
            print('FPM disk config explicit', key, ':', 'present' if entries else 'absent')
    else:
        print('FPM configured environment presence: unverified (configuration test incomplete)')

sock = Path('/run/php/php8.3-fpm.sock')
try:
    info = sock.stat()
    print('Existing socket metadata:', json.dumps({'uid': info.st_uid, 'gid': info.st_gid,
                                                 'mode': oct(stat.S_IMODE(info.st_mode))}))
except OSError:
    print('Existing socket unavailable')

PROBE = r'''<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');
$keys = ['OEM_DATA_DIR','OEM_ADMIN_USER','OEM_ADMIN_PASSWORD_HASH',
         'TELEGRAM_BOT_TOKEN','TELEGRAM_CHAT_ID'];
$out = ['sapi' => PHP_SAPI, 'pid' => getmypid(), 'presence' => []];
foreach ($keys as $key) {
    $value = getenv($key, true);
    $out['presence'][$key] = is_string($value) && $value !== '';
}
$out['effective_uid'] = function_exists('posix_geteuid') ? posix_geteuid() : null;
$out['effective_gid'] = function_exists('posix_getegid') ? posix_getegid() : null;
$out['php_83_plus'] = PHP_VERSION_ID >= 80300;
$out['sqlite'] = class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true);
$out['curl'] = function_exists('curl_init');
$out['https_parameter_on'] = ($_SERVER['HTTPS'] ?? '') === 'on';
$directory = getenv('OEM_DATA_DIR', true) ?: '';
$real = $directory !== '' ? realpath($directory) : false;
$out['storage'] = ['configured' => $directory !== '', 'exists' => $real !== false];
if ($real !== false) {
    $meta = stat($real);
    $roots = json_decode($_SERVER['OEM_AUDIT_ROOTS'] ?? '[]', true) ?: [];
    $outside = true;
    foreach ($roots as $root) {
        if (str_contains($root, '$')) { $outside = null; break; }
        $root = realpath($root);
        if ($root !== false && ($root === '/' || $real === $root || str_starts_with($real, rtrim($root, '/') . '/'))) {
            $outside = false; break;
        }
    }
    $out['storage'] += ['is_directory' => is_dir($real), 'uid' => $meta['uid'],
        'gid' => $meta['gid'], 'mode' => sprintf('%04o', $meta['mode'] & 0777),
        'worker_writable' => is_writable($real), 'outside_known_served_roots' => count($roots) ? $outside : null];
    $db = $real . '/orders.sqlite';
    $out['storage']['database_exists'] = file_exists($db);
    $out['storage']['database_symlink'] = is_link($db);
    if (file_exists($db)) {
        $meta = stat($db);
        $out['storage']['database_metadata'] = ['uid' => $meta['uid'], 'gid' => $meta['gid'],
            'mode' => sprintf('%04o', $meta['mode'] & 0777), 'worker_writable' => is_writable($db)];
    }
}
echo json_encode($out);
'''

if not roots:
    print('BLOCKED: cannot certify scratch location outside served roots without nginx configuration.')
elif any('$' in p or not p.startswith('/') for p in roots):
    print('BLOCKED: dynamic or relative served roots need private review before creating a probe.')
elif any(Path('/tmp').is_relative_to(Path(p).resolve()) for p in roots):
    print('SKIP: /tmp may be served; do not place probe there.')
elif not shutil.which('cgi-fcgi'):
    print('BLOCKED: cgi-fcgi unavailable; do not install packages or alter FPM automatically.')
else:
    with tempfile.TemporaryDirectory(prefix='oem-readonly-') as directory:
        os.chmod(directory, 0o755)
        script = Path(directory) / 'probe.php'
        script.write_text(PROBE)
        os.chmod(script, 0o644)
        env = {'SCRIPT_FILENAME': str(script), 'SCRIPT_NAME': '/oem-private-probe.php',
               'REQUEST_METHOD': 'GET', 'SERVER_PROTOCOL': 'HTTP/1.1',
               'SERVER_NAME': 'oemunlocks.com', 'GATEWAY_INTERFACE': 'CGI/1.1',
               'DOCUMENT_ROOT': '/var/www/oemunlocks', 'HTTPS': 'on',
               'OEM_AUDIT_ROOTS': json.dumps(roots)}
        result = capture([shutil.which('cgi-fcgi'), '-bind', '-connect', str(sock)],
                         env=env, input='')
        try:
            body = re.split(r'\r?\n\r?\n', result.stdout, maxsplit=1)[1]
            data = json.loads(body)
            assert data['sapi'] == 'fpm-fcgi'
            print('Effective pool via existing OEM socket:', json.dumps(data, sort_keys=True))
            print('HTTPS above was supplied by the audit FastCGI client; verify nginx transport separately.')
        except (AttributeError, IndexError, KeyError, ValueError, AssertionError):
            print('BLOCKED: effective FPM probe unavailable; raw errors withheld. No config overrides attempted.')

# Only read existing public pages; no payment/order endpoints, query strings, or bodies.
for path in ['/', '/oem-preview.html', '/landing/', '/api/']:
    result = capture(['/usr/bin/curl', '--silent', '--show-error', '--max-time', '10',
                      '--resolve', 'oemunlocks.com:443:127.0.0.1',
                      '--output', '/dev/null', '--write-out', '%{http_code}',
                      'https://oemunlocks.com' + path])
    print('Existing TLS route', path, ':',
          result.stdout.strip() if result is not None and result.returncode == 0 else 'BLOCKED/unverified')
result = capture(['/usr/bin/curl', '--silent', '--show-error', '--max-time', '10',
                  '--resolve', 'oemunlocks.com:80:127.0.0.1', '--output', '/dev/null',
                  '--write-out', '%{http_code} %{redirect_url}', 'http://oemunlocks.com/'])
print('Existing HTTP redirect:', result.stdout.strip() if result is not None and result.returncode == 0
      else 'BLOCKED/unverified')
