# Configuration

Copy the example and edit the local file:

```bash
cp config.example.php config.php
```

All installation-specific settings belong in `config.php`:

- application name
- logo and home URL
- Jellyfin URL and API key
- Playback Reporting database path
- labelled media-library paths
- cache directory
- timezone

## Media libraries

```php
$mediaLibraries = [
    'Movies' => '/mnt/media/movies',
    'TV Series' => '/mnt/media/tv',
    'Anime' => '/mnt/media/anime',
];
```

The labels are displayed in the storage panel.

The older `$mediaPaths` format remains supported for existing installations,
but new installations should use `$mediaLibraries`.

Rebuild the dashboard cache after changing paths:

```bash
sudo -u www-data php bin/refresh-dashboard.php
```
