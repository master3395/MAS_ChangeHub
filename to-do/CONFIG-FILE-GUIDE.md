# Configuration File Guide - snapshot_config.conf

## Overview

The `snapshot_config.conf` file provides complete control over your archive snapshot system. You can configure custom schedules, choose which domains to snapshot, and control all enhanced options directly from this file.

## Location

```bash
/home/MAS_ChangeHub/snapshot_config.conf
```

## Key Features

### ✅ **Custom Schedule Times**
Hard-code exact snapshot times in the config file

### ✅ **Domain Selection**
Choose to snapshot all domains, just the main domain, or a custom selection

### ✅ **Auto-Apply Schedules**
Automatically apply schedule from config file instead of using cron manager

### ✅ **Enhanced Options**
Configure all Internet Archive API options

## Configuration Sections

### 1. Schedule Configuration

#### **SNAPSHOT_FREQUENCY**
```bash
SNAPSHOT_FREQUENCY=1  # 1, 2, 3, 4, 6, or custom
```

Controls the frequency of snapshots. Used by schedule manager.

#### **CUSTOM_SCHEDULE_TIMES**
```bash
CUSTOM_SCHEDULE_TIMES="23:00"
```

Define exact times when snapshots should run. Enter times in 24-hour format (HH:MM) separated by spaces.

**Examples:**
```bash
# Single run (current)
CUSTOM_SCHEDULE_TIMES="23:00"

# Business hours
CUSTOM_SCHEDULE_TIMES="09:00 13:00 17:00 21:00"

# Every 4 hours
CUSTOM_SCHEDULE_TIMES="00:00 04:00 08:00 12:00 16:00 20:00"

# Morning and evening
CUSTOM_SCHEDULE_TIMES="08:00 20:00"

# Every 6 hours
CUSTOM_SCHEDULE_TIMES="00:00 06:00 12:00 18:00"
```

#### **AUTO_APPLY_SCHEDULE**
```bash
AUTO_APPLY_SCHEDULE=false  # true or false
```

Controls whether to use config-based scheduling:
- **false** (default): Use cron jobs via schedule manager
- **true**: Apply CUSTOM_SCHEDULE_TIMES from this file

**When to use true:**
- You want all configuration in one file
- You prefer editing config over using menu
- You want version-controlled schedules

**When to use false:**
- You prefer interactive schedule management
- You want to use pre-defined frequency options
- You're not comfortable editing config files

### 2. Domain Selection Configuration

#### **DOMAIN_SELECTION_MODE**
```bash
DOMAIN_SELECTION_MODE="all"  # all, main, or custom
```

Controls which domains to snapshot:

**Options:**

1. **"all"** (default)
   - Snapshots all 15 domains and sub-domains
   - Uses WEBSITES array
   - Recommended for complete coverage

2. **"main"**
   - Only snapshots the main domain
   - Uses MAIN_DOMAIN setting
   - Recommended for testing or minimal setup

3. **"custom"**
   - Snapshots selected domains
   - Uses CUSTOM_DOMAINS array
   - Recommended for selective archiving

#### **MAIN_DOMAIN**
```bash
MAIN_DOMAIN="https://newstargeted.com"
```

The main domain to snapshot when `DOMAIN_SELECTION_MODE="main"`

#### **CUSTOM_DOMAINS**
```bash
CUSTOM_DOMAINS=(
    "https://newstargeted.com"
    "https://api.newstargeted.com"
    "https://mas.newstargeted.com"
    "https://infoskjerm.newstargeted.com"
)
```

List of selected domains when `DOMAIN_SELECTION_MODE="custom"`

**How to customize:**
1. Copy domains from WEBSITES array below
2. Paste into CUSTOM_DOMAINS array
3. Add or remove domains as needed
4. Each domain on its own line with quotes

### 3. Enhanced Internet Archive Options

#### **CAPTURE_OUTLINKS**
```bash
CAPTURE_OUTLINKS=true  # true or false
```

Captures all external links found on pages.

#### **CAPTURE_SCREENSHOT**
```bash
CAPTURE_SCREENSHOT=true  # true or false
```

Takes full-page screenshots in PNG format.

#### **CAPTURE_ALL**
```bash
CAPTURE_ALL=true  # true or false
```

Captures pages even with HTTP 4xx/5xx status codes (error pages).

#### **SKIP_FIRST_ARCHIVE**
```bash
SKIP_FIRST_ARCHIVE=true  # true or false
```

Disables ad blockers for complete content capture.

#### **EMAIL_RESULT**
```bash
EMAIL_RESULT=false  # true or false
```

Sends email notifications when archive is complete (requires email setup with Internet Archive).

#### **WACZ_FILE**
```bash
WACZ_FILE=false  # true or false
```

Generates downloadable WACZ (Web Archive Collection) files.

### 4. Discord Webhook Configuration

```bash
DISCORD_WEBHOOK_ENABLED=true  # true or false
DISCORD_WEBHOOK_URL="your-webhook-url-here"
```

