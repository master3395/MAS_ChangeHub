# Schedule Frequency Guide - Archive Snapshots

## Overview

The MAS_ChangeHub system now supports running multiple times per day with flexible scheduling options. You can configure it to run 1, 2, 3, 4, 6, or even more times daily, or create custom schedules.

## Quick Start

### Using the Schedule Manager

```bash
# Launch the interactive schedule manager
cd /home/MAS_ChangeHub
./schedule_manager.sh
```

### Quick Commands

```bash
# View current schedule
./schedule_manager.sh --status

# Set frequency quickly (1, 2, 3, 4, or 6)
./schedule_manager.sh --set 2    # Run twice daily
./schedule_manager.sh --set 4    # Run 4 times daily
```

## Pre-Defined Schedules

### 1. Once Daily (Current Default)
- **Frequency**: 1x per day
- **Times**: 23:00 GMT+2
- **Use Case**: Standard daily archiving
- **Benefit**: Runs 1 hour before Contabo VM snapshots

```bash
# Set via schedule manager
./schedule_manager.sh --set 1
```

### 2. Twice Daily
- **Frequency**: 2x per day
- **Times**: 11:00, 23:00 GMT+2 (12 hours apart)
- **Use Case**: Business hours + evening coverage
- **Benefit**: Captures major content updates

```bash
# Set via schedule manager
./schedule_manager.sh --set 2
```

### 3. Three Times Daily
- **Frequency**: 3x per day
- **Times**: 07:00, 15:00, 23:00 GMT+2 (8 hours apart)
- **Use Case**: Regular business monitoring
- **Benefit**: Morning, afternoon, and evening coverage

```bash
# Set via schedule manager
./schedule_manager.sh --set 3
```

### 4. Four Times Daily
- **Frequency**: 4x per day
- **Times**: 05:00, 11:00, 17:00, 23:00 GMT+2 (6 hours apart)
- **Use Case**: Frequent content changes
- **Benefit**: Every 6 hours coverage

```bash
# Set via schedule manager
./schedule_manager.sh --set 4
```

### 5. Six Times Daily
- **Frequency**: 6x per day
- **Times**: 03:00, 07:00, 11:00, 15:00, 19:00, 23:00 GMT+2 (4 hours apart)
- **Use Case**: High-frequency monitoring
- **Benefit**: Every 4 hours coverage

```bash
# Set via schedule manager
./schedule_manager.sh --set 6
```

## Custom Schedules

You can create completely custom schedules with any times you want:

### Via Schedule Manager
1. Run `./schedule_manager.sh`
2. Select option `6` for "Custom schedule"
3. Enter times in 24-hour format separated by spaces
4. Example: `06:00 12:00 18:00 23:00`

### Manual Cron Setup
```bash
# Edit crontab directly
crontab -e

# Add custom entries (example: 8AM, 2PM, 10PM)
0 8 * * * /home/MAS_ChangeHub/website_snapshot.sh >> /home/MAS_ChangeHub/snapshot.log 2>&1
0 14 * * * /home/MAS_ChangeHub/website_snapshot.sh >> /home/MAS_ChangeHub/snapshot.log 2>&1
0 22 * * * /home/MAS_ChangeHub/website_snapshot.sh >> /home/MAS_ChangeHub/snapshot.log 2>&1
```

## Important Considerations

### Internet Archive Rate Limits

⚠️ **CRITICAL**: Internet Archive allows **1 snapshot per URL per hour**

**What this means:**
- Running snapshots more frequently than once per hour per URL may result in some being skipped
- With 15 websites taking ~2 seconds each = ~30 seconds total
- You can safely run snapshots every hour if needed
- **Recommended maximum**: 6 times daily (every 4 hours)

### Best Practices

#### For 15 Websites (Current Setup)
- ✅ **1-6 times daily**: Safe and recommended
- ⚠️ **Every hour**: Possible but may hit rate limits
- ❌ **More than once per hour**: Not recommended

#### Timing Considerations
1. **Business Hours**: 07:00-23:00 GMT+2
2. **Peak Updates**: Usually during business hours
3. **Off-Peak**: 00:00-06:00 GMT+2 (least content changes)
4. **Contabo Conflict**: Avoid 00:00 GMT+2 (Contabo VM snapshots)

## Configuration File

The schedule frequency is stored in `/home/MAS_ChangeHub/snapshot_config.conf`:

```bash
# Schedule Configuration
SNAPSHOT_FREQUENCY=1  # Current frequency (1, 2, 3, 4, 6, or custom)
```

This is automatically updated when you use the schedule manager.

## Monitoring Multiple Runs

