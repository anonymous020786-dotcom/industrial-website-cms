<?php /** @var array $rows @var array $totals @var string $group @var string $from @var string $to @var string $range @var array $months @var array $events @var array $downloads */
$groups = report_groups();
$money = fn(float $v) => $v > 0 ? number_format($v, 0) : '—';
$maxLeads = max(1, ...array_map(fn($r) => $r['leads'], $rows ?: [['leads' => 1]]));
$maxMonth = max(1, ...array_map(fn($m) => (int) $m['n'], $months ?: [['n' => 1]]));
$eventLabels = ['whatsapp' => 'WhatsApp clicks', 'phone' => 'Phone clicks', 'email' => 'Email clicks', 'datasheet' => 'Datasheet downloads', 'catalogue' => 'Catalogue downloads'];
?>
<form method="get" class="adm-card mb-6 flex flex-wrap items-end gap-3 p-4">
  <input type="hidden" name="by" value="<?= e($group) ?>">
  <div><label class="adm-label" for="rg">Period</label>
    <select id="rg" name="range" class="adm-input !w-auto" data-range-select>
      <?php foreach (['30' => 'Last 30 days', '90' => 'Last 90 days', '180' => 'Last 6 months', '365' => 'Last 12 months', 'custom' => 'Custom range'] as $k => $l): ?><option value="<?= $k ?>"<?= $range === (string) $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach ?>
    </select></div>
  <div class="flex gap-3" data-custom <?= $range === 'custom' ? '' : 'hidden' ?>>
    <div><label class="adm-label" for="rf">From</label><input id="rf" type="date" name="from" value="<?= e($from) ?>" class="adm-input"></div>
    <div><label class="adm-label" for="rt">To</label><input id="rt" type="date" name="to" value="<?= e($to) ?>" class="adm-input"></div>
  </div>
  <button class="adm-btn adm-btn-primary"><?= icon('filter', 'h-4 w-4') ?> Apply</button>
  <p class="ml-auto text-sm text-charcoal/60"><?= e(date('j M Y', strtotime($from))) ?> – <?= e(date('j M Y', strtotime($to))) ?> · spam excluded</p>
</form>

<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
  <?php foreach ([['Leads', number_format($totals['leads']), 'inbox'], ['Qualified or better', number_format($totals['qualified']), 'badge-check'], ['Won', number_format($totals['won']), 'trophy'],
      ['Win rate', $totals['win_rate'] !== null ? $totals['win_rate'] . '%' : '—', 'percent'], ['Won value', $money($totals['won_value']), 'banknote']] as [$l, $v, $ic]): ?>
  <div class="adm-card p-5"><p class="flex items-center gap-2 text-sm text-charcoal/70"><?= icon($ic, 'h-4 w-4 text-steel') ?><?= $l ?></p><p class="mt-2 text-3xl font-bold text-navy"><?= $v ?></p></div>
  <?php endforeach ?>
</div>

