<?php /** @var array $rows @var string $status @var string $s @var array $stats */
$cur = (string) setting('quote_currency', 'INR'); ?>
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <?php foreach ([['Quotes (90 days)', (int) ($stats['n'] ?? 0), 'file-text', ''], ['Open — awaiting customer', (int) ($stats['open_n'] ?? 0), 'hourglass', money((float) ($stats['open_v'] ?? 0), $cur)],
                  ['Accepted', (int) ($stats['won_n'] ?? 0), 'badge-check', money((float) ($stats['won_v'] ?? 0), $cur)], ['Declined', (int) ($stats['lost_n'] ?? 0), 'circle-x', '']] as [$l, $n, $ic, $sub]): ?>
  <div class="adm-card flex items-center gap-4 p-5"><span class="grid h-11 w-11 place-items-center rounded-full bg-mist text-steel"><?= icon($ic, 'h-5 w-5') ?></span>
    <div><p class="text-2xl font-bold text-navy"><?= $n ?></p><p class="text-sm text-charcoal/70"><?= e($l) ?><?= $sub ? ' · <strong>' . e($sub) . '</strong>' : '' ?></p></div></div>
  <?php endforeach ?>
</div>
<div class="adm-card">
  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-black/5 p-4">
    <nav class="flex flex-wrap gap-1" aria-label="Filter">
      <?php foreach (['' => 'All'] + array_map(fn($x) => $x[0], QUOTE_STATUSES) as $k => $l): ?>
      <a href="<?= e(url('admin/quotes' . ($k !== '' ? '?status=' . $k : ''))) ?>" class="adm-btn adm-btn-sm <?= $status === $k ? 'adm-btn-primary' : 'adm-btn-light' ?>"><?= e($l) ?></a>
      <?php endforeach ?>
    </nav>
    <form method="get" class="flex gap-2"><?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif ?>
      <label class="sr-only" for="qs">Search quotes</label><input id="qs" name="q" value="<?= e($s) ?>" class="adm-input !w-56" placeholder="Number, customer, email">
      <button class="adm-btn adm-btn-light adm-btn-sm"><?= icon('search', 'h-4 w-4') ?> Search</button></form>
  </div>
  <?php if (!$rows): ?>
    <div class="p-12 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-mist text-steel"><?= icon('file-text', 'h-7 w-7') ?></span>
      <p class="mt-4 font-semibold text-navy">No quotations<?= $status || $s ? ' match' : ' yet' ?></p>
      <p class="mt-1 text-sm text-charcoal/70">Open a lead and press <strong>Create quote</strong> — the customer and requested items are filled in for you.</p></div>
  <?php else: ?>
  <div class="overflow-x-auto"><table class="adm-table">
    <thead><tr><th>Quotation</th><th>Customer</th><th class="text-right">Total</th><th>Status</th><th>Valid until</th><th>Sent</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $q): ?>
      <tr>
        <td><a class="font-mono font-semibold text-navy hover:text-steel" href="<?= e(url('admin/quotes/' . $q['id'] . '/edit')) ?>"><?= e($q['number']) ?></a><?php if ($q['lead_id']): ?><span class="block text-xs text-charcoal/60">Lead #<?= (int) $q['lead_id'] ?></span><?php endif ?></td>
        <td><span class="font-semibold text-navy"><?= e($q['customer_name']) ?></span><span class="block text-xs text-charcoal/65"><?= e((string) $q['company']) ?></span></td>
        <td class="whitespace-nowrap text-right font-semibold"><?= e(money((float) $q['total'], $q['currency'])) ?></td>
        <td><?= quote_badge($q) ?><?php if ($q['viewed_at'] && $q['status'] === 'sent'): ?><span class="block text-xs text-charcoal/60">viewed <?= e(time_ago((string) $q['viewed_at'])) ?></span><?php endif ?></td>
        <td class="whitespace-nowrap text-sm"><?= $q['valid_until'] ? e(date('j M Y', strtotime((string) $q['valid_until']))) : '—' ?></td>
        <td class="whitespace-nowrap text-sm text-charcoal/70"><?= $q['sent_at'] ? e(time_ago((string) $q['sent_at'])) : 'not sent' ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <?php endif ?>
</div>
