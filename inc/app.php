<?php

declare(strict_types=1);

const JELANA_VERSION = '2.1.0';

$appName = trim((string) ($appName ?? 'Jelana'));
$appName = $appName !== '' ? $appName : 'Jelana';

$jellyfinServer = rtrim(trim((string) ($jellyfinServer ?? '')), '/');
$apiKey = trim((string) ($apiKey ?? ''));

$brandHomeUrl = trim((string) ($brandHomeUrl ?? $jellyfinServer));
$brandLogoUrl = trim((string) ($brandLogoUrl ?? ''));

$playbackDatabase = trim((string) (
    $playbackDatabase ?? '/var/lib/jellyfin/data/playback_reporting.db'
));

$cacheDirectory = rtrim(trim((string) (
    $cacheDirectory ?? '/var/cache/jelana'
)), '/');

$timezone = trim((string) ($timezone ?? 'UTC'));
if ($timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
    $timezone = 'UTC';
}
date_default_timezone_set($timezone);

// Backward compatibility with the older unlabelled $mediaPaths option.
if (!isset($mediaLibraries) || !is_array($mediaLibraries)) {
    $legacyPaths = isset($mediaPaths) && is_array($mediaPaths)
        ? array_values($mediaPaths)
        : [];

    $mediaLibraries = [
        'Movies' => (string) ($legacyPaths[0] ?? ''),
        'TV' => (string) ($legacyPaths[1] ?? ''),
    ];
}

$normalizedLibraries = [];
foreach ($mediaLibraries as $label => $path) {
    if (!is_string($path) || trim($path) === '') {
        continue;
    }

    $label = is_string($label) && trim($label) !== ''
        ? trim($label)
        : basename(rtrim($path, '/'));

    $normalizedLibraries[$label] = trim($path);
}
$mediaLibraries = $normalizedLibraries;
$mediaPaths = array_values($mediaLibraries);

if ($jellyfinServer === '') {
    throw new RuntimeException('Missing $jellyfinServer in config.php.');
}
if ($apiKey === '') {
    throw new RuntimeException('Missing $apiKey in config.php.');
}
if ($cacheDirectory === '') {
    throw new RuntimeException('Missing $cacheDirectory in config.php.');
}

if ($playbackDatabase === '') {
    throw new RuntimeException('Missing $playbackDatabase in config.php.');
}

if (!is_file($playbackDatabase)) {
    throw new RuntimeException(
        'Playback Reporting database not found: ' . $playbackDatabase
    );
}

if (!is_readable($playbackDatabase)) {
    throw new RuntimeException(
        'Playback Reporting database is not readable by the web server: '
        . $playbackDatabase
    );
}

if ($mediaLibraries === []) {
    throw new RuntimeException(
        'No valid media libraries are configured in $mediaLibraries.'
    );
}

foreach ($mediaLibraries as $libraryLabel => $libraryPath) {
    if (!is_dir($libraryPath)) {
        throw new RuntimeException(
            'Media library path for "' . $libraryLabel . '" was not found: '
            . $libraryPath
        );
    }

    if (!is_readable($libraryPath)) {
        throw new RuntimeException(
            'Media library path for "' . $libraryLabel
            . '" is not readable by the web server: ' . $libraryPath
        );
    }
}
