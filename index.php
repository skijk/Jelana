<?php

declare(strict_types=1);

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/inc/app.php';
    require_once __DIR__ . '/inc/jellyfin.php';
    require_once __DIR__ . '/inc/dashboard.php';
} catch (Throwable $exception) {
    http_response_code(500);
    $message = htmlspecialchars(
        $exception->getMessage(),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Jelana configuration error</title>';
    echo '<style>body{margin:0;background:#101114;color:#eee;font:16px/1.5 system-ui,sans-serif}'
        . 'main{max-width:760px;margin:10vh auto;padding:32px;background:#1b1d22;border-radius:18px}'
        . 'h1{margin-top:0}code{display:block;padding:16px;background:#111318;border-radius:10px;'
        . 'overflow-wrap:anywhere;color:#f2b8b5}</style></head><body><main>';
    echo '<h1>Jelana configuration error</h1>';
    echo '<p>Check <strong>config.php</strong> and the file permissions for the configured paths.</p>';
    echo '<code>' . $message . '</code>';
    echo '</main></body></html>';
    exit;
}

const DASHBOARD_SECTION_LABELS = [
    'overview' => 'Overview',
    'playback' => 'Playback',
    'rankings' => 'Rankings',
    'users' => 'Users and playback',
    'activity' => 'Activity and new items',
    'technical' => 'Media profile',
    'recent' => 'Recently added',
];

/*
 * Normal page views read the prepared cache and therefore do not query either
 * the Jellyfin API or the Playback Reporting database.
 *
 * The first request builds the cache once if the scheduled refresh has not run.
 */
$dashboard = readDashboardCache();

if ($dashboard === null) {
    $dashboard = refreshDashboardCache($mediaLibraries);
}

extract($dashboard, EXTR_SKIP);

$updatedAt = new DateTimeImmutable((string) $updatedAt);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatNumber(int|float $value): string
{
    return number_format((float) $value, 0, '.', ',');
}

function formatBytes(?int $bytes): string
{
    if ($bytes === null || $bytes < 0) {
        return '–';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $value = (float) $bytes;
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        $unitIndex++;
    }

    $decimals = $unitIndex >= 4 ? 2 : ($unitIndex >= 3 ? 1 : 0);

    return number_format($value, $decimals, '.', ',') . ' ' . $units[$unitIndex];
}

function formatDuration(int $seconds): string
{
    if ($seconds <= 0) {
        return '0 min';
    }

    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    if ($days > 0) {
        return $days . ' d ' . $hours . ' h';
    }

    if ($hours > 0) {
        return $hours . ' h ' . $minutes . ' min';
    }

    return max(1, $minutes) . ' min';
}

function jellyfinItemUrl(string $server, string $id): string
{
    return rtrim($server, '/') . '/web/#/details?id=' . rawurlencode($id);
}

function percentage(int $count, int $total): string
{
    if ($total <= 0) {
        return '0%';
    }

    return number_format(($count / $total) * 100, 0, '.', ',') . '%';
}


function renderOverviewIcon(string $key): string
{
    $paths = [
        'MovieCount' => '<path d="M4 7h16v13H4z"/><path d="m4 7 3-4h4L8 7m4 0 3-4h4l-3 4"/><path d="m10 12 5 3-5 3z"/>',
        'SeriesCount' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 22h8M12 19v3M8 2l4 3 4-3"/>',
        'EpisodeCount' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 2h8M8 22h8M9 9h6M9 13h6M9 17h4"/>',
        'UserCount' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'Storage' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/>',
    ];

    if (!isset($paths[$key])) {
        return '';
    }

    return '<span class="overview-icon" aria-hidden="true">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        . $paths[$key]
        . '</svg></span>';
}

function renderTopList(array $items, string $server): void
{
    if ($items === []) {
        echo '<p class="empty-state compact">No statistics available yet.</p>';
        return;
    }

    echo '<div class="ranking-columns">';

    foreach (array_chunk(array_slice($items, 0, 10), 5) as $columnIndex => $columnItems) {
        echo '<ol class="ranking-list">';

        foreach ($columnItems as $index => $item) {
            $rank = ($columnIndex * 5) + $index + 1;
            $id = (string) ($item['Id'] ?? '');
            $name = (string) ($item['Name'] ?? 'Unknown title');
            $plays = (int) ($item['Plays'] ?? 0);

            echo '<li class="ranking-item">';
            echo '<a href="' . e(jellyfinItemUrl($server, $id)) . '" target="_blank" rel="noopener noreferrer">';
            echo '<span class="rank-number">' . $rank . '</span>';
            echo '<img src="image.php?id=' . rawurlencode($id) . '" alt="" loading="lazy">';
            echo '<span class="rank-copy">';
            echo '<strong>' . e($name) . '</strong>';
            $uniqueViewers = (int) ($item['UniqueViewers'] ?? 0);
            echo '<small>' . formatNumber($plays) . ' plays · ' . formatNumber($uniqueViewers) . ' unique viewers</small>';
            echo '</span>';
            echo '</a>';
            echo '</li>';
        }

        echo '</ol>';
    }

    echo '</div>';
}

function renderRankingPanel(
    string $key,
    string $label,
    array $periods,
    string $server
): void {
    $defaultPeriod = (string) array_key_first($periods);

    echo '<article id="most-watched" class="panel ranking-panel" data-ranking-panel="' . e($key) . '">';
    echo '<div class="ranking-panel-header">';
    echo '<div class="panel-heading"><p>' . e($label) . '</p><h2>Most played</h2></div>';
    echo '<div class="ranking-tabs" role="tablist" aria-label="Select time range">';

    foreach ($periods as $period => $_items) {
        $period = (string) $period;
        $isActive = $period === $defaultPeriod;

        echo '<button type="button" class="ranking-tab' . ($isActive ? ' is-active' : '') . '"';
        echo ' role="tab" aria-selected="' . ($isActive ? 'true' : 'false') . '"';
        echo ' data-ranking-period="' . e($period) . '">' . e($period) . ' days</button>';
    }

    echo '</div>';
    echo '</div>';

    foreach ($periods as $period => $items) {
        $period = (string) $period;
        $isActive = $period === $defaultPeriod;

        echo '<div class="ranking-period' . ($isActive ? ' is-active' : '') . '"';
        echo ' data-ranking-content="' . e($period) . '"' . ($isActive ? '' : ' hidden') . '>';

        renderTopList($items, $server);

        echo '</div>';
    }

    echo '</article>';
}

function renderUsers(array $users): void
{
    if ($users === []) {
        echo '<p class="empty-state compact">No statistics available yet.</p>';
        return;
    }

    echo '<ol class="user-list">';

    foreach (array_slice($users, 0, 10) as $index => $user) {
        $plays = (int) ($user['Plays'] ?? 0);
        $duration = (int) ($user['Duration'] ?? 0);

        echo '<li>';
        echo '<span class="user-rank">' . ($index + 1) . '</span>';
        echo '<span class="user-copy">';
        echo '<strong class="user-name">' . e((string) ($user['Name'] ?? 'Unknown user')) . '</strong>';
        echo '<small>' . formatNumber($plays) . ' plays</small>';
        echo '</span>';
        echo '<span class="user-count">' . e(formatDuration($duration)) . '</span>';
        echo '</li>';
    }

    echo '</ol>';
}

function renderUserPanel(array $periods): void
{
    $defaultPeriod = (string) array_key_first($periods);

    echo '<article class="panel user-panel" data-user-panel>';
    echo '<div class="ranking-panel-header">';
    echo '<div class="panel-heading"><p>USERS</p><h2>Most watched</h2></div>';
    echo '<div class="ranking-tabs" role="tablist" aria-label="Select time range">';

    foreach ($periods as $period => $_users) {
        $period = (string) $period;
        $isActive = $period === $defaultPeriod;

        echo '<button type="button" class="ranking-tab' . ($isActive ? ' is-active' : '') . '"';
        echo ' role="tab" aria-selected="' . ($isActive ? 'true' : 'false') . '"';
        echo ' data-user-period="' . e($period) . '">' . e($period) . ' days</button>';
    }

    echo '</div>';
    echo '</div>';

    foreach ($periods as $period => $users) {
        $period = (string) $period;
        $isActive = $period === $defaultPeriod;

        echo '<div class="user-period' . ($isActive ? ' is-active' : '') . '"';
        echo ' data-user-content="' . e($period) . '"' . ($isActive ? '' : ' hidden') . '>';

        renderUsers($users);

        echo '</div>';
    }

    echo '</article>';
}

function renderBreakdown(array $rows, int $limit = 6): void
{
    $rows = array_slice($rows, 0, $limit);
    $total = array_sum(
        array_map(
            static fn(array $row): int => (int) ($row['Count'] ?? 0),
            $rows
        )
    );

    if ($rows === []) {
        echo '<p class="empty-state compact">No statistics available yet.</p>';
        return;
    }

    echo '<div class="breakdown-list">';

    foreach ($rows as $row) {
        $label = (string) ($row['Label'] ?? 'Unknown');
        $count = (int) ($row['Count'] ?? 0);
        $width = $total > 0 ? ($count / $total) * 100 : 0;

        echo '<div class="breakdown-row">';
        echo '<div><strong>' . e($label) . '</strong>';
        echo '<span>' . formatNumber($count) . ' · ' . percentage($count, $total) . '</span></div>';
        echo '<i><b style="width:' . number_format($width, 2, '.', '') . '%"></b></i>';
        echo '</div>';
    }

    echo '</div>';
}

function associativeRows(array $data, int $limit = 5): array
{
    $rows = [];

    foreach (array_slice($data, 0, $limit, true) as $label => $count) {
        $rows[] = [
            'Label' => (string) $label,
            'Count' => (int) $count,
        ];
    }

    return $rows;
}

$overviewCards = [
    ['key' => 'MovieCount', 'label' => 'Movies'],
    ['key' => 'SeriesCount', 'label' => 'TV Series'],
    ['key' => 'EpisodeCount', 'label' => 'Episodes'],
    ['key' => 'UserCount', 'label' => 'Users'],
];

$maxActivityDuration = max(1, ...array_column($activity, 'Duration'));
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?> Stats</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="jellyfin-topbar">
    <div class="jellyfin-topbar-inner">
        <?php if ($brandHomeUrl !== ''): ?>
            <a class="jellyfin-brand-link" href="<?= e($brandHomeUrl) ?>" aria-label="Open <?= e($appName) ?>">
        <?php else: ?>
            <span class="jellyfin-brand-link">
        <?php endif; ?>
        <?php if ($brandLogoUrl !== ''): ?>
            <img class="jellyfin-brand-logo" src="<?= e($brandLogoUrl) ?>" alt="<?= e($appName) ?>">
        <?php else: ?>
            <span class="jellyfin-brand-text"><?= e($appName) ?></span>
        <?php endif; ?>
        <?php if ($brandHomeUrl !== ''): ?>
            </a>
        <?php else: ?>
            </span>
        <?php endif; ?>
        <nav class="jellyfin-nav" aria-label="Dashboard navigation">
            <a class="is-active" href="#overview">Overview</a>
            <a href="#most-watched">Most Played</a>
            <a href="#users">Users</a>
            <a href="#playback">Playback</a>
        </nav>
    </div>
</header>

<main id="overview" class="page-shell">
    <header class="site-header">
        <div>
            <p class="eyebrow">JELLYFIN LIBRARY</p>
            <h1><?= e($appName) ?> Stats</h1>
        </div>

        <div class="header-tools">
            <button
                class="settings-button"
                type="button"
                aria-label="Customize dashboard"
                title="Customize dashboard"
            >⚙</button>

            <div
                class="status-pill"
                title="Dashboard data is refreshed at most once per hour by the scheduled job"
            >
                <span class="status-dot"></span>
                <span class="status-text">
                    <strong>Cached data</strong>
                    <small>Updated <?= e($updatedAt->format('H:i')) ?></small>
                </span>
            </div>
        </div>
    </header>

    <section class="v2-kpi-grid" data-section="overview">
        <div class="stats-grid stats-grid-five">
            <?php foreach ($overviewCards as $card): ?>
                <article class="stat-card">
                    <div class="overview-card-content">
                        <?= renderOverviewIcon((string) $card['key']) ?>
                        <div class="overview-card-copy">
                            <span class="stat-value">
                                <?= formatNumber((int) ($counts[$card['key']] ?? 0)) ?>
                            </span>
                            <span class="stat-label"><?= e($card['label']) ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

            <article class="stat-card storage-card">
                <div class="overview-card-content">
                    <?= renderOverviewIcon('Storage') ?>
                    <div class="overview-card-copy">
                        <span class="stat-value stat-value-small">
                            <?= e(formatBytes($storage['Total'])) ?>
                        </span>
                        <span class="stat-label">Storage</span>
                    </div>
                </div>
                <span class="storage-split">
                    <?php
                    $storageParts = [];

                    foreach (($storage['Libraries'] ?? []) as $libraryLabel => $libraryBytes) {
                        $storageParts[] = e((string) $libraryLabel)
                            . ' '
                            . e(formatBytes(is_int($libraryBytes) ? $libraryBytes : null));
                    }

                    echo implode(' · ', $storageParts);
                    ?>
                </span>
            </article>
        </div>
    </section>

    <section class="metric-grid v2-metric-grid" id="playback" data-section="playback">
        <article class="metric-card">
            <span class="metric-label">Playback count</span>
            <strong><?= formatNumber((int) $playback30['Plays']) ?></strong>
            <small>last 30 days</small>
        </article>

        <article class="metric-card">
            <span class="metric-label">Playback count</span>
            <strong><?= formatNumber((int) $playbackAll['Plays']) ?></strong>
            <small>all time</small>
        </article>

        <article class="metric-card">
            <span class="metric-label">Watch time</span>
            <strong><?= e(formatDuration((int) $playback30['Duration'])) ?></strong>
            <small>last 30 days</small>
        </article>

        <article class="metric-card">
            <span class="metric-label">Watch time</span>
            <strong><?= e(formatDuration((int) $playbackAll['Duration'])) ?></strong>
            <small>all time</small>
        </article>
    </section>

    <section class="v2-activity-row" data-section="activity">
        <section class="panel chart-panel v2-chart-panel">
            <div class="panel-heading chart-heading">
                <div>
                    <p>ACTIVITY</p>
                    <h2>Daily watch time · 30 days</h2>
                </div>
                <span><?= e(formatDuration(array_sum(array_column($activity, 'Duration')))) ?></span>
            </div>

            <div class="activity-chart" aria-label="Daily watch time for the last 30 days">
                <?php foreach ($activity as $day): ?>
                    <?php
                    $height = max(3, ((int) $day['Duration'] / $maxActivityDuration) * 100);
                    $date = new DateTimeImmutable((string) $day['Date']);
                    $classes = ['chart-day'];

                    if ($date->format('Y-m-d') === $today) {
                        $classes[] = 'is-today';
                    }

                    if ((int) $date->format('N') >= 6) {
                        $classes[] = 'is-weekend';
                    }

                    $tooltip = sprintf(
                        '%s · %s · %s playback records',
                        $date->format('j M'),
                        formatDuration((int) $day['Duration']),
                        formatNumber((int) $day['Plays'])
                    );
                    ?>
                    <div
                        class="<?= e(implode(' ', $classes)) ?>"
                        tabindex="0"
                        data-tooltip="<?= e($tooltip) ?>"
                    >
                        <span
                            class="chart-bar"
                            style="height:<?= number_format($height, 2, '.', '') ?>%"
                        ></span>
                        <small><?= e($date->format('d')) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <article class="panel new-panel v2-new-panel" data-new-panel>
            <div class="new-panel-header">
                <div class="panel-heading">
                    <p>RECENTLY ADDED</p>
                    <h2>Library growth</h2>
                </div>

                <div
                    class="ranking-tabs"
                    role="tablist"
                    aria-label="Select time range for recently added titles"
                >
                    <button
                        type="button"
                        class="ranking-tab is-active"
                        role="tab"
                        aria-selected="true"
                        data-new-period="7"
                    >7 days</button>
                    <button
                        type="button"
                        class="ranking-tab"
                        role="tab"
                        aria-selected="false"
                        data-new-period="30"
                    >30 days</button>
                </div>
            </div>

            <div class="new-period is-active" data-new-content="7">
                <div class="new-items-grid">
                    <div>
                        <span>Movies</span>
                        <strong>+<?= formatNumber((int) $newItems['Movies7']) ?></strong>
                        <small>7 days</small>
                    </div>
                    <div>
                        <span>TV Series</span>
                        <strong>+<?= formatNumber((int) $newItems['Series7']) ?></strong>
                        <small>7 days</small>
                    </div>
                </div>
            </div>

            <div class="new-period" data-new-content="30" hidden>
                <div class="new-items-grid">
                    <div>
                        <span>Movies</span>
                        <strong>+<?= formatNumber((int) $newItems['Movies30']) ?></strong>
                        <small>30 days</small>
                    </div>
                    <div>
                        <span>TV Series</span>
                        <strong>+<?= formatNumber((int) $newItems['Series30']) ?></strong>
                        <small>30 days</small>
                    </div>
                </div>
            </div>
        </article>
    </section>

    <section class="dashboard-grid ranking-grid v2-ranking-grid" data-section="rankings">
        <?php
        renderRankingPanel(
            'movies',
            'MOVIES',
            ['7' => $topMovies7, '30' => $topMovies30],
            $jellyfinServer
        );

        renderRankingPanel(
            'series',
            'TV SERIES',
            ['7' => $topSeries7, '30' => $topSeries30],
            $jellyfinServer
        );
        ?>
    </section>

    <section class="v2-operations-grid" id="users" data-section="users">
        <?php renderUserPanel(['7' => $topUsers7, '30' => $topUsers30]); ?>

        <article class="panel">
            <div class="panel-heading">
                <p>PLAYBACK METHODS</p>
                <h2>Last 30 days</h2>
            </div>
            <?php renderBreakdown($methods); ?>
        </article>

        <article class="panel">
            <div class="panel-heading">
                <p>CLIENTS</p>
                <h2>Most used · 30 days</h2>
            </div>
            <?php renderBreakdown($clients); ?>
        </article>
    </section>

    <section class="v2-library-grid" data-section="technical">
        <article class="panel">
            <div class="panel-heading">
                <p>VIDEO</p>
                <h2>Video codecs</h2>
            </div>
            <?php renderBreakdown(associativeRows($mediaProfile['Video'] ?? [])); ?>
        </article>

        <article class="panel">
            <div class="panel-heading">
                <p>VIDEO</p>
                <h2>Resolutions</h2>
            </div>
            <?php renderBreakdown(associativeRows($mediaProfile['Resolution'] ?? [])); ?>
        </article>

        <article class="panel">
            <div class="panel-heading">
                <p>AUDIO</p>
                <h2>Audio codecs</h2>
            </div>
            <?php renderBreakdown(associativeRows($mediaProfile['Audio'] ?? [])); ?>
        </article>
    </section>

    <section class="panel recent-panel v2-recent-panel" data-section="recent">
        <div class="panel-heading">
            <p>RECENTLY ADDED</p>
            <h2>New in <?= e($appName) ?></h2>
        </div>

        <div class="recent-grid">
            <?php foreach ($recent as $item): ?>
                <?php
                $itemJson = json_encode(
                    $item,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_HEX_APOS
                    | JSON_HEX_QUOT
                );
                ?>
                <button
                    type="button"
                    class="recent-item"
                    data-item="<?= e(is_string($itemJson) ? $itemJson : '{}') ?>"
                >
                    <img
                        src="image.php?id=<?= rawurlencode((string) $item['Id']) ?>"
                        alt=""
                        loading="lazy"
                    >
                    <span>
                        <strong><?= e((string) $item['Name']) ?></strong>
                        <small>
                            <?= e((string) $item['Type'] === 'Movie' ? 'Movie' : 'TV Series') ?>
                            <?= !empty($item['Year']) ? ' · ' . e((string) $item['Year']) : '' ?>
                        </small>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <dialog class="item-modal">
        <button class="modal-close" type="button" aria-label="Close">×</button>
        <div class="modal-content"></div>
    </dialog>

    <dialog class="settings-modal">
        <button class="modal-close" type="button" aria-label="Close">×</button>
        <h2>Show sections</h2>
        <div class="settings-list">
            <?php foreach (DASHBOARD_SECTION_LABELS as $key => $label): ?>
                <label>
                    <input type="checkbox" data-toggle-section="<?= e($key) ?>" checked>
                    <?= e($label) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <button type="button" class="reset-settings">Reset layout</button>
    </dialog>

<footer class="site-footer">
    <span>Jelana v<?= e(JELANA_VERSION) ?></span>
    <span>Analytics for Jellyfin</span>
</footer>

</main>

<script>
const itemModal = document.querySelector('.item-modal');
const settingsModal = document.querySelector('.settings-modal');
const storageKey = 'jelana-sections';

function configureTabs(panel, tabSelector, contentSelector, dataKey) {
    const tabs = [...panel.querySelectorAll(tabSelector)];
    const contents = [...panel.querySelectorAll(contentSelector)];

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const selectedPeriod = tab.dataset[dataKey];

            tabs.forEach((currentTab) => {
                const isActive = currentTab === tab;
                currentTab.classList.toggle('is-active', isActive);
                currentTab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            contents.forEach((content) => {
                const contentPeriod = content.dataset[dataKey.replace('Period', 'Content')];
                const isActive = contentPeriod === selectedPeriod;

                content.classList.toggle('is-active', isActive);
                content.hidden = !isActive;
            });
        });
    });
}

