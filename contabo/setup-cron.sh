#!/bin/bash

# Contabo Snapshot Manager - Cron Setup Script
# This script sets up the cron job for daily snapshot management

echo "Setting up Contabo Snapshot Manager cron job..."

# Create necessary directories
mkdir -p /home/MAS_ChangeHub/contabo/logs
mkdir -p /home/MAS_ChangeHub/contabo/test

# Set proper permissions
chmod 755 /home/MAS_ChangeHub/contabo
chmod 644 /home/MAS_ChangeHub/contabo/config.php
chmod 755 /home/MAS_ChangeHub/contabo/snapshot-manager.php
chmod 755 /home/MAS_ChangeHub/contabo/setup-cron.sh
chmod 755 /home/MAS_ChangeHub/contabo/test-snapshots.sh

# Set proper ownership (adjust user/group as needed)
chown -R newst3922:newst3922 /home/MAS_ChangeHub/contabo

# Create cron job entry
CRON_ENTRY="0 0 * * * /usr/bin/php /home/MAS_ChangeHub/contabo/snapshot-manager.php >> /home/MAS_ChangeHub/contabo/logs/cron.log 2>&1"

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "contabo-snapshots"; then
    echo "Cron job already exists. Updating..."
    # Remove existing entry
    crontab -l 2>/dev/null | grep -v "contabo-snapshots" | crontab -
fi

# Add new cron job
(crontab -l 2>/dev/null; echo "# Contabo Snapshot Manager - Daily at 00:00 GMT+2 (Europe/Oslo)"; echo "$CRON_ENTRY") | crontab -

echo "Cron job added successfully!"
echo "The snapshot manager will run daily at 00:00 GMT+2 (Europe/Oslo timezone)"
echo ""
echo "To verify the cron job was added:"
echo "crontab -l"
echo ""
echo "To test the script manually:"
echo "/home/MAS_ChangeHub/contabo/test-snapshots.sh"
echo ""
echo "To view logs:"
echo "tail -f /home/MAS_ChangeHub/contabo/logs/snapshot-manager.log"
echo "tail -f /home/MAS_ChangeHub/contabo/logs/snapshot-errors.log"
