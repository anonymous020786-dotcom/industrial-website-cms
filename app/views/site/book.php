<?php /** @var array $meta @var array $types @var ?array $type @var array $days @var ?string $date @var array $slots @var ?int $slot @var ?array $resched */
$q = fn(array $p) => url('book') . '?' . http_build_query(array_filter($p + ['reschedule' => $resched['token'] ?? null], fn($v) => $v !== null && $v !== ''));
$step = !$type ? 1 : (!$slot ? 2 : 3);
?>
<?= page_hero(['eyebrow' => $resched ? 'Reschedule ' . appointment_ref($resched) : 'Book an appointment', 'title' => $resched ? 'Choose a new time' : 'Talk to an engineer', 'lead' => $resched ? 'Your current time: ' . appointment_when($resched) . '. Pick a new slot — we will send an updated calendar invite.' : 'Book a site inspection, a factory visit or a video call. Pick a free time — you get a confirmation and a calendar invite at once.', 'crumbs' => $meta['crumbs']]) ?>

<section class="section bg-mist !pt-12">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <ol class="booking-steps" aria-label="Steps">
      <?php foreach (['What', 'When', 'Your details'] as $i => $label): ?><li class="<?= $step === $i + 1 ? 'is-current' : ($step > $i + 1 ? 'is-done' : '') ?>"><span><?= $i + 1 ?></span><?= $label ?></li><?php endforeach ?>
    </ol>

    <?php if (!$types): ?>
    <p class="mt-8 bg-white p-8 text-charcoal/75">Online booking is being set up. Please <a class="font-semibold text-steel underline" href="<?= e(url('contact')) ?>">contact us</a> to arrange a visit.</p>
    <?php elseif ($step === 1): ?>
    <div class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3" data-booking-types>
      <?php foreach ($types as $t): [$ml, $mi] = APPOINTMENT_MODES[$t['mode']] ?? ['', 'calendar']; ?>
      <a href="<?= e($q(['type' => $t['slug']])) ?>" class="group flex flex-col bg-white p-7 transition hover:-translate-y-1 hover:shadow-lg<?= $type && $type['id'] === $t['id'] ? ' ring-2 ring-safety' : '' ?>" data-booking-type="<?= e($t['slug']) ?>">
        <span class="grid h-12 w-12 place-items-center bg-navy text-safety"><?= icon($mi, 'h-6 w-6') ?></span>
        <span class="mt-5 font-display text-2xl font-bold uppercase tracking-wide text-navy group-hover:text-steel"><?= e($t['name']) ?></span>
        <span class="mt-1 text-sm font-semibold text-charcoal/60"><?= (int) $t['duration_min'] ?> min · <?= e($ml) ?></span>
        <?php if ($t['description']): ?><span class="mt-3 flex-1 text-charcoal/75"><?= e($t['description']) ?></span><?php endif ?>
        <span class="mt-5 inline-flex items-center gap-2 font-semibold text-steel">Choose a time <?= icon('arrow-right', 'h-4 w-4') ?></span>
      </a>
      <?php endforeach ?>
    </div>
    <?php elseif ($step === 2): ?>
    <div class="mt-8 grid gap-8 lg:grid-cols-12">
      <div class="bg-white p-6 lg:col-span-5">
        <div class="flex items-center justify-between gap-3"><h2 class="font-display text-2xl font-bold uppercase text-navy"><?= e($type['name']) ?></h2><?php if (!$resched): ?><a class="text-sm font-semibold text-steel underline" href="<?= e(url('book')) ?>">Change</a><?php endif ?></div>
        <p class="mt-1 text-sm text-charcoal/60"><?= (int) $type['duration_min'] ?> minutes · <?= e(APPOINTMENT_MODES[$type['mode']][0] ?? '') ?> · times in <?= e(date_default_timezone_get()) ?></p>
        <?php if ($days): ?>
        <div class="booking-days mt-5" data-booking-days>
          <?php foreach ($days as $d => $n): ?>
          <a href="<?= e($q(['type' => $type['slug'], 'date' => $d])) ?>" class="booking-day<?= $d === $date ? ' is-active' : '' ?>"<?= $d === $date ? ' aria-current="date"' : '' ?>>
            <span class="text-xs uppercase"><?= e(date('D', strtotime($d))) ?></span><strong><?= e(date('j', strtotime($d))) ?></strong><span class="text-xs"><?= e(date('M', strtotime($d))) ?></span></a>
          <?php endforeach ?>
        </div>
        <?php else: ?><p class="mt-5 text-charcoal/75">No free times in the coming weeks. Please <a class="font-semibold text-steel underline" href="<?= e(url('contact')) ?>">contact us</a>.</p><?php endif ?>
      </div>
      <div class="bg-white p-6 lg:col-span-7">
        <?php if ($date): ?>
        <h3 class="font-semibold text-navy"><?= e(date('l j F Y', strtotime($date))) ?></h3>
        <?php if ($slots): ?>
        <div class="booking-slots mt-4" data-booking-slots>
          <?php foreach ($slots as $s): ?><a class="booking-slot" href="<?= e($q(['type' => $type['slug'], 'date' => $date, 'time' => date('Hi', $s)])) ?>#details"><?= e(date('H:i', $s)) ?></a><?php endforeach ?>
        </div>
        <?php else: ?><p class="mt-3 text-charcoal/70">No free times on this day — choose another day.</p><?php endif ?>
        <?php endif ?>
      </div>
    </div>
    <?php else: ?>
    <div class="mt-8 grid gap-8 lg:grid-cols-12" id="details">
      <aside class="h-fit bg-navy p-7 text-white lg:col-span-4">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-safety">Your appointment</p>
        <p class="mt-3 font-display text-2xl font-bold uppercase"><?= e($type['name']) ?></p>
        <p class="mt-3 flex items-center gap-2 text-white/85"><?= icon('calendar', 'h-4 w-4 text-safety') ?><?= e(date('l j F Y', $slot)) ?></p>
        <p class="mt-1 flex items-center gap-2 text-white/85"><?= icon('clock', 'h-4 w-4 text-safety') ?><?= e(date('H:i', $slot)) ?>–<?= e(date('H:i', $slot + (int) $type['duration_min'] * 60)) ?></p>
        <p class="mt-1 flex items-center gap-2 text-white/85"><?= icon(APPOINTMENT_MODES[$type['mode']][1] ?? 'map-pin', 'h-4 w-4 text-safety') ?><?= e(APPOINTMENT_MODES[$type['mode']][0] ?? '') ?></p>
        <a class="mt-6 inline-block text-sm font-semibold text-white underline" href="<?= e($q(['type' => $type['slug'], 'date' => date('Y-m-d', $slot)])) ?>">Change the time</a>
      </aside>
      <form method="post" action="<?= e(url('book')) ?>" class="grid gap-5 bg-white p-7 sm:grid-cols-2 lg:col-span-8" novalidate data-booking-form>
        <input type="hidden" name="type_id" value="<?= (int) $type['id'] ?>"><input type="hidden" name="start" value="<?= (int) $slot ?>">
        <?php if ($resched): ?>
        <input type="hidden" name="reschedule" value="<?= e($resched['token']) ?>">
        <p class="sm:col-span-2 text-charcoal/80">Move <strong><?= e(appointment_ref($resched)) ?></strong> for <?= e($resched['name']) ?> to this time?</p>
        <div class="sm:col-span-2"><button class="btn btn-primary btn-lg"><?= icon('calendar-check', 'h-5 w-5') ?> Confirm new time</button></div>
        <?php else: ?>
        <?= form_hidden('booking') ?>
        <?= form_field(['id' => 'fullName', 'label' => 'Full Name', 'required' => true, 'autocomplete' => 'name']) ?>
        <?= form_field(['id' => 'company', 'label' => 'Company', 'autocomplete' => 'organization']) ?>
        <?= form_field(['id' => 'email', 'label' => 'Email (for the calendar invite)', 'type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
        <?= form_field(['id' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel']) ?>
        <?php if ($type['mode'] === 'onsite'): ?><?= form_field(['id' => 'location', 'label' => 'Site address', 'required' => true, 'full' => true, 'autocomplete' => 'street-address', 'placeholder' => 'Plant name, street, city']) ?><?php endif ?>
        <?= form_field(['id' => 'notes', 'label' => 'What should we prepare? (optional)', 'type' => 'textarea', 'full' => true, 'rows' => 4, 'placeholder' => 'Equipment, the problem, drawings you will bring, safety requirements on site…']) ?>
        <div class="field sm:col-span-2"><label class="consent"><input type="checkbox" name="consent" value="1" required><span>I agree that <?= e(company('name')) ?> may use these details to arrange this appointment (<a class="font-semibold text-steel underline" href="<?= e(url('privacy-policy')) ?>">Privacy Policy</a>). <span class="req" aria-hidden="true">*</span></span></label></div>
        <?= turnstile_widget('sm:col-span-2') ?>
        <div class="sm:col-span-2"><button class="btn btn-primary btn-lg"><?= icon('calendar-check', 'h-5 w-5') ?> <?= setting('booking_auto_confirm', true) ? 'Book this time' : 'Request this time' ?></button></div>
        <?php endif ?>
      </form>
    </div>
    <?php endif ?>
  </div>
</section>
