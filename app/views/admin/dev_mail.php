<?php /** @var array $list @var ?string $current @var string $show */ ?>
<div class="adm-flash adm-flash-info"><?= icon('bug', 'h-5 w-5 shrink-0') ?><span>Debug mode is on, so every outgoing email is saved here (newest 200 kept). Turn off <code>debug</code> in <code>app/config.php</code> for production.</span></div>
<div class="grid gap-6 lg:grid-cols-[380px_1fr]">
  <div class="adm-card max-h-[75vh] overflow-y-auto">
    <?php if (!$list): ?><p class="p-8 text-center text-sm text-charcoal/55">No emails yet.</p><?php endif ?>
    <?php foreach ($list as $m): ?>
    <a href="<?= e(url('admin/dev-mail') . '?f=' . rawurlencode($m['file'])) ?>" class="block border-b border-black/5 px-4 py-3 hover:bg-mist <?= $show === $m['file'] ? 'bg-mist' : '' ?>">
      <p class="truncate text-sm font-semibold text-navy"><?= e($m['subject']) ?></p>
      <p class="truncate text-xs text-charcoal/55"><?= e($m['to']) ?> · <?= e(format_date($m['date'], 'j M H:i')) ?></p>
    </a>
    <?php endforeach ?>
  </div>
  <div class="adm-card overflow-hidden">
    <?php if ($current): ?>
      <iframe class="h-[75vh] w-full border-0 bg-white" sandbox srcdoc="<?= e($current) ?>" title="Email preview"></iframe>
    <?php else: ?><p class="p-10 text-center text-sm text-charcoal/55">Select an email to preview it.</p><?php endif ?>
  </div>
</div>
