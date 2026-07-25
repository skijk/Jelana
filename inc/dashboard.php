<?php

declare(strict_types=1);

const DASHBOARD_CACHE_KEY = 'dashboard';
const DASHBOARD_CACHE_MAX_AGE = 3600;

function dashboardCacheDirectory(): string
{
    return '/var/cache/fulflix-stats';
}

function dashboardCacheFile(): string
{
    return dashboardCacheDirectory() . '/' . DASHBOARD_CACHE_KEY . '.json';
}

function dashboardLockFile(): string
{
    return dashboardCacheDirectory() . '/' . DASHBOARD_CACHE_KEY . '.lock';
}

function ensureDashboardCacheDirectory(): bool
{
    $directory = dashboardCacheDirectory();

    return is_dir($directory)
        || (@mkdir($directory, 0770, true) && is_dir($directory));
}

function readDashboardCache(): ?array
{
    $file = dashboardCacheFile();

    if (!is_file($file) || !is_readable($file)) {
        return null;
    }

    $payload = json_decode((string)@file_get_contents($file), true);

    if (!is_array($payload) || !isset($payload['value']) || !is_array($payload['value'])) {
        return null;
    }

    return $payload['value'];
}

function dashboardCacheAge(): ?int
{
    $file = dashboardCacheFile();
    $modified = is_file($file) ? filemtime($file) : false;

    return $modified === false ? null : max(0, time() - $modified);
}

function buildDashboardData(array $mediaPaths): array
{
    return [
        'counts' => getLibraryCounts(),
        'storage' => getLibraryStorageBreakdown($mediaPaths),
        'newItems' => getNewItemCounts(),
        'playback30' => getPlaybackSummary(30),
        'playbackAll' => getPlaybackSummary(null),
        'topMovies7' => getTopMovies(7, 10),
        'topMovies30' => getTopMovies(30, 10),
        'topSeries7' => getTopSeries(7, 10),
        'topSeries30' => getTopSeries(30, 10),
        'activeUsers7' => getTopActiveUsers(7, 6),
        'activeUsers30' => getTopActiveUsers(30, 6),
        'activity' => getDailyActivity(30),
        'methods' => getPlaybackMethodStats(30),
        'clients' => getClientStats(30, 6),
        'recent' => getRecentItems(8),
        'mediaProfile' => getLibraryMediaProfile(),
        'updatedAt' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
    ];
}

function writeDashboardCache(array $value): bool
{
    if (!ensureDashboardCacheDirectory()) {
        return false;
    }

    $payload = json_encode(
        ['created' => time(), 'value' => $value],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if (!is_string($payload)) {
        return false;
    }

    $file = dashboardCacheFile();
    $temporary = $file . '.' . getmypid() . '.tmp';

    if (@file_put_contents($temporary, $payload, LOCK_EX) === false) {
        return false;
    }

    @chmod($temporary, 0660);

    if (!@rename($temporary, $file)) {
        @unlink($temporary);
        return false;
    }

    return true;
}

function refreshDashboardCache(array $mediaPaths, bool $waitForLock = true): array
{
    if (!ensureDashboardCacheDirectory()) {
        throw new RuntimeException('Could not create the dashboard cache directory.');
    }

    $lockHandle = @fopen(dashboardLockFile(), 'c');

    if ($lockHandle === false) {
        throw new RuntimeException('Could not open the dashboard lock file.');
    }

    $operation = LOCK_EX | ($waitForLock ? 0 : LOCK_NB);

    if (!flock($lockHandle, $operation)) {
        fclose($lockHandle);

        $cached = readDashboardCache();
        if ($cached !== null) {
            return $cached;
        }

        throw new RuntimeException('Another cache refresh is already running.');
    }

    try {
        $value = buildDashboardData($mediaPaths);

        if (!writeDashboardCache($value)) {
            throw new RuntimeException('Could not write the dashboard cache file.');
        }

        return $value;
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}
