#!/usr/bin/env python3
"""
AI Website Generator — Reusable Website Generation System
Generates HTML, CSS, and JavaScript websites from descriptions.
Supports multiple themes, responsive layouts, galleries, and animations.
"""

import os
import sys
import json
import shutil
import argparse
import importlib.util
from datetime import datetime
from pathlib import Path

# Base directories
BASE_DIR = Path(__file__).parent.parent
PROJECTS_DIR = BASE_DIR / "projects"
TEMPLATES_DIR = BASE_DIR / "templates"
CONFIG_DIR = BASE_DIR / "config"

# Available themes
THEMES = ["luxury", "cyberpunk", "modern", "business", "electric-blue-3d"]

# Default config template
DEFAULT_CONFIG = {
    "project_name": "Untitled",
    "business_type": "general",
    "theme": "cyberpunk",
    "description": "",
    "services": [],
    "gallery_images": [],
    "contact_email": "",
    "created_at": "",
    "version": "1.0.0"
}


def create_project_folder(project_name: str) -> Path:
    """Create a new project folder with unique timestamp suffix."""
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    safe_name = project_name.lower().replace(" ", "_").replace("-", "_")
    project_dir = PROJECTS_DIR / f"{safe_name}_{timestamp}"
    project_dir.mkdir(parents=True, exist_ok=True)
    return project_dir


def init_config(project_dir: Path, description: str, theme: str, business_type: str) -> dict:
    """Initialize project configuration file."""
    config = DEFAULT_CONFIG.copy()
    config["project_name"] = project_dir.stem
    config["business_type"] = business_type
    config["theme"] = theme
    config["description"] = description
    config["created_at"] = datetime.now().isoformat()

    config_path = project_dir / "project.json"
    with open(config_path, "w") as f:
        json.dump(config, f, indent=2)

    return config


def generate_html(config: dict, project_dir: Path) -> str:
    """Generate main HTML file from config."""
    project_name = config["project_name"]
    theme = config["theme"]
    description = config["description"]
    services = config.get("services", [])
    gallery = config.get("gallery_images", [])
    contact_email = config.get("contact_email", "")

    # Build services HTML
    services_html = ""
    for i, service in enumerate(services):
        delay = (i % 3) + 1
        services_html += f"""
                <div class="service-card animate-on-scroll delay-{delay}">
                    <div class="service-icon">{service.get("icon", "⚡")}</div>
                    <h3>{service.get("name", "Service")}</h3>
                    <p>{service.get("description", "Description")}</p>
                </div>"""

    # Build gallery HTML
    gallery_html = ""
    for i, img in enumerate(gallery):
        delay = (i % 3) + 1
        gallery_html += f"""
                <div class="gallery-item animate-on-scroll delay-{delay}">
                    <div class="gallery-img">
                        <svg viewBox="0 0 400 250" xmlns="http://www.w3.org/2000/svg">
                            <rect width="400" height="250" fill="#1a1a2e"/>
                            <text x="200" y="130" text-anchor="middle" fill="#00d4ff" font-size="18" font-family="monospace">{img.get("title", "Gallery")}</text>
                            <rect x="50" y="160" width="300" height="60" rx="8" fill="#16213e" stroke="#00d4ff" stroke-width="2"/>
                            <text x="200" y="190" text-anchor="middle" fill="#888" font-size="12">{img.get("caption", "Description")}</text>
                        </svg>
                    </div>
                    <div class="gallery-caption">{img.get("title", "Gallery Item")}</div>
                </div>"""

    html = f"""<!DOCTYPE html>
<html lang="en" data-theme="{theme}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{description}">
    <title>{project_name}</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/themes.css">
    <link rel="stylesheet" href="css/animations.css">
</head>
<body>
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="#hero" class="nav-logo">{project_name}</a>
            <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation">
                <span class="hamburger"></span>
            </button>
            <ul class="nav-menu" id="nav-menu">
                <li><a href="#hero" class="nav-link">Home</a></li>
                <li><a href="#services" class="nav-link">Services</a></li>
                <li><a href="#gallery" class="nav-link">Gallery</a></li>
                <li><a href="#contact" class="nav-link">Contact</a></li>
                <li><button class="theme-toggle" id="theme-toggle" title="Switch theme">🌓</button></li>
            </ul>
        </div>
    </nav>

    <section id="hero" class="hero">
        <div class="hero-content animate-on-scroll">
            <span class="hero-badge">{description}</span>
            <h1 class="hero-title">{project_name}</h1>
            <p class="hero-subtitle">{description}</p>
            <div class="hero-buttons">
                <a href="#services" class="btn btn-primary glow-btn">Explore Services</a>
                <a href="#contact" class="btn btn-secondary">Get Started</a>
            </div>
        </div>
        <div class="hero-visual animate-on-scroll delay-1">
            <div class="hero-illustration">
                <div class="floating-card card-1">Service 1</div>
                <div class="floating-card card-2">Service 2</div>
                <div class="floating-card card-3">Service 3</div>
            </div>
        </div>
    </section>

    <section id="services" class="services">
        <div class="container">
            <h2 class="section-title animate-on-scroll">Our Services</h2>
            <p class="section-subtitle animate-on-scroll delay-1">Comprehensive solutions tailored for enterprise scale.</p>
            <div class="services-grid">
                {services_html}
            </div>
        </div>
    </section>

    <section id="gallery" class="gallery">
        <div class="container">
            <h2 class="section-title animate-on-scroll">Project Gallery</h2>
            <p class="section-subtitle animate-on-scroll delay-1">Selected showcases of our work.</p>
            <div class="gallery-grid">
                {gallery_html}
            </div>
        </div>
    </section>

    <section id="contact" class="contact">
        <div class="container">
            <h2 class="section-title animate-on-scroll">Get In Touch</h2>
            <p class="section-subtitle animate-on-scroll delay-1">Ready to get started? Let's build the future together.</p>
            <form class="contact-form animate-on-scroll delay-2" id="contact-form">
                <div class="form-group">
                    <label for="name">Company Name</label>
                    <input type="text" id="name" name="name" placeholder="Acme Corp" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="engineering@example.com" required>
                </div>
                <div class="form-group">
                    <label for="service">Service Interest</label>
                    <select id="service" name="service">
                        <option value="">Select a service</option>
                        {"".join(f'<option value="{s.get("id", i)}">{s.get("name", f"Service {i}")}</option>' for i, s in enumerate(services))}
                    </select>
                </div>
                <div class="form-group">
                    <label for="message">Project Details</label>
                    <textarea id="message" name="message" rows="5" placeholder="Tell us about your needs..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary glow-btn w-full">Send Inquiry</button>
            </form>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; {datetime.now().year} {project_name}. Generated by AI Website Generator.</p>
        </div>
    </footer>

    <script src="js/theme-switcher.js"></script>
    <script src="js/main.js"></script>
</body>
</html>"""

    html_path = project_dir / "index.html"
    with open(html_path, "w") as f:
        f.write(html)

    return html_path.name


