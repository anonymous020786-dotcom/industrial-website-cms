<?php /** @var array $pager */ if ($pager['pages'] <= 1) return; ?>
<nav class="mt-4 flex items-center justify-between gap-3" aria-label="Pagination">
  <p class="text-sm text-charcoal/55">Page <?= (int) $pager['page'] ?> of <?= (int) $pager['pages'] ?></p>
  <div class="flex gap-2">
    <?php if ($pager['page'] > 1): ?><a class="adm-btn adm-btn-light adm-btn-sm" href="<?= e(qs(['page' => $pager['page'] - 1])) ?>"><?= icon('chevron-left', 'h-4 w-4') ?> Previous</a><?php endif ?>
    <?php if ($pager['page'] < $pager['pages']): ?><a class="adm-btn adm-btn-light adm-btn-sm" href="<?= e(qs(['page' => $pager['page'] + 1])) ?>">Next <?= icon('chevron-right', 'h-4 w-4') ?></a><?php endif ?>
  </div>
</nav>
