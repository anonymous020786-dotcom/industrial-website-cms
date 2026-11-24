<?php /** @var array $v @var array $errors @var ?array $done */
$in = fn($k, $label, $type = 'text', $help = '') => '<div class="adm-field"><label class="adm-label" for="i_' . $k . '">' . e($label) . '</label><input class="adm-input" id="i_' . $k . '" name="' . $k . '" type="' . $type . '" value="' . ($type === 'password' ? '' : e($v[$k])) . '">' . ($help ? '<p class="adm-help">' . $help . '</p>' : '') . '</div>';
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex">
<title>Install</title><link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>"></head>
<body class="adm-login !items-start py-12">
<main class="w-full max-w-2xl">
  <div class="mb-6 text-center text-white"><p class="font-display text-3xl font-bold uppercase tracking-wide">Website Installer</p><p class="mt-1 text-sm text-white/60">Sets up the database, demo content, 70+ blog drafts and your admin account.</p></div>
  <?php if ($done): ?>
  <div class="adm-card p-8">
    <h1 class="flex items-center gap-2 text-xl font-bold text-navy"><?= icon('circle-check', 'h-6 w-6 text-emerald-600') ?> Installation complete</h1>
    <ul class="mt-4 list-disc space-y-1 pl-6 text-sm text-charcoal/75"><?php foreach ($done as $line): ?><li><?= e($line) ?></li><?php endforeach ?></ul>
    <p class="mt-4 text-sm text-charcoal/70">The installer is now locked. Blog posts were imported as <strong>drafts</strong> — review and schedule them from Admin → Blog Posts.</p>
    <div class="mt-6 flex gap-3"><a class="adm-btn adm-btn-primary" href="<?= e(url('admin/login')) ?>">Sign in to admin <?= icon('arrow-right', 'h-4 w-4') ?></a><a class="adm-btn adm-btn-light" href="<?= e(url('')) ?>">View website</a></div>
  </div>
  <?php else: ?>
  <form method="post" class="adm-card space-y-6 p-8">
    <?= csrf_field() ?>
    <?php foreach ($errors as $err): ?><div class="adm-flash adm-flash-error"><?= icon('circle-alert', 'h-5 w-5 shrink-0') ?><span><?= e($err) ?></span></div><?php endforeach ?>
    <section><h2 class="adm-subheading">1. Database (MySQL / MariaDB)</h2><p class="adm-help mb-3">On Hostinger/cPanel, create the database and user first in your hosting panel, then enter them here.</p>
      <div class="grid gap-4 sm:grid-cols-2"><?= $in('db_host', 'Host') ?><?= $in('db_port', 'Port') ?><?= $in('db_name', 'Database name') ?><?= $in('db_user', 'Username') ?><?= $in('db_pass', 'Password', 'password') ?></div></section>
    <section><h2 class="adm-subheading">2. Website</h2><div class="grid gap-4"><?= $in('site_url', 'Site URL', 'url', 'The public address, e.g. https://www.yourcompany.com') ?></div></section>
    <section><h2 class="adm-subheading">3. Administrator account</h2>
      <div class="grid gap-4 sm:grid-cols-2"><?= $in('admin_name', 'Your name') ?><?= $in('admin_email', 'Email', 'email') ?><?= $in('admin_pass', 'Password', 'password', 'At least 10 characters with letters and numbers.') ?></div></section>
    <button class="adm-btn adm-btn-primary w-full justify-center py-3">Install website <?= icon('arrow-right', 'h-4 w-4') ?></button>
  </form>
  <?php endif ?>
</main></body></html>