<div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
  <div class="adm-card">
    <div class="flex flex-wrap items-center gap-2 border-b border-black/10 p-3">
      <span class="text-sm font-semibold text-navy">Group by</span>
      <?php foreach ($groups as $k => $l): ?><a class="adm-btn adm-btn-sm <?= $group === $k ? 'adm-btn-primary' : 'adm-btn-light' ?>" href="<?= e(url('admin/reports') . qs(['by' => $k])) ?>"><?= e($l) ?></a><?php endforeach ?>
    </div>
    <?php if ($rows): ?>
    <div class="overflow-x-auto"><table class="adm-table">
      <thead><tr><th><?= e($groups[$group]) ?></th><th class="w-1/4">Leads</th><th class="text-right">Qualified+</th><th class="text-right">Quoted+</th><th class="text-right">Won</th><th class="text-right">Win rate</th><th class="text-right">Won value</th><th class="text-right">Open pipeline</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="max-w-[16rem] break-words font-medium text-navy"><?= e((string) $r['label']) ?></td>
          <td><div class="flex items-center gap-2"><span class="h-2 rounded-full bg-steel" style="width: <?= max(4, round($r['leads'] / $maxLeads * 100)) ?>%"></span><span class="text-sm"><?= $r['leads'] ?></span></div></td>
          <td class="text-right text-sm"><?= $r['qualified'] ?></td>
          <td class="text-right text-sm"><?= $r['quoted'] ?></td>
          <td class="text-right text-sm font-semibold"><?= $r['won'] ?></td>
          <td class="text-right text-sm"><?= $r['win_rate'] !== null ? $r['win_rate'] . '%' : '—' ?></td>
          <td class="text-right text-sm"><?= $money($r['won_value']) ?></td>
          <td class="text-right text-sm text-charcoal/70"><?= $money($r['pipeline']) ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table></div>
    <?php else: ?><p class="p-12 text-center text-sm text-charcoal/60">No leads in this period.</p><?php endif ?>
    <p class="border-t border-black/5 p-3 text-xs text-charcoal/60">Source/medium/campaign come from UTM tags on the visitor’s landing URL (e.g. <code>?utm_source=linkedin&amp;utm_medium=cpc</code>), otherwise from the referring website. Win rate = won ÷ (won + lost). Values use each lead’s estimated value.</p>
  </div>

  <aside class="space-y-6">
    <div class="adm-card p-5">
      <h2 class="adm-card-title"><?= icon('chart-column', 'h-5 w-5 text-steel') ?> Last 12 months</h2>
      <?php if ($months): ?>
      <div class="mt-4 flex h-36 items-end gap-1.5" role="img" aria-label="Leads per month">
        <?php foreach ($months as $m): ?>
        <div class="flex flex-1 flex-col items-center gap-1" title="<?= e(date('M Y', strtotime($m['m'] . '-01'))) ?>: <?= (int) $m['n'] ?> leads, <?= (int) $m['won'] ?> won">
          <div class="relative w-full rounded-t bg-steel/80" style="height: <?= max(3, round($m['n'] / $maxMonth * 120)) ?>px"><?php if ($m['won']): ?><div class="absolute bottom-0 w-full rounded-t bg-emerald-500" style="height: <?= round($m['won'] / max(1, $m['n']) * 100) ?>%"></div><?php endif ?></div>
          <span class="text-[10px] text-charcoal/60"><?= e(date('M', strtotime($m['m'] . '-01'))) ?></span>
        </div>
        <?php endforeach ?>
      </div>
      <p class="mt-3 flex gap-4 text-xs text-charcoal/70"><span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-steel"></span>Leads</span><span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-emerald-500"></span>Won</span></p>
      <?php else: ?><p class="mt-3 text-sm text-charcoal/60">No data yet.</p><?php endif ?>
    </div>
    <div class="adm-card p-5">
      <h2 class="adm-card-title"><?= icon('mouse-pointer-click', 'h-5 w-5 text-steel') ?> Engagement</h2>
      <ul class="mt-3 space-y-2 text-sm">
        <?php foreach ($eventLabels as $k => $l): ?><li class="flex justify-between"><span class="text-charcoal/70"><?= $l ?></span><strong class="text-navy"><?= (int) ($events[$k] ?? 0) ?></strong></li><?php endforeach ?>
      </ul>
      <?php if ($downloads): ?>
      <h3 class="mt-5 text-xs font-bold uppercase tracking-wide text-charcoal/60">Top downloads</h3>
      <ul class="mt-2 space-y-1 text-sm"><?php foreach ($downloads as $d): ?><li class="flex justify-between gap-3"><span class="truncate"><?= e((string) $d['page']) ?></span><strong><?= (int) $d['n'] ?></strong></li><?php endforeach ?></ul>
      <?php endif ?>
    </div>
  </aside>
</div>
