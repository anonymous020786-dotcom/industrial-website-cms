<?php /** @var array $d @var array $user */
$kpi = function (string $label, string $value, string $icon, string $sub = '', string $href = '') {
    $tag = $href ? 'a href="' . e(url($href)) . '"' : 'div';
    return '<' . $tag . ' class="adm-card adm-kpi"><div class="flex items-start justify-between"><p class="adm-kpi-label">' . e($label) . '</p><span class="adm-kpi-icon">' . icon($icon, 'h-5 w-5') . '</span></div>'
        . '<p class="adm-kpi-value">' . $value . '</p>' . ($sub ? '<p class="text-xs text-charcoal/55">' . $sub . '</p>' : '') . '</' . ($href ? 'a' : 'div') . '>';
};
$todo = array_filter($d['checklist'] ?? [], fn($c) => !$c['done']);
?>
<p class="mb-6 text-charcoal/60">Welcome back, <strong class="text-navy"><?= e($user['name']) ?></strong>. Here’s what’s happening.</p>

<?php if ($todo): ?>
<div class="adm-card mb-6 border-l-4 !border-l-safety p-5">
  <div class="flex items-center justify-between gap-4"><h2 class="adm-card-title"><?= icon('rocket', 'h-5 w-5 text-safety') ?> Go-live checklist</h2><span class="text-sm text-charcoal/55"><?= count($d['checklist']) - count($todo) ?>/<?= count($d['checklist']) ?> done</span></div>
  <ul class="mt-4 grid gap-2 sm:grid-cols-2">
    <?php foreach ($d['checklist'] as $c): ?>
    <li><a href="<?= e(url($c['url'])) ?>" class="flex items-center gap-2 text-sm <?= $c['done'] ? 'text-charcoal/45 line-through' : 'text-navy hover:text-steel' ?>"><?= icon($c['done'] ? 'circle-check' : 'circle', 'h-4 w-4 ' . ($c['done'] ? 'text-emerald-600' : 'text-charcoal/30')) ?><?= e($c['label']) ?></a></li>
    <?php endforeach ?>
  </ul>
</div>
<?php endif ?>

