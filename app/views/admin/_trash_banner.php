<?php /** @var string $type posts|pages @var array $item */
$noun = $type === 'posts' ? 'post' : 'page';
$left = max(0, 30 - (int) floor((time() - strtotime((string) $item['deleted_at'])) / 86400));
?>
<div class="adm-flash adm-flash-error mb-4 flex-wrap" role="status">
  <?= icon('trash-2', 'h-5 w-5 shrink-0') ?>
  <span class="flex-1">This <?= $noun ?> is in the <strong>Trash</strong> and hidden from the website. It will be deleted permanently in <?= $left ?> day<?= $left === 1 ? '' : 's' ?>.</span>
  <form method="post" action="<?= e(url("admin/{$type}/{$item['id']}/restore")) ?>" class="inline"><?= csrf_field() ?><button class="adm-btn adm-btn-light adm-btn-sm"><?= icon('rotate-ccw', 'h-4 w-4') ?> Restore</button></form>
  <form method="post" action="<?= e(url("admin/{$type}/{$item['id']}/destroy")) ?>" class="inline"><?= csrf_field() ?><button class="adm-btn adm-btn-danger adm-btn-sm" data-confirm="Delete permanently? This cannot be undone."><?= icon('x', 'h-4 w-4') ?> Delete permanently</button></form>
</div>
