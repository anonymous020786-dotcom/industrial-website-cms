<?php /** @var array $r @var string $res @var ?array $row @var array $schema @var array $values @var array $errors */
$html = '';
foreach ($schema as $k => $def) {
    if (($def['type'] ?? '') === 'multiselect') {
        $sel = array_map('intval', (array) ($values[$k] ?? []));
        $html .= '<div class="adm-field sm:col-span-2"><span class="adm-label">' . e($def['label']) . '</span><div class="grid gap-2 sm:grid-cols-2">';
        foreach (field_options($def) as $oid => $label) {
            $html .= '<label class="adm-check"><input type="checkbox" name="' . e($k) . '[]" value="' . (int) $oid . '"' . (in_array((int) $oid, $sel, true) ? ' checked' : '') . '><span>' . e($label) . '</span></label>';
        }
        $html .= '</div></div>';
        continue;
    }
    $html .= render_field($k, $def, $values[$k] ?? ($def['default'] ?? null), $errors[$k] ?? null, $k);
}
?>
<form method="post" class="adm-card max-w-4xl p-6">
  <?= csrf_field() ?>
  <div class="grid gap-4 sm:grid-cols-2"><?= $html ?></div>
  <div class="mt-6 flex justify-end gap-2 border-t border-black/10 pt-5">
    <a href="<?= e(url("admin/r/{$res}")) ?>" class="adm-btn adm-btn-light">Cancel</a>
    <button class="adm-btn adm-btn-primary"><?= icon('save', 'h-4 w-4') ?> Save <?= e(strtolower($r['singular'])) ?></button>
  </div>
</form>
