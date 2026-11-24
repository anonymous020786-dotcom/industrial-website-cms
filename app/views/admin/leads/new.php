<?php /** @var array $v @var array $errors @var array $users */
$f = function ($k, $label, $type = 'text', $req = false) use ($v, $errors) {
    return '<div class="adm-field' . (isset($errors[$k]) ? ' has-error' : '') . '"><label class="adm-label" for="n_' . $k . '">' . e($label) . ($req ? ' <span class="text-safety">*</span>' : '') . '</label>'
        . '<input id="n_' . $k . '" name="' . $k . '" type="' . $type . '" class="adm-input" value="' . e($v[$k] ?? '') . '"' . ($req ? ' required' : '') . '>'
        . (isset($errors[$k]) ? '<p class="adm-error">' . e($errors[$k]) . '</p>' : '') . '</div>';
};
?>
<form method="post" class="adm-card max-w-3xl p-6">
  <?= csrf_field() ?>
  <p class="mb-5 text-sm text-charcoal/60">Record enquiries received by phone, email, trade shows or walk-ins so every lead is tracked in one pipeline.</p>
  <div class="grid gap-4 sm:grid-cols-2">
    <?= $f('name', 'Contact name', 'text', true) ?>
    <?= $f('company', 'Company') ?>
    <?= $f('email', 'Email', 'email') ?>
    <?= $f('phone', 'Phone', 'tel') ?>
    <div class="adm-field"><label class="adm-label" for="n_product">Product / service</label><input id="n_product" name="product" class="adm-input" list="product-list" value="<?= e($v['product'] ?? '') ?>"><datalist id="product-list"><?php foreach (products_all() as $p): ?><option value="<?= e($p['name']) ?>"><?php endforeach ?></datalist></div>
    <div class="adm-field"><label class="adm-label" for="n_source">Source</label><select id="n_source" name="source" class="adm-input"><?php foreach (['Phone call', 'Email', 'WhatsApp', 'Trade show', 'Referral', 'Walk-in', 'Existing customer', 'Other'] as $s): ?><option<?= ($v['source'] ?? '') === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach ?></select></div>
    <div class="adm-field"><label class="adm-label" for="n_status">Status</label><select id="n_status" name="status" class="adm-input"><?php foreach (LEAD_STATUSES as $k => [$l]): ?><option value="<?= $k ?>"<?= ($v['status'] ?? 'new') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></div>
    <div class="adm-field"><label class="adm-label" for="n_priority">Priority</label><select id="n_priority" name="priority" class="adm-input"><?php foreach (['normal' => 'Normal', 'high' => 'High', 'low' => 'Low'] as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach ?></select></div>
    <div class="adm-field"><label class="adm-label" for="n_assigned">Owner</label><select id="n_assigned" name="assigned_to" class="adm-input"><?php foreach ($users as $id => $n): ?><option value="<?= (int) $id ?>"<?= (int) $id === (int) current_user()['id'] ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach ?></select></div>
    <?= $f('follow_up_at', 'Follow-up date', 'date') ?>
    <?= $f('value', 'Estimated value', 'number') ?>
    <div class="adm-field sm:col-span-2"><label class="adm-label" for="n_message">Requirement / notes</label><textarea id="n_message" name="message" rows="5" class="adm-input"><?= e($v['message'] ?? '') ?></textarea></div>
  </div>
  <div class="mt-6 flex justify-end gap-2"><a href="<?= e(url('admin/leads')) ?>" class="adm-btn adm-btn-light">Cancel</a><button class="adm-btn adm-btn-primary"><?= icon('save', 'h-4 w-4') ?> Create lead</button></div>
</form>
