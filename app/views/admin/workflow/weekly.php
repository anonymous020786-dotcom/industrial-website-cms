<?php /** @var string $html @var ?int $next */ ?>
<div class="grid gap-6 xl:grid-cols-3">
  <div class="adm-card p-6 xl:col-span-2">
    <div class="weekly-preview mx-auto max-w-2xl bg-white p-6 text-gray-800" data-weekly-preview><?= $html ?></div>
  </div>
  <aside class="space-y-6">
    <div class="adm-card p-6 text-sm">
      <h2 class="adm-card-title"><?= icon('calendar-clock', 'h-5 w-5 text-steel') ?> Schedule</h2>
      <p class="mt-3 text-charcoal/75"><?= $next ? 'Next report: <strong>' . e(date('l j M, H:i', $next)) . '</strong>.' : 'The weekly report is <strong>off</strong>.' ?></p>
      <p class="mt-2 text-charcoal/60">Sent to: <?= e((string) setting('report_recipients', '') ?: 'all active administrators') ?></p>
    </div>
    <form method="post" action="<?= e(url('admin/reports/weekly/send')) ?>" class="adm-card p-6">
      <?= csrf_field() ?>
      <p class="text-sm text-charcoal/70">Send this report now (last 7 days).</p>
      <button class="adm-btn adm-btn-primary mt-4"><?= icon('send', 'h-4 w-4') ?> Send now</button>
    </form>
  </aside>
</div>
