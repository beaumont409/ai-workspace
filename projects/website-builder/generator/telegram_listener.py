#!/usr/bin/env python3
"""
Telegram Website Request Listener
Accepts website descriptions through Telegram and queues them for generation.
"""

import json
import os
import sys
from datetime import datetime
from pathlib import Path

# Configuration
REQUESTS_DIR = Path(__file__).parent.parent / "config" / "pending_requests"
REQUESTS_DIR.mkdir(parents=True, exist_ok=True)

def create_request(chat_id: str, message: str, theme: str = "cyberpunk") -> dict:
    """Create a pending website generation request from Telegram message."""
    request = {
        "id": f"req_{datetime.now().strftime('%Y%m%d_%H%M%S')}_{chat_id}",
        "chat_id": chat_id,
        "message": message,
        "theme": theme,
        "status": "pending",
        "created_at": datetime.now().isoformat(),
        "generated_project": None
    }
    
    # Save request
    request_file = REQUESTS_DIR / f"{request['id']}.json"
    with open(request_file, "w") as f:
        json.dump(request, f, indent=2)
    
    return request

def list_pending_requests() -> list:
    """List all pending generation requests."""
    requests = []
    if REQUESTS_DIR.exists():
        for req_file in sorted(REQUESTS_DIR.glob("*.json")):
            with open(req_file) as f:
                req = json.load(f)
            requests.append(req)
    return requests

def update_request_status(request_id: str, status: str, project_path: str = None):
    """Update a request's status."""
    request_file = REQUESTS_DIR / f"{request_id}.json"
    if request_file.exists():
        with open(request_file) as f:
            req = json.load(f)
        req["status"] = status
        if project_path:
            req["generated_project"] = project_path
        req["updated_at"] = datetime.now().isoformat()
        with open(request_file, "w") as f:
            json.dump(req, f, indent=2)

if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="Telegram Website Request Listener")
    parser.add_argument("--create", "-c", help="Create a request from message")
    parser.add_argument("--chat-id", "-i", default="user", help="Telegram chat ID")
    parser.add_argument("--theme", "-t", default="cyberpunk", help="Website theme")
    parser.add_argument("--list", "-l", action="store_true", help="List pending requests")
    
    args = parser.parse_args()
    
    if args.list:
        requests = list_pending_requests()
        print(json.dumps(requests, indent=2))
    elif args.create:
        req = create_request(args.chat_id, args.create, args.theme)
        print(f"✅ Request created: {req['id']}")
        print(f"   Message: {args.create[:50]}...")
        print(f"   Theme: {args.theme}")
