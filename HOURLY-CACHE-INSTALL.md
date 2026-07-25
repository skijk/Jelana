## Create the shared cache directory

```bash
sudo install -d -o www-data -g www-data -m 0770 /var/cache/jelana
sudo install -d -o www-data -g www-data -m 0770 /var/cache/jelana/posters
```

This avoids separate temporary directories when Apache or PHP-FPM runs with
systemd `PrivateTmp=yes`.

# Hourly Cache Installation

Jelana uses a prebuilt JSON cache so normal dashboard requests do not query Jellyfin or the Playback Reporting database.

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
sudo -u www-data php /var/www/html/jelana/bin/refresh-dashboard.php
```

A successful run should produce output similar to:

```text
[2026-07-25 17:30:00] Jelana cache refreshed in 4.82 seconds.
```

## 3. Install the Cron Job

```bash
sudo cp /var/www/html/jelana.cron.example /etc/cron.d/jelana
sudo chmod 644 /etc/cron.d/jelana
```

Create the log file:

```bash
sudo touch /var/log/jelana.log
sudo chown www-data:www-data /var/log/jelana.log
```

The included cron job runs at the top of every hour.

## 4. Verify the Cache

```bash
ls -lh /var/cache/jelana/dashboard.json
head -c 200 /var/cache/jelana/dashboard.json
```

Check the refresh log:

```bash
tail -n 20 /var/log/jelana.log
```

Normal page views should only read the JSON file and should not update its modification time.

```bash
stat /var/cache/jelana/dashboard.json
```

Reload the dashboard and run the command again. The timestamp should remain unchanged until the scheduled refresh runs.

## Cache Files

The default cache directory is:

```text
/var/cache/jelana/
```

Typical files include:

```text
dashboard.json
dashboard.lock
images/
```

The lock file prevents multiple refresh processes from running at the same time. The dashboard cache is written atomically so the web interface never reads a partially written JSON file.
