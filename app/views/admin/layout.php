<?php
/** @var string $title @var string $content @var string $actions @var array $crumbs @var bool $wide */
$user = current_user();
$flashes = flashes();
$path = request_path();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($title) ?> · <?= e(company('name', 'Admin')) ?> Admin</title>
  <link rel="icon" href="<?= e(url('assets/images/favicon.svg')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/quill/quill.snow.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
</head>
<body class="adm-body" data-base="<?= e(base_path()) ?>">
<?php if ($user): ?>
<div class="adm-shell">
  <aside class="adm-sidebar" id="adm-sidebar">
    <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
      <svg class="h-8 w-8 shrink-0" viewBox="0 0 40 40" aria-hidden="true"><rect width="40" height="40" fill="#fff"/><path d="M9 30 L17 10 H23 L15 30 Z" fill="#F28C28"/><path d="M19 30 L27 10 H33 L25 30 Z" fill="#174A78"/></svg>
      <div class="min-w-0 leading-tight"><p class="truncate font-display text-lg font-bold uppercase tracking-wide text-white"><?= e(company('short_name', 'Admin')) ?></p><p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/50">Control Panel</p></div>
    </div>
    <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Admin">
      <?php foreach (admin_nav() as $item): ?>
        <?php if ($item[0] === '_'): ?><p class="adm-nav-heading"><?= e($item[1]) ?></p><?php continue; endif ?>
        <?php if (!can($item[3])) continue;
          $active = $item[1] === 'admin' ? $path === '/admin' : str_starts_with($path . '/', '/' . $item[1] . '/') || ($item[1] === 'admin/settings/general' && str_starts_with($path, '/admin/settings') && !str_ends_with($path, 'integrations')); ?>
        <a href="<?= e(url($item[1])) ?>" class="adm-nav-link<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>>
          <?= icon($item[2], 'h-[18px] w-[18px]') ?><span class="flex-1"><?= e($item[0]) ?></span>
          <?php if (!empty($item[4])): ?><span class="adm-nav-badge"><?= (int) $item[4] ?></span><?php endif ?>
        </a>
      <?php endforeach ?>
    </nav>
    <div class="border-t border-white/10 p-3">
      <a href="<?= e(url('')) ?>" target="_blank" class="adm-nav-link"><?= icon('external-link', 'h-[18px] w-[18px]') ?><span>View website</span></a>
    </div>
  </aside>
  <div class="adm-backdrop" data-sidebar-close></div>

  <div class="adm-main">
    <header class="adm-topbar">
      <button type="button" class="adm-icon-btn lg:hidden" data-sidebar-open aria-label="Open menu"><?= icon('menu', 'h-5 w-5') ?></button>
      <div class="min-w-0 flex-1">
        <?php if ($crumbs): ?><nav class="flex items-center gap-1 text-xs text-charcoal/50" aria-label="Breadcrumb"><?php foreach ($crumbs as [$l, $u]): ?><a class="hover:text-navy" href="<?= e(url($u)) ?>"><?= e($l) ?></a><?= icon('chevron-right', 'h-3 w-3') ?><?php endforeach ?></nav><?php endif ?>
        <h1 class="truncate text-lg font-bold text-navy"><?= e($title) ?></h1>
      </div>
      <div class="flex items-center gap-2"><?= $actions ?></div>
      <details class="adm-user">
        <summary><span class="adm-avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span><span class="hidden text-sm font-semibold text-navy sm:block"><?= e($user['name']) ?></span><?= icon('chevron-down', 'h-4 w-4 text-charcoal/50') ?></summary>
        <div class="adm-user-menu">
          <p class="px-4 pb-2 pt-3 text-xs text-charcoal/55"><?= e($user['email']) ?><br><span class="font-semibold uppercase tracking-wider"><?= e($user['role']) ?></span></p>
          <a href="<?= e(url('admin/profile')) ?>" class="adm-menu-item"><?= icon('user-cog', 'h-4 w-4') ?> My profile</a>
          <form method="post" action="<?= e(url('admin/logout')) ?>"><?= csrf_field() ?><button class="adm-menu-item w-full text-left"><?= icon('log-out', 'h-4 w-4') ?> Sign out</button></form>
        </div>
      </details>
    </header>

    <main class="adm-content<?= $wide ? ' adm-content-wide' : '' ?>">
      <?php foreach ($flashes as $f): ?>
      <div class="adm-flash adm-flash-<?= e($f['type']) ?>" role="status"><?= icon($f['type'] === 'error' ? 'circle-alert' : ($f['type'] === 'info' ? 'info' : 'circle-check'), 'h-5 w-5 shrink-0') ?><span><?= e($f['message']) ?></span><button type="button" class="ml-auto opacity-60 hover:opacity-100" data-dismiss aria-label="Dismiss"><?= icon('x', 'h-4 w-4') ?></button></div>
      <?php endforeach ?>
      <?= $content ?>
    </main>
  </div>
</div>

<!-- Media picker modal -->
<dialog id="media-dialog" class="adm-dialog">
  <div class="flex items-center justify-between border-b border-black/10 px-5 py-4">
    <h2 class="font-bold text-navy">Media library</h2>
    <div class="flex items-center gap-2">
      <label class="adm-btn adm-btn-primary adm-btn-sm cursor-pointer"><?= icon('upload', 'h-4 w-4') ?> Upload<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only" data-dialog-upload></label>
      <button type="button" class="adm-icon-btn" data-dialog-close aria-label="Close"><?= icon('x', 'h-5 w-5') ?></button>
    </div>
  </div>
  <div class="max-h-[65vh] overflow-y-auto p-5"><div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6" data-media-grid></div><p class="py-10 text-center text-sm text-charcoal/50" data-media-empty hidden>No images uploaded yet.</p></div>
</dialog>
<datalist id="lucide-icons"></datalist>
<?php else: ?>
<?= $content ?>
<?php endif ?>
<script src="<?= e(asset('vendor/quill/quill.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
