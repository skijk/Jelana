<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function outputPlaceholder(): never
{
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    echo <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="500" height="750" viewBox="0 0 500 750"><rect width="500" height="750" fill="#292e33"/><rect x="40" y="40" width="420" height="670" rx="24" fill="#202428" stroke="#3a4249" stroke-width="4"/><text x="250" y="350" text-anchor="middle" fill="#7ec8f5" font-family="Arial,sans-serif" font-size="72">🎬</text><text x="250" y="420" text-anchor="middle" fill="#b8c0c7" font-family="Arial,sans-serif" font-size="28">Ingen poster</text></svg>
SVG;
    exit;
}

$id = $_GET['id'] ?? '';
if (!is_string($id) || !preg_match('/^[a-fA-F0-9]{16,64}$/', $id)) outputPlaceholder();

$cacheDirectory = sys_get_temp_dir() . '/fulflix-poster-cache';
if (!is_dir($cacheDirectory)) @mkdir($cacheDirectory, 0770, true);
$cacheFile = $cacheDirectory . '/' . strtolower($id) . '.img';
$metaFile = $cacheFile . '.json';
$maxAge = 604800;

if (is_file($cacheFile) && is_file($metaFile) && filemtime($cacheFile) !== false && filemtime($cacheFile) > time() - $maxAge) {
    $meta = json_decode((string)@file_get_contents($metaFile), true);
    $type = is_array($meta) ? (string)($meta['contentType'] ?? 'image/jpeg') : 'image/jpeg';
    header('Content-Type: ' . $type);
    header('Content-Length: ' . filesize($cacheFile));
    header('Cache-Control: public, max-age=' . $maxAge . ', immutable');
    header('ETag: "' . md5_file($cacheFile) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($cacheFile);
    exit;
}

$url = rtrim($jellyfinServer, '/') . '/Items/' . rawurlencode($id) . '/Images/Primary?maxWidth=500&quality=88';
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['X-Emby-Token: '.$apiKey]]);
$image = curl_exec($ch);
if ($image === false) { curl_close($ch); outputPlaceholder(); }
$statusCode=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $contentType=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE); curl_close($ch);
if ($statusCode<200 || $statusCode>=300 || !str_starts_with($contentType,'image/')) outputPlaceholder();

if (is_dir($cacheDirectory)) {
    $tmp=$cacheFile.'.'.getmypid().'.tmp';
    if (@file_put_contents($tmp,$image,LOCK_EX)!==false) { @rename($tmp,$cacheFile); @file_put_contents($metaFile,json_encode(['contentType'=>$contentType])); }
}
header('Content-Type: '.$contentType); header('Content-Length: '.strlen($image)); header('Cache-Control: public, max-age='.$maxAge.', immutable'); header('ETag: "'.md5($image).'"'); header('X-Content-Type-Options: nosniff'); echo $image;