def copy_assets(project_dir: Path):
    """Copy CSS, JS, and asset templates to project folder."""
    # Create directories
    (project_dir / "css").mkdir(exist_ok=True)
    (project_dir / "js").mkdir(exist_ok=True)
    (project_dir / "assets").mkdir(exist_ok=True)

    # Copy base CSS files from templates
    for css_file in ["style.css", "themes.css", "animations.css"]:
        src = TEMPLATES_DIR / "css" / css_file
        if src.exists():
            shutil.copy2(src, project_dir / "css" / css_file)

    # Copy JS files
    for js_file in ["main.js", "theme-switcher.js"]:
        src = TEMPLATES_DIR / "js" / js_file
        if src.exists():
            shutil.copy2(src, project_dir / "js" / js_file)

    # Copy placeholder
    src = TEMPLATES_DIR / "assets" / "placeholder.svg"
    if src.exists():
        shutil.copy2(src, project_dir / "assets" / "placeholder.svg")


def generate_project(description: str, theme: str = "cyberpunk", business_type: str = "general",
                     services: list = None, gallery: list = None, contact_email: str = "") -> Path:
    """Generate a complete website project from description."""
    # Parse project name from description
    project_name = description.split(".")[0].strip()[:50] if description else "Untitled"

    # Create project folder
    project_dir = create_project_folder(project_name)

    # Initialize config
    config = init_config(project_dir, description, theme, business_type)
    config["services"] = services or []
    config["gallery_images"] = gallery or []
    config["contact_email"] = contact_email

    # Generate HTML
    generate_html(config, project_dir)

    # Copy assets
    copy_assets(project_dir)

    # Update config with generated files
    config["generated_files"] = [
        "index.html",
        "css/style.css",
        "css/themes.css",
        "css/animations.css",
        "js/main.js",
        "js/theme-switcher.js",
        "assets/placeholder.svg"
    ]

    # Save updated config
    config_path = project_dir / "project.json"
    with open(config_path, "w") as f:
        json.dump(config, f, indent=2)

    return project_dir


