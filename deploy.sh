#!/bin/bash
# Deployment helper for dominickzou.dev ecosystem
# Usage: ./deploy.sh [test|prod]

set -e

REPO_DIR="/var/www/personal-website"
TEST_HTML="$REPO_DIR/apps/test.dominickzou.dev/public_html"
PROD_HTML="$REPO_DIR/apps/dominickzou.dev/public_html"

case "$1" in
  test)
    echo "📥 Pulling latest changes from GitHub..."
    cd "$REPO_DIR"
    git pull origin main
    echo "✅ test.dominickzou.dev updated."
    ;;
  prod)
    echo "🚀 Promoting test → production..."
    rsync -av --delete \
      --exclude='.github' \
      --exclude='.git' \
      "$TEST_HTML/" \
      "$PROD_HTML/"
    echo "✅ dominickzou.dev updated from test."
    ;;
  *)
    echo "Usage: $0 {test|prod}"
    echo "  test  - Pull latest from GitHub to update test site"
    echo "  prod  - Promote test site content to production"
    exit 1
    ;;
esac
