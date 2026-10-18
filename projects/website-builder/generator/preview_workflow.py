#!/usr/bin/env python3
"""
Preview Workflow
Creates temporary preview URLs for generated websites before approval.
"""

import json
import os
import subprocess
import tempfile
from datetime import datetime
from pathlib import Path

PREVIEW_DIR = Path(__file__).parent.parent / "config" / "previews"
PREVIEW_DIR.mkdir(parents=True, exist_ok=True)

def create_preview(project_path: str, port: int = 8080) -> dict:
    """Create a temporary preview for a project."""
    project_dir = Path(project_path)
    if not (project_dir / "index.html").exists():
        return {"error": "No index.html found in project"}
    
    preview_id = f"preview_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
    preview_dir = PREVIEW_DIR / preview_id
    preview_dir.mkdir(parents=True, exist_ok=True)
    
    # Copy project files to preview directory
    subprocess.run(["cp", "-r", str(project_dir / "."), str(preview_dir)], check=True)
    
    preview = {
        "id": preview_id,
        "project_path": str(project_dir),
        "preview_path": str(preview_dir),
        "port": port,
        "url": f"http://localhost:{port}",
        "created_at": datetime.now().isoformat(),
        "status": "active",
        "approved": False
    }
    
    # Save preview config
    config_file = preview_dir / "preview.json"
    with open(config_file, "w") as f:
        json.dump(preview, f, indent=2)
    
    return preview

def approve_preview(preview_id: str):
    """Approve a preview for deployment."""
    preview_dir = PREVIEW_DIR / preview_id
    config_file = preview_dir / "preview.json"
    if config_file.exists():
        with open(config_file) as f:
            preview = json.load(f)
        preview["approved"] = True
        preview["approved_at"] = datetime.now().isoformat()
        with open(config_file, "w") as f:
            json.dump(preview, f, indent=2)
        return preview
    return None

def list_previews() -> list:
    """List all previews."""
    previews = []
    if PREVIEW_DIR.exists():
        for preview_dir in sorted(PREVIEW_DIR.iterdir()):
            config_file = preview_dir / "preview.json"
            if config_file.exists():
                with open(config_file) as f:
                    preview = json.load(f)
                previews.append(preview)
    return previews

if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="Preview Workflow")
    parser.add_argument("--create", "-c", help="Create preview for project path")
    parser.add_argument("--approve", "-a", help="Approve preview by ID")
    parser.add_argument("--list", "-l", action="store_true", help="List previews")
    
    args = parser.parse_args()
    
    if args.list:
        previews = list_previews()
        print(json.dumps(previews, indent=2))
    elif args.create:
        preview = create_preview(args.create)
        print(f"✅ Preview created: {preview['id']}")
        print(f"   URL: {preview['url']}")
    elif args.approve:
        preview = approve_preview(args.approve)
        if preview:
            print(f"✅ Preview approved: {preview['id']}")
        else:
            print("❌ Preview not found")
