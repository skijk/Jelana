# Fulflix Stats

Fulflix Stats is a lightweight, self-hosted statistics dashboard for Jellyfin.

It provides a compact overview of library totals, playback activity, watch time, top movies and TV series, active users, playback methods, client usage, recently added media, storage usage, and media profile information.

The project uses PHP, Apache, SQLite, and plain JavaScript. It does not require Docker or a JavaScript framework.

## Features

- Responsive dashboard for desktop and mobile
- Movie, TV series, and episode counts
- Playback totals and accumulated watch time
- Top movies and TV series for 7- and 30-day periods
- Active user rankings
- Daily playback activity
- Playback method statistics
- Client usage statistics
- Recently added media
- Storage breakdown
- Video and audio profile summary
- Local image proxy and cache
- Hourly JSON dashboard cache
- No direct database or API queries during normal page views

## Requirements

- PHP 8.1 or later
- Apache or another PHP-capable web server
- PHP extensions: `pdo_sqlite`, `curl`, `json`, and `mbstring`
- Access to a Jellyfin server
- Jellyfin Playback Reporting plugin
- Read access to the Playback Reporting SQLite database
- Optional read access to media paths for storage calculations

## Project Structure

```text
.
├── bin/
│   └── refresh-dashboard.php
├── css/
│   └── style.css
├── inc/
│   ├── dashboard.php
│   └── jellyfin.php
├── index.php
├── image.php
├── config.example.php
├── fulflix-stats.cron.example
├── HOURLY-CACHE-INSTALL.md
└── README.md
```

`config.php` is intentionally excluded from version control because it may contain local paths, server addresses, and API keys.

## Installation

Clone or copy the project into your web root:

```bash
cd /var/www/html
git clone <repository-url> fulflix-stats
cd fulflix-stats
```

Copy the example configuration:

```bash
cp config.example.php config.php
```

Edit `config.php` and configure:

- Jellyfin server URL
- Jellyfin API key
- Playback Reporting database path
- Media paths used for storage calculations

Make sure the web server user can read the required files and directories.

## Dashboard Cache

Normal page views do not query Jellyfin or the Playback Reporting database.

Instead, the dashboard reads a prepared JSON cache file:

```text
/tmp/fulflix-stats-cache/dashboard.json
```

Build or refresh it manually:

```bash
sudo -u www-data php /var/www/html/bin/refresh-dashboard.php
```

The refresh process uses a lock file to prevent overlapping runs and writes the JSON file atomically.

If the cache does not exist, the web interface builds it once so the dashboard can start before the cron job has been installed.

## Hourly Refresh

Install the included cron example:

```bash
sudo cp /var/www/html/fulflix-stats.cron.example /etc/cron.d/fulflix-stats
sudo chmod 644 /etc/cron.d/fulflix-stats
sudo touch /var/log/fulflix-stats.log
sudo chown www-data:www-data /var/log/fulflix-stats.log
```

The default schedule runs at the top of every hour:

```cron
0 * * * * www-data /usr/bin/php /var/www/html/bin/refresh-dashboard.php >> /var/log/fulflix-stats.log 2>&1
```

Check the latest refresh:

```bash
tail -n 20 /var/log/fulflix-stats.log
```

## Image Cache

Poster and backdrop images are proxied through `image.php` and stored locally under the Fulflix cache directory.

This reduces repeated image requests to Jellyfin and allows the dashboard to reuse cached files.

The cache is normally located under:

```text
/tmp/fulflix-stats-cache/
```

## Playback Reporting

Fulflix Stats uses Jellyfin's Playback Reporting data for playback history, watch time, rankings, client usage, and playback method statistics.

The Playback Reporting plugin must be installed and configured in Jellyfin. The PHP process must be able to read its SQLite database.

## Security

Do not commit `config.php` to a public repository.

Use a Jellyfin API key with only the access required by the dashboard. Keep the dashboard behind authentication or a trusted reverse proxy if it is reachable outside your local network.

## Development

Run syntax checks before committing:

```bash
php -l index.php
php -l image.php
php -l inc/dashboard.php
php -l inc/jellyfin.php
php -l bin/refresh-dashboard.php
```

Refresh the cache manually after backend changes:

```bash
sudo -u www-data php bin/refresh-dashboard.php
```

## License

No license has been selected yet.

Until a license is added, the source code remains copyrighted and is not automatically available for redistribution or modification by third parties.
