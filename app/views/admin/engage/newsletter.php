<?php /** @var array $rows @var array $subs @var int $growth @var int $lost */
$tile = fn($label, $value, $sub = '', $icon = 'users-round') => '<div class="adm-card p-5"><p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-charcoal/55">' . icon($icon, 'h-4 w-4 text-steel') . e($label) . '</p><p class="mt-2 font-display text-3xl font-bold text-navy">' . e((string) $value) . '</p>' . ($sub !== '' ? '<p class="text-xs text-charcoal/55">' . e($sub) . '</p>' : '') . '</div>';
?>
<?php if (!newsletter_enabled()): ?>
<div class="adm-card mb-6 flex items-center gap-3 border-l-4 border-amber-500 p-4 text-sm"><?= icon('info', 'h-5 w-5 text-amber-600') ?><p class="flex-1">The sign-up form is switched off on the website.</p><?php if (can('settings')): ?><a class="adm-btn adm-btn-light adm-btn-sm" href="<?= e(url('admin/settings/community')) ?>">Turn on</a><?php endif ?></div>
<?php endif ?>
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <?= $tile('Subscribers', (int) ($subs['subscribed'] ?? 0), '+' . $growth . ' in the last 30 days', 'users-round') ?>
  <?= $tile('Waiting to confirm', (int) ($subs['pending'] ?? 0), 'They clicked subscribe but not the email link', 'hourglass') ?>
  <?= $tile('Unsubscribed (30 days)', $lost, (int) ($subs['unsubscribed'] ?? 0) . ' in total', 'mail-x') ?>
  <?= $tile('Newsletters sent', count(array_filter($rows, fn($r) => $r['status'] === 'sent')), '', 'megaphone') ?>
</div>

<div class="adm-card">
  <?php if ($rows): ?>
  <div class="overflow-x-auto">
    <table class="adm-table">
      <thead><tr><th>Newsletter</th><th>Status</th><th class="text-right">Recipients</th><th class="text-right">Opened</th><th class="text-right">Clicked</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): [$sl, $sc] = CAMPAIGN_STATUSES[$r['status']] ?? [ucfirst($r['status']), 'bg-gray-100 text-gray-700']; $s = $r['stats']; ?>
        <tr data-campaign="<?= (int) $r['id'] ?>">
          <td><a class="font-semibold text-navy hover:text-steel" href="<?= e(url('admin/newsletter/' . $r['id'] . '/edit')) ?>"><?= e($r['subject']) ?></a><div class="text-xs text-charcoal/55"><?= e((string) $r['author']) ?></div></td>
          <td><span class="adm-badge <?= $sc ?>"><?= e($sl) ?></span><?php if ($r['status'] === 'sending' && $s): ?><div class="mt-1 text-xs text-charcoal/55"><?= $s['sent'] ?> / <?= $s['total'] ?></div><?php endif ?></td>
          <td class="text-right"><?= $s ? $s['total'] : '—' ?></td>
          <td class="text-right"><?= $s ? $s['open_rate'] . '%' : '—' ?></td>
          <td class="text-right"><?= $s ? $s['click_rate'] . '%' : '—' ?></td>
          <td class="whitespace-nowrap text-sm text-charcoal/60"><?= e(format_date((string) ($r['sent_at'] ?: $r['scheduled_at'] ?: $r['updated_at']), 'j M Y H:i')) ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="p-12 text-center"><span class="mx-auto block w-fit text-charcoal/30"><?= icon('megaphone', 'h-10 w-10') ?></span><p class="mt-3 font-semibold text-navy">No newsletters yet</p><p class="mt-1 text-sm text-charcoal/55">Write your first one — include your latest articles automatically.</p>
    <a class="adm-btn adm-btn-primary mt-5" href="<?= e(url('admin/newsletter/new')) ?>"><?= icon('plus', 'h-4 w-4') ?> New newsletter</a></div>
  <?php endif ?>
</div>
<p class="mt-4 text-xs text-charcoal/55">Open rates are approximate: some mail apps block tracking images. Emails go out in batches of <?= (int) setting('newsletter_batch', 100) ?> every time the scheduled job runs (every 10 minutes).</p>
