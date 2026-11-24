import http.server
import socketserver
import urllib.parse
import os
import requests

HOST = "127.0.0.1"
PORT = 8081

BOT_TOKEN = os.environ["BOT_TOKEN"]
CHAT_ID = os.environ["CHAT_ID"]


class Handler(http.server.SimpleHTTPRequestHandler):

    def do_POST(self):
        length = int(self.headers.get("Content-Length", 0))
        body = self.rfile.read(length).decode("utf-8")
        data = urllib.parse.parse_qs(body)

        name = data.get("name", [""])[0]
        phone = data.get("phone", [""])[0]

        print(f"Form received: name={name!r}, phone={phone!r}", flush=True)

        message = (
            "🔔 New Form Submission\n\n"
            f"Name: {name}\n"
            f"Phone: {phone}"
        )

        try:
            response = requests.post(
                f"https://api.telegram.org/bot{BOT_TOKEN}/sendMessage",
                data={
                    "chat_id": CHAT_ID,
                    "text": message
                },
                timeout=10
            )

            response.raise_for_status()

            self.send_response(200)
            self.end_headers()
            self.wfile.write(b"Form received successfully.")

        except Exception as e:
            print(f"Telegram error: {e}", flush=True)

            self.send_response(500)
            self.end_headers()
            self.wfile.write(b"Unable to process form.")

    def do_GET(self):
        if self.path == "/":
            self.send_response(200)
            self.send_header("Content-Type", "text/plain")
            self.end_headers()
            self.wfile.write(b"O.E.M.Unlocks form server is running.")
        else:
            self.send_error(404)


with socketserver.TCPServer((HOST, PORT), Handler) as server:
    print(f"Server running on http://{HOST}:{PORT}", flush=True)
    server.serve_forever()
