<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const POSTER_CACHE_MAX_AGE = 604800;
const POSTER_MAX_WIDTH = 500;
const POSTER_QUALITY = 88;

function outputPlaceholder(): never
{
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');

    echo <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="500" height="750" viewBox="0 0 500 750">
    <rect width="500" height="750" fill="#292e33"/>
    <rect x="40" y="40" width="420" height="670" rx="24" fill="#202428" stroke="#3a4249" stroke-width="4"/>
    <text x="250" y="350" text-anchor="middle" fill="#7ec8f5" font-family="Arial,sans-serif" font-size="72">🎬</text>
    <text x="250" y="420" text-anchor="middle" fill="#b8c0c7" font-family="Arial,sans-serif" font-size="28">No poster available</text>
</svg>
SVG;

    exit;
}

function outputCachedImage(string $file, string $contentType): never
{
    $size = filesize($file);
    $etag = md5_file($file);

    if ($size === false || $etag === false) {
        outputPlaceholder();
    }

    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . $size);
    header('Cache-Control: public, max-age=' . POSTER_CACHE_MAX_AGE . ', immutable');
    header('ETag: "' . $etag . '"');
    header('X-Content-Type-Options: nosniff');

    readfile($file);
    exit;
}

function writePosterCache(
    string $cacheFile,
    string $metadataFile,
    string $image,
    string $contentType
): void {
    $temporaryFile = $cacheFile . '.' . getmypid() . '.tmp';

    if (@file_put_contents($temporaryFile, $image, LOCK_EX) === false) {
        return;
    }

    if (!@rename($temporaryFile, $cacheFile)) {
        @unlink($temporaryFile);
        return;
    }

    @file_put_contents(
        $metadataFile,
        json_encode(
            ['contentType' => $contentType],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ),
        LOCK_EX
    );
}

$id = $_GET['id'] ?? '';

if (!is_string($id) || preg_match('/^[a-fA-F0-9]{16,64}$/', $id) !== 1) {
    outputPlaceholder();
}

$cacheDirectory = '/var/cache/fulflix-stats/posters';

if (!is_dir($cacheDirectory)) {
    @mkdir($cacheDirectory, 0770, true);
}

$cacheFile = $cacheDirectory . '/' . strtolower($id) . '.img';
$metadataFile = $cacheFile . '.json';
$cacheTimestamp = is_file($cacheFile) ? filemtime($cacheFile) : false;
$hasFreshCache = is_file($metadataFile)
    && $cacheTimestamp !== false
    && $cacheTimestamp > time() - POSTER_CACHE_MAX_AGE;

if ($hasFreshCache) {
    $metadata = json_decode((string) @file_get_contents($metadataFile), true);
    $contentType = is_array($metadata)
        ? (string) ($metadata['contentType'] ?? 'image/jpeg')
        : 'image/jpeg';

    outputCachedImage($cacheFile, $contentType);
}

$query = http_build_query([
    'maxWidth' => POSTER_MAX_WIDTH,
    'quality' => POSTER_QUALITY,
]);

$url = rtrim($jellyfinServer, '/')
    . '/Items/'
    . rawurlencode($id)
    . '/Images/Primary?'
    . $query;

$curl = curl_init($url);

curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'X-Emby-Token: ' . $apiKey,
    ],
]);

$image = curl_exec($curl);

if ($image === false) {
    curl_close($curl);
    outputPlaceholder();
}

$statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);

curl_close($curl);

if (
    $statusCode < 200
    || $statusCode >= 300
    || !str_starts_with($contentType, 'image/')
) {
    outputPlaceholder();
}

if (is_dir($cacheDirectory)) {
    writePosterCache(
        $cacheFile,
        $metadataFile,
        $image,
        $contentType
    );
}

header('Content-Type: ' . $contentType);
header('Content-Length: ' . strlen($image));
header('Cache-Control: public, max-age=' . POSTER_CACHE_MAX_AGE . ', immutable');
header('ETag: "' . md5($image) . '"');
header('X-Content-Type-Options: nosniff');

echo $image;
