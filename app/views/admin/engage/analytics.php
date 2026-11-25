<?php /** @var array $a @var int $range @var string $from @var string $to */
$trend = function (int $now, int $before): string {
    if (!$before) return $now ? '<span class="text-emerald-700">new</span>' : '';
    $p = (int) round(($now - $before) / $before * 100);
    return '<span class="' . ($p >= 0 ? 'text-emerald-700' : 'text-red-700') . '">' . ($p >= 0 ? '▲' : '▼') . ' ' . abs($p) . '%</span> vs previous period';
};
$max = max(1, ...array_values(array_map(fn($d) => $d['views'], $a['days'])));
$devTotal = max(1, array_sum($a['devices']));
$srcMax = max(1, ...array_map(fn($s) => (int) $s['visitors'], $a['sources'] ?: [['visitors' => 1]]));
$pageMax = max(1, ...array_map(fn($p) => (int) $p['views'], $a['pages'] ?: [['views' => 1]]));
?>
<div class="mb-4 flex flex-wrap items-center gap-2">
  <?php foreach (['7' => '7 days', '30' => '30 days', '90' => '90 days', '365' => '12 months'] as $k => $l): ?>
  <a class="adm-tab<?= (string) $range === $k ? ' is-active' : '' ?>" href="<?= e(url('admin/analytics') . '?range=' . $k) ?>"><?= $l ?></a>
  <?php endforeach ?>
  <span class="ml-auto inline-flex items-center gap-2 text-sm text-charcoal/65" data-live><span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span></span><?= $a['live'] ?> visitor<?= $a['live'] === 1 ? '' : 's' ?> in the last 5 minutes</span>
</div>

<?php if (!analytics_enabled()): ?><div class="adm-card mb-4 border-l-4 border-amber-500 p-4 text-sm">Built-in analytics is switched off — no new visits are counted.</div><?php endif ?>

<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-analytics-totals>
  <?php foreach ([['Visitors', number_format($a['visitors']), $trend($a['visitors'], $a['prev_visitors']), 'users-round'], ['Page views', number_format($a['views']), $trend($a['views'], $a['prev_views']), 'eye'],
    ['Pages per visit', $a['visitors'] ? number_format($a['views'] / $a['visitors'], 1) : '—', '', 'layers'], ['Enquiry rate', $a['conversion'] . '%', $a['leads'] . ' enquiries', 'inbox']] as [$l, $v, $sub, $ic]): ?>
  <div class="adm-card p-5"><p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-charcoal/55"><?= icon($ic, 'h-4 w-4 text-steel') ?><?= $l ?></p>
    <p class="mt-2 font-display text-3xl font-bold text-navy"><?= e($v) ?></p><p class="text-xs text-charcoal/55"><?= $sub ?></p></div>
  <?php endforeach ?>
</div>

<div class="adm-card mb-6 p-6">
  <h2 class="adm-card-title"><?= icon('chart-line', 'h-5 w-5 text-steel') ?> Page views per day</h2>
  <div class="mt-5 flex h-44 items-end gap-[2px]" role="img" aria-label="Page views per day">
    <?php foreach ($a['days'] as $day => $d): ?>
    <div class="group relative flex h-full flex-1 items-end" title="<?= e(date('D j M', strtotime($day))) ?>: <?= $d['views'] ?> views, <?= $d['visitors'] ?> visitors">
      <span class="adm-chart-bar w-full rounded-t bg-steel/80 group-hover:bg-steel" style="height: <?= max(1, round($d['views'] / $max * 100)) ?>%"></span>
    </div>
    <?php endforeach ?>
  </div>
  <div class="mt-2 flex justify-between text-xs text-charcoal/50"><span><?= e(date('j M', strtotime($from))) ?></span><span><?= e(date('j M', strtotime($to))) ?></span></div>
</div>

<div class="grid gap-6 xl:grid-cols-3">
  <div class="adm-card p-6 xl:col-span-2">
    <h2 class="adm-card-title"><?= icon('file-text', 'h-5 w-5 text-steel') ?> Top pages</h2>
    <?php if ($a['pages']): ?>
    <table class="adm-table mt-3"><thead><tr><th>Page</th><th class="text-right">Visitors</th><th class="text-right">Views</th></tr></thead><tbody>
      <?php foreach ($a['pages'] as $p): ?>
      <tr data-top-page="<?= e($p['path']) ?>"><td class="relative"><span class="absolute inset-y-1 left-0 rounded bg-steel/10" style="width: <?= round($p['views'] / $pageMax * 100) ?>%"></span><a class="relative text-sm text-navy hover:underline" href="<?= e(url(ltrim($p['path'], '/'))) ?>" target="_blank"><?= e($p['path']) ?></a></td>
        <td class="text-right text-sm"><?= number_format((int) $p['visitors']) ?></td><td class="text-right text-sm font-semibold"><?= number_format((int) $p['views']) ?></td></tr>
      <?php endforeach ?>
    </tbody></table>
    <?php else: ?><p class="mt-3 text-sm text-charcoal/60">No page views in this period yet.</p><?php endif ?>
  </div>
  <div class="space-y-6">
    <div class="adm-card p-6">
      <h2 class="adm-card-title"><?= icon('signpost', 'h-5 w-5 text-steel') ?> Sources</h2>
      <ul class="mt-4 space-y-3 text-sm">
        <?php foreach ($a['sources'] as $s): ?>
        <li><div class="flex justify-between gap-3"><span class="truncate"><?= e($s['source'] !== '' ? $s['source'] : 'Direct / unknown') ?></span><strong><?= number_format((int) $s['visitors']) ?></strong></div>
          <div class="mt-1 h-1.5 bg-black/5"><div class="h-1.5 bg-steel" style="width: <?= round((int) $s['visitors'] / $srcMax * 100) ?>%"></div></div></li>
        <?php endforeach ?>
        <?php if (!$a['sources']): ?><li class="text-charcoal/60">No visits yet.</li><?php endif ?>
      </ul>
    </div>
    <div class="adm-card p-6">
      <h2 class="adm-card-title"><?= icon('monitor-smartphone', 'h-5 w-5 text-steel') ?> Devices</h2>
      <ul class="mt-4 space-y-2 text-sm">
        <?php foreach (['desktop' => ['Desktop', 'monitor'], 'mobile' => ['Mobile', 'smartphone'], 'tablet' => ['Tablet', 'tablet']] as $k => [$l, $ic]): $n = (int) ($a['devices'][$k] ?? 0); ?>
        <li class="flex items-center gap-3"><?= icon($ic, 'h-4 w-4 text-steel') ?><span class="flex-1"><?= $l ?></span><strong><?= round($n / $devTotal * 100) ?>%</strong></li>
        <?php endforeach ?>
      </ul>
    </div>
  </div>
</div>
<p class="mt-4 text-xs text-charcoal/55">Cookie-free: visitors are counted with a hash that changes every day, so nobody is tracked across days. Your team’s own visits, robots and Do-Not-Track browsers are not counted.</p>
