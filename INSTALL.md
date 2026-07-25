# Installing Jelana

These instructions target Debian or Ubuntu with Apache and PHP.

## 1. Install requirements

```bash
sudo apt update
sudo apt install apache2 php php-cli php-curl php-sqlite3 unzip git
```

Jellyfin must have the Playback Reporting plugin installed and populated.

## 2. Clone the project

```bash
cd /var/www/html
sudo git clone <repository-url> jelana
cd jelana
```

## 3. Create the local configuration

```bash
sudo cp config.example.php config.php
sudo nano config.php
```

All installation-specific values belong in `config.php`. Do not edit the
application source for local paths, names, URLs, or credentials.

## 4. Create writable cache directories

```bash
sudo install -d -o www-data -g www-data -m 0770 /var/cache/jelana
sudo install -d -o www-data -g www-data -m 0770 /var/cache/jelana/posters
```

Ensure `www-data` can read:

- `config.php`
- the Playback Reporting SQLite database
- every configured media-library directory

## 5. Build the first cache

```bash
sudo -u www-data php bin/refresh-dashboard.php
```

## 6. Install the hourly refresh job

```bash
sudo cp jelana.cron.example /etc/cron.d/jelana
sudo chmod 644 /etc/cron.d/jelana
sudo touch /var/log/jelana.log
sudo chown www-data:www-data /var/log/jelana.log
```

Adjust the project path inside `/etc/cron.d/jelana` if Jelana is not installed
under `/var/www/html/jelana`.

## 7. Open the dashboard

Open:

```text
http://SERVER-IP/jelana/
```

For public access, place Jelana behind authentication or a trusted reverse
proxy.

See [CONFIGURATION.md](CONFIGURATION.md) and
[HOURLY-CACHE-INSTALL.md](HOURLY-CACHE-INSTALL.md) for further details.
