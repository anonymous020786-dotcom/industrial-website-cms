<?php
/** Toolbar for signed-in team members browsing the public site. @var array $user @var ?array $edit [label, admin path, permission] */
$newLeads = can('leads') ? (int) db_val("SELECT COUNT(*) FROM leads WHERE status = 'new'") : 0;
$here = ltrim(substr((string) ($_SERVER['REQUEST_URI'] ?? '/'), strlen(base_path())), '/');
?>
<div class="site-adminbar" role="region" aria-label="Admin toolbar">
  <div class="mx-auto flex max-w-site items-center gap-1 px-4 sm:px-6 lg:px-8">
    <a href="<?= e(url('admin')) ?>" class="site-adminbar-link font-semibold" title="Control panel">
      <svg class="h-4 w-4" viewBox="0 0 40 40" aria-hidden="true"><rect width="40" height="40" fill="#fff"/><path d="M9 30 L17 10 H23 L15 30 Z" fill="#F28C28"/><path d="M19 30 L27 10 H33 L25 30 Z" fill="#174A78"/></svg>
      <span class="hidden sm:inline">Control panel</span>
    </a>
    <?php if ($edit && can($edit[2])): ?>
    <a href="<?= e(url($edit[1])) ?>" class="site-adminbar-link"><?= icon('pencil', 'h-4 w-4') ?><span><?= e($edit[0]) ?></span></a>
    <?php endif ?>
    <?php if (can('leads')): ?>
    <a href="<?= e(url('admin/leads')) ?>" class="site-adminbar-link"><?= icon('inbox', 'h-4 w-4') ?><span class="hidden md:inline">Leads</span><?php if ($newLeads): ?><span class="site-adminbar-badge" title="<?= $newLeads ?> new"><?= $newLeads > 99 ? '99+' : $newLeads ?></span><?php endif ?></a>
    <?php endif ?>
    <?php if (can('content')): ?>
    <a href="<?= e(url('admin/posts/new')) ?>" class="site-adminbar-link hidden md:inline-flex"><?= icon('plus', 'h-4 w-4') ?><span>New post</span></a>
    <?php endif ?>
    <span class="flex-1"></span>
    <span class="hidden items-center gap-2 px-2 text-white/70 lg:flex"><?= user_avatar_html($user, 'site-adminbar-avatar') ?> <?= e($user['name']) ?></span>
    <form method="post" action="<?= e(url('admin/logout')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($here) ?>"><button class="site-adminbar-link"><?= icon('log-out', 'h-4 w-4') ?><span class="hidden sm:inline">Sign out</span></button></form>
  </div>
</div>
