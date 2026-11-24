<?php
/** Admin-area error for someone who is not signed in (e.g. the sign-in form expired). @var int $status @var string $title @var string $text @var string $icon @var ?string $ref @var int $retryAfter @var string $back */
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?> · <?= e(company('name', 'Admin')) ?></title>
  <link rel="icon" href="<?= e(url('favicon.ico')) ?>" sizes="48x48"><link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
</head>
<body class="adm-login">
  <main class="w-full max-w-md">
    <div class="mb-8 flex items-center justify-center gap-3">
      <svg class="h-10 w-10" viewBox="0 0 40 40" aria-hidden="true"><rect width="40" height="40" fill="#fff"/><path d="M9 30 L17 10 H23 L15 30 Z" fill="#F28C28"/><path d="M19 30 L27 10 H33 L25 30 Z" fill="#174A78"/></svg>
      <p class="font-display text-2xl font-bold uppercase tracking-wide text-white"><?= e(company('name', 'Admin')) ?></p>
    </div>
    <div class="adm-card p-8 text-center">
      <span class="mx-auto grid h-14 w-14 place-items-center rounded-full <?= $status >= 500 ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' ?>"><?= icon($icon, 'h-7 w-7') ?></span>
      <p class="mt-4 text-xs font-bold uppercase tracking-[0.2em] text-charcoal/60">Error <?= (int) $status ?></p>
      <h1 class="mt-1 text-xl font-bold text-navy"><?= e($title) ?></h1>
      <p class="mt-3 text-sm leading-relaxed text-charcoal/75"><?= e($text) ?></p>
      <?php if ($status === 429 && $retryAfter): ?><p class="mt-2 text-sm text-charcoal/75">Try again in about <?= $retryAfter < 90 ? (int) $retryAfter . ' seconds' : (int) ceil($retryAfter / 60) . ' minutes' ?>.</p><?php endif ?>
      <?php if ($ref): ?><p class="mt-4 rounded-lg bg-mist px-3 py-2 text-sm text-charcoal/75">Reference <code class="font-mono font-bold text-navy"><?= e($ref) ?></code></p><?php endif ?>
      <div class="mt-6 grid gap-2">
        <a href="<?= e(url('admin/login')) ?>" class="adm-btn adm-btn-primary justify-center"><?= icon('log-in', 'h-4 w-4') ?> <?= $status === 419 ? 'Sign in again' : 'Go to sign-in' ?></a>
        <a href="<?= e(url('')) ?>" class="adm-btn adm-btn-light justify-center">Back to website</a>
      </div>
    </div>
  </main>
</body>
</html>
