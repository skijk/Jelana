<?php

declare(strict_types=1);

date_default_timezone_set('Europe/Stockholm');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/jellyfin.php';

$dashboard = cacheRemember('dashboard', 300, static function () use ($mediaPaths): array {
    return [
        'counts' => getLibraryCounts(),
        'storage' => getLibraryStorageBreakdown($mediaPaths ?? []),
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
});

extract($dashboard, EXTR_SKIP);
$updatedAt = new DateTimeImmutable((string)$updatedAt);

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function formatNumber(int|float $value): string { return number_format((float)$value, 0, ',', ' '); }
function formatBytes(?int $bytes): string
{
    if ($bytes === null || $bytes < 0) return '–';
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $value = (float)$bytes; $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) { $value /= 1024; $unit++; }
    $decimals = $unit >= 4 ? 2 : ($unit >= 3 ? 1 : 0);
    return number_format($value, $decimals, ',', ' ') . ' ' . $units[$unit];
}
function formatDuration(int $seconds): string
{
    if ($seconds <= 0) return '0 min';
    $days = intdiv($seconds, 86400); $hours = intdiv($seconds % 86400, 3600); $minutes = intdiv($seconds % 3600, 60);
    if ($days > 0) return $days . ' d ' . $hours . ' h';
    if ($hours > 0) return $hours . ' h ' . $minutes . ' min';
    return max(1, $minutes) . ' min';
}
function jellyfinItemUrl(string $server, string $id): string { return rtrim($server, '/') . '/web/#/details?id=' . rawurlencode($id); }
function percentage(int $count, int $total): string { return $total > 0 ? number_format(($count / $total) * 100, 0, ',', ' ') . '%' : '0%'; }
function renderTopList(array $items, string $server): void
{
    if (!$items) { echo '<p class="empty-state compact">Ingen statistik ännu.</p>'; return; }

    echo '<div class="ranking-columns">';
    foreach (array_chunk(array_slice($items, 0, 10), 5) as $columnIndex => $columnItems) {
        echo '<ol class="ranking-list">';
        foreach ($columnItems as $index => $item) {
            $rank=($columnIndex*5)+$index+1;
            $id=(string)($item['Id']??''); $name=(string)($item['Name']??'Okänd titel'); $plays=(int)($item['Plays']??0); $duration=(int)($item['Duration']??0);
            echo '<li class="ranking-item"><a href="'.e(jellyfinItemUrl($server,$id)).'" target="_blank" rel="noopener noreferrer">';
            echo '<span class="rank-number">'.$rank.'</span><img src="image.php?id='.rawurlencode($id).'" alt="" loading="lazy">';
            echo '<span class="rank-copy"><strong>'.e($name).'</strong><small>'.formatNumber($plays).' uppspelningar · '.e(formatDuration($duration)).'</small></span></a></li>';
        }
        echo '</ol>';
    }
    echo '</div>';
}
function renderUsers(array $users): void
{
    if (!$users) { echo '<p class="empty-state compact">Ingen statistik ännu.</p>'; return; }
    echo '<ol class="user-list">';
    foreach ($users as $index=>$user) echo '<li><span class="user-rank">'.($index+1).'</span><span class="user-name">'.e((string)$user['Name']).'</span><span class="user-count">'.formatNumber((int)$user['Plays']).'</span></li>';
    echo '</ol>';
}
function renderBreakdown(array $rows, int $limit = 6): void
{
    $rows=array_slice($rows,0,$limit); $total=array_sum(array_map(static fn($r)=>(int)($r['Count']??0),$rows));
    if (!$rows) { echo '<p class="empty-state compact">Ingen statistik ännu.</p>'; return; }
    echo '<div class="breakdown-list">';
    foreach ($rows as $row) {
        $label=(string)($row['Label']??'Okänt'); $count=(int)($row['Count']??0); $pct=$total?($count/$total)*100:0;
        echo '<div class="breakdown-row"><div><strong>'.e($label).'</strong><span>'.formatNumber($count).' · '.percentage($count,$total).'</span></div><i><b style="width:'.number_format($pct,2,'.','').'%"></b></i></div>';
    }
    echo '</div>';
}
function associativeRows(array $data, int $limit=5): array
{
    $rows=[]; foreach(array_slice($data,0,$limit,true) as $label=>$count) $rows[]=['Label'=>(string)$label,'Count'=>(int)$count]; return $rows;
}
?>
<!doctype html><html lang="sv"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fulflix Stats</title><link rel="stylesheet" href="css/style.css"></head>
<body><main class="page-shell">
<header class="site-header"><div><p class="eyebrow">JELLYFIN-BIBLIOTEKET</p><h1>Fulflix Stats</h1></div><div class="header-tools"><button class="settings-button" type="button" aria-label="Anpassa dashboard" title="Anpassa dashboard">⚙</button><div class="status-pill" title="Dashboarddata cachelagras i fem minuter"><span class="status-dot"></span><span class="status-text"><strong>Cachedata</strong><small>Uppdaterad <?=e($updatedAt->format('H:i'))?></small></span></div></div></header>