document.querySelectorAll('[data-new-panel]').forEach((panel) => {
    configureTabs(
        panel,
        '[data-new-period]',
        '[data-new-content]',
        'newPeriod'
    );
});

document.querySelectorAll('[data-ranking-panel]').forEach((panel) => {
    configureTabs(
        panel,
        '[data-ranking-period]',
        '[data-ranking-content]',
        'rankingPeriod'
    );
});

document.querySelectorAll('[data-user-panel]').forEach((panel) => {
    configureTabs(
        panel,
        '[data-user-period]',
        '[data-user-content]',
        'userPeriod'
    );
});

document.querySelectorAll('.recent-item').forEach((button) => {
    button.addEventListener('click', () => {
        const item = JSON.parse(button.dataset.item);
        const details = [
            item.Year,
            item.Container,
            item.Resolution,
            item.VideoCodec,
            item.AudioCodec,
            item.Size ? formatBytes(item.Size) : null,
        ].filter(Boolean);

        const typeLabel = item.Type === 'Movie' ? 'MOVIE' : 'TV SERIES';
        const rating = item.Rating
            ? `<p>★ ${Number(item.Rating).toFixed(1)}</p>`
            : '';

        itemModal.querySelector('.modal-content').innerHTML = `
            <img src="image.php?id=${encodeURIComponent(item.Id)}" alt="">
            <div>
                <p class="eyebrow">${typeLabel}</p>
                <h2>${escapeHtml(item.Name)}</h2>
                <p class="modal-meta">${details.map(escapeHtml).join(' · ')}</p>
                ${rating}
                <p>${escapeHtml(item.Overview || 'No description available.')}</p>
                <a
                    href="<?= e(rtrim($jellyfinServer, '/')) ?>/web/#/details?id=${encodeURIComponent(item.Id)}"
                    target="_blank"
                    rel="noopener noreferrer"
                >Open in Jellyfin</a>
            </div>
        `;

        itemModal.showModal();
    });
});

