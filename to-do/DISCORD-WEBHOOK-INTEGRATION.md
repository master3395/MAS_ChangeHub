# Discord Webhook Integration - Internet Archive Snapshot Manager

## Overview
Successfully integrated Discord webhook notifications into the Internet Archive Snapshot Manager system. The system now sends detailed status reports to Discord when snapshot operations complete.

## Implementation Details

### 1. Configuration (`snapshot_config.conf`)
Added Discord webhook configuration:
```bash
# Discord Webhook Configuration
DISCORD_WEBHOOK_ENABLED=true
DISCORD_WEBHOOK_URL="https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN"
```

### 2. Main Script (`website_snapshot.sh`)

#### Added Discord Webhook Configuration
The script now includes:
- Discord webhook URL
- Enable/disable flag
- Full webhook integration in bash

#### New Function: `send_discord_notification()`
Sends rich embed notifications to Discord with:
- **Status**: Success (green) or Error (red)
- **Statistics**: Total websites, successful/failed snapshots
- **Website Details**: List of monitored domains
- **Archive Location**: Link to archive.org
- **Failed URLs**: List of any failed snapshots (if applicable)
- **Timestamp**: Oslo timezone (GMT+2)

#### Enhanced Main Function
Updated `main()` to:
- Track success and failure counts
- Build failed URLs list for Discord
- Call `send_discord_notification()` on completion

### 3. Test Script (`test-discord-webhook.sh`)
Created comprehensive test script that:
- Validates webhook configuration
- Sends test notification with sample data
- Verifies webhook connectivity
- Provides clear success/error feedback

### 4. Documentation (`to-do/README.md`)
Updated README with:
- Discord webhook feature in features list
- Configuration instructions
- Test command for webhook verification
- Schedule change from 00:00 to 23:00 GMT+2

## Schedule Change

### Old Schedule
- **Time**: 00:00 (midnight) GMT+2
- **Conflict**: Ran at the same time as Contabo snapshots

### New Schedule
- **Time**: 23:00 (11:00 PM) GMT+2
- **Benefit**: Runs 1 hour before Contabo snapshots
- **Reason**: Ensures Internet Archive snapshots are captured before VM snapshots

## Notification Content

### Success Notification (Green)
```
✅ Internet Archive Snapshot Manager - Completed Successfully
Daily snapshot management completed at 2025-10-08 23:00:15 CEST

📊 Statistics
Total Websites: 15
Successful Snapshots: 15
Failed Snapshots: 0

🌐 Websites Archived
• newstargeted.com - Main domain (clickable Wayback Machine link)
• api.newstargeted.com (clickable Wayback Machine link)
• infoskjerm.newstargeted.com (clickable Wayback Machine link)
• mas.newstargeted.com (clickable Wayback Machine link)
• discord.newstargeted.com (clickable Wayback Machine link)
• and 10 more domains...

📦 View All Snapshots
Click any domain link above to view its Wayback Machine calendar with all historical snapshots.
```

**Each domain is a clickable link** to its Wayback Machine calendar:
- Example: https://web.archive.org/web/*/https://newstargeted.com/
- Example: https://web.archive.org/web/*/https://api.newstargeted.com/

### Error Notification (Red)
```
❌ Internet Archive Snapshot Manager - Completed with Errors
Daily snapshot management completed at 2025-10-08 23:00:15 CEST

📊 Statistics
Total Websites: 15
Successful Snapshots: 14
Failed Snapshots: 1

🌐 Websites Archived
• newstargeted.com - Main domain (clickable Wayback Machine link)
• api.newstargeted.com (clickable Wayback Machine link)
• infoskjerm.newstargeted.com (clickable Wayback Machine link)
• mas.newstargeted.com (clickable Wayback Machine link)
• discord.newstargeted.com (clickable Wayback Machine link)
• and 10 more domains...

⚠️ Failed URLs
• https://webhook.newstargeted.com

📦 View All Snapshots
Click any domain link above to view its Wayback Machine calendar with all historical snapshots.
```

## Testing Results

✅ **Test Passed**: Successfully sent test notification to Discord
- HTTP Status: 204 (No Content - Success)
- Webhook URL: Validated and working
- Rich embed formatting: Correct
- No bash script errors

## Cron Job Configuration

### Current Cron Jobs
```bash
# Internet Archive snapshots (23:00 GMT+2)
0 23 * * * /home/MAS_ChangeHub/website_snapshot.sh >> /home/MAS_ChangeHub/snapshot.log 2>&1

# Contabo VM snapshots (00:00 GMT+2)
0 0 * * * /usr/bin/php /home/contabo-snapshots/snapshot-manager.php >> /home/contabo-snapshots/logs/cron.log 2>&1
```

