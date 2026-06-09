#!/bin/bash

# Contabo API Setup Verification Script
# This script helps verify your API setup

echo "Contabo API Setup Verification"
echo "=============================="
echo ""

echo "1. Check if you can access Contabo Customer Control Panel:"
echo "   https://my.contabo.com"
echo ""

echo "2. In the Control Panel, look for:"
echo "   - API section or API Management"
echo "   - Client ID and Client Secret (should match config.php)"
echo "   - API User creation/management"
echo ""

echo "3. Current configuration in config.php:"
echo "   Client ID: $(grep 'CONTABO_CLIENT_ID' /home/contabo-snapshots/config.php | cut -d"'" -f2)"
echo "   Client Secret: $(grep 'CONTABO_CLIENT_SECRET' /home/contabo-snapshots/config.php | cut -d"'" -f2)"
echo "   API User: $(grep 'CONTABO_API_USER' /home/contabo-snapshots/config.php | cut -d"'" -f2)"
echo "   API Password: [HIDDEN]"
echo ""

echo "4. Test current credentials:"
cd /home/contabo-snapshots
php test-credentials.php

echo ""
echo "5. If authentication fails, you need to:"
echo "   a) Create an API User in Contabo Control Panel"
echo "   b) Set a specific API password (not your login password)"
echo "   c) Update config.php with the correct credentials"
echo ""

echo "6. After updating credentials, test again:"
echo "   php test-credentials.php"
echo ""

echo "7. Once authentication works, run the full test:"
echo "   ./test-snapshots.sh"
echo ""

echo "8. Set up the cron job:"
echo "   ./setup-cron.sh"
echo ""