def generate_qwen_files(description: str, theme: str, project_dir: Path) -> dict:
    """Request optional Colab output and save it as review-only project files."""
    provider_path = Path(__file__).parent / "providers" / "qwen_colab.py"
    spec = importlib.util.spec_from_file_location("website_builder_qwen_colab", provider_path)
    provider = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(provider)

    prompt = (
        "Create a mobile-friendly website implementation for the existing AI Website Builder. "
        "Use the selected theme and return only index.html, css/style.css, and js/main.js. "
        "Use lightweight HTML/CSS/vanilla JavaScript, accessible controls, and responsive layout. "
        "Do not add external dependencies, backend calls, secrets, payment collection, or executable setup instructions.\n\n"
        f"Theme: {theme}\nProject brief: {description}"
    )
    adapter = provider.QwenColabAdapter.from_environment()
    job_id, files = adapter.generate_files(prompt=prompt, theme=theme)
    job_record = {
        "job_id": job_id,
        "provider": "qwen-colab",
        "status": adapter.job_status(job_id),
        "files_written": [],
        "review_required": True,
        "code_executed": False,
    }
    if files:
        output_root = project_dir / "qwen-generated"
        for relative_path, contents in files.items():
            destination = output_root / relative_path
            destination.parent.mkdir(parents=True, exist_ok=True)
            destination.write_text(contents, encoding="utf-8")
            job_record["files_written"].append(relative_path)
    (project_dir / "qwen-job.json").write_text(json.dumps(job_record, indent=2), encoding="utf-8")
    return job_record


def list_projects() -> list:
    """List all generated projects."""
    projects = []
    if PROJECTS_DIR.exists():
        for project_dir in sorted(PROJECTS_DIR.iterdir(), reverse=True):
            if project_dir.is_dir():
                config_path = project_dir / "project.json"
                if config_path.exists():
                    with open(config_path) as f:
                        config = json.load(f)
                    projects.append({
                        "folder": project_dir.name,
                        "name": config.get("project_name", "Unknown"),
                        "theme": config.get("theme", "unknown"),
                        "created": config.get("created_at", "unknown")
                    })
    return projects


def main():
    parser = argparse.ArgumentParser(description="AI Website Generator")
    parser.add_argument("--generate", "-g", help="Generate website from description")
    parser.add_argument("--theme", "-t", default="cyberpunk", choices=THEMES, help="Website theme")
    parser.add_argument("--business", "-b", default="general", help="Business type")
    parser.add_argument("--provider", choices=["template", "qwen-colab"], default="template",
                        help="Optional generation provider (Qwen requires an authenticated Colab endpoint)")
    parser.add_argument("--list", "-l", action="store_true", help="List all projects")
    parser.add_argument("--demo", action="store_true", help="Generate luxury watch demo")

    args = parser.parse_args()

    if args.list:
        projects = list_projects()
        print(json.dumps(projects, indent=2))
        return

    if args.demo:
        # Luxury watch business demo
        description = "ChronoLux — Premium Swiss Watchmakers"
        services = [
            {"id": "watches", "name": "Luxury Timepieces", "icon": "⌚", "description": "Exclusive collection of Swiss luxury watches"},
            {"id": "repair", "name": "Expert Repair", "icon": "🔧", "description": "Professional watch restoration and maintenance"},
            {"id": "custom", "name": "Custom Designs", "icon": "🎨", "description": "Bespoke watch design and engraving services"},
            {"id": "consulting", "name": "Watch Consulting", "icon": "💎", "description": "Investment guidance and collection appraisal"}
        ]
        gallery = [
            {"title": "Chronograph Collection", "caption": "Mechanical precision"},
            {"title": "Diamond Dial", "caption": "Hand-set crystals"},
            {"title": "Vintage Restoration", "caption": "Heritage timepieces"},
            {"title": "Custom Engraving", "caption": "Personalized details"},
            {"title": "Limited Edition", "caption": "Exclusive releases"},
            {"title": "Watch Making", "caption": "Craftsmanship"}
        ]

        project_dir = generate_project(
            description=description,
            theme="luxury",
            business_type="luxury-goods",
            services=services,
            gallery=gallery,
            contact_email="info@chronolux.example.com"
        )
        print(f"✅ Luxury watch demo generated: {project_dir}")
        return

    if args.generate:
        project_dir = generate_project(
            description=args.generate,
            theme=args.theme,
            business_type=args.business
        )
        if args.provider == "qwen-colab":
            result = generate_qwen_files(args.generate, args.theme, project_dir)
            print(json.dumps({"project": str(project_dir), "qwen_job": result}, indent=2))
            return
        print(f"✅ Website generated: {project_dir}")
        return

    parser.print_help()


if __name__ == "__main__":
    main()
