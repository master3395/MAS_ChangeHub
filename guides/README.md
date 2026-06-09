# Website Snapshot System

This system automatically creates daily snapshots of all newstargeted.com websites and sub-domains using the Internet Archive's Wayback Machine API.

## Overview

The snapshot system consists of:
- **Main script**: `website_snapshot.sh` - Creates snapshots of all websites
- **Test script**: `test_snapshot.sh` - Tests the snapshot functionality
- **Status checker**: `check_snapshots.sh` - Monitors snapshot status
- **Configuration**: `snapshot_config.conf` - Configuration settings
- **Cron job**: Runs daily at 23:00 GMT+2 (Europe/Oslo)

## Features

- ✅ **Daily automated snapshots** of all websites and sub-domains
- ✅ **Internet Archive integration** using Wayback Machine API
- ✅ **Enhanced snapshot options** (outlinks, screenshots, error pages)
- ✅ **Discord webhook notifications** with detailed status reports
- ✅ **Comprehensive logging** with success/failure tracking
- ✅ **Website accessibility checking** before snapshot creation
- ✅ **Automatic cleanup** of old logs
- ✅ **Error handling and notifications**
- ✅ **Status monitoring and reporting**
- ✅ **Wayback Machine link viewing** in CLI menu
- ✅ **Configurable snapshot options** via configuration file

## Websites Included

The system snapshots the following websites:
- https://newstargeted.com (main domain)
- https://api.newstargeted.com
- https://cmstest.newstargeted.com
- https://console.newstargeted.com
- https://convert.newstargeted.com
- https://dashboard.newstargeted.com
- https://diabetes.newstargeted.com
- https://discord.newstargeted.com
- https://extensions.newstargeted.com
- https://infoskjerm.newstargeted.com
- https://mas.newstargeted.com
- https://rawdata.newstargeted.com
- https://test.newstargeted.com
- https://webhook.newstargeted.com
- https://yourls.newstargeted.com

## File Structure

```
/home/MAS_ChangeHub/
├── website_snapshot.sh      # Main snapshot script
├── test_snapshot.sh         # Test script
├── check_snapshots.sh       # Status checker
├── menu.sh                  # Interactive CLI menu
├── snapshot_config.conf     # Configuration file
├── snapshot.log            # Main log file
├── test_snapshot.log       # Test log file
└── README.md               # This file
```

## Usage

### Manual Snapshot Creation
```bash
# Run snapshot for all websites
/home/MAS_ChangeHub/website_snapshot.sh

# Test the snapshot system
/home/MAS_ChangeHub/test_snapshot.sh

# Check snapshot status
/home/MAS_ChangeHub/check_snapshots.sh
```

### Cron Job
The system runs automatically daily at 23:00 GMT+2 (Europe/Oslo):
```bash
# View current cron jobs
crontab -l

# The snapshot cron job:
0 23 * * * /home/MAS_ChangeHub/website_snapshot.sh >> /home/MAS_ChangeHub/snapshot.log 2>&1
```

**Note**: This runs 1 hour before the Contabo snapshot manager (00:00) to ensure Internet Archive snapshots are captured first.

## Configuration

### Internet Archive API
The system uses the Internet Archive's Wayback Machine API with the following credentials:
- **Access Key**: XhXHGXAQ91hl3xwd
- **Secret Key**: YQ9r1GOi6ysc9jlD
- **API URL**: https://web.archive.org/save/

### Discord Webhook
The system sends notifications to Discord via webhook:
- **Webhook URL**: Configured in `snapshot_config.conf`
- **Enabled by default**: Set `DISCORD_WEBHOOK_ENABLED=true`
- **Test webhook**: Run `./test-discord-webhook.sh`

### Settings
Key configuration options in `snapshot_config.conf`:
- `LOG_RETENTION_DAYS=90` - Keep logs for 90 days
- `BACKUP_RETENTION_DAYS=30` - Keep local backups for 30 days
- `REQUEST_TIMEOUT=60` - Request timeout in seconds
- `DELAY_BETWEEN_REQUESTS=2` - Delay between requests in seconds
- `DISCORD_WEBHOOK_ENABLED=true` - Enable Discord notifications
- `DISCORD_WEBHOOK_URL` - Discord webhook URL

