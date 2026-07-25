# Hourly Cache Installation

Fulflix Stats uses a prebuilt JSON cache so normal dashboard requests do not query Jellyfin or the Playback Reporting database.

## 1. Validate the PHP Files

```bash
cd /var/www/html

php -l index.php
php -l inc/dashboard.php
php -l bin/refresh-dashboard.php
```

## 2. Build the Initial Cache

Run the refresh script as the web server user:

```bash
sudo -u www-data php /var/www/html/bin/refresh-dashboard.php
```

A successful run should produce output similar to:

```text
[2026-07-25 17:30:00] Fulflix cache refreshed in 4.82 seconds.
```

## 3. Install the Cron Job

```bash
sudo cp /var/www/html/fulflix-stats.cron.example /etc/cron.d/fulflix-stats
sudo chmod 644 /etc/cron.d/fulflix-stats
```

Create the log file:

```bash
sudo touch /var/log/fulflix-stats.log
sudo chown www-data:www-data /var/log/fulflix-stats.log
```

The included cron job runs at the top of every hour.

## 4. Verify the Cache

```bash
ls -lh /tmp/fulflix-stats-cache/dashboard.json
head -c 200 /tmp/fulflix-stats-cache/dashboard.json
```

Check the refresh log:

```bash
tail -n 20 /var/log/fulflix-stats.log
```

Normal page views should only read the JSON file and should not update its modification time.

```bash
stat /tmp/fulflix-stats-cache/dashboard.json
```

Reload the dashboard and run the command again. The timestamp should remain unchanged until the scheduled refresh runs.

## Cache Files

The default cache directory is:

```text
/tmp/fulflix-stats-cache/
```

Typical files include:

```text
dashboard.json
dashboard.lock
images/
```

The lock file prevents multiple refresh processes from running at the same time. The dashboard cache is written atomically so the web interface never reads a partially written JSON file.
