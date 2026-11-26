<?php /** @var array $a @var ?array $type */
[$sl] = APPOINTMENT_STATUSES[$a['status']] ?? [ucfirst($a['status'])];
$active = in_array($a['status'], ['pending', 'confirmed'], true) && strtotime($a['starts_at']) > time();
?>
<section class="section bg-mist">
  <div class="mx-auto max-w-3xl px-4 sm:px-6" data-booking-manage>
    <?php if (query('booked')): ?><div class="comment-notice is-ok mb-6" role="status" data-booked><?= icon('circle-check', 'h-5 w-5') ?> <?= $a['status'] === 'confirmed' ? 'Booked! A confirmation with a calendar invite is on its way to ' . e($a['email']) . '.' : 'Request received — we will confirm it by email shortly.' ?></div><?php endif ?>
    <?php if (query('changed')): ?><div class="comment-notice is-ok mb-6" role="status"><?= icon('circle-check', 'h-5 w-5') ?> New time saved. We sent an updated calendar invite.</div><?php endif ?>
    <?php if (query('cancelled')): ?><div class="comment-notice mb-6" role="status"><?= icon('circle-x', 'h-5 w-5') ?> Your appointment is cancelled.</div><?php endif ?>
    <div class="bg-white p-8">
      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-charcoal/55"><?= e(appointment_ref($a)) ?> · <span data-booking-status="<?= e($a['status']) ?>"><?= e($sl) ?></span></p>
      <h1 class="mt-2 font-display text-4xl font-bold uppercase tracking-tight text-navy"><?= e((string) ($type['name'] ?? 'Appointment')) ?></h1>
      <dl class="mt-6 grid gap-4 sm:grid-cols-2">
        <div><dt class="text-sm text-charcoal/55">When</dt><dd class="font-semibold text-navy"><?= e(appointment_when($a)) ?></dd></div>
        <div><dt class="text-sm text-charcoal/55">How</dt><dd class="font-semibold text-navy"><?= e(APPOINTMENT_MODES[$a['mode']][0] ?? '') ?></dd></div>
        <?php if ($a['mode'] === 'onsite' && $a['location']): ?><div class="sm:col-span-2"><dt class="text-sm text-charcoal/55">Site</dt><dd><?= e($a['location']) ?></dd></div><?php endif ?>
        <?php if ($a['mode'] === 'factory'): ?><div class="sm:col-span-2"><dt class="text-sm text-charcoal/55">Our address</dt><dd class="whitespace-pre-line"><?= e(company('address')) ?></dd></div><?php endif ?>
        <?php if (!empty($type['location_note'])): ?><div class="sm:col-span-2"><dt class="text-sm text-charcoal/55">Details</dt><dd><?= e($type['location_note']) ?></dd></div><?php endif ?>
      </dl>
      <?php if ($a['status'] === 'cancelled' && $a['cancel_reason']): ?><p class="mt-4 text-sm text-charcoal/70">Reason: <?= e($a['cancel_reason']) ?></p><?php endif ?>
      <div class="mt-8 flex flex-wrap gap-3">
        <a class="btn btn-outline" href="<?= e(url('book/manage/' . $a['token'] . '/invite.ics')) ?>"><?= icon('calendar-plus', 'h-4 w-4') ?> Add to calendar</a>
        <?php if ($active): ?><a class="btn btn-outline" href="<?= e(url('book') . '?reschedule=' . $a['token']) ?>"><?= icon('calendar-clock', 'h-4 w-4') ?> Reschedule</a><?php endif ?>
      </div>
      <?php if ($active): ?>
      <form method="post" class="mt-8 border-t border-black/10 pt-6">
        <label class="text-sm font-semibold text-navy" for="cancel-reason">Need to cancel?</label>
        <input id="cancel-reason" name="reason" maxlength="500" class="mt-2 w-full border border-black/15 px-3 py-2" placeholder="Reason (optional)">
        <button class="btn btn-outline mt-3" data-confirm-cancel><?= icon('circle-x', 'h-4 w-4') ?> Cancel appointment</button>
      </form>
      <?php endif ?>
    </div>
    <p class="mt-6 text-center text-sm text-charcoal/60">Questions? Call <?= e(company('phone')) ?> or reply to the confirmation email. Keep this page private — anyone with the link can change the booking.</p>
  </div>
</section>
