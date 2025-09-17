# GreenGeeks Shared Hosting Cron Setup

## Overview
GreenGeeks shared hosting has a minimum cron interval of 15 minutes. This setup optimizes EDI file monitoring for this constraint.

## Cron Configuration

### Recommended Cron Entry
Add this to your cPanel Cron Jobs (or via command line if available):

```bash
# EDI File Monitor - Every 15 minutes
*/15 * * * * /usr/bin/php /home/username/public_html/edimodule/cron_monitor.php
```

### Alternative Intervals
- **Every 15 minutes**: `*/15 * * * *` (Recommended for active EDI)
- **Every 30 minutes**: `*/30 * * * *` (Light EDI traffic)
- **Every hour**: `0 * * * *` (Minimal EDI traffic)

## Setup Steps

### 1. In cPanel Cron Jobs:
1. Log into your GreenGeeks cPanel
2. Go to "Cron Jobs" under "Advanced"
3. Add new cron job with:
   - **Minute**: */15
   - **Hour**: * 
   - **Day**: *
   - **Month**: *
   - **Weekday**: *
   - **Command**: `/usr/bin/php /home/yourusername/public_html/edimodule/cron_monitor.php`

### 2. Update File Paths:
Replace `/home/yourusername/public_html/` with your actual GreenGeeks path.

### 3. Environment Variables:
Ensure your `.env` file has:
```bash
# Cron-optimized settings
FILE_MONITOR_ENABLED=true
LOG_FILE_PATH=/home/yourusername/public_html/edimodule/logs/cron_monitor.log

# Production SFTP settings
SFTP_HOST=edi.nifco.com
SFTP_USERNAME=greenfield_plastics_edi
SFTP_PASSWORD=your_production_password
```

## Features for Shared Hosting

### Lock File Protection
- Prevents multiple cron instances from running simultaneously
- Uses `/tmp/edi_cron_monitor.lock` for process coordination

### Enhanced Logging
- Detailed logging for each cron execution
- Separate log file: `logs/cron_monitor.log`
- Includes execution time, downloaded files, processing results

### Error Handling
- Graceful handling of SFTP connection issues
- Detailed error reporting for troubleshooting
- Exit codes for cron monitoring

### Performance Optimization
- Single execution model (not continuous daemon)
- Memory-efficient processing
- Quick execution to minimize server load

## Monitoring Cron Performance

### View Recent Logs:
```bash
tail -f /home/yourusername/public_html/edimodule/logs/cron_monitor.log
```

### Check Cron Execution:
Look for cron emails or check cPanel cron job logs.

### Test Manual Execution:
```bash
cd /home/yourusername/public_html/edimodule
php cron_monitor.php
```

## Troubleshooting

### Common Issues:
1. **Permission errors**: Ensure files are executable (755)
2. **Path issues**: Use absolute paths in cron commands
3. **Memory limits**: Shared hosting may have PHP memory restrictions
4. **SFTP timeouts**: GreenGeeks may have network restrictions

### Debug Mode:
Add debug logging by setting in `.env`:
```bash
LOG_LEVEL=debug
DEBUG_EDI_PARSING=true
```

## Production Checklist

- [ ] Cron job scheduled in cPanel
- [ ] File permissions set correctly (755)
- [ ] `.env` file configured with production credentials
- [ ] Log directory writable
- [ ] SFTP connection tested manually
- [ ] Monitor logs for first few executions

## Comparison: Daemon vs Cron

| Feature | Daemon Mode | Cron Mode (GreenGeeks) |
|---------|-------------|------------------------|
| **Frequency** | Every 60 seconds | Every 15 minutes |
| **Resource Usage** | Continuous | Periodic |
| **Shared Hosting** | Not allowed | ✓ Supported |
| **Memory Efficiency** | Higher usage | Lower usage |
| **Real-time Processing** | ✓ Near real-time | 15-minute delay |
| **Reliability** | Process monitoring needed | Cron reliability |

## Notes
- 15-minute intervals are sufficient for most EDI scenarios
- Nifco typically doesn't require real-time processing
- Consider business hours scheduling if needed
- Monitor initial setup closely for any issues