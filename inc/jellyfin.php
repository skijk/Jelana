<?php

declare(strict_types=1);

const PLAYBACK_SESSION_GAP_SECONDS = 1800;

function jellyfin(string $endpoint): array
{
    global $jellyfinServer, $apiKey;

    $url = rtrim($jellyfinServer, '/') . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-Emby-Token: ' . $apiKey,
        ],
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log('Jellyfin cURL error: ' . curl_error($ch));
        curl_close($ch);
        return [];
    }

    $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($statusCode < 200 || $statusCode >= 300) {
        error_log('Jellyfin returned HTTP ' . $statusCode . ' for ' . $endpoint);
        return [];
    }

    $data = json_decode($response, true);

    return is_array($data) ? $data : [];
}

function getLibraryCounts(): array
{
    $counts = jellyfin('/Items/Counts');
    $users = jellyfin('/Users');
    $counts['UserCount'] = count($users);

    return $counts;
}

function playbackDb(): ?PDO
{
    global $playbackDatabase;

    static $pdo = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_readable($playbackDatabase)) {
        error_log('Playback Reporting database is not readable.');
        return null;
    }

    try {
        $pdo = new PDO('sqlite:' . $playbackDatabase, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA query_only = ON');
        return $pdo;
    } catch (Throwable $e) {
        error_log('Could not open Playback Reporting database: ' . $e->getMessage());
        return null;
    }
}

function sinceDate(?int $days): ?string
{
    if ($days === null) {
        return null;
    }

    return (new DateTimeImmutable('now'))
        ->modify('-' . max(1, $days) . ' days')
        ->format('Y-m-d H:i:s');
}

function sessionCte(string $where = ''): string
{
    return "
        WITH ordered AS (
            SELECT
                DateCreated,
                UserId,
                ItemId,
                ItemType,
                ItemName,
                COALESCE(PlayDuration, 0) AS PlayDuration,
                LAG(DateCreated) OVER (
                    PARTITION BY UserId, ItemId
                    ORDER BY DateCreated
                ) AS PreviousDate
            FROM PlaybackActivity
            {$where}
        ),
        sessions AS (
            SELECT *,
                CASE
                    WHEN PreviousDate IS NULL THEN 1
                    WHEN (julianday(DateCreated) - julianday(PreviousDate)) * 86400 > " . PLAYBACK_SESSION_GAP_SECONDS . " THEN 1
                    ELSE 0
                END AS NewPlay
            FROM ordered
        )
    ";
}

function getPlaybackSummary(?int $days): array
{
    $pdo = playbackDb();

    if (!$pdo) {
        return ['Plays' => 0, 'Duration' => 0];
    }

    $where = $days === null ? '' : 'WHERE DateCreated >= :since';
    $sql = sessionCte($where) . "
        SELECT
            COALESCE(SUM(NewPlay), 0) AS Plays,
            COALESCE(SUM(PlayDuration), 0) AS Duration
        FROM sessions
    ";

    $stmt = $pdo->prepare($sql);

    if ($days !== null) {
        $stmt->bindValue(':since', sinceDate($days));
    }

    $stmt->execute();
    $row = $stmt->fetch() ?: [];

    return [
        'Plays' => (int)($row['Plays'] ?? 0),
        'Duration' => (int)($row['Duration'] ?? 0),
    ];
}

function getTopMovies(int $days, int $limit): array
{
    $pdo = playbackDb();

    if (!$pdo) {
        return [];
    }

    $sql = sessionCte("WHERE DateCreated >= :since AND ItemType = 'Movie'") . "
        SELECT
            ItemId AS Id,
            ItemName AS Name,
            SUM(NewPlay) AS Plays,
            SUM(PlayDuration) AS Duration,
            COUNT(DISTINCT UserId) AS UniqueViewers
        FROM sessions
        GROUP BY ItemId, ItemName
        ORDER BY Plays DESC, Duration DESC
        LIMIT :limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':since', sinceDate($days));
    $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}

