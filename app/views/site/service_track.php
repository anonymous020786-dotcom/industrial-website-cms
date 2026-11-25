<?php /** @var array $lead @var array $updates */
$steps = array_keys(SERVICE_STATUSES);
$current = (string) ($lead['service_status'] ?: 'received');
$idx = array_search($current, $steps, true);
?>
<section class="bg-navy text-white">
  <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-safety">Service request</p>
    <h1 class="mt-2 font-display text-4xl font-bold uppercase tracking-tight sm:text-5xl"><?= e(service_ref($lead)) ?></h1>
    <p class="mt-2 text-white/75"><?= e((string) $lead['product']) ?> · <?= e((string) $lead['location']) ?> · received <?= e(date('j M Y, H:i', strtotime((string) $lead['created_at']))) ?></p>
    <p class="mt-5 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 font-semibold"><span class="h-2.5 w-2.5 rounded-full <?= in_array($current, ['resolved', 'closed'], true) ? 'bg-emerald-400' : 'bg-safety' ?>"></span> <?= e(SERVICE_STATUSES[$current][0]) ?></p>
    <?php if ($lead['priority'] === 'high'): ?><span class="ml-2 rounded-full bg-red-500/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-red-100">Urgent</span><?php endif ?>
  </div>
</section>
<section class="bg-white py-12">
  <div class="mx-auto max-w-4xl space-y-10 px-4 sm:px-6 lg:px-8">
    <ol class="grid gap-3 sm:grid-cols-6" aria-label="Progress">
      <?php foreach ($steps as $i => $s): $done = $idx !== false && $i <= $idx; ?>
      <li class="rounded-lg border p-3 text-sm <?= $done ? 'border-safety bg-safety/10 font-semibold text-navy' : 'border-black/10 text-charcoal/60' ?>"<?= $s === $current ? ' aria-current="step"' : '' ?>><?= e(SERVICE_STATUSES[$s][0]) ?></li>
      <?php endforeach ?>
    </ol>
    <div>
      <h2 class="font-display text-2xl font-bold uppercase text-navy">Updates</h2>
      <ul class="mt-4 space-y-4">
        <?php foreach ($updates as $u): ?>
        <li class="rounded-lg border border-black/10 p-4"><p class="text-xs font-semibold uppercase tracking-wider text-steel"><?= e(date('j M Y, H:i', strtotime((string) $u['created_at']))) ?></p><p class="mt-1 whitespace-pre-line text-charcoal/85"><?= e((string) $u['body']) ?></p></li>
        <?php endforeach ?>
        <li class="rounded-lg border border-black/10 p-4"><p class="text-xs font-semibold uppercase tracking-wider text-steel"><?= e(date('j M Y, H:i', strtotime((string) $lead['created_at']))) ?></p><p class="mt-1 text-charcoal/85">Request received. <?= e(SERVICE_STATUSES['received'][1]) ?></p></li>
      </ul>
    </div>
    <div class="flex flex-wrap gap-3">
      <?php if (company('phone')): ?><a class="btn btn-primary" href="tel:<?= e(company('phone_href') ?: preg_replace('~[^\d+]~', '', company('phone'))) ?>"><?= icon('phone', 'h-4 w-4') ?> Call the service team</a><?php endif ?>
      <a class="btn btn-outline" href="<?= e(url('service-request')) ?>">New request</a>
    </div>
  </div>
</section>