Controls Discord notifications.

## Usage Examples

### Example 1: Business Hours Only (Main Domain)

```bash
# Schedule Configuration
SNAPSHOT_FREQUENCY=custom
CUSTOM_SCHEDULE_TIMES="09:00 13:00 17:00"
AUTO_APPLY_SCHEDULE=true

# Domain Selection
DOMAIN_SELECTION_MODE="main"
MAIN_DOMAIN="https://newstargeted.com"
```

**Result:**
- Snapshots at 09:00, 13:00, and 17:00 GMT+2
- Only the main domain
- 3 snapshots per day

### Example 2: Critical Domains Every 6 Hours

```bash
# Schedule Configuration
SNAPSHOT_FREQUENCY=custom
CUSTOM_SCHEDULE_TIMES="00:00 06:00 12:00 18:00"
AUTO_APPLY_SCHEDULE=true

# Domain Selection
DOMAIN_SELECTION_MODE="custom"
CUSTOM_DOMAINS=(
    "https://newstargeted.com"
    "https://api.newstargeted.com"
    "https://mas.newstargeted.com"
)
```

**Result:**
- Snapshots every 6 hours (4x daily)
- 3 selected critical domains
- 12 snapshots per day (4 runs × 3 domains)

### Example 3: All Domains Twice Daily

```bash
# Schedule Configuration
SNAPSHOT_FREQUENCY=custom
CUSTOM_SCHEDULE_TIMES="11:00 23:00"
AUTO_APPLY_SCHEDULE=true

# Domain Selection
DOMAIN_SELECTION_MODE="all"
```

**Result:**
- Snapshots at 11:00 and 23:00 GMT+2
- All 15 domains
- 30 snapshots per day (2 runs × 15 domains)

### Example 4: Testing Configuration (No Auto-Apply)

```bash
# Schedule Configuration
SNAPSHOT_FREQUENCY=1
CUSTOM_SCHEDULE_TIMES="23:00"
AUTO_APPLY_SCHEDULE=false  # Use schedule manager instead

# Domain Selection
DOMAIN_SELECTION_MODE="custom"
CUSTOM_DOMAINS=(
    "https://test.newstargeted.com"
)
```

**Result:**
- Manually control schedule via schedule manager
- Only test subdomain
- Config ready but not auto-applied

## Applying Configuration

### Method 1: Auto-Apply (Recommended)

1. **Edit the config file:**
```bash
nano /home/MAS_ChangeHub/snapshot_config.conf
```

2. **Set your preferences:**
```bash
CUSTOM_SCHEDULE_TIMES="09:00 17:00"
AUTO_APPLY_SCHEDULE=true
DOMAIN_SELECTION_MODE="custom"
```

3. **Apply the configuration:**
```bash
cd /home/MAS_ChangeHub
./apply_config_schedule.sh
```

### Method 2: Via Menu

1. **Launch the menu:**
```bash
cd /home/MAS_ChangeHub
./menu.sh
```

2. **Navigate to:**
   - Cron Job Management (option 5)
   - Apply Schedule from Config File (option 3)

### Method 3: Manual Test

Test without applying to cron:

```bash
cd /home/MAS_ChangeHub
./website_snapshot.sh
```

This will use the domain selection from config but won't change the schedule.

## Verification

### Check Current Configuration

```bash
cd /home/MAS_ChangeHub

# View current config
cat snapshot_config.conf | grep -A5 "CUSTOM_SCHEDULE_TIMES\|DOMAIN_SELECTION_MODE"

# Check active cron jobs
crontab -l | grep MAS_ChangeHub

# Test configuration
./website_snapshot.sh
```

### Monitor Results

```bash
# View logs
tail -f /home/MAS_ChangeHub/snapshot.log

# Check what was snapshotted
grep "Processing:" /home/MAS_ChangeHub/snapshot.log | tail -20

# Check domain mode
grep "Domain mode:" /home/MAS_ChangeHub/snapshot.log | tail -5
```

## Troubleshooting

### Problem: Config changes not taking effect

**Solution 1:** Check AUTO_APPLY_SCHEDULE
```bash
grep "AUTO_APPLY_SCHEDULE" /home/MAS_ChangeHub/snapshot_config.conf
```

If false, run:
```bash
./apply_config_schedule.sh
```

**Solution 2:** Verify syntax
```bash
# Test config loading
bash -n /home/MAS_ChangeHub/snapshot_config.conf
```

### Problem: Custom domains not being snapshotted

**Check domain mode:**
```bash
grep "DOMAIN_SELECTION_MODE" /home/MAS_ChangeHub/snapshot_config.conf
```

**Check custom domains list:**
```bash
grep -A10 "CUSTOM_DOMAINS=" /home/MAS_ChangeHub/snapshot_config.conf
```

**Test manually:**
```bash
./website_snapshot.sh
# Check log for "Domain mode:" line
```

### Problem: Schedule times not working