### View All Scheduled Times
```bash
# View all archive snapshot cron jobs
crontab -l | grep MAS_ChangeHub
```

### Check Execution Logs
```bash
# View recent executions
grep "Starting daily website snapshot process" /home/MAS_ChangeHub/snapshot.log

# Count runs per day
grep "Starting daily website snapshot process" /home/MAS_ChangeHub/snapshot.log | grep "$(date +%Y-%m-%d)" | wc -l
```

### Discord Notifications
Each run will send a separate Discord notification showing:
- Timestamp of execution
- Success/failure count
- Enhanced options used
- Failed URLs (if any)

## Use Cases and Recommendations

### Standard Websites (Low Change Frequency)
**Recommendation**: 1x daily (23:00 GMT+2)
- **Pros**: Simple, reliable, minimal API usage
- **Cons**: May miss some content changes
- **Best for**: Static sites, documentation, portfolios

### Active Websites (Medium Change Frequency)
**Recommendation**: 2-3x daily (11:00, 15:00, 23:00 GMT+2)
- **Pros**: Captures most content updates, reasonable API usage
- **Cons**: May miss some rapid changes
- **Best for**: Blogs, news sites, business sites

### High-Traffic Websites (High Change Frequency)
**Recommendation**: 4-6x daily (every 4-6 hours)
- **Pros**: Comprehensive coverage, catches most changes
- **Cons**: Higher API usage, more notifications
- **Best for**: E-commerce, social platforms, news aggregators

### Development/Testing Environments
**Recommendation**: Custom schedule (during business hours only)
- **Example**: 09:00, 12:00, 15:00, 18:00 GMT+2
- **Pros**: Captures testing/development changes
- **Cons**: N/A
- **Best for**: Staging sites, test environments

## Troubleshooting

### Problem: Some snapshots are failing
**Solution**: Reduce frequency or check Internet Archive rate limits

```bash
# Check for 429 errors (rate limit)
grep "HTTP 429" /home/MAS_ChangeHub/snapshot.log
```

### Problem: Too many Discord notifications
**Solution**: 
1. Reduce snapshot frequency
2. Or disable Discord notifications for some runs:
```bash
# Edit config file
nano /home/MAS_ChangeHub/snapshot_config.conf

# Change:
DISCORD_WEBHOOK_ENABLED=false  # For specific runs
```

### Problem: Cron jobs not running
**Solution**: Check cron service and logs

```bash
# Check cron service
systemctl status crond

# View cron logs
journalctl -u crond -n 50

# Verify cron jobs
crontab -l | grep MAS_ChangeHub
```

### Problem: Duplicate cron entries
**Solution**: Clean up and reconfigure

```bash
# Remove all archive snapshot crons
crontab -l | grep -v MAS_ChangeHub | crontab -

# Then reconfigure using schedule manager
./schedule_manager.sh
```

## Integration with Contabo Snapshots

### Current Setup
```
22:00 - (preparation)
23:00 - Internet Archive snapshots (RUN 1)
00:00 - Contabo VM snapshots
```

### With Multiple Runs Per Day
```
Example: 4x daily

05:00 - Internet Archive snapshots (RUN 1)
11:00 - Internet Archive snapshots (RUN 2)
17:00 - Internet Archive snapshots (RUN 3)
23:00 - Internet Archive snapshots (RUN 4)
00:00 - Contabo VM snapshots
```

**Important**: Always keep at least one run at 23:00 GMT+2 to maintain the 1-hour gap before Contabo snapshots.

## Performance Impact

### Disk Space
- **Log files**: Each run adds ~50-100 KB to logs
- **Monthly growth**: ~1-5 MB with 4x daily runs
- **Auto-cleanup**: Logs older than 90 days are automatically deleted

### API Usage
- **Current**: 15 URLs × 1 run/day = 15 API calls/day
- **2x daily**: 15 URLs × 2 runs/day = 30 API calls/day
- **4x daily**: 15 URLs × 4 runs/day = 60 API calls/day
- **6x daily**: 15 URLs × 6 runs/day = 90 API calls/day

### Discord Notifications
- **Current**: 1 notification/day
- **4x daily**: 4 notifications/day
- **Consider**: Creating a dedicated Discord channel for frequent updates

## Migration from Current Setup

### Step 1: Check Current Schedule
```bash
./schedule_manager.sh --status
```

### Step 2: Choose New Frequency
```bash
# Interactive
./schedule_manager.sh

# Or quick set
./schedule_manager.sh --set 2  # For twice daily
```

