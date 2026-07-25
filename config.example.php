<?php

declare(strict_types=1);

/*
 * Local configuration.
 *
 * Copy this file to config.php. The real config.php is ignored by Git.
 */

// Name shown in the browser title, page heading and CLI output.
$appName = 'Jelana';

// Jellyfin API connection.
$jellyfinServer = 'https://jellyfin.example.com:8920';
$apiKey = 'YOUR_API_KEY';

// Branding in the fixed top bar.
$brandHomeUrl = $jellyfinServer;
$brandLogoUrl = ''; // Optional image URL. Empty = show $appName as text.

// Jellyfin Playback Reporting plugin database.
$playbackDatabase = '/var/lib/jellyfin/data/playback_reporting.db';

// Labels and paths shown in the storage panel.
$mediaLibraries = [
    'Movies' => '/mnt/media/movies',
    'TV' => '/mnt/media/tv',
];

// Shared writable cache directory.
$cacheDirectory = '/var/cache/jelana';

// Timezone used for date ranges and timestamps.
$timezone = 'Europe/Stockholm';
