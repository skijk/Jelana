# Fulflix Stats

En enkel PHP-baserad statistiksida för Jellyfin.

## Installation

1. Kopiera `config.example.php` till `config.php`.
2. Fyll i Jellyfin-adress och API-nyckel i `config.php`.
3. Kontrollera att Apache/PHP har `curl` aktiverat.
4. Öppna sidan i webbläsaren.

`config.php` ska inte versionshanteras.


## Cache och belastning

Dashboarden läser endast den färdigbyggda JSON-cachen vid vanliga sidvisningar.
Jellyfin-API:t och `playback_reporting.db` används endast av det separata
uppdateringsskriptet.

Bygg cachen manuellt:

```bash
sudo -u www-data php /var/www/html/bin/refresh-dashboard.php
```

Installera uppdatering varje hel timme:

```bash
sudo cp /var/www/html/fulflix-stats.cron.example /etc/cron.d/fulflix-stats
sudo chmod 644 /etc/cron.d/fulflix-stats
sudo touch /var/log/fulflix-stats.log
sudo chown www-data:www-data /var/log/fulflix-stats.log
```

Kontrollera senaste körningen:

```bash
tail -n 20 /var/log/fulflix-stats.log
```

Cachefilen ligger normalt här:

```text
/tmp/fulflix-stats-cache/dashboard.json
```

Om cachefilen saknas bygger webbgränssnittet den en enda gång, så att sidan
fortfarande kan starta innan cronjobbet installerats. Därefter ska ordinarie
sidvisningar inte läsa Jellyfin-databasen eller göra statistikfrågor mot API:t.
