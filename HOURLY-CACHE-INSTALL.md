# Installation of hourly cache-update
# The example presumes your installation is in /var/www/html
# Modify as needed for your installation

Copy projekt files and then run:

```bash
cd /var/www/html

php -l index.php
php -l inc/dashboard.php
php -l bin/refresh-dashboard.php

sudo -u www-data php bin/refresh-dashboard.php

sudo cp fulflix-stats.cron.example /etc/cron.d/fulflix-stats
sudo chmod 644 /etc/cron.d/fulflix-stats

sudo touch /var/log/fulflix-stats.log
sudo chown www-data:www-data /var/log/fulflix-stats.log
```

Verify:

```bash
cat /tmp/fulflix-stats-cache/dashboard.json | head -c 200
tail -n 20 /var/log/fulflix-stats.log
```

Cronjob runs every hour. Regular pageloads reads only the JSON-cache
