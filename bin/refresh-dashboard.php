#!/usr/bin/env php
<?php

declare(strict_types=1);

date_default_timezone_set('Europe/Stockholm');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/inc/jellyfin.php';
require_once dirname(__DIR__) . '/inc/dashboard.php';

$started = microtime(true);

try {
    $dashboard = refreshDashboardCache($mediaPaths ?? []);
    $updatedAt = new DateTimeImmutable((string)$dashboard['updatedAt']);
    $elapsed = microtime(true) - $started;

    fwrite(
        STDOUT,
        sprintf(
            "[%s] Fulflix cache refreshed in %.2f seconds.\n",
            $updatedAt->format('Y-m-d H:i:s'),
            $elapsed
        )
    );

    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, '[Fulflix] Cache refresh failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