**Verify time format:**
- Must be HH:MM (24-hour format)
- Must be separated by spaces
- Must be in quotes

**Correct:**
```bash
CUSTOM_SCHEDULE_TIMES="09:00 13:00 17:00"
```

**Incorrect:**
```bash
CUSTOM_SCHEDULE_TIMES=09:00 13:00 17:00    # Missing quotes
CUSTOM_SCHEDULE_TIMES="9:00 13:00"         # Missing leading zero
CUSTOM_SCHEDULE_TIMES="09:00,13:00"        # Wrong separator
```

## Best Practices

### 1. Start Small
```bash
# Test with one domain first
DOMAIN_SELECTION_MODE="main"
CUSTOM_SCHEDULE_TIMES="14:00"
AUTO_APPLY_SCHEDULE=true
```

### 2. Incremental Changes
```bash
# Add one domain at a time to custom list
CUSTOM_DOMAINS=(
    "https://newstargeted.com"  # Start here
    # "https://api.newstargeted.com"  # Add next
)
```

### 3. Monitor Impact
```bash
# Check logs after each change
tail -50 /home/MAS_ChangeHub/snapshot.log

# Check Discord notifications
# Verify snapshot success rate
```

### 4. Document Changes
Add comments to your config:
```bash
# Changed 2025-10-12: Testing business hours only
CUSTOM_SCHEDULE_TIMES="09:00 17:00"
```

### 5. Backup Config
```bash
cp /home/MAS_ChangeHub/snapshot_config.conf \
   /home/MAS_ChangeHub/snapshot_config.conf.backup
```

## Advanced Configuration

### Combining with Contabo Snapshots

Keep last run at 23:00 to maintain 1-hour gap before Contabo VM snapshots at 00:00:

```bash
CUSTOM_SCHEDULE_TIMES="07:00 15:00 23:00"
```

### Development vs Production

**Development:**
```bash
DOMAIN_SELECTION_MODE="custom"
CUSTOM_DOMAINS=(
    "https://test.newstargeted.com"
    "https://cmstest.newstargeted.com"
)
CUSTOM_SCHEDULE_TIMES="09:00 17:00"  # Business hours only
```

**Production:**
```bash
DOMAIN_SELECTION_MODE="all"
CUSTOM_SCHEDULE_TIMES="11:00 23:00"  # Morning and evening
```

### High-Frequency Monitoring

For websites with frequent updates:

```bash
DOMAIN_SELECTION_MODE="custom"
CUSTOM_DOMAINS=(
    "https://newstargeted.com"
    "https://api.newstargeted.com"
)
CUSTOM_SCHEDULE_TIMES="03:00 07:00 11:00 15:00 19:00 23:00"  # Every 4 hours
```

**Warning:** Respect Internet Archive rate limits (1 snapshot per URL per hour).

## Configuration Templates

### Template 1: Minimal (Main Domain Daily)
```bash
CUSTOM_SCHEDULE_TIMES="23:00"
AUTO_APPLY_SCHEDULE=true
DOMAIN_SELECTION_MODE="main"
```

### Template 2: Standard (All Domains Twice Daily)
```bash
CUSTOM_SCHEDULE_TIMES="11:00 23:00"
AUTO_APPLY_SCHEDULE=true
DOMAIN_SELECTION_MODE="all"
```

### Template 3: Business (Selected Domains Business Hours)
```bash
CUSTOM_SCHEDULE_TIMES="09:00 13:00 17:00 21:00"
AUTO_APPLY_SCHEDULE=true
DOMAIN_SELECTION_MODE="custom"
CUSTOM_DOMAINS=(
    "https://newstargeted.com"
    "https://api.newstargeted.com"
    "https://mas.newstargeted.com"
)
```

### Template 4: High-Frequency (Critical Domains Every 4 Hours)
```bash
CUSTOM_SCHEDULE_TIMES="03:00 07:00 11:00 15:00 19:00 23:00"
AUTO_APPLY_SCHEDULE=true
DOMAIN_SELECTION_MODE="custom"
CUSTOM_DOMAINS=(
    "https://newstargeted.com"
    "https://api.newstargeted.com"
)
```

## Summary

The `snapshot_config.conf` file provides:

✅ **Complete control** over snapshot scheduling
✅ **Flexible domain selection** (all, main, or custom)
✅ **Hard-coded schedules** for version control
✅ **Auto-apply capability** for hands-free configuration
✅ **Enhanced options** for comprehensive archiving

**Quick Reference:**
```bash
# Edit config
nano /home/MAS_ChangeHub/snapshot_config.conf

# Apply config
./apply_config_schedule.sh

# Test config
./website_snapshot.sh

# Check logs
tail -f snapshot.log
```

For more information, see:
- `/home/MAS_ChangeHub/to-do/SCHEDULE-FREQUENCY-GUIDE.md`
- `/home/MAS_ChangeHub/QUICK-START-SCHEDULE.txt`

