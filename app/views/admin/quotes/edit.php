<?php /** @var ?array $q @var array $values @var array $errors @var ?array $lead @var bool $locked */
$v = fn($k, $d = '') => e((string) ($values[$k] ?? $d));
$err = fn($k) => isset($errors[$k]) ? '<p class="adm-error">' . e($errors[$k]) . '</p>' : '';
$items = $values['items'] ?: [['description' => '', 'part' => '', 'qty' => 1, 'unit' => 'pcs', 'price' => 0, 'discount' => 0]];
$dis = $locked ? ' disabled' : '';
$row = function ($i, $it) use ($dis) { ob_start(); ?>
  <tr data-quote-row>
    <td class="align-top"><input class="adm-input" name="items[<?= $i ?>][description]" value="<?= e((string) ($it['description'] ?? '')) ?>" placeholder="e.g. Double-acting hydraulic cylinder 80/45 × 500" aria-label="Description"<?= $dis ?>>
      <input class="adm-input mt-1 !py-1 text-xs" name="items[<?= $i ?>][part]" value="<?= e((string) ($it['part'] ?? '')) ?>" placeholder="Part / drawing no. (optional)" aria-label="Part number"<?= $dis ?>></td>
    <td class="align-top"><input class="adm-input text-right" type="number" step="any" min="0" name="items[<?= $i ?>][qty]" value="<?= e((string) ($it['qty'] ?? 1)) ?>" data-q="qty" aria-label="Quantity"<?= $dis ?>>
      <input class="adm-input mt-1 !py-1 text-xs" name="items[<?= $i ?>][unit]" value="<?= e((string) ($it['unit'] ?? 'pcs')) ?>" aria-label="Unit"<?= $dis ?>></td>
    <td class="align-top"><input class="adm-input text-right" type="number" step="0.01" min="0" name="items[<?= $i ?>][price]" value="<?= e((string) ($it['price'] ?? 0)) ?>" data-q="price" aria-label="Unit price"<?= $dis ?>></td>
    <td class="align-top"><input class="adm-input text-right" type="number" step="0.01" min="0" max="100" name="items[<?= $i ?>][discount]" value="<?= e((string) ($it['discount'] ?? 0)) ?>" data-q="discount" aria-label="Discount percent"<?= $dis ?>></td>
    <td class="whitespace-nowrap pt-3 text-right align-top font-semibold" data-q="amount">0.00</td>
    <td class="align-top"><?php if (!$dis): ?><button type="button" class="adm-icon-btn mt-1" data-quote-remove aria-label="Remove line"><?= icon('trash-2', 'h-4 w-4') ?></button><?php endif ?></td>
  </tr>
<?php return (string) ob_get_clean(); };
?>
<?php if ($q): ?>
<div class="adm-card mb-6 flex flex-wrap items-center gap-4 p-5">
  <div class="min-w-0 flex-1">
    <p class="flex flex-wrap items-center gap-2 text-lg font-bold text-navy"><?= e($q['number']) ?> <?= quote_badge($q) ?></p>
    <p class="text-sm text-charcoal/70">
      <?= $q['sent_at'] ? 'Sent ' . e(time_ago((string) $q['sent_at'])) . ' to ' . e((string) $q['email']) : 'Not sent yet' ?>
      <?= $q['viewed_at'] ? ' · opened by the customer ' . e(time_ago((string) $q['viewed_at'])) : '' ?>
      <?php if ($q['decided_at']): ?> · <?= e(ucfirst((string) $q['status'])) ?> by <strong><?= e((string) $q['decided_name']) ?></strong> <?= e(time_ago((string) $q['decided_at'])) ?><?php endif ?>
      <?= $q['lead_id'] ? ' · <a class="adm-link" href="' . e(url('admin/leads/' . $q['lead_id'])) . '">Lead #' . (int) $q['lead_id'] . '</a>' : '' ?>
    </p>
    <?php if ($q['status'] === 'declined' && $q['decline_reason']): ?><p class="mt-1 text-sm text-red-700">Reason: <?= e((string) $q['decline_reason']) ?></p><?php endif ?>
  </div>
  <div class="flex flex-wrap gap-2">
    <?php if (!$locked): ?>
    <details class="relative"><summary class="adm-btn adm-btn-primary cursor-pointer list-none"><?= icon('send', 'h-4 w-4') ?> <?= $q['sent_at'] ? 'Send again' : 'Email to customer' ?></summary>
      <form method="post" action="<?= e(url('admin/quotes/' . $q['id'] . '/send')) ?>" class="adm-card absolute right-0 z-10 mt-2 w-80 space-y-3 p-4"><?= csrf_field() ?>
        <p class="text-sm text-charcoal/75">Sends the PDF and a private link where <strong><?= e((string) ($q['email'] ?: 'the customer')) ?></strong> can accept online. Save your changes first.</p>
        <label class="adm-label" for="msg">Message (optional)</label><textarea id="msg" name="message" rows="4" class="adm-input" placeholder="Dear …, thank you for your enquiry…"></textarea>
        <button class="adm-btn adm-btn-primary w-full justify-center"><?= icon('send', 'h-4 w-4') ?> Send now</button></form></details>
    <?php endif ?>
    <form method="post" action="<?= e(url('admin/quotes/' . $q['id'] . '/duplicate')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn-light"><?= icon('copy', 'h-4 w-4') ?> Duplicate</button></form>
    <form method="post" action="<?= e(url('admin/quotes/' . $q['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn-danger" data-confirm="Delete quotation <?= e($q['number']) ?>? The customer's link will stop working."><?= icon('trash-2', 'h-4 w-4') ?></button></form>
  </div>