document.querySelectorAll('.modal-close').forEach((button) => {
    button.addEventListener('click', () => {
        button.closest('dialog')?.close();
    });
});

document.querySelector('.settings-button')?.addEventListener('click', () => {
    settingsModal?.showModal();
});

let savedSections = {};

try {
    savedSections = JSON.parse(localStorage.getItem(storageKey) || '{}');
} catch {
    localStorage.removeItem(storageKey);
}

document.querySelectorAll('[data-toggle-section]').forEach((input) => {
    const key = input.dataset.toggleSection;

    if (savedSections[key] === false) {
        input.checked = false;
        document.querySelector(`[data-section="${key}"]`)?.classList.add('section-hidden');
    }

    input.addEventListener('change', () => {
        savedSections[key] = input.checked;
        document
            .querySelector(`[data-section="${key}"]`)
            ?.classList.toggle('section-hidden', !input.checked);
        localStorage.setItem(storageKey, JSON.stringify(savedSections));
    });
});

document.querySelector('.reset-settings')?.addEventListener('click', () => {
    localStorage.removeItem(storageKey);
    location.reload();
});

function escapeHtml(value) {
    return String(value).replace(
        /[&<>'"]/g,
        (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;',
        })[character]
    );
}

function formatBytes(bytes) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = Number(bytes);
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex++;
    }

    return `${value.toLocaleString('en-US', {
        maximumFractionDigits: unitIndex >= 4 ? 2 : 1,
    })} ${units[unitIndex]}`;
}
</script>
</body>
</html>
