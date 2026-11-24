<?php /** @var string $action @var array $fields @var string $label */
$inputs = function (array $data, string $prefix = '') use (&$inputs): string {
    $h = '';
    foreach ($data as $k => $v) {
        $name = $prefix === '' ? (string) $k : $prefix . '[' . $k . ']';
        $h .= is_array($v) ? $inputs($v, $name) : '<input type="hidden" name="' . e($name) . '" value="' . e((string) $v) . '">';
    }
    return $h;
};
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex, nofollow">
  <title>Continuing… · <?= e(company('name', 'Admin')) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
</head>
<body class="adm-login" data-base="<?= e(base_path()) ?>">
  <main class="w-full max-w-md">
    <form method="post" action="<?= e($action) ?>" class="adm-card space-y-4 p-8 text-center" data-autosubmit>
      <?= csrf_field() ?><?= $inputs($fields) ?>
      <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-emerald-50 text-emerald-700"><?= icon('circle-check', 'h-6 w-6') ?></span>
      <h1 class="text-xl font-bold text-navy">Identity confirmed</h1>
      <p class="text-sm text-charcoal/75">Continuing with: <strong><?= e($label) ?></strong>…</p>
      <button class="adm-btn adm-btn-primary w-full justify-center py-3">Continue <?= icon('arrow-right', 'h-4 w-4') ?></button>
    </form>
  </main>
  <script src="<?= e(asset('js/pwtoggle.js')) ?>" defer></script>
  <script src="<?= e(asset('js/passkey.js')) ?>" defer></script>
</body>
</html>
