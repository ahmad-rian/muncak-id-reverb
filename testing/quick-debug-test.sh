#!/bin/bash

# Quick debug test - run for 30 seconds only
cd "$(dirname "$0")"

echo "🔍 Running quick debug test..."
echo "This will run for 30 seconds to check Reverb connection"
echo ""

# Run Artillery with minimal load
artillery quick \
    --count 5 \
    --num 10 \
    --config .env.artillery \
    https://reverb.muncak.id/live-cam/2

echo ""
echo "✅ Quick test completed"