### Enhanced Snapshot Options
Internet Archive API enhancement options:
- `CAPTURE_OUTLINKS=true` - Capture all outlinks found on pages
- `CAPTURE_SCREENSHOT=true` - Take full-page screenshots
- `CAPTURE_ALL=true` - Capture error pages (4xx/5xx status codes)
- `SKIP_FIRST_ARCHIVE=true` - Disable ad blockers for complete capture
- `EMAIL_RESULT=false` - Send email notifications (requires email setup)
- `WACZ_FILE=false` - Generate downloadable WACZ files

## Monitoring

### Check Status
```bash
# Run status check
/home/MAS_ChangeHub/check_snapshots.sh
```

This will show:
- Last snapshot run time
- Success rate statistics
- Recent errors
- Backup file information
- Cron job status
- Internet Archive API status

### Test Discord Webhook
```bash
# Test Discord webhook integration
/home/MAS_ChangeHub/test-discord-webhook.sh
```

This will send a test notification to Discord to verify the webhook is working correctly.

### Test Enhanced Snapshot Options
```bash
# Test enhanced Internet Archive options
/home/MAS_ChangeHub/test_enhanced_snapshot.sh
```

This will test the enhanced snapshot functionality with all available options.

### View Logs
```bash
# View main log
tail -f /home/MAS_ChangeHub/snapshot.log

# View recent entries
tail -50 /home/MAS_ChangeHub/snapshot.log

# Search for errors
grep "❌" /home/MAS_ChangeHub/snapshot.log
```

## Troubleshooting

### Common Issues

1. **Website not accessible**
   - Check if the website is online
   - Verify DNS resolution
   - Check firewall settings

2. **Internet Archive API errors**
   - Verify API credentials
   - Check API rate limits
   - Ensure internet connectivity

3. **Cron job not running**
   - Check cron service status: `systemctl status crond`
   - Verify cron job syntax: `crontab -l`
   - Check log files for errors

4. **Permission issues**
   - Ensure scripts are executable: `chmod +x /home/MAS_ChangeHub/*.sh`
   - Check file ownership: `ls -la /home/MAS_ChangeHub/`

### Log Analysis
```bash
# Check for successful snapshots
grep "Successfully submitted" /home/MAS_ChangeHub/snapshot.log

# Check for failed snapshots
grep "Failed to submit" /home/MAS_ChangeHub/snapshot.log

# Check website accessibility
grep "Website is not accessible" /home/MAS_ChangeHub/snapshot.log
```

## Maintenance

### Regular Tasks
1. **Monitor logs** for errors and issues
2. **Check backup directory** for disk space
3. **Verify cron job** is running correctly
4. **Test API connectivity** periodically

### Cleanup
The system automatically cleans up:
- Log files older than 90 days
- Local backup files older than 30 days

Manual cleanup:
```bash
# Clean old logs
find /home/MAS_ChangeHub -name "*.log" -mtime +90 -delete

# Clean old backups
find /home/MAS_ChangeHub/backups -name "*.html.gz" -mtime +30 -delete
```

## Security

- API credentials are stored in the configuration file
- Local backups are compressed to save space
- Logs contain no sensitive information
- Scripts have proper permissions (755 for executables, 644 for configs)

## Support

For issues or questions:
1. Check the log files first
2. Run the status checker script
3. Test individual components
4. Review this documentation

## Changelog

### Version 1.1 (2025-10-08)
- Added Discord webhook notifications
- Changed schedule from 00:00 to 23:00 GMT+2 (runs 1 hour before Contabo snapshots)
- Added test script for webhook validation
- Enhanced status reporting with Discord embeds
- Updated documentation

### Version 1.0 (2025-10-07)
- Initial implementation
- Daily snapshot automation
- Internet Archive integration
- Local backup creation
- Comprehensive logging
- Status monitoring
- Error handling and notifications
