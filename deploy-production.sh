#!/bin/bash
set -e

echo "🚀 Deploying EasyDeploy to production..."
echo ""

# Navigate to deployment directory
cd /opt/easydeploy

# Pull latest changes
echo "📥 Pulling latest changes from git..."
git pull origin main

# Rebuild orchestrator service
echo "🔨 Rebuilding orchestrator..."
docker compose build orchestrator

# Restart orchestrator to apply changes
echo "♻️  Restarting orchestrator service..."
docker compose restart orchestrator

# Wait for orchestrator to be ready
echo "⏳ Waiting for orchestrator to start..."
sleep 5

# Check orchestrator status
echo "✅ Checking orchestrator status..."
docker compose ps orchestrator

echo ""
echo "✨ Deployment completed successfully!"
echo ""
echo "The zero-downtime deployment strategy is now active."
echo "Next deploys will:"
echo "  1. Create new containers alongside old ones"
echo "  2. Wait for health checks to pass"
echo "  3. Update Traefik configuration"
echo "  4. Remove old containers only after new ones are healthy"