</div>
<?php endif ?>
<?php if ($locked): ?><div class="adm-flash adm-flash-info"><?= icon('lock', 'h-5 w-5 shrink-0') ?><span>This quotation was <?= e((string) $q['status']) ?> by the customer, so it is locked. Use <strong>Duplicate</strong> to prepare a revised version.</span></div><?php endif ?>

<form method="post" class="grid gap-6 xl:grid-cols-[1fr_22rem]" data-quote-form>
  <?= csrf_field() ?>
  <?php if (!$q && $lead): ?><input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>"><?php endif ?>
  <div class="space-y-6">
    <section class="adm-card p-6">
      <h2 class="adm-card-title mb-4"><?= icon('building-2', 'h-5 w-5 text-steel') ?> Customer<?= $lead ? ' <span class="text-sm font-normal text-charcoal/60">— from lead #' . (int) $lead['id'] . '</span>' : '' ?></h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="adm-field<?= isset($errors['customer_name']) ? ' has-error' : '' ?>"><label class="adm-label" for="customer_name">Contact name *</label><input id="customer_name" name="customer_name" class="adm-input" value="<?= $v('customer_name') ?>" required<?= $dis ?>><?= $err('customer_name') ?></div>
        <div class="adm-field"><label class="adm-label" for="company">Company</label><input id="company" name="company" class="adm-input" value="<?= $v('company') ?>"<?= $dis ?>></div>
        <div class="adm-field<?= isset($errors['email']) ? ' has-error' : '' ?>"><label class="adm-label" for="email">Email</label><input id="email" type="email" name="email" class="adm-input" value="<?= $v('email') ?>"<?= $dis ?>><?= $err('email') ?></div>
        <div class="adm-field"><label class="adm-label" for="phone">Phone</label><input id="phone" name="phone" class="adm-input" value="<?= $v('phone') ?>"<?= $dis ?>></div>
        <div class="adm-field"><label class="adm-label" for="address">Billing / delivery address</label><textarea id="address" name="address" rows="3" class="adm-input"<?= $dis ?>><?= $v('address') ?></textarea></div>
        <div class="adm-field"><label class="adm-label" for="customer_tax_id">Customer GSTIN / tax number</label><input id="customer_tax_id" name="customer_tax_id" class="adm-input" value="<?= $v('customer_tax_id') ?>"<?= $dis ?>></div>
      </div>
    </section>

    <section class="adm-card p-6">
      <h2 class="adm-card-title mb-4"><?= icon('list', 'h-5 w-5 text-steel') ?> Items</h2>
      <?= $err('items') ?>
      <div class="overflow-x-auto"><table class="w-full min-w-[640px] border-separate border-spacing-x-1 border-spacing-y-2 text-sm">
        <thead><tr class="text-left text-xs font-bold uppercase tracking-wider text-charcoal/70"><th>Description</th><th class="w-24">Qty / unit</th><th class="w-32">Unit price</th><th class="w-20">Disc. %</th><th class="w-28 text-right">Amount</th><th class="w-8"></th></tr></thead>
        <tbody data-quote-items><?php foreach (array_values($items) as $i => $it) echo $row($i, $it); ?></tbody>
      </table></div>
      <?php if (!$locked): ?>
      <template data-quote-template><?= $row('__i__', ['description' => '', 'part' => '', 'qty' => 1, 'unit' => 'pcs', 'price' => '', 'discount' => 0]) ?></template>
      <button type="button" class="adm-btn adm-btn-light adm-btn-sm mt-2" data-quote-add><?= icon('plus', 'h-4 w-4') ?> Add line</button>
      <?php endif ?>
    </section>

    <section class="adm-card grid gap-4 p-6 sm:grid-cols-2">
      <div class="adm-field"><label class="adm-label" for="delivery">Delivery</label><input id="delivery" name="delivery" class="adm-input" value="<?= $v('delivery') ?>" placeholder="e.g. 3–4 weeks from purchase order, ex-works"<?= $dis ?>></div>
      <div class="adm-field"><label class="adm-label" for="payment_terms">Payment terms</label><input id="payment_terms" name="payment_terms" class="adm-input" value="<?= $v('payment_terms') ?>" placeholder="e.g. 50% advance, balance before dispatch"<?= $dis ?>></div>
      <div class="adm-field sm:col-span-2"><label class="adm-label" for="notes">Notes for the customer</label><textarea id="notes" name="notes" rows="2" class="adm-input"<?= $dis ?>><?= $v('notes') ?></textarea></div>
      <div class="adm-field sm:col-span-2"><label class="adm-label" for="terms">Terms &amp; conditions</label><textarea id="terms" name="terms" rows="4" class="adm-input"<?= $dis ?>><?= $v('terms') ?></textarea><p class="adm-help">Defaults come from Settings → Quotes.</p></div>
    </section>
  </div>

  <aside class="adm-aside space-y-6">
    <section class="adm-card space-y-4 p-6">
      <h2 class="adm-card-title"><?= icon('calculator', 'h-5 w-5 text-steel') ?> Totals</h2>
      <div class="grid grid-cols-2 gap-3">
        <div class="adm-field"><label class="adm-label" for="currency">Currency</label><select id="currency" name="currency" class="adm-input" data-q="currency"<?= $dis ?>><?php foreach (QUOTE_CURRENCIES as $c => $sym): ?><option value="<?= $c ?>"<?= ($values['currency'] ?? 'INR') === $c ? ' selected' : '' ?>><?= $c ?></option><?php endforeach ?></select></div>
        <div class="adm-field"><label class="adm-label" for="valid_until">Valid until</label><input id="valid_until" type="date" name="valid_until" class="adm-input" value="<?= $v('valid_until') ?>"<?= $dis ?>></div>
        <div class="adm-field"><label class="adm-label" for="tax_label">Tax name</label><input id="tax_label" name="tax_label" class="adm-input" value="<?= $v('tax_label', 'GST') ?>"<?= $dis ?>></div>
        <div class="adm-field"><label class="adm-label" for="tax_rate">Tax %</label><input id="tax_rate" type="number" step="0.01" min="0" max="100" name="tax_rate" class="adm-input text-right" value="<?= $v('tax_rate', '18') ?>" data-q="tax_rate"<?= $dis ?>></div>
      </div>
      <dl class="space-y-2 border-t border-black/10 pt-4 text-sm">
        <div class="flex justify-between"><dt class="text-charcoal/70">Subtotal</dt><dd class="font-semibold" data-q="subtotal">0.00</dd></div>
        <div class="flex justify-between"><dt class="text-charcoal/70">Tax</dt><dd class="font-semibold" data-q="tax">0.00</dd></div>
        <div class="flex justify-between rounded-lg bg-navy px-3 py-2 text-white"><dt class="font-semibold">Total</dt><dd class="text-lg font-bold text-safety" data-q="total">0.00</dd></div>
      </dl>
      <?php if (!$locked): ?>
      <button class="adm-btn adm-btn-primary w-full justify-center"><?= icon('save', 'h-4 w-4') ?> Save quotation</button>
      <?php if (!$q): ?><p class="text-xs text-charcoal/65">Save first — then you can download the PDF and email it to the customer.</p><?php endif ?>
      <?php endif ?>
    </section>
  </aside>
</form>