function getEpisodePlaybackRows(int $days): array
{
    $pdo = playbackDb();

    if (!$pdo) {
        return [];
    }

    $sql = sessionCte("WHERE DateCreated >= :since AND ItemType = 'Episode'") . "
        SELECT
            ItemId,
            ItemName,
            UserId,
            SUM(NewPlay) AS Plays,
            SUM(PlayDuration) AS Duration
        FROM sessions
        GROUP BY ItemId, ItemName, UserId
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':since', sinceDate($days));
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}

function getItemsByIds(array $ids, string $fields = ''): array
{
    $itemsById = [];

    foreach (array_chunk(array_values(array_unique(array_filter($ids))), 50) as $chunk) {
        $query = [
            'Ids' => implode(',', $chunk),
            'Recursive' => 'true',
            'Limit' => count($chunk),
        ];

        if ($fields !== '') {
            $query['Fields'] = $fields;
        }

        $response = jellyfin('/Items?' . http_build_query($query));

        foreach (($response['Items'] ?? []) as $item) {
            if (isset($item['Id'])) {
                $itemsById[(string)$item['Id']] = $item;
            }
        }
    }

    return $itemsById;
}

function fallbackSeriesName(string $episodeName): string
{
    $parts = preg_split('/\s+-\s+s\d{1,3}e\d{1,3}\s+-\s+/i', $episodeName, 2);

    return trim((string)($parts[0] ?? $episodeName));
}

function findSeriesByName(string $name): ?array
{
    static $cache = [];

    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }

    $response = jellyfin('/Items?' . http_build_query([
        'Recursive' => 'true',
        'IncludeItemTypes' => 'Series',
        'SearchTerm' => $name,
        'Limit' => 10,
        'Fields' => 'ProductionYear',
    ]));

    foreach (($response['Items'] ?? []) as $item) {
        if (strcasecmp((string)($item['Name'] ?? ''), $name) === 0) {
            return $cache[$name] = $item;
        }
    }

    return $cache[$name] = null;
}

function getTopSeries(int $days, int $limit): array
{
    $rows = getEpisodePlaybackRows($days);

    if (!$rows) {
        return [];
    }

    $metadata = getItemsByIds(array_column($rows, 'ItemId'), 'SeriesId,SeriesName');
    $series = [];

    foreach ($rows as $row) {
        $episodeId = (string)$row['ItemId'];
        $item = $metadata[$episodeId] ?? [];
        $seriesId = (string)($item['SeriesId'] ?? '');
        $seriesName = (string)($item['SeriesName'] ?? '');

        if ($seriesName === '') {
            $seriesName = fallbackSeriesName((string)$row['ItemName']);
        }

        if ($seriesId === '') {
            $matchedSeries = findSeriesByName($seriesName);
            $seriesId = (string)($matchedSeries['Id'] ?? '');
        }

        $key = $seriesId !== '' ? $seriesId : strtolower($seriesName);

        if (!isset($series[$key])) {
            $series[$key] = [
                'Id' => $seriesId,
                'Name' => $seriesName,
                'Plays' => 0,
                'Duration' => 0,
                '_ViewerIds' => [],
            ];
        }

        $series[$key]['Plays'] += (int)$row['Plays'];
        $series[$key]['Duration'] += (int)$row['Duration'];

        $viewerId = (string)($row['UserId'] ?? '');
        if ($viewerId !== '') {
            $series[$key]['_ViewerIds'][$viewerId] = true;
        }
    }

    foreach ($series as &$entry) {
        $entry['UniqueViewers'] = count($entry['_ViewerIds']);
        unset($entry['_ViewerIds']);
    }
    unset($entry);

    usort($series, static fn(array $a, array $b): int =>
        [$b['Plays'], $b['Duration']] <=> [$a['Plays'], $a['Duration']]
    );

    return array_slice($series, 0, max(1, $limit));
}