<section class="compact-summary" data-section="overview"><div class="stats-grid stats-grid-five">
<?php foreach ([['MovieCount','Filmer'],['SeriesCount','Serier'],['EpisodeCount','Avsnitt'],['UserCount','Användare']] as [$key,$label]): ?><article class="stat-card"><span class="stat-value"><?=formatNumber((int)($counts[$key]??0))?></span><span class="stat-label"><?=$label?></span></article><?php endforeach; ?>
<article class="stat-card storage-card"><span class="stat-value stat-value-small"><?=e(formatBytes($storage['Total']))?></span><span class="stat-label">Lagring</span><span class="storage-split">Film <?=e(formatBytes($storage['Movies']))?> · TV <?=e(formatBytes($storage['TV']))?></span></article>
</div></section>

<section class="metric-grid compact-metrics" data-section="playback"><article class="metric-card"><span class="metric-label">Uppspelningar</span><strong><?=formatNumber((int)$playback30['Plays'])?></strong><small>senaste 30 dagarna</small></article><article class="metric-card"><span class="metric-label">Uppspelningar</span><strong><?=formatNumber((int)$playbackAll['Plays'])?></strong><small>totalt</small></article><article class="metric-card"><span class="metric-label">Tittartid</span><strong><?=e(formatDuration((int)$playback30['Duration']))?></strong><small>senaste 30 dagarna</small></article><article class="metric-card"><span class="metric-label">Tittartid</span><strong><?=e(formatDuration((int)$playbackAll['Duration']))?></strong><small>totalt</small></article></section>

<section class="dashboard-grid ranking-grid compact-rankings" data-section="rankings">
<?php foreach ([['FILMER','Mest sedda · 7 dagar',$topMovies7],['FILMER','Mest sedda · 30 dagar',$topMovies30],['SERIER','Mest sedda · 7 dagar',$topSeries7],['SERIER','Mest sedda · 30 dagar',$topSeries30]] as [$kind,$title,$items]): ?><article class="panel"><div class="panel-heading"><p><?=$kind?></p><h2><?=$title?></h2></div><?php renderTopList($items,$jellyfinServer);?></article><?php endforeach; ?>
</section>

<section class="dashboard-grid lower-grid compact-lower-grid" data-section="users"><article class="panel new-panel"><div class="panel-heading"><p>NYTT I BIBLIOTEKET</p><h2>Senaste 7 / 30 dagarna</h2></div><div class="new-items-grid"><?php foreach ([['Filmer',$newItems['Movies7'],'7 dagar'],['Filmer',$newItems['Movies30'],'30 dagar'],['Serier',$newItems['Series7'],'7 dagar'],['Serier',$newItems['Series30'],'30 dagar']] as [$label,$value,$period]): ?><div><span><?=$label?></span><strong>+<?=formatNumber((int)$value)?></strong><small><?=$period?></small></div><?php endforeach;?></div></article><article class="panel"><div class="panel-heading"><p>ANVÄNDARE</p><h2>Mest aktiva · 7 dagar</h2></div><?php renderUsers($activeUsers7);?></article><article class="panel"><div class="panel-heading"><p>ANVÄNDARE</p><h2>Mest aktiva · 30 dagar</h2></div><?php renderUsers($activeUsers30);?></article></section>

<section class="panel chart-panel compact-chart-panel" data-section="activity"><div class="panel-heading chart-heading"><div><p>AKTIVITET</p><h2>Tittartid per dag · 30 dagar</h2></div><span><?=e(formatDuration(array_sum(array_column($activity,'Duration'))))?></span></div><?php $maxDuration=max(1,...array_column($activity,'Duration')); ?><div class="activity-chart" aria-label="Tittartid per dag de senaste 30 dagarna"><?php foreach($activity as $day): $height=max(3,((int)$day['Duration']/$maxDuration)*100); $date=new DateTimeImmutable((string)$day['Date']); $classes=['chart-day']; if($date->format('Y-m-d')===(new DateTimeImmutable('today'))->format('Y-m-d'))$classes[]='is-today'; if((int)$date->format('N')>=6)$classes[]='is-weekend'; ?><div class="<?=implode(' ',$classes)?>" tabindex="0" data-tooltip="<?=e($date->format('j M')).' · '.e(formatDuration((int)$day['Duration'])).' · '.formatNumber((int)$day['Plays']).' registreringar'?>"><span class="chart-bar" style="height:<?=number_format($height,2,'.','')?>%"></span><small><?=e($date->format('d'))?></small></div><?php endforeach;?></div></section>

