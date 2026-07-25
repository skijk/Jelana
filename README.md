# Fulflix Stats

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4)
![Jellyfin](https://img.shields.io/badge/Jellyfin-dashboard-00A4DC)
![License](https://img.shields.io/badge/license-MIT-green)

**A lightweight, cache-driven statistics dashboard for Jellyfin.**

Fulflix Stats presents playback history, watch time, active users, library
growth, storage usage, media formats, and recently added titles in a compact
responsive interface. Normal page views read a prepared JSON cache instead of
querying Jellyfin and Playback Reporting on every request.

The application uses PHP, SQLite, Apache-compatible hosting, and plain
JavaScript. Docker and frontend frameworks are not required.

## Highlights

- Movie, TV series, episode, and user totals
- Playback count and watch time
- Most-watched movies and TV series for 7 and 30 days
- Most-active users
- Daily watch-time chart
- Playback-method and client statistics
- Recently added media and library growth
- Storage usage by media library
- Video codec, resolution, and audio codec summaries
- Local poster proxy and cache
- Hourly JSON dashboard cache with locking and atomic writes
- Responsive interface with configurable sections

## Screenshots

Add project screenshots under a `docs/` directory and reference them here when
the dashboard is published.

## Requirements

- PHP 8.1 or later
- Apache, Nginx with PHP-FPM, or another PHP-capable web server
- PHP extensions:
  - `curl`
  - `json`
  - `mbstring`
  - `pdo_sqlite`
- Jellyfin server access
- Jellyfin Playback Reporting plugin
- Read access to the Playback Reporting SQLite database
- Read access to media paths when storage totals are enabled

## Project Structure

```text
.
├── .github/
│   ├── ISSUE_TEMPLATE/
│   └── PULL_REQUEST_TEMPLATE.md
├── bin/
│   └── refresh-dashboard.php
├── css/
│   └── style.css
├── inc/
│   ├── dashboard.php
│   └── jellyfin.php
├── CHANGELOG.md
├── CONTRIBUTING.md
├── HOURLY-CACHE-INSTALL.md
├── LICENSE
├── README.md
├── SECURITY.md
├── config.example.php
├── fulflix-stats.cron.example
├── image.php
└── index.php
```

`config.php` is excluded from version control because it contains local paths,
server details, and an API key.

## Installation

Clone or copy the project into the web root:

```bash
cd /var/www/html
git clone <repository-url> fulflix-stats
cd fulflix-stats
```

Create the local configuration:

```bash
cp config.example.php config.php
```

Edit `config.php`:

```php
<?php

declare(strict_types=1);

$jellyfinServer = 'https://jellyfin.example.com';
$apiKey = 'YOUR_API_KEY';

$mediaPaths = [
    '/mnt/media/movies',
    '/mnt/media/tv',
];
```

The Playback Reporting database path is defined by `PLAYBACK_DATABASE` in
`inc/jellyfin.php`. Change that constant if Jellyfin stores the database
elsewhere.

The web server and refresh job require:

- Read access to `config.php`
- Read access to the Playback Reporting database
- Read access to the configured media directories
- Write access to the system temporary directory used for Fulflix caches

## Create the Cache Directory

Create a shared cache directory before running the web application or cron job:

```bash
sudo install -d -o www-data -g www-data -m 0770 /var/cache/fulflix-stats
sudo install -d -o www-data -g www-data -m 0770 /var/cache/fulflix-stats/posters
```

A fixed path is used instead of the system temporary directory so Apache,
PHP-FPM, and cron all access the same cache even when systemd `PrivateTmp` is
enabled.

## Build the Initial Cache

```bash
sudo -u www-data php bin/refresh-dashboard.php
```

A successful refresh prints a timestamp and elapsed time.

The dashboard cache is stored by default at:

```text
/var/cache/fulflix-stats/dashboard.json
```

Poster files are stored separately at:

```text
/var/cache/fulflix-stats/posters/
```

## Hourly Refresh

Install the included cron definition:

```bash
sudo cp fulflix-stats.cron.example /etc/cron.d/fulflix-stats
sudo chmod 644 /etc/cron.d/fulflix-stats
sudo touch /var/log/fulflix-stats.log
sudo chown www-data:www-data /var/log/fulflix-stats.log
```

The default schedule runs at the top of every hour:

```cron
0 * * * * www-data /usr/bin/php /var/www/html/bin/refresh-dashboard.php >> /var/log/fulflix-stats.log 2>&1
```

See [HOURLY-CACHE-INSTALL.md](HOURLY-CACHE-INSTALL.md) for verification and
troubleshooting steps.

## Cache Architecture

```text
Browser
  │
  └── index.php
        │
        └── dashboard.json

Scheduled refresh
  │
  └── bin/refresh-dashboard.php
        ├── Jellyfin API
        ├── Playback Reporting SQLite database
        └── dashboard.json
```

The refresh process:

1. Obtains an exclusive lock.
2. Collects Jellyfin and Playback Reporting data.
3. Writes a temporary JSON file.
4. Atomically renames the file into place.
5. Releases the lock.

If the cache does not exist, the first page request builds it once.

## Security

- Never commit `config.php`.
- Use a dedicated Jellyfin API key and rotate it if exposed.
- Restrict filesystem permissions to the required database and media paths.
- Place the dashboard behind authentication or a trusted reverse proxy when it
  is accessible outside a trusted network.
- Review [SECURITY.md](SECURITY.md) before reporting a vulnerability.

## Validation

Check every PHP file:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

Refresh the cache after backend changes:

```bash
sudo -u www-data php bin/refresh-dashboard.php
```

## Terminology

The interface uses the following terms consistently:

- **Playback count** for the number of distinct playback sessions
- **Watch time** for accumulated playback duration
- **TV Series** for series-level media
- **Recently Added** for newly indexed library items
- **Playback Methods** for direct play, direct stream, and transcoding
- **Media Profile** for codec and resolution summaries
- **Cached data** for the prepared dashboard JSON

## Known Limitations

- Playback accuracy depends on the data recorded by the Playback Reporting
  plugin.
- Series rankings depend on Jellyfin metadata resolving episodes to their
  parent series.
- Storage totals require filesystem access to the configured media paths.
- The dashboard currently uses one configured Jellyfin server.
- The interface does not provide authentication by itself.

## Roadmap

Potential future work:

- Configurable timezone and locale
- Optional application-level authentication
- Additional date ranges
- Exportable reports
- Automated tests
- Screenshot documentation

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

Fulflix Stats is available under the [MIT License](LICENSE).
