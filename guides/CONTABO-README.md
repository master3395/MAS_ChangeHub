# Contabo Snapshot Manager

Automated daily snapshot management for Contabo instances using the Contabo API.

## Features

- **Daily Snapshots**: Automatically creates daily snapshots at 00:00 GMT+2 (Europe/Oslo)
- **Smart Cleanup**: Automatically deletes oldest snapshots when limit is exceeded
- **Multi-Instance Support**: Manages snapshots for multiple instances simultaneously
- **Discord Notifications**: Sends detailed status reports to Discord via webhook
- **Comprehensive Logging**: Detailed logs for monitoring and troubleshooting
- **Error Handling**: Robust error handling with detailed error logs
- **Security**: Credentials stored in protected config file

## Target Instances

The script manages snapshots for these specific instances:
- `CP-2-4-4-Alma-sieve-1`
- `CP-2-4-4-Alma-sieve-2`
- `CP-2-4-4-Alma-sieve-3`

## Configuration

### 1. Update Credentials

Edit `/home/contabo-snapshots/config.php` and update:

```php
define('CONTABO_API_USER', 'your-email@example.com'); // Your Contabo login email
define('CONTABO_API_PASSWORD', 'your-api-password'); // Your Contabo API password
```

### 2. API Credentials

The following are already configured:
- **Client ID**: `INT-506877`
- **Client Secret**: `b170d60f-879c-4fee-9928-af2257c36690`

### 3. Discord Webhook (Optional)

Discord notifications are enabled by default. To disable or change the webhook:

```php
define('DISCORD_WEBHOOK_URL', 'your-webhook-url-here');
define('DISCORD_WEBHOOK_ENABLED', true); // Set to false to disable
```

The webhook sends notifications when snapshot operations complete, including:
- Number of instances processed
- Snapshots created and deleted
- Any errors encountered
- Timestamp and status

## Installation

1. **Set up the cron job**:
   ```bash
   chmod +x /home/contabo-snapshots/setup-cron.sh
   /home/contabo-snapshots/setup-cron.sh
   ```

2. **Test the installation**:
   ```bash
   chmod +x /home/contabo-snapshots/test-snapshots.sh
   /home/contabo-snapshots/test-snapshots.sh
   ```

3. **Test Discord webhook (optional)**:
   ```bash
   php /home/contabo-snapshots/test-discord-webhook.php
   ```

## Usage

### Manual Execution

Run the snapshot manager manually:
```bash
php /home/contabo-snapshots/snapshot-manager.php
```

### Automated Execution

The cron job runs automatically every day at 00:00 GMT+2 (Europe/Oslo timezone).

## Logging

### Log Files

- **Main Log**: `/home/contabo-snapshots/logs/snapshot-manager.log`
- **Error Log**: `/home/contabo-snapshots/logs/snapshot-errors.log`
- **Cron Log**: `/home/contabo-snapshots/logs/cron.log`

### View Logs

```bash
# View main log
tail -f /home/contabo-snapshots/logs/snapshot-manager.log

# View error log
tail -f /home/contabo-snapshots/logs/snapshot-errors.log

# View recent activity
tail -n 50 /home/contabo-snapshots/logs/snapshot-manager.log
```

## Configuration Options

### Snapshot Settings

```php
// Maximum snapshots to keep per instance
define('MAX_SNAPSHOTS_PER_INSTANCE', 3);

// Snapshot name prefix
define('SNAPSHOT_PREFIX', 'daily-backup-');
```

### Instance Names

To modify target instances, edit the `INSTANCE_NAMES` array in `config.php`:

```php
define('INSTANCE_NAMES', [
    'CP-2-4-4-Alma-sieve-1',
    'CP-2-4-4-Alma-sieve-2', 
    'CP-2-4-4-Alma-sieve-3'
]);
```

## Cron Job Details

The cron job is configured as:
```
0 0 * * * /usr/bin/php /home/contabo-snapshots/snapshot-manager.php >> /home/contabo-snapshots/logs/cron.log 2>&1
```

This runs:
- **Time**: 00:00 (midnight)
- **Timezone**: Europe/Oslo (GMT+2)
- **Frequency**: Daily
- **Logging**: All output redirected to cron.log

## Troubleshooting

### Common Issues

1. **Authentication Failed**
   - Verify API credentials in `config.php`
   - Check if API password is correct
   - Ensure API user has proper permissions

2. **Instance Not Found**
   - Verify instance names match exactly
   - Check if instances exist in your Contabo account
   - Ensure instances are in the correct region

3. **Permission Denied**
   - Check file permissions: `ls -la /home/contabo-snapshots/`
   - Ensure proper ownership: `chown -R newst3922:newst3922 /home/contabo-snapshots/`

4. **PHP Errors**
   - Check PHP version: `php -v`
   - Ensure required extensions: `php -m | grep -E "(curl|json)"`
   - Check PHP error log: `tail -f /var/log/php_errors.log`

### Debug Mode

For detailed debugging, run with error reporting:
```bash
php -d display_errors=1 /home/contabo-snapshots/snapshot-manager.php
```

## Security

- Configuration file is protected from direct web access
- Credentials are stored securely in `config.php`
- Log files contain no sensitive information
- Proper file permissions are enforced

## Monitoring

### Check Cron Job Status

```bash
# View current cron jobs
crontab -l

# Check cron service status
systemctl status crond

# View cron logs
tail -f /var/log/cron
```

### Verify Snapshots

Check your Contabo control panel to verify snapshots are being created and managed correctly.

## Support

For issues or questions:
1. Check the error logs first
2. Run the test script to verify configuration
3. Verify API credentials and permissions
4. Check Contabo API status

## Changelog

### Version 1.0 (2024-01-01)
- Initial release
- Daily snapshot management
- Automatic cleanup of old snapshots
- Multi-instance support
- Comprehensive logging
- Error handling and recovery