<section class="dashboard-grid insight-grid compact-insights" data-section="technical"><article class="panel"><div class="panel-heading"><p>UPPSPELNING</p><h2>Metod · 30 dagar</h2></div><?php renderBreakdown($methods);?></article><article class="panel"><div class="panel-heading"><p>KLIENTER</p><h2>Vanligast · 30 dagar</h2></div><?php renderBreakdown($clients);?></article><article class="panel"><div class="panel-heading"><p>VIDEO</p><h2>Codec i biblioteket</h2></div><?php renderBreakdown(associativeRows($mediaProfile['Video']??[]));?></article><article class="panel"><div class="panel-heading"><p>VIDEO</p><h2>Upplösningar</h2></div><?php renderBreakdown(associativeRows($mediaProfile['Resolution']??[]));?></article><article class="panel"><div class="panel-heading"><p>LJUD</p><h2>Vanligaste format</h2></div><?php renderBreakdown(associativeRows($mediaProfile['Audio']??[]));?></article></section>

<section class="panel recent-panel compact-recent-panel" data-section="recent"><div class="panel-heading"><p>SENAST TILLAGT</p><h2>Nytt i Fulflix</h2></div><div class="recent-grid"><?php foreach($recent as $item): ?><button type="button" class="recent-item" data-item='<?=e(json_encode($item,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>'><img src="image.php?id=<?=rawurlencode((string)$item['Id'])?>" alt="" loading="lazy"><span><strong><?=e((string)$item['Name'])?></strong><small><?=e((string)$item['Type']==='Movie'?'Film':'Serie')?><?=!empty($item['Year'])?' · '.e((string)$item['Year']):''?></small></span></button><?php endforeach;?></div></section>
</main>

<dialog class="item-modal"><button class="modal-close" type="button" aria-label="Stäng">×</button><div class="modal-content"></div></dialog>
<dialog class="settings-modal"><button class="modal-close" type="button" aria-label="Stäng">×</button><h2>Visa sektioner</h2><div class="settings-list"><?php foreach(['overview'=>'Översikt','playback'=>'Uppspelning','rankings'=>'Topplistor','users'=>'Nytt och användare','activity'=>'Aktivitetsgraf','technical'=>'Teknisk statistik','recent'=>'Senast tillagt'] as $key=>$label): ?><label><input type="checkbox" data-toggle-section="<?=$key?>" checked> <?=$label?></label><?php endforeach;?></div><button type="button" class="reset-settings">Återställ</button></dialog>
<script>
const itemModal=document.querySelector('.item-modal'), settingsModal=document.querySelector('.settings-modal');
document.querySelectorAll('.recent-item').forEach(button=>button.addEventListener('click',()=>{const item=JSON.parse(button.dataset.item); const bits=[item.Year,item.Container,item.Resolution,item.VideoCodec,item.AudioCodec,item.Size?formatBytes(item.Size):null].filter(Boolean); itemModal.querySelector('.modal-content').innerHTML=`<img src="image.php?id=${encodeURIComponent(item.Id)}" alt=""><div><p class="eyebrow">${item.Type==='Movie'?'FILM':'SERIE'}</p><h2>${escapeHtml(item.Name)}</h2><p class="modal-meta">${bits.map(escapeHtml).join(' · ')}</p>${item.Rating?`<p>★ ${item.Rating.toFixed(1)}</p>`:''}<p>${escapeHtml(item.Overview||'Ingen beskrivning tillgänglig.')}</p><a href="<?=e(rtrim($jellyfinServer,'/'))?>/web/#/details?id=${encodeURIComponent(item.Id)}" target="_blank" rel="noopener">Öppna i Jellyfin</a></div>`; itemModal.showModal();}));
document.querySelectorAll('.modal-close').forEach(b=>b.addEventListener('click',()=>b.closest('dialog').close()));
document.querySelector('.settings-button').addEventListener('click',()=>settingsModal.showModal());
const saved=JSON.parse(localStorage.getItem('fulflix-sections')||'{}');
document.querySelectorAll('[data-toggle-section]').forEach(input=>{const key=input.dataset.toggleSection;if(saved[key]===false){input.checked=false;document.querySelector(`[data-section="${key}"]`)?.classList.add('section-hidden');}input.addEventListener('change',()=>{saved[key]=input.checked;document.querySelector(`[data-section="${key}"]`)?.classList.toggle('section-hidden',!input.checked);localStorage.setItem('fulflix-sections',JSON.stringify(saved));});});
document.querySelector('.reset-settings').addEventListener('click',()=>{localStorage.removeItem('fulflix-sections');location.reload();});
function escapeHtml(v){return String(v).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function formatBytes(bytes){const units=['B','KB','MB','GB','TB'];let v=Number(bytes),i=0;while(v>=1024&&i<units.length-1){v/=1024;i++;}return `${v.toLocaleString('sv-SE',{maximumFractionDigits:i>=4?2:1})} ${units[i]}`;}
</script></body></html>
