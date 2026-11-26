<?php /** @var array $a @var ?array $type @var array $slots */
[$sl, $sc] = APPOINTMENT_STATUSES[$a['status']] ?? [$a['status'], ''];
$active = in_array($a['status'], ['pending', 'confirmed'], true);
$btn = fn(string $act, string $label, string $icon, string $cls = 'adm-btn-light', string $confirm = '') => '<form method="post" action="' . e(url('admin/appointments/' . $a['id'] . '/action')) . '">' . csrf_field()
    . '<input type="hidden" name="action" value="' . $act . '"><button class="adm-btn ' . $cls . ' adm-btn-sm"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . icon($icon, 'h-4 w-4') . ' ' . e($label) . '</button></form>';
?>
<div class="grid gap-6 xl:grid-cols-3">
  <div class="space-y-6 xl:col-span-2">
    <div class="adm-card p-6">
      <div class="flex flex-wrap items-center gap-3"><span class="adm-badge <?= $sc ?>" data-appointment-status="<?= e($a['status']) ?>"><?= e($sl) ?></span><span class="text-sm text-charcoal/60"><?= e(appointment_ref($a)) ?> · booked <?= e(time_ago($a['created_at'])) ?></span></div>
      <h2 class="mt-3 text-2xl font-bold text-navy"><?= e((string) $a['type_name']) ?> — <?= e(appointment_when($a)) ?></h2>
      <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
        <div><dt class="text-charcoal/55">Customer</dt><dd class="font-semibold"><?= e($a['name']) ?><?= $a['company'] ? ' · ' . e($a['company']) : '' ?></dd></div>
        <div><dt class="text-charcoal/55">How</dt><dd><?= e(APPOINTMENT_MODES[$a['mode']][0] ?? '') ?></dd></div>
        <div><dt class="text-charcoal/55">Email</dt><dd><a class="adm-link" href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></dd></div>
        <div><dt class="text-charcoal/55">Phone</dt><dd><a class="adm-link" href="tel:<?= e(preg_replace('~[^\d+]~', '', (string) $a['phone'])) ?>"><?= e((string) $a['phone']) ?></a></dd></div>
        <?php if ($a['location']): ?><div class="sm:col-span-2"><dt class="text-charcoal/55">Site</dt><dd><?= e($a['location']) ?> · <a class="adm-link" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode((string) $a['location']) ?>">Map</a></dd></div><?php endif ?>
        <?php if ($a['notes']): ?><div class="sm:col-span-2"><dt class="text-charcoal/55">Customer notes</dt><dd class="whitespace-pre-line"><?= e($a['notes']) ?></dd></div><?php endif ?>
        <?php if ($a['cancel_reason']): ?><div class="sm:col-span-2"><dt class="text-charcoal/55">Cancellation</dt><dd><?= e($a['cancel_reason']) ?></dd></div><?php endif ?>
      </dl>
      <div class="mt-6 flex flex-wrap gap-2">
        <?php if ($a['status'] === 'pending'): ?><?= $btn('confirm', 'Confirm & send invite', 'calendar-check', 'adm-btn-primary') ?><?php endif ?>
        <?php if ($active && strtotime($a['starts_at']) <= time() + 86400): ?><?= $btn('complete', 'Completed', 'check') ?><?= $btn('no_show', 'No-show', 'user-x') ?><?php endif ?>
        <?php if ($a['lead_id']): ?><a class="adm-btn adm-btn-light adm-btn-sm" href="<?= e(url('admin/leads/' . $a['lead_id'])) ?>"><?= icon('inbox', 'h-4 w-4') ?> Lead</a><?php endif ?>
        <a class="adm-btn adm-btn-light adm-btn-sm" href="<?= e(url('book/manage/' . $a['token'] . '/invite.ics')) ?>"><?= icon('calendar-plus', 'h-4 w-4') ?> .ics</a>
      </div>
    </div>

    <?php if ($active && $slots): ?>
    <div class="adm-card p-6" data-admin-reschedule>
      <h2 class="adm-card-title"><?= icon('calendar-clock', 'h-5 w-5 text-steel') ?> Move to another time</h2>
      <form method="get" class="mt-3 flex items-center gap-2"><input type="date" name="date" value="<?= e($slots['date']) ?>" class="adm-input !w-auto" min="<?= date('Y-m-d') ?>"><button class="adm-btn adm-btn-light adm-btn-sm">Show free times</button></form>
      <div class="mt-4 flex flex-wrap gap-2">
        <?php foreach ($slots['times'] as $t): ?>
        <form method="post" action="<?= e(url('admin/appointments/' . $a['id'] . '/action')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="start" value="<?= (int) $t ?>">
          <button class="adm-btn adm-btn-light adm-btn-sm" data-confirm="Move to <?= e(date('D j M, H:i', $t)) ?> and email the customer?"><?= e(date('H:i', $t)) ?></button></form>
        <?php endforeach ?>
        <?php if (!$slots['times']): ?><p class="text-sm text-charcoal/60">No free times that day (working hours, holidays and other bookings are respected).</p><?php endif ?>
      </div>
    </div>
    <?php endif ?>
  </div>

  <aside class="space-y-6">
    <form method="post" action="<?= e(url('admin/appointments/' . $a['id'] . '/action')) ?>" class="adm-card space-y-3 p-6">
      <?= csrf_field() ?><input type="hidden" name="action" value="notes">
      <label class="adm-card-title" for="staff_notes"><?= icon('sticky-note', 'h-5 w-5 text-steel') ?> Internal notes</label>
      <textarea id="staff_notes" name="staff_notes" rows="5" class="adm-input" placeholder="Who goes, tools to bring, access instructions…"><?= e((string) $a['staff_notes']) ?></textarea>
      <button class="adm-btn adm-btn-light adm-btn-sm"><?= icon('save', 'h-4 w-4') ?> Save</button>
    </form>
    <?php if ($active): ?>
    <form method="post" action="<?= e(url('admin/appointments/' . $a['id'] . '/action')) ?>" class="adm-card space-y-3 p-6">
      <?= csrf_field() ?><input type="hidden" name="action" value="cancel">
      <h2 class="adm-card-title"><?= icon('calendar-x', 'h-5 w-5 text-red-600') ?> Cancel</h2>
      <input name="reason" class="adm-input" placeholder="Reason (shown to the customer)">
      <label class="adm-check"><input type="checkbox" name="notify" value="1" checked><span>Email the customer</span></label>
      <button class="adm-btn adm-btn-light adm-btn-sm text-red-600" data-confirm="Cancel this appointment?">Cancel appointment</button>
    </form>
    <?php endif ?>
  </aside>
</div>