### Step 3: Verify New Schedule
```bash
# Check cron entries
crontab -l | grep MAS_ChangeHub

# Check next run times
# The cron jobs will show when they'll execute next
```

### Step 4: Monitor First Few Runs
```bash
# Watch logs in real-time
tail -f /home/MAS_ChangeHub/snapshot.log

# Check Discord notifications
```

## Examples

### Example 1: Enable Twice Daily (Quick)
```bash
cd /home/MAS_ChangeHub
./schedule_manager.sh --set 2
```

Output:
```
✅ Schedule set: Twice daily at 11:00 and 23:00 GMT+2
ℹ️  Note: Internet Archive allows 1 snapshot per URL per hour.
   Running too frequently may result in some snapshots being skipped.
```

### Example 2: Custom Business Hours Schedule
```bash
cd /home/MAS_ChangeHub
./schedule_manager.sh

# Choose option 6 (Custom schedule)
# Enter: 09:00 13:00 17:00 21:00
```

Output:
```
✓ Added: 09:00 GMT+2
✓ Added: 13:00 GMT+2
✓ Added: 17:00 GMT+2
✓ Added: 21:00 GMT+2

✅ Custom schedule set with 4 snapshot time(s)
```

### Example 3: Disable All Schedules
```bash
cd /home/MAS_ChangeHub
./schedule_manager.sh

# Choose option 7 (Disable all schedules)
```

Output:
```
⚠️  Disabling all snapshot schedules...
✅ All snapshot schedules disabled
💡 To re-enable, run this script and choose a frequency
```

## Command Reference

### Schedule Manager Commands
```bash
# Interactive menu
./schedule_manager.sh

# View current schedule
./schedule_manager.sh --status

# Quick set frequency
./schedule_manager.sh --set 1   # Once daily
./schedule_manager.sh --set 2   # Twice daily
./schedule_manager.sh --set 3   # 3x daily
./schedule_manager.sh --set 4   # 4x daily
./schedule_manager.sh --set 6   # 6x daily
```

### Direct Cron Commands
```bash
# View all archive snapshot crons
crontab -l | grep MAS_ChangeHub

# Remove all archive snapshot crons
crontab -l | grep -v MAS_ChangeHub | crontab -

# Add single cron entry
(crontab -l; echo "0 23 * * * /home/MAS_ChangeHub/website_snapshot.sh >> /home/MAS_ChangeHub/snapshot.log 2>&1") | crontab -
```

### Monitoring Commands
```bash
# Check snapshot status
cd /home/MAS_ChangeHub
./check_snapshots.sh

# View recent logs
tail -50 /home/MAS_ChangeHub/snapshot.log

# Count today's runs
grep "Starting daily website snapshot process" /home/MAS_ChangeHub/snapshot.log | grep "$(date +%Y-%m-%d)" | wc -l

# Check for errors
grep "❌" /home/MAS_ChangeHub/snapshot.log | tail -20
```

## Best Practices Summary

1. ✅ **Start with 1x or 2x daily** - Test before increasing frequency
2. ✅ **Monitor logs and Discord** - Watch for rate limit errors
3. ✅ **Keep 23:00 run** - Maintain gap before Contabo snapshots
4. ✅ **Use schedule manager** - Easier than editing cron directly
5. ✅ **Document changes** - Note when and why you changed frequency
6. ⚠️ **Respect API limits** - Max 1 snapshot per URL per hour
7. ⚠️ **Watch disk space** - More runs = more logs
8. ❌ **Don't exceed 6x daily** - Risk hitting rate limits
9. ❌ **Don't run every hour** - Unless you have very few URLs
10. ❌ **Don't disable 23:00 run** - Without setting up an alternative

## Support

For issues or questions:
1. Run `./check_snapshots.sh` to check system status
2. Run `./schedule_manager.sh --status` to view schedule
3. Check logs: `tail -f /home/MAS_ChangeHub/snapshot.log`
4. Review Discord notifications for errors
5. Check Internet Archive rate limits if seeing 429 errors

## Changelog

### Version 1.2 (2025-10-12)
- ✅ Added schedule frequency configuration
- ✅ Created interactive schedule manager
- ✅ Added pre-defined schedules (1x, 2x, 3x, 4x, 6x daily)
- ✅ Added custom schedule support
- ✅ Updated menu integration
- ✅ Added quick command options
- ✅ Enhanced documentation

### Version 1.1 (2025-10-08)
- Added Discord webhook notifications
- Changed default schedule to 23:00 GMT+2
- Enhanced snapshot options

### Version 1.0 (2025-10-07)
- Initial implementation
- Daily snapshot automation at 00:00 GMT+2

