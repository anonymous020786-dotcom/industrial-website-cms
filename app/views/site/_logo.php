<?php
/** @var bool $dark */
$logoImg = (string) setting($dark ? 'logo_image_dark' : 'logo_image', '');
$logoDarkImg = $dark ? '' : (string) setting('logo_image_dark', ''); // swapped in when the dark theme is active
?>
<a href="<?= e(url('')) ?>" class="logo flex items-center gap-3" aria-label="<?= e(company('name')) ?> — home">
  <?php if ($logoImg): ?>
    <img src="<?= e(media_url($logoImg)) ?>" alt="<?= e(company('name')) ?>" class="no-dim h-10 w-auto<?= $logoDarkImg ? ' logo-img-light' : '' ?>">
    <?php if ($logoDarkImg): ?><img src="<?= e(media_url($logoDarkImg)) ?>" alt="<?= e(company('name')) ?>" class="no-dim logo-img-dark h-10 w-auto"><?php endif ?>
  <?php else: ?>
    <svg class="h-9 w-9 shrink-0" viewBox="0 0 40 40" aria-hidden="true">
      <rect width="40" height="40" fill="<?= $dark ? '#ffffff' : '#101D32' ?>"<?= $dark ? '' : ' class="logo-mark-bg"' ?> />
      <path d="M9 30 L17 10 H23 L15 30 Z" fill="#F28C28" />
      <path d="M19 30 L27 10 H33 L25 30 Z" fill="#174A78" />
    </svg>
    <span class="leading-none">
      <span class="block font-display text-[22px] font-bold uppercase tracking-wide <?= $dark ? 'text-white' : 'text-navy' ?>"><?= e(company('short_name', company('name'))) ?></span>
      <span class="block text-[10.5px] font-semibold uppercase tracking-[0.22em] <?= $dark ? 'text-white/60' : 'text-steel' ?>"><?= e(company('logo_sub')) ?></span>
    </span>
  <?php endif ?>
</a>
