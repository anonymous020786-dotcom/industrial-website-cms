<?php
/** @var string $title @var string $content @var string $actions @var array $crumbs @var bool $wide */
$user = current_user();
$flashes = flashes();
$path = request_path();
?><!DOCTYPE html>
<html lang="en" data-theme-default="<?= e(theme_default()) ?>">
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
  <?= admin_theme_head() ?>
</head>
<body class="adm-body" data-base="<?= e(base_path()) ?>">
<?php if ($user): ?>
<div class="adm-shell">
  <aside class="adm-sidebar" id="adm-sidebar">
    <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
      <svg class="h-8 w-8 shrink-0" viewBox="0 0 40 40" aria-hidden="true"><rect width="40" height="40" fill="#fff"/><path d="M9 30 L17 10 H23 L15 30 Z" fill="#F28C28"/><path d="M19 30 L27 10 H33 L25 30 Z" fill="#174A78"/></svg>
      <div class="adm-brand-text min-w-0 flex-1 leading-tight"><p class="truncate font-display text-lg font-bold uppercase tracking-wide text-white"><?= e(company('short_name', 'Admin')) ?></p><p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/50">Control Panel</p></div>
      <button type="button" class="adm-icon-btn !text-white/80 hover:!bg-white/10 lg:hidden" data-sidebar-close aria-label="Close menu"><?= icon('x', 'h-5 w-5') ?></button>
    </div>
    <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Admin">
      <?php foreach (admin_nav() as $item): ?>
        <?php if ($item[0] === '_'): ?><p class="adm-nav-heading"><?= e($item[1]) ?></p><?php continue; endif ?>
        <?php if (!can($item[3])) continue;
          $active = $item[1] === 'admin' ? $path === '/admin' : str_starts_with($path . '/', '/' . $item[1] . '/') || ($item[1] === 'admin/settings/general' && str_starts_with($path, '/admin/settings') && !preg_match('~/(integrations|maintenance)$~', $path)); ?>
        <a href="<?= e(url($item[1])) ?>" class="adm-nav-link<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?> title="<?= e($item[0]) ?>">
          <?= icon($item[2], 'h-[18px] w-[18px] shrink-0') ?><span class="adm-nav-text flex-1"><?= e($item[0]) ?></span>
          <?php if (!empty($item[4])): ?><span class="adm-nav-badge"><?= (int) $item[4] ?></span><?php endif ?>
        </a>
      <?php endforeach ?>
    </nav>
    <div class="border-t border-white/10 p-3">
      <a href="<?= e(url('')) ?>" target="_blank" class="adm-nav-link" title="View website"><?= icon('external-link', 'h-[18px] w-[18px] shrink-0') ?><span class="adm-nav-text">View website</span></a>
    </div>
  </aside>
  <div class="adm-backdrop" data-sidebar-close></div>

  <div class="adm-main">
    <header class="adm-topbar">
      <button type="button" class="adm-icon-btn lg:hidden" data-sidebar-open aria-label="Open menu" aria-controls="adm-sidebar"><?= icon('menu', 'h-5 w-5') ?></button>
      <button type="button" class="adm-icon-btn adm-sidebar-toggle !hidden lg:!inline-grid" data-sidebar-toggle aria-controls="adm-sidebar" aria-expanded="true" aria-label="Collapse sidebar" title="Collapse sidebar ( [ )"><?= icon('panel-left-close', 'icon-collapse h-5 w-5') ?><?= icon('panel-left-open', 'icon-expand h-5 w-5') ?></button>
      <div class="min-w-0 flex-1">
        <?php if ($crumbs): ?><nav class="flex items-center gap-1 text-xs text-charcoal/50" aria-label="Breadcrumb"><?php foreach ($crumbs as [$l, $u]): ?><a class="hover:text-navy" href="<?= e(url($u)) ?>"><?= e($l) ?></a><?= icon('chevron-right', 'h-3 w-3') ?><?php endforeach ?></nav><?php endif ?>
        <h1 class="truncate text-lg font-bold text-navy"><?= e($title) ?></h1>
      </div>
      <div class="flex items-center gap-2"><?= $actions ?></div>
      <?= admin_theme_button('adm-icon-btn !h-10 !w-10 rounded-full') ?>
      <?php $unreadN = notifications_unread((int) $user['id']); ?>
      <a href="<?= e(url('admin/notifications')) ?>" class="adm-bell" aria-label="Notifications<?= $unreadN ? ' (' . $unreadN . ' unread)' : '' ?>" title="Notifications"><?= icon('bell', 'h-5 w-5') ?><?php if ($unreadN): ?><span class="adm-bell-count"><?= $unreadN > 99 ? '99+' : $unreadN ?></span><?php endif ?></a>
      <?php if (stepup_valid()): ?><span class="adm-verified" title="Sensitive actions confirmed for <?= (int) ceil(stepup_remaining() / 60) ?> more minute(s)"><?= icon('shield-check', 'h-4 w-4') ?><span class="hidden md:inline">Verified</span></span><?php endif ?>
      <details class="adm-user">
        <summary><?= user_avatar_html($user) ?><span class="hidden text-sm font-semibold text-navy sm:block"><?= e($user['name']) ?></span><?= icon('chevron-down', 'h-4 w-4 text-charcoal/50') ?></summary>
        <div class="adm-user-menu">
          <p class="px-4 pb-2 pt-3 text-xs text-charcoal/55"><?= e($user['email']) ?><br><span class="font-semibold uppercase tracking-wider"><?= e($user['role']) ?></span></p>
          <a href="<?= e(url('admin/profile')) ?>" class="adm-menu-item"><?= icon('user-cog', 'h-4 w-4') ?> My profile</a>
          <a href="<?= e(url('admin/notifications')) ?>" class="adm-menu-item"><?= icon('bell', 'h-4 w-4') ?> Notifications<?= $unreadN ? ' (' . $unreadN . ')' : '' ?></a>
          <form method="post" action="<?= e(url('admin/logout')) ?>"><?= csrf_field() ?><button class="adm-menu-item w-full text-left"><?= icon('log-out', 'h-4 w-4') ?> Sign out</button></form>
        </div>
      </details>
    </header>

    <main class="adm-content<?= $wide ? ' adm-content-wide' : '' ?>">
      <?php foreach ($flashes as $f): ?>
      <div class="adm-flash adm-flash-<?= e($f['type']) ?>" role="status"><?= icon($f['type'] === 'error' ? 'circle-alert' : ($f['type'] === 'info' ? 'info' : 'circle-check'), 'h-5 w-5 shrink-0') ?><span><?= e($f['message']) ?></span><button type="button" class="ml-auto opacity-60 hover:opacity-100" data-dismiss aria-label="Dismiss"><?= icon('x', 'h-4 w-4') ?></button></div>
      <?php endforeach ?>
      <?php
      $me = current_user();
      $needs2fa = $me && $me['role'] === 'admin' && setting('require_strong_2fa', true) && request_path() !== '/admin/profile'
          && !(bool) db_val('SELECT totp_enabled_at IS NOT NULL OR EXISTS(SELECT 1 FROM passkeys WHERE user_id = ?) FROM users WHERE id = ?', [(int) $me['id'], (int) $me['id']]);
      $cronLast = (string) setting('cron_last_run', '');
      $cronStale = $me && $me['role'] === 'admin' && !config('debug') && ($cronLast === '' || strtotime($cronLast) < time() - 26 * 3600);
      ?>
      <?php if ($needs2fa): ?>
      <div class="adm-flash adm-flash-info" role="status"><?= icon('shield-alert', 'h-5 w-5 shrink-0') ?><span>Protect your administrator account: add a <strong>passkey</strong> or an <strong>authenticator app</strong> so a stolen password is not enough to sign in.</span><a class="adm-btn adm-btn-light adm-btn-sm ml-auto shrink-0" href="<?= e(url('admin/profile#passkeys')) ?>">Set up now</a></div>
      <?php endif ?>
      <?php $mState = maintenance_state(); if ($mState['active'] && $path !== '/admin/settings/maintenance'): ?>
      <div class="adm-maint-banner" role="status">
        <?= icon('construction', 'h-5 w-5 shrink-0') ?>
        <span class="min-w-0 flex-1"><strong>Maintenance mode is on.</strong> Visitors see the maintenance page<?= $mState['until'] ? ($mState['until'] > time() ? ' until about ' . e(maintenance_time($mState['until'])) : ' (the expected end time has passed)') : '' ?><?= $mState['source'] === 'scheduled' ? ' — scheduled window' : '' ?>.</span>
        <a class="adm-btn adm-btn-light adm-btn-sm shrink-0" href="<?= e(url('?maintenance_preview=1')) ?>" target="_blank" rel="noopener"><?= icon('eye', 'h-4 w-4') ?> Preview</a>
        <?php if (can('settings')): ?><form method="post" action="<?= e(url('admin/maintenance')) ?>" class="shrink-0"><?= csrf_field() ?><input type="hidden" name="action" value="off"><input type="hidden" name="return" value="<?= e(ltrim($path, '/')) ?>"><button class="adm-btn adm-btn-primary adm-btn-sm"><?= icon('power', 'h-4 w-4') ?> Go live now</button></form><?php endif ?>
      </div>
      <?php endif ?>
      <?php if ($cronStale): ?>
      <div class="adm-flash adm-flash-error" role="status"><?= icon('clock', 'h-5 w-5 shrink-0') ?><span>Scheduled tasks have not run in over a day, so backups and follow-up emails are not happening.</span><a class="adm-btn adm-btn-light adm-btn-sm ml-auto shrink-0" href="<?= e(url('admin/backups')) ?>">Fix</a></div>
      <?php endif ?>
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
<?php if (setting('scroll_buttons', true)): ?>
<div class="adm-scroll">
  <button type="button" class="adm-scroll-btn adm-scroll-down" data-scroll-down aria-label="Scroll down"><?= icon('chevron-down', 'h-5 w-5') ?></button>
  <button type="button" class="adm-scroll-btn adm-scroll-up" data-scroll-up aria-label="Back to top">
    <svg class="adm-scroll-ring" viewBox="0 0 48 48" aria-hidden="true"><circle class="ring-bg" cx="24" cy="24" r="22"/><circle class="ring-fg" cx="24" cy="24" r="22" stroke-dasharray="138.23" stroke-dashoffset="138.23"/></svg>
    <?= icon('arrow-up', 'relative h-5 w-5') ?>
  </button>
</div>
<?php endif ?>
<?php else: ?>
<?= $content ?>
<?php endif ?>
<script src="<?= e(asset('vendor/quill/quill.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
<script src="<?= e(asset('js/pwtoggle.js')) ?>" defer></script>
</body>
</html>