### Schedule Timeline
```
22:00 - (preparation)
23:00 - Internet Archive snapshots START
23:15 - Internet Archive snapshots COMPLETE (estimated)
00:00 - Contabo VM snapshots START
00:15 - Contabo VM snapshots COMPLETE (estimated)
```

## File Permissions

All files have correct ownership and permissions:
```bash
-rwxr-xr-x. 1 root root    website_snapshot.sh
-rwxr-xr-x. 1 root root    test-discord-webhook.sh
-rw-r--r--. 1 root root    snapshot_config.conf
```

## Security Considerations

✅ **Implemented**:
- No sensitive data in webhook messages
- Webhook URL stored in config file
- Proper error handling for webhook failures
- No use of `await=true` parameter (per user rules)
- SSL/TLS enabled for webhook requests
- API credentials not exposed in notifications

## Usage

### Test Webhook
```bash
cd /home/MAS_ChangeHub
./test-discord-webhook.sh
```

### Disable Webhook
Edit `/home/MAS_ChangeHub/snapshot_config.conf`:
```bash
DISCORD_WEBHOOK_ENABLED=false
```

### Change Webhook URL
Edit `/home/MAS_ChangeHub/snapshot_config.conf`:
```bash
DISCORD_WEBHOOK_URL="your-new-webhook-url-here"
```

### Manual Run
```bash
/home/MAS_ChangeHub/website_snapshot.sh
```

## Next Scheduled Run

The cron job will run at:
- **Time**: 23:00 (11:00 PM)
- **Timezone**: Europe/Oslo (GMT+2)
- **Frequency**: Daily
- **Next run**: Tonight at 23:00

The next notification will be sent automatically when the cron job completes.

## Monitoring

You can verify webhook notifications by:
1. Checking Discord channel for messages
2. Reviewing logs: `/home/MAS_ChangeHub/snapshot.log`
3. Running test script: `./test-discord-webhook.sh`
4. Checking webhook errors in log files

## Comparison with Contabo Snapshots

### Internet Archive Snapshots (23:00)
- **What**: Website content snapshots
- **Where**: archive.org (Internet Archive)
- **Type**: HTML/webpage snapshots
- **Purpose**: Preserve website content publicly
- **Retention**: Permanent on archive.org
- **Discord Channel**: Same webhook as Contabo

### Contabo Snapshots (00:00)
- **What**: Virtual machine snapshots
- **Where**: Contabo infrastructure
- **Type**: Full VM disk snapshots
- **Purpose**: System backup and recovery
- **Retention**: 3 snapshots (rotating)
- **Discord Channel**: Same webhook

## Changelog

### Version 1.1 (2025-10-08)
- ✅ Added Discord webhook integration
- ✅ Changed schedule from 00:00 to 23:00 GMT+2
- ✅ Created test script for webhook validation
- ✅ Enhanced status reporting with Discord embeds
- ✅ Added dynamic Wayback Machine calendar links for each domain
- ✅ Updated documentation
- ✅ Moved README.md to to-do folder

### Version 1.0 (2025-10-07)
- Initial implementation
- Daily snapshot automation
- Internet Archive integration
- Comprehensive logging
- Status monitoring

## Features Highlight

### Dynamic Wayback Machine Links
Each domain in the Discord notification is a **clickable link** to its own Wayback Machine calendar:

- **newstargeted.com** → `https://web.archive.org/web/*/https://newstargeted.com/`
- **api.newstargeted.com** → `https://web.archive.org/web/*/https://api.newstargeted.com/`
- **infoskjerm.newstargeted.com** → `https://web.archive.org/web/*/https://infoskjerm.newstargeted.com/`
- **mas.newstargeted.com** → `https://web.archive.org/web/*/https://mas.newstargeted.com/`
- **discord.newstargeted.com** → `https://web.archive.org/web/*/https://discord.newstargeted.com/`

This makes it easy to:
- View historical snapshots for any specific domain
- See snapshot calendar for each sub-domain
- Track archiving history per domain
- Share specific domain archives with others

### Scalability
If you add more domains to snapshot in the future, they will automatically:
- Get their own Wayback Machine calendar link
- Show up in the Discord notification
- Be trackable individually

## Status: COMPLETE ✅

All features implemented and tested successfully. The system is ready for production use with:
- Discord notifications enabled with dynamic domain links
- New schedule (23:00 GMT+2)
- 1 hour separation from Contabo snapshots
- Full webhook integration tested and working
- Individual Wayback Machine calendar links for each domain

## Next Steps

The system will automatically:
1. Run at 23:00 GMT+2 tonight
2. Create snapshots of all 15 websites
3. Send Discord notification on completion
4. Log all activity to snapshot.log
5. Run 1 hour before Contabo VM snapshots

No further action required. The system is fully automated.