function jellyfinUsers(): array
{
    static $users = null;

    if (is_array($users)) {
        return $users;
    }

    $users = [];

    foreach (jellyfin('/Users') as $user) {
        if (isset($user['Id'])) {
            $users[(string)$user['Id']] = (string)($user['Name'] ?? 'Unknown user');
        }
    }

    return $users;
}

function getTopWatchedUsers(int $days, int $limit): array
{
    $pdo = playbackDb();

    if (!$pdo) {
        return [];
    }

    $sql = sessionCte('WHERE DateCreated >= :since') . "
        SELECT
            UserId,
            SUM(NewPlay) AS Plays,
            SUM(PlayDuration) AS Duration
        FROM sessions
        GROUP BY UserId
        ORDER BY Duration DESC, Plays DESC
        LIMIT :limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':since', sinceDate($days));
    $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    $names = jellyfinUsers();
    $result = [];

    foreach ($stmt->fetchAll() ?: [] as $row) {
        $id = (string) $row['UserId'];
        $result[] = [
            'Name' => $names[$id] ?? 'Unknown user',
            'Plays' => (int) $row['Plays'],
            'Duration' => (int) $row['Duration'],
        ];
    }

    return $result;
}

function getDailyActivity(int $days): array
{
    $pdo = playbackDb();
    $days = max(1, $days);
    $result = [];

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = (new DateTimeImmutable('today'))->modify('-' . $i . ' days')->format('Y-m-d');
        $result[$date] = ['Date' => $date, 'Duration' => 0, 'Plays' => 0];
    }

    if (!$pdo) {
        return array_values($result);
    }

    $sql = "
        SELECT
            DATE(DateCreated) AS ActivityDate,
            COALESCE(SUM(PlayDuration), 0) AS Duration,
            COUNT(*) AS RowsCount
        FROM PlaybackActivity
        WHERE DateCreated >= :since
        GROUP BY DATE(DateCreated)
        ORDER BY ActivityDate
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':since', sinceDate($days));
    $stmt->execute();

    foreach ($stmt->fetchAll() ?: [] as $row) {
        $date = (string)$row['ActivityDate'];

        if (isset($result[$date])) {
            $result[$date]['Duration'] = (int)$row['Duration'];
            $result[$date]['Plays'] = (int)$row['RowsCount'];
        }
    }

    return array_values($result);
}

function getNewItemCounts(): array
{
    $response = jellyfin('/Items?' . http_build_query([
        'Recursive' => 'true',
        'IncludeItemTypes' => 'Movie,Series',
        'SortBy' => 'DateCreated',
        'SortOrder' => 'Descending',
        'Fields' => 'DateCreated',
        'Limit' => 2000,
    ]));

    $now = new DateTimeImmutable('now');
    $sevenDaysAgo = $now->modify('-7 days');
    $thirtyDaysAgo = $now->modify('-30 days');

    $counts = [
        'Movies7' => 0,
        'Movies30' => 0,
        'Series7' => 0,
        'Series30' => 0,
    ];

    foreach (($response['Items'] ?? []) as $item) {
        if (empty($item['DateCreated']) || empty($item['Type'])) {
            continue;
        }

        try {
            $created = new DateTimeImmutable((string)$item['DateCreated']);
        } catch (Throwable) {
            continue;
        }

        $prefix = $item['Type'] === 'Movie' ? 'Movies' : ($item['Type'] === 'Series' ? 'Series' : null);

        if ($prefix === null) {
            continue;
        }

        if ($created >= $thirtyDaysAgo) {
            $counts[$prefix . '30']++;
        }

        if ($created >= $sevenDaysAgo) {
            $counts[$prefix . '7']++;
        }
    }

    return $counts;
}

