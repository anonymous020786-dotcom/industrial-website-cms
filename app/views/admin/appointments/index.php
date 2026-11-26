<?php /** @var array $rows @var string $view @var array $counts */
$byDay = [];
foreach ($rows as $r) $byDay[date('Y-m-d', strtotime($r['starts_at']))][] = $r;
?>
<div class="mb-4 grid gap-4 sm:grid-cols-3">
  <?php foreach ([['Today', $counts['today'], 'calendar'], ['Next 7 days', $counts['week'], 'calendar-range'], ['Waiting for confirmation', $counts['pending'], 'hourglass']] as [$l, $n, $ic]): ?>
  <div class="adm-card p-5"><p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-charcoal/55"><?= icon($ic, 'h-4 w-4 text-steel') ?><?= $l ?></p><p class="mt-2 font-display text-3xl font-bold text-navy"><?= (int) $n ?></p></div>
  <?php endforeach ?>
</div>
<div class="adm-tabs mb-4">
  <?php foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled'] as $k => $l): ?><a class="adm-tab<?= $view === $k ? ' is-active' : '' ?>" href="<?= e(url('admin/appointments') . '?view=' . $k) ?>"><?= $l ?></a><?php endforeach ?>
</div>
<?php if ($byDay): ?>
<div class="space-y-6">
  <?php foreach ($byDay as $day => $list): ?>
  <div class="adm-card">
    <h2 class="adm-card-title border-b border-black/10 p-4"><?= icon('calendar', 'h-5 w-5 text-steel') ?> <?= e(date('l j F Y', strtotime($day))) ?><?= $day === date('Y-m-d') ? ' <span class="adm-badge bg-blue-100 text-blue-800">Today</span>' : '' ?></h2>
    <ul class="divide-y divide-black/5">
      <?php foreach ($list as $a): [$sl, $sc] = APPOINTMENT_STATUSES[$a['status']] ?? [$a['status'], '']; ?>
      <li><a class="flex flex-wrap items-center gap-4 p-4 hover:bg-black/[.02]" href="<?= e(url('admin/appointments/' . $a['id'])) ?>" data-appointment="<?= (int) $a['id'] ?>">
        <span class="w-24 font-mono text-sm font-semibold text-navy"><?= e(date('H:i', strtotime($a['starts_at']))) ?>–<?= e(date('H:i', strtotime($a['ends_at']))) ?></span>
        <span class="flex items-center gap-2 text-sm text-charcoal/70"><?= icon(APPOINTMENT_MODES[$a['mode']][1] ?? 'calendar', 'h-4 w-4 text-steel') ?><?= e((string) $a['type_name']) ?></span>
        <span class="min-w-0 flex-1"><span class="font-semibold text-navy"><?= e($a['name']) ?></span><span class="text-sm text-charcoal/55"><?= $a['company'] ? ' · ' . e($a['company']) : '' ?><?= $a['mode'] === 'onsite' && $a['location'] ? ' · ' . e($a['location']) : '' ?></span></span>
        <span class="adm-badge <?= $sc ?>"><?= e($sl) ?></span>
      </a></li>
      <?php endforeach ?>
    </ul>
  </div>
  <?php endforeach ?>
</div>
<?php else: ?>
<div class="adm-card p-12 text-center"><span class="mx-auto block w-fit text-charcoal/30"><?= icon('calendar-x', 'h-10 w-10') ?></span><p class="mt-3 font-semibold text-navy">No appointments here</p><p class="mt-1 text-sm text-charcoal/55">Customers book at <a class="adm-link" href="<?= e(url('book')) ?>" target="_blank">/book</a>. Link it from emails, quotes and WhatsApp.</p></div>
<?php endif ?>
