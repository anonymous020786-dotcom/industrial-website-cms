<?php /** @var array $q @var array $items @var ?string $error @var string $done */
$status = quote_status($q);
$num = fn($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
?>
<section class="bg-navy text-white">
  <div class="mx-auto flex max-w-5xl flex-wrap items-end justify-between gap-6 px-4 py-12 sm:px-6 lg:px-8">
    <div>
      <p class="text-xs font-semibold uppercase tracking-[0.2em] text-safety">Quotation</p>
      <h1 class="mt-2 font-display text-4xl font-bold uppercase tracking-tight sm:text-5xl"><?= e($q['number']) ?></h1>
      <p class="mt-2 text-white/75">For <?= e($q['customer_name']) ?><?= $q['company'] ? ', ' . e($q['company']) : '' ?> · <?= e(date('j M Y', strtotime((string) ($q['sent_at'] ?: $q['created_at'])))) ?></p>
    </div>
    <div class="text-right">
      <p class="text-sm text-white/70">Total<?= (float) $q['tax_rate'] > 0 ? ' incl. ' . e($q['tax_label']) : '' ?></p>
      <p class="font-display text-4xl font-bold text-safety"><?= e(money((float) $q['total'], $q['currency'])) ?></p>
      <?php if ($q['valid_until']): ?><p class="text-sm text-white/70">Valid until <?= e(date('j M Y', strtotime((string) $q['valid_until']))) ?></p><?php endif ?>
    </div>
  </div>
</section>

<section class="bg-white py-12">
  <div class="mx-auto max-w-5xl space-y-8 px-4 sm:px-6 lg:px-8">
    <?php if ($error && $status !== 'sent'): ?><p class="rounded-lg bg-red-50 p-4 text-sm font-semibold text-red-800" role="alert"><?= e($error) ?></p><?php endif ?>
    <?php if ($done === 'accepted' || $status === 'accepted'): ?>
      <div class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-5 text-emerald-900" role="status"><?= icon('circle-check', 'h-6 w-6 shrink-0') ?>
        <div><p class="font-bold">Thank you — quotation accepted<?= $q['decided_name'] ? ' by ' . e($q['decided_name']) : '' ?>.</p><p class="text-sm">Our team has been notified and will contact you shortly to confirm the order and delivery.</p></div></div>
    <?php elseif ($done === 'declined' || $status === 'declined'): ?>
      <div class="flex items-start gap-3 rounded-lg border border-black/10 bg-mist p-5" role="status"><?= icon('info', 'h-6 w-6 shrink-0 text-steel') ?>
        <div><p class="font-bold text-navy">You declined this quotation.</p><p class="text-sm text-charcoal/80">Thank you for letting us know. If you'd like a revised offer, just reply to our email or <a class="font-semibold text-steel underline" href="<?= e(url('contact')) ?>">contact us</a>.</p></div></div>
    <?php elseif ($status === 'expired'): ?>
      <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-900" role="status"><p class="font-bold">This quotation has expired.</p><p class="text-sm">Prices and lead times may have changed — <a class="font-semibold underline" href="<?= e(url('contact')) ?>">contact us</a> for an updated quotation.</p></div>
    <?php endif ?>

    <div class="overflow-x-auto rounded-lg border border-black/10">
      <table class="w-full min-w-[620px] text-sm">
        <thead class="bg-mist text-left text-xs font-bold uppercase tracking-wider text-navy"><tr><th class="p-3">#</th><th class="p-3">Description</th><th class="p-3 text-right">Qty</th><th class="p-3 text-right">Unit price</th><th class="p-3 text-right">Disc.</th><th class="p-3 text-right">Amount</th></tr></thead>
        <tbody class="divide-y divide-black/5">
        <?php foreach ($items as $i => $it): ?>
          <tr><td class="p-3 align-top text-charcoal/70"><?= $i + 1 ?></td>
            <td class="p-3 align-top"><span class="font-semibold text-navy"><?= e((string) $it['description']) ?></span><?= $it['part'] ? '<span class="block text-xs text-charcoal/65">Part no. ' . e((string) $it['part']) . '</span>' : '' ?></td>
            <td class="whitespace-nowrap p-3 text-right align-top"><?= e($num($it['qty'])) ?> <?= e((string) $it['unit']) ?></td>
            <td class="whitespace-nowrap p-3 text-right align-top"><?= e(number_format((float) $it['price'], 2)) ?></td>
            <td class="p-3 text-right align-top"><?= (float) $it['discount'] > 0 ? e($num($it['discount'])) . '%' : '—' ?></td>
            <td class="whitespace-nowrap p-3 text-right align-top font-semibold"><?= e(number_format((float) $it['amount'], 2)) ?></td></tr>
        <?php endforeach ?>
        </tbody>
        <tfoot class="text-sm">
          <tr><td colspan="5" class="p-3 text-right text-charcoal/70">Subtotal</td><td class="p-3 text-right font-semibold"><?= e(money((float) $q['subtotal'], $q['currency'])) ?></td></tr>
          <?php if ((float) $q['tax_rate'] > 0): ?><tr><td colspan="5" class="p-3 text-right text-charcoal/70"><?= e($q['tax_label']) ?> <?= e($num($q['tax_rate'])) ?>%</td><td class="p-3 text-right font-semibold"><?= e(money((float) $q['tax'], $q['currency'])) ?></td></tr><?php endif ?>
          <tr class="bg-navy text-white"><td colspan="5" class="p-3 text-right font-bold">Total</td><td class="p-3 text-right text-lg font-bold text-safety"><?= e(money((float) $q['total'], $q['currency'])) ?></td></tr>
        </tfoot>
      </table>
    </div>

    <dl class="grid gap-6 sm:grid-cols-2">
      <?php foreach (['Delivery' => $q['delivery'], 'Payment terms' => $q['payment_terms'], 'Notes' => $q['notes'], 'Terms & conditions' => $q['terms']] as $l => $t): if (trim((string) $t) === '') continue; ?>
      <div<?= $l === 'Terms & conditions' ? ' class="sm:col-span-2"' : '' ?>><dt class="text-xs font-bold uppercase tracking-wider text-steel"><?= e($l) ?></dt><dd class="mt-1 whitespace-pre-line text-sm text-charcoal/85"><?= e((string) $t) ?></dd></div>
      <?php endforeach ?>
    </dl>

    <div class="flex flex-wrap gap-3">
      <a href="<?= e(url('q/' . $q['token'] . '/pdf')) ?>" class="btn btn-outline"><?= icon('file-down', 'h-4 w-4') ?> Download PDF</a>
      <a href="<?= e(url('contact')) ?>" class="btn btn-outline"><?= icon('message-circle', 'h-4 w-4') ?> Ask a question</a>
    </div>

    <?php if ($status === 'sent' && !$done): ?>
    <div class="grid gap-6 lg:grid-cols-2" id="decide">
      <form method="post" class="space-y-4 rounded-xl border-2 border-safety p-6">
        <h2 class="font-display text-2xl font-bold uppercase text-navy">Accept this quotation</h2>
        <?php if ($error): ?><p class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert"><?= e($error) ?></p><?php endif ?>
        <input type="hidden" name="decision" value="accept">
        <div class="field"><label for="name">Your full name</label><input id="name" name="name" autocomplete="name" required></div>
        <label class="flex items-start gap-2 text-sm text-charcoal/85"><input type="checkbox" name="agree" value="1" required class="mt-1"> I accept quotation <?= e($q['number']) ?> for <?= e(money((float) $q['total'], $q['currency'])) ?> and its terms on behalf of <?= e((string) ($q['company'] ?: $q['customer_name'])) ?>.</label>
        <button class="btn btn-primary w-full justify-center"><?= icon('check', 'h-4 w-4') ?> Accept quotation</button>
        <p class="text-xs text-charcoal/65">We record your name, the time and your IP address as confirmation.</p>
      </form>
      <form method="post" class="space-y-4 rounded-xl border border-black/10 p-6">
        <h2 class="font-display text-2xl font-bold uppercase text-navy">Not right for you?</h2>
        <input type="hidden" name="decision" value="decline">
        <div class="field"><label for="dname">Your name (optional)</label><input id="dname" name="name" autocomplete="name"></div>
        <div class="field"><label for="reason">What should we change? (optional)</label><textarea id="reason" name="reason" rows="3" placeholder="Price, lead time, specification…"></textarea></div>
        <button class="btn btn-outline w-full justify-center">Decline quotation</button>
      </form>
    </div>
    <?php endif ?>
  </div>
</section>