function getLibraryStorageBytes(array $paths): ?int
{
    $paths = array_values(array_filter($paths, static fn($path): bool =>
        is_string($path) && $path !== '' && is_dir($path)
    ));

    if (!$paths || !function_exists('shell_exec')) {
        return null;
    }

    $cacheFile = sys_get_temp_dir() . '/jelana-library-size.json';

    if (is_file($cacheFile) && filemtime($cacheFile) !== false && filemtime($cacheFile) > time() - 3600) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);

        if (isset($cached['bytes'])) {
            return (int)$cached['bytes'];
        }
    }

    $total = 0;

    foreach ($paths as $path) {
        $output = shell_exec('du -sb -- ' . escapeshellarg($path) . ' 2>/dev/null');

        if (!is_string($output) || !preg_match('/^(\d+)/', trim($output), $matches)) {
            return null;
        }

        $total += (int)$matches[1];
    }

    @file_put_contents($cacheFile, json_encode(['bytes' => $total]));

    return $total;
}


function cacheRemember(string $key, int $ttl, callable $producer, ?string $directory = null): mixed
{
    global $cacheDirectory;

    $directory ??= $cacheDirectory . '/data';

    if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
        return $producer();
    }

    $file = $directory . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '-', $key) . '.json';

    if (is_file($file) && filemtime($file) !== false && filemtime($file) >= time() - max(1, $ttl)) {
        $cached = json_decode((string)@file_get_contents($file), true);
        if (is_array($cached) && array_key_exists('value', $cached)) {
            return $cached['value'];
        }
    }

    $value = $producer();
    $payload = json_encode(['created' => time(), 'value' => $value], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (is_string($payload)) {
        $temporary = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($temporary, $payload, LOCK_EX) !== false) {
            @rename($temporary, $file);
        }
    }

    return $value;
}

function getLibraryStorageBreakdown(array $libraries): array
{
    global $cacheDirectory;

    $result = ['Libraries' => [], 'Total' => null];
    $valid = [];

    foreach ($libraries as $label => $path) {
        if (is_string($path) && $path !== '' && is_dir($path)) {
            $valid[(string) $label] = $path;
        }
    }

    if ($valid === [] || !function_exists('shell_exec')) {
        return $result;
    }

    $cacheKey = 'library-storage-' . hash('sha256', (string) json_encode($valid));

    return cacheRemember($cacheKey, 3600, static function () use ($valid): array {
        $values = [];

        foreach ($valid as $label => $path) {
            $output = shell_exec('du -sb -- ' . escapeshellarg($path) . ' 2>/dev/null');
            $values[$label] = is_string($output)
                && preg_match('/^(\\d+)/', trim($output), $matches)
                    ? (int) $matches[1]
                    : null;
        }

        $known = array_filter($values, static fn($value): bool => is_int($value));

        return [
            'Libraries' => $values,
            'Total' => count($known) === count($values) ? array_sum($known) : null,
        ];
    }, $cacheDirectory . '/data');
}