<?php if (can('leads')): $trend = $d['leads_prev30'] ? round(($d['leads_30'] - $d['leads_prev30']) / $d['leads_prev30'] * 100) : null; ?>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <?= $kpi('New leads', (string) $d['leads_new'], 'inbox', 'Waiting for first contact', 'admin/leads?status=new') ?>
  <?= $kpi('Leads (30 days)', (string) $d['leads_30'], 'trending-up', $trend === null ? 'No data for previous period' : (($trend >= 0 ? '▲ ' : '▼ ') . abs($trend) . '% vs previous 30 days')) ?>
  <?= $kpi('Open pipeline', (string) $d['leads_open'], 'git-branch', $d['pipeline_value'] ? 'Est. value ' . e(number_format($d['pipeline_value'], 0)) : 'Add estimated values to leads', 'admin/leads?status=open') ?>
  <?= $kpi('Win rate', $d['win_rate'] === null ? '—' : $d['win_rate'] . '%', 'trophy', 'Won ÷ (won + lost)') ?>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
  <div class="adm-card p-6 xl:col-span-2">
    <div class="flex items-center justify-between"><h2 class="adm-card-title">Leads — last 14 days</h2><a class="adm-link" href="<?= e(url('admin/leads')) ?>">All leads →</a></div>
    <?php $max = max(1, max($d['daily'])); ?>
    <div class="adm-chart mt-6" role="img" aria-label="Bar chart of leads per day">
      <?php foreach ($d['daily'] as $day => $n): ?>
      <div class="adm-chart-col" title="<?= e(format_date($day, 'D j M')) ?>: <?= $n ?> lead<?= $n === 1 ? '' : 's' ?>">
        <span class="adm-chart-val"><?= $n ?: '' ?></span>
        <span class="adm-chart-bar" style="height: <?= max(2, round($n / $max * 100)) ?>%"></span>
        <span class="adm-chart-label"><?= e(date('j', strtotime($day))) ?></span>
      </div>
      <?php endforeach ?>
    </div>
  </div>
  <div class="adm-card p-6">
    <h2 class="adm-card-title">Pipeline by status</h2>
    <?php $totalS = max(1, array_sum($d['by_status'])); ?>
    <ul class="mt-5 space-y-3">
      <?php foreach (LEAD_STATUSES as $k => [$label]): $n = (int) ($d['by_status'][$k] ?? 0); ?>
      <li><a href="<?= e(url('admin/leads?status=' . $k)) ?>" class="block">
        <div class="flex justify-between text-sm"><span class="font-medium text-navy"><?= e($label) ?></span><span class="text-charcoal/60"><?= $n ?></span></div>
        <div class="mt-1 h-1.5 bg-mist"><div class="h-full bg-steel" style="width: <?= round($n / $totalS * 100) ?>%"></div></div>
      </a></li>
      <?php endforeach ?>
    </ul>
  </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
  <div class="adm-card xl:col-span-2">
    <div class="flex items-center justify-between p-6 pb-3"><h2 class="adm-card-title">Recent leads</h2><a class="adm-link" href="<?= e(url('admin/leads/new')) ?>">+ Add lead</a></div>
    <?php if ($d['recent']): ?>
    <div class="overflow-x-auto"><table class="adm-table">
      <thead><tr><th>Name</th><th>Product</th><th>Type</th><th>Status</th><th>Received</th></tr></thead>
      <tbody><?php foreach ($d['recent'] as $l): ?>
        <tr><td><a class="font-semibold text-navy hover:text-steel" href="<?= e(url('admin/leads/' . $l['id'])) ?>"><?= e($l['name']) ?></a><div class="text-xs text-charcoal/55"><?= e($l['company']) ?></div></td>
          <td class="text-sm"><?= e($l['product']) ?></td><td class="text-sm capitalize"><?= e($l['type']) ?></td><td><?= lead_status_badge($l['status']) ?></td><td class="whitespace-nowrap text-sm text-charcoal/60"><?= e(time_ago($l['created_at'])) ?></td></tr>
      <?php endforeach ?></tbody>
    </table></div>
    <?php else: ?><p class="px-6 pb-8 text-sm text-charcoal/55">No leads yet. Enquiries from the contact and quote forms will appear here.</p><?php endif ?>
  </div>
  <div class="space-y-6">
    <div class="adm-card p-6">
      <h2 class="adm-card-title"><?= icon('calendar-clock', 'h-5 w-5 text-safety') ?> Follow-ups due</h2>
      <?php if ($d['followups']): ?><ul class="mt-4 divide-y divide-black/5">
        <?php foreach ($d['followups'] as $f): $overdue = $f['follow_up_at'] < date('Y-m-d'); ?>
        <li class="py-2.5"><a href="<?= e(url('admin/leads/' . $f['id'])) ?>" class="flex items-center justify-between gap-3 text-sm"><span class="font-medium text-navy"><?= e($f['name']) ?></span><span class="<?= $overdue ? 'font-semibold text-red-600' : 'text-charcoal/55' ?>"><?= $overdue ? 'Overdue · ' : '' ?><?= e(format_date($f['follow_up_at'], 'j M')) ?></span></a></li>
        <?php endforeach ?></ul>
      <?php else: ?><p class="mt-3 text-sm text-charcoal/55">Nothing due in the next 7 days.</p><?php endif ?>
    </div>
    <div class="adm-card p-6">
      <h2 class="adm-card-title">Contact clicks (30 days)</h2>
      <div class="mt-4 grid grid-cols-3 gap-3 text-center">
        <?php foreach (['whatsapp' => ['WhatsApp', 'message-circle'], 'phone' => ['Phone', 'phone'], 'email' => ['Email', 'mail']] as $k => [$l, $i]): ?>
        <div class="bg-mist p-3"><span class="mx-auto block w-fit text-steel"><?= icon($i, 'h-5 w-5') ?></span><p class="mt-1 text-xl font-bold text-navy"><?= (int) ($d['clicks'][$k] ?? 0) ?></p><p class="text-[11px] uppercase tracking-wider text-charcoal/55"><?= $l ?></p></div>
        <?php endforeach ?>
      </div>
    </div>
    <?php if ($d['sources']): ?>
    <div class="adm-card p-6">
      <h2 class="adm-card-title">Lead sources (90 days)</h2>
      <ul class="mt-4 space-y-2 text-sm"><?php foreach ($d['sources'] as $s): ?><li class="flex justify-between"><span class="truncate text-navy"><?= e($s['src']) ?></span><span class="text-charcoal/60"><?= (int) $s['n'] ?></span></li><?php endforeach ?></ul>
    </div>
    <?php endif ?>
  </div>
</div>
<?php endif ?>

<?php if (can('content')): ?>
<div class="mt-6 grid gap-6 xl:grid-cols-3">
  <div class="grid gap-4 sm:grid-cols-3 xl:col-span-2">
    <?= $kpi('Published articles', (string) $d['posts_published'], 'newspaper', '', 'admin/posts?status=published') ?>
    <?= $kpi('Drafts to review', (string) $d['posts_draft'], 'file-pen', 'Publish 2–3 per week for steady SEO growth', 'admin/posts?status=draft') ?>
    <?= $kpi('Scheduled', (string) $d['posts_scheduled'], 'calendar-clock', '', 'admin/posts?status=scheduled') ?>
  </div>
  <div class="adm-card p-6">
    <h2 class="adm-card-title">Most-read articles</h2>
    <?php if ($d['top_posts']): ?><ol class="mt-4 space-y-2 text-sm"><?php foreach ($d['top_posts'] as $p): ?><li class="flex justify-between gap-3"><a class="truncate text-navy hover:text-steel" href="<?= e(url('admin/posts/' . $p['id'] . '/edit')) ?>"><?= e($p['title']) ?></a><span class="shrink-0 text-charcoal/55"><?= (int) $p['views'] ?> views</span></li><?php endforeach ?></ol>
    <?php else: ?><p class="mt-3 text-sm text-charcoal/55">No published articles yet.</p><?php endif ?>
  </div>
</div>
<?php endif ?>
