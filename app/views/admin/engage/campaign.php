<?php /** @var ?array $c @var array $values @var array $errors @var bool $locked @var ?array $stats @var int $audience @var ?string $preview @var array $links */
$schema = [
    'subject' => ['type' => 'text', 'label' => 'Subject line', 'required' => true, 'wide' => true, 'placeholder' => 'e.g. 5 signs your hoist brake needs attention'],
    'preheader' => ['type' => 'text', 'label' => 'Preview text (shown after the subject in the inbox)', 'wide' => true],
    'body' => ['type' => 'html', 'label' => 'Message'],
    'latest_posts' => ['type' => 'select', 'label' => 'Add latest blog articles', 'options' => [0 => 'None', 3 => 'Latest 3', 5 => 'Latest 5', 8 => 'Latest 8']],
];
?>
<div class="grid gap-6 xl:grid-cols-3">
  <div class="space-y-6 xl:col-span-2">
    <?php if ($locked && $stats): ?>
    <div class="grid gap-4 sm:grid-cols-4" data-campaign-stats>
      <?php foreach ([['Sent', $stats['sent'] . ' / ' . $stats['total']], ['Opened', $stats['open_rate'] . '% (' . $stats['opened'] . ')'], ['Clicked', $stats['click_rate'] . '% (' . $stats['clicked'] . ')'], ['Unsubscribed', (string) $stats['unsubscribed']]] as [$l, $v]): ?>
      <div class="adm-card p-4"><p class="text-xs font-semibold uppercase tracking-wider text-charcoal/55"><?= $l ?></p><p class="mt-1 font-display text-2xl font-bold text-navy"><?= e($v) ?></p></div>
      <?php endforeach ?>
    </div>
    <?php if ($stats['failed']): ?><div class="adm-card border-l-4 border-red-500 p-4 text-sm"><?= $stats['failed'] ?> email(s) could not be sent — see the list below.</div><?php endif ?>
    <?php endif ?>

    <?php if (!$locked): ?>
    <form method="post" class="adm-card space-y-4 p-6" id="campaign-form">
      <?= csrf_field() ?>
      <?= render_fields($schema, $values, $errors) ?>
      <p class="adm-help">Every email automatically gets your company address and an unsubscribe link (required by anti-spam law).</p>
      <div class="flex flex-wrap gap-2">
        <button class="adm-btn adm-btn-primary"><?= icon('save', 'h-4 w-4') ?> Save draft</button>
        <button class="adm-btn adm-btn-light" name="then" value="test"><?= icon('send', 'h-4 w-4') ?> Save &amp; send me a test</button>
      </div>
    </form>
    <?php endif ?>

    <?php if ($preview): ?>
    <div class="adm-card p-0">
      <h2 class="adm-card-title border-b border-black/10 p-4"><?= icon('eye', 'h-5 w-5 text-steel') ?> Preview</h2>
      <iframe class="h-[640px] w-full bg-white" sandbox="" srcdoc="<?= e($preview) ?>" title="Newsletter preview"></iframe>
    </div>
    <?php endif ?>

    <?php if ($links): ?>
    <div class="adm-card">
      <h2 class="adm-card-title border-b border-black/10 p-4"><?= icon('mouse-pointer-click', 'h-5 w-5 text-steel') ?> Recipients</h2>
      <div class="max-h-96 overflow-y-auto"><table class="adm-table"><thead><tr><th>Email</th><th>Sent</th><th>Opened</th><th>Clicked</th></tr></thead><tbody>
      <?php foreach ($links as $l): ?><tr><td class="text-sm"><?= e($l['email']) ?><?php if ($l['error']): ?><div class="text-xs text-red-700"><?= e($l['error']) ?></div><?php endif ?></td>
        <td class="text-sm"><?= $l['sent_at'] && !$l['error'] ? e(format_date($l['sent_at'], 'j M H:i')) : ($l['error'] ? '✕' : 'queued') ?></td><td class="text-sm"><?= $l['opened_at'] ? '✓' : '' ?></td><td class="text-sm"><?= $l['clicked_at'] ? '✓' : '' ?></td></tr><?php endforeach ?>
      </tbody></table></div>
    </div>
    <?php endif ?>
  </div>

  <aside class="space-y-6">
    <?php if ($c): ?>
    <div class="adm-card p-6 text-sm">
      <p>Status: <span class="adm-badge <?= CAMPAIGN_STATUSES[$c['status']][1] ?? '' ?>"><?= e(CAMPAIGN_STATUSES[$c['status']][0] ?? $c['status']) ?></span></p>
      <?php if ($c['status'] === 'scheduled'): ?><p class="mt-2">Goes out <strong><?= e(format_date((string) $c['scheduled_at'], 'l j M Y, H:i')) ?></strong>.</p><?php endif ?>
      <p class="mt-2 text-charcoal/65">Confirmed subscribers: <strong><?= $audience ?></strong></p>
    </div>
    <?php if (in_array($c['status'], ['draft', 'scheduled'], true)): ?>
    <form method="post" action="<?= e(url('admin/newsletter/' . $c['id'] . '/send')) ?>" class="adm-card space-y-4 p-6">
      <?= csrf_field() ?>
      <h2 class="adm-card-title"><?= icon('megaphone', 'h-5 w-5 text-steel') ?> Send</h2>
      <button class="adm-btn adm-btn-primary w-full justify-center" name="mode" value="now" data-confirm="Send “<?= e($c['subject']) ?>” to <?= $audience ?> subscriber(s) now? This cannot be undone."<?= $audience ? '' : ' disabled' ?>><?= icon('send', 'h-4 w-4') ?> Send now to <?= $audience ?></button>
      <div class="border-t border-black/10 pt-4"><label class="adm-label" for="c-when">Or schedule</label>
        <input id="c-when" type="datetime-local" name="scheduled_at" class="adm-input" value="<?= e($c['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($c['scheduled_at'])) : '') ?>">
        <button class="adm-btn adm-btn-light mt-2 w-full justify-center" name="mode" value="schedule"><?= icon('calendar-clock', 'h-4 w-4') ?> Schedule</button></div>
      <p class="adm-help">Save your latest edits before sending.</p>
    </form>
    <?php endif ?>
    <div class="adm-card flex flex-wrap gap-2 p-4">
      <?php if (!$locked): ?><form method="post" action="<?= e(url('admin/newsletter/' . $c['id'] . '/test')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn-light adm-btn-sm"><?= icon('send', 'h-4 w-4') ?> Send test</button></form><?php endif ?>
      <?php if (in_array($c['status'], ['scheduled', 'sending'], true)): ?><form method="post" action="<?= e(url('admin/newsletter/' . $c['id'] . '/cancel')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn-light adm-btn-sm" data-confirm="Stop this newsletter?"><?= icon('circle-stop', 'h-4 w-4') ?> <?= $c['status'] === 'scheduled' ? 'Unschedule' : 'Stop sending' ?></button></form><?php endif ?>
      <form method="post" action="<?= e(url('admin/newsletter/' . $c['id'] . '/duplicate')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn-light adm-btn-sm"><?= icon('copy', 'h-4 w-4') ?> Duplicate</button></form>
      <?php if (in_array($c['status'], ['draft', 'cancelled', 'sent'], true)): ?><form method="post" action="<?= e(url('admin/newsletter/' . $c['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn-light adm-btn-sm text-red-600" data-confirm="Delete this newsletter and its statistics?"><?= icon('trash-2', 'h-4 w-4') ?> Delete</button></form><?php endif ?>
    </div>
    <?php else: ?>
    <div class="adm-card p-6 text-sm text-charcoal/70"><p>Save the draft to preview it, send yourself a test, and send or schedule it.</p><p class="mt-2">Confirmed subscribers: <strong><?= $audience ?></strong></p></div>
    <?php endif ?>
  </aside>
</div>
