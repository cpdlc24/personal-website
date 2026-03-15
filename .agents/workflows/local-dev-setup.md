---
description: How to set up a local development environment for the personal-website monorepo
---

# Local Development Setup

## Prerequisites
- Git installed
- SSH key configured for GitHub (`git@github.com:cpdlc24/personal-website.git`)
- Python 3 (for local dev server) or any static file server

## Clone the Repo

```bash
git clone git@github.com:cpdlc24/personal-website.git
cd personal-website
```

## Run a Local Dev Server

```bash
# Serve the test site locally
cd apps/test.dominickzou.dev/public_html
python3 -m http.server 8080
# Visit http://localhost:8080
```

For PHP apps (reporting/collector), use PHP's built-in server:

```bash
cd apps/reporting.dominickzou.dev/public_html
php -S localhost:8081
```

## Development Workflow

1. Make changes in `apps/test.dominickzou.dev/public_html/`
2. Preview locally at `http://localhost:8080`
3. Commit and push:
   ```bash
   git add -A && git commit -m "description" && git push
   ```
4. GitHub Actions auto-deploys to `https://test.dominickzou.dev`
5. Verify on the live test site
6. When ready for production, either:
   - **GitHub**: Actions tab → "Promote Test to Production" → Run workflow → type "yes"
   - **Server SSH**: `cd /var/www/personal-website && ./deploy.sh prod`
