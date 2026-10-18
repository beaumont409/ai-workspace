# AI Website Builder — Demonstration Workspace

## Overview
A professional, responsive website template system built with HTML, CSS, and JavaScript.
Demonstrates multi-theme support, mobile-responsive layouts, smooth animations, and
a safe deployment workflow — all without modifying existing production websites.

## Fictional Business
**NexaCore Technologies** — A fictional AI/ML infrastructure company used for demonstration.

## Workspace Structure
```
website-builder/
├── README.md              # This file
├── index.html             # Main website (all sections)
├── css/
│   ├── style.css          # Base styles + layout
│   ├── themes.css         # Theme system (4 visual themes)
│   └── animations.css     # Smooth animations & transitions
├── js/
│   ├── main.js            # Navigation, scroll effects, gallery
│   └── theme-switcher.js  # Theme switching logic
├── assets/
│   └── placeholder.svg    # SVG placeholder for demo images
└── DEPLOYMENT.md          # Safe deployment workflow guide
```

## Features
- **4 Visual Themes**: Luxury (black/gold), Cyberpunk (black/laser-green), Modern (blue/white), Business (professional gray/blue)
- **Mobile-Responsive**: Fully responsive navigation, grid layouts, and touch-friendly elements
- **Smooth Animations**: Scroll-triggered reveals, hover effects, glowing buttons
- **GitHub Integration**: Version control ready with `.gitignore` for secrets
- **Safe Deployment**: Backup, validation, and rollback procedures documented

## Preview
Open `index.html` in any modern browser:
```bash
# Option 1: Direct file open
open index.html

# Option 2: Local Python server (recommended for animations)
cd /root/.openclaw/workspace/website-builder
python3 -m http.server 8080
# Then visit: http://localhost:8080
```

## Theme Switching
Click the theme toggle button in the navigation bar to cycle through all 4 themes.
Each theme changes colors, fonts, and visual styling without altering content.

## Design Specifications
This demo accepts design specs from Relume or any other design tool.
To generate a website from Relume specs:
1. Export Relume design as HTML/CSS
2. Map Relume components to this template structure
3. Apply theme CSS variables to match brand colors
4. Validate responsiveness across breakpoints

## Safety Notes
- ✅ Does NOT modify existing production websites (k2prisonsheetz, etc.)
- ✅ No unverified MCP packages installed
- ✅ All changes isolated to `/website-builder/` workspace
- ✅ Git version control enabled for full change tracking
- ✅ Deployment workflow includes backups and rollback capability
