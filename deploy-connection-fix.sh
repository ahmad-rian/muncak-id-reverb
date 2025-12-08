#!/bin/bash

# ========================================
# Deploy Reverb Connection Stability Fix
# ========================================

echo "🚀 Deploying connection stability improvements..."

# 1. Update .env with new config
echo "📝 Updating .env configuration..."
cat >> .env << 'EOF'

# ✅ CONNECTION STABILITY FIX (Added: $(date))
REVERB_MAX_CONNECTIONS=50000
REVERB_MAX_BACKEND_EVENTS_PER_SEC=5000
REVERB_MAX_CLIENT_MESSAGES_PER_SEC=500
REVERB_MAX_READ_BUFFER=102400
REVERB_MAX_WRITE_BUFFER=102400
REVERB_SERVER_PING_INTERVAL=15
REVERB_APP_CONNECTION_TIMEOUT=30
REVERB_APP_READ_TIMEOUT=30
REVERB_APP_WRITE_TIMEOUT=30
EOF

# 2. Clear caches
echo "🗑️  Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 3. Rebuild JavaScript assets
echo "🔨 Rebuilding assets..."
npm run build

# 4. Restart Reverb server
echo "🔄 Restarting Reverb server..."
php artisan reverb:restart

# 5. Restart queue workers (if using)
echo "🔄 Restarting queue workers..."
php artisan queue:restart

echo "✅ Deployment complete!"
echo ""
echo "📊 Test with:"
echo "   npm run test:performance"
echo ""
echo "🎯 Expected improvements:"
echo "   - WebSocket failure: 21.32% → < 5%"
echo "   - Connection timeout: Reduced"
echo "   - Reconnection: Faster (500ms start)"
echo "   - Max retry: 5 → 10 attempts"