function getPlaybackMethodStats(int $days = 30): array
{
    $pdo = playbackDb();
    if (!$pdo) return [];

    $stmt = $pdo->prepare("SELECT COALESCE(NULLIF(PlaybackMethod, ''), 'Unknown') AS Label, COUNT(*) AS Count FROM PlaybackActivity WHERE DateCreated >= :since GROUP BY Label ORDER BY Count DESC");
    $stmt->bindValue(':since', sinceDate($days));
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function getClientStats(int $days = 30, int $limit = 6): array
{
    $pdo = playbackDb();
    if (!$pdo) return [];

    $stmt = $pdo->prepare("SELECT COALESCE(NULLIF(ClientName, ''), NULLIF(DeviceName, ''), 'Unknown') AS Label, COUNT(*) AS Count FROM PlaybackActivity WHERE DateCreated >= :since GROUP BY Label ORDER BY Count DESC LIMIT :limit");
    $stmt->bindValue(':since', sinceDate($days));
    $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function getRecentItems(int $limit = 8): array
{
    $response = jellyfin('/Items?' . http_build_query([
        'Recursive' => 'true',
        'IncludeItemTypes' => 'Movie,Series',
        'SortBy' => 'DateCreated',
        'SortOrder' => 'Descending',
        'Fields' => 'DateCreated,ProductionYear,Overview,RunTimeTicks,CommunityRating,MediaSources',
        'Limit' => max(1, $limit),
    ]));

    return array_values(array_map(static function (array $item): array {
        $source = $item['MediaSources'][0] ?? [];
        $video = null;
        $audio = null;
        foreach (($source['MediaStreams'] ?? []) as $stream) {
            if (($stream['Type'] ?? '') === 'Video' && $video === null) $video = $stream;
            if (($stream['Type'] ?? '') === 'Audio' && $audio === null) $audio = $stream;
        }

        return [
            'Id' => (string)($item['Id'] ?? ''),
            'Name' => (string)($item['Name'] ?? 'Unknown title'),
            'Type' => (string)($item['Type'] ?? ''),
            'Year' => (string)($item['ProductionYear'] ?? ''),
            'Overview' => (string)($item['Overview'] ?? ''),
            'Created' => (string)($item['DateCreated'] ?? ''),
            'Rating' => isset($item['CommunityRating']) ? (float)$item['CommunityRating'] : null,
            'Container' => strtoupper((string)($source['Container'] ?? '')),
            'Size' => isset($source['Size']) ? (int)$source['Size'] : null,
            'VideoCodec' => strtoupper((string)($video['Codec'] ?? '')),
            'Resolution' => isset($video['Width'], $video['Height']) ? ((int)$video['Width'] . '×' . (int)$video['Height']) : '',
            'AudioCodec' => strtoupper((string)($audio['Codec'] ?? '')),
        ];
    }, $response['Items'] ?? []));
}

function classifyResolution(?int $width, ?int $height): string
{
    $height = (int)$height;
    $width = (int)$width;
    if ($height >= 2000 || $width >= 3800) return '4K';
    if ($height >= 1000 || $width >= 1900) return '1080p';
    if ($height >= 700 || $width >= 1200) return '720p';
    return 'SD';
}

function getLibraryMediaProfile(): array
{
    return cacheRemember('library-media-profile', 21600, static function (): array {
        $profile = ['Video' => [], 'Resolution' => [], 'Audio' => []];
        $start = 0;
        $limit = 500;

        do {
            $response = jellyfin('/Items?' . http_build_query([
                'Recursive' => 'true',
                'IncludeItemTypes' => 'Movie,Episode',
                'Fields' => 'MediaSources',
                'StartIndex' => $start,
                'Limit' => $limit,
            ]));
            $items = $response['Items'] ?? [];

            foreach ($items as $item) {
                $source = $item['MediaSources'][0] ?? [];
                foreach (($source['MediaStreams'] ?? []) as $stream) {
                    $type = (string)($stream['Type'] ?? '');
                    if ($type === 'Video') {
                        $codec = strtoupper((string)($stream['Codec'] ?? 'OKÄNT'));
                        $profile['Video'][$codec] = ($profile['Video'][$codec] ?? 0) + 1;
                        $resolution = classifyResolution(isset($stream['Width']) ? (int)$stream['Width'] : null, isset($stream['Height']) ? (int)$stream['Height'] : null);
                        $profile['Resolution'][$resolution] = ($profile['Resolution'][$resolution] ?? 0) + 1;
                        break;
                    }
                }
                foreach (($source['MediaStreams'] ?? []) as $stream) {
                    if (($stream['Type'] ?? '') === 'Audio') {
                        $codec = strtoupper((string)($stream['Codec'] ?? 'OKÄNT'));
                        $profile['Audio'][$codec] = ($profile['Audio'][$codec] ?? 0) + 1;
                        break;
                    }
                }
            }

            $start += count($items);
            $total = (int)($response['TotalRecordCount'] ?? $start);
        } while ($items && $start < $total);

        foreach ($profile as &$section) arsort($section);
        unset($section);
        return $profile;
    });
}
