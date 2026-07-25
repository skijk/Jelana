#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/inc/app.php';
require_once dirname(__DIR__) . '/inc/jellyfin.php';
require_once dirname(__DIR__) . '/inc/dashboard.php';

$started = microtime(true);

try {
    $dashboard = refreshDashboardCache($mediaLibraries);
    $updatedAt = new DateTimeImmutable((string)$dashboard['updatedAt']);
    $elapsed = microtime(true) - $started;

    fwrite(
        STDOUT,
        sprintf(
            "[%s] %s Stats cache refreshed in %.2f seconds.\n",
            $updatedAt->format('Y-m-d H:i:s'),
            $appName,
            $elapsed
        )
    );

    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, '[' . $appName . ' Stats] Cache refresh failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
