<?php
/** @var ?string $error @var string $step  password | otp | forgot | reset */
$flashes = flashes();
$step = $step ?? 'password';
$email = $email ?? '';
$titles = ['password' => 'Sign in', 'otp' => 'Verify sign-in', 'forgot' => 'Forgot password', 'reset' => 'Reset password'];
$alerts = function () use ($flashes, $error) {
    $h = '';
    foreach ($flashes as $f) $h .= '<div class="adm-flash adm-flash-' . e($f['type']) . '">' . e($f['message']) . '</div>';
    if ($error) $h .= '<div class="adm-flash adm-flash-error" role="alert">' . icon('circle-alert', 'h-5 w-5 shrink-0') . '<span>' . e($error) . '</span></div>';
    return $h;
};
$devHint = fn($code) => $code ? '<div class="adm-flash adm-flash-info">' . icon('bug', 'h-5 w-5 shrink-0') . '<span><strong>Debug mode:</strong> the code is <code class="font-bold">' . e($code) . '</code>. Emails are also saved in Admin → Settings → System → Dev mailbox. This hint never appears when debug is off.</span></div>' : '';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex, nofollow">
  <title><?= e($titles[$step] ?? 'Sign in') ?> · <?= e(company('name', 'Admin')) ?></title>
  <link rel="icon" href="<?= e(url('favicon.ico')) ?>" sizes="48x48"><link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
  <?php if (!empty($turnstile)): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif ?>
</head>
<body class="adm-login">
  <main class="w-full max-w-md">
    <div class="mb-8 flex items-center justify-center gap-3">
      <svg class="h-10 w-10" viewBox="0 0 40 40" aria-hidden="true"><rect width="40" height="40" fill="#fff"/><path d="M9 30 L17 10 H23 L15 30 Z" fill="#F28C28"/><path d="M19 30 L27 10 H33 L25 30 Z" fill="#174A78"/></svg>
      <p class="font-display text-2xl font-bold uppercase tracking-wide text-white"><?= e(company('name', 'Admin')) ?></p>
    </div>

    <?php if ($step === 'otp'): ?>
    <form method="post" action="<?= e(url('admin/login/verify')) ?>" class="adm-card space-y-5 p-8" autocomplete="off">
      <?= csrf_field() ?>
      <div>
        <span class="mb-4 grid h-12 w-12 place-items-center rounded-full bg-mist text-steel"><?= icon('mail-check', 'h-6 w-6') ?></span>
        <h1 class="text-xl font-bold text-navy">Check your email</h1>
        <p class="mt-1 text-sm text-charcoal/65">We sent a 6-digit code to <strong class="text-navy"><?= e($maskedEmail) ?></strong><?= !empty($sentToBackup) ? ' (backup email)' : '' ?>. It expires in 10 minutes.</p>
      </div>
      <?= $alerts() ?>
      <?= $devHint($devCode ?? null) ?>
      <div class="adm-field">
        <label class="adm-label" for="code">Verification code</label>
        <input class="adm-input text-center font-mono text-2xl tracking-[0.5em]" id="code" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required autofocus>
      </div>
      <?php if ($trustDays > 0): ?>
      <label class="adm-check text-sm"><input type="checkbox" name="trust" value="1"><span>Trust this browser for <?= (int) $trustDays ?> days</span></label>
      <?php endif ?>
      <button class="adm-btn adm-btn-primary w-full justify-center py-3">Verify &amp; sign in <?= icon('arrow-right', 'h-4 w-4') ?></button>
    </form>
    <div class="mt-4 space-y-2 text-sm">
      <div class="flex items-center justify-between">
        <form method="post" action="<?= e(url('admin/login/resend')) ?>"><?= csrf_field() ?><button class="font-semibold text-white/80 hover:text-white">Resend code<?= !empty($sentToBackup) ? ' to primary email' : '' ?></button></form>
        <a class="text-white/60 hover:text-white" href="<?= e(url('admin/login')) ?>">Use a different account</a>
      </div>
      <?php if (!empty($hasBackup) && empty($sentToBackup)): ?>
      <form method="post" action="<?= e(url('admin/login/resend')) ?>"><?= csrf_field() ?><input type="hidden" name="to" value="backup"><button class="text-white/70 hover:text-white">No access to this inbox? Send the code to <?= e($maskedBackup) ?> instead</button></form>
      <?php endif ?>
    </div>

    <?php elseif ($step === 'forgot'): ?>
    <form method="post" action="<?= e(url('admin/forgot')) ?>" class="adm-card space-y-5 p-8" novalidate>
      <?= csrf_field() ?>
      <div>
        <span class="mb-4 grid h-12 w-12 place-items-center rounded-full bg-mist text-steel"><?= icon('key-round', 'h-6 w-6') ?></span>
        <h1 class="text-xl font-bold text-navy">Forgot your password?</h1>
        <p class="mt-1 text-sm text-charcoal/65">Enter your email address or username. If an account exists, we’ll email a reset code to its primary address and to the verified backup email.</p>
      </div>
      <?= $alerts() ?>
      <div class="adm-field"><label class="adm-label" for="login">Email or username</label><input class="adm-input" id="login" name="login" value="<?= e($email) ?>" autocomplete="username" required autofocus></div>
      <?php if (!empty($turnstile)): ?><div class="cf-turnstile" data-sitekey="<?= e(setting('turnstile_site_key')) ?>" data-theme="light"></div><?php endif ?>
      <button class="adm-btn adm-btn-primary w-full justify-center py-3">Send reset code <?= icon('arrow-right', 'h-4 w-4') ?></button>
    </form>
    <p class="mt-4 text-center text-sm"><a class="text-white/70 hover:text-white" href="<?= e(url('admin/login')) ?>">← Back to sign in</a></p>

    <?php elseif ($step === 'reset'): ?>
    <form method="post" action="<?= e(url('admin/reset')) ?>" class="adm-card space-y-5 p-8" autocomplete="off">
      <?= csrf_field() ?>
      <div>
        <span class="mb-4 grid h-12 w-12 place-items-center rounded-full bg-mist text-steel"><?= icon('lock-keyhole', 'h-6 w-6') ?></span>
        <h1 class="text-xl font-bold text-navy">Set a new password</h1>
        <p class="mt-1 text-sm text-charcoal/65">If the account exists, a 6-digit code is on its way. Enter it below with your new password. The code expires in 10 minutes.</p>
      </div>
      <?= $alerts() ?>
      <?= $devHint($devCode ?? null) ?>
      <div class="adm-field"><label class="adm-label" for="code">Reset code</label><input class="adm-input text-center font-mono text-2xl tracking-[0.5em]" id="code" name="code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required autofocus></div>
      <div class="adm-field"><label class="adm-label" for="password">New password</label><input class="adm-input" id="password" name="password" type="password" autocomplete="new-password" required><p class="adm-help">At least 10 characters with letters and numbers.</p></div>
      <div class="adm-field"><label class="adm-label" for="confirm">Confirm new password</label><input class="adm-input" id="confirm" name="confirm" type="password" autocomplete="new-password" required></div>
      <button class="adm-btn adm-btn-primary w-full justify-center py-3">Reset password <?= icon('arrow-right', 'h-4 w-4') ?></button>
      <p class="text-xs text-charcoal/55">Resetting signs out every other session and trusted browser on this account.</p>
    </form>
    <p class="mt-4 flex justify-between text-sm"><a class="text-white/70 hover:text-white" href="<?= e(url('admin/forgot')) ?>">Didn’t get a code? Try again</a><a class="text-white/70 hover:text-white" href="<?= e(url('admin/login')) ?>">Back to sign in</a></p>

    <?php else: ?>
    <form method="post" action="<?= e(url('admin/login')) ?>" class="adm-card space-y-5 p-8" novalidate>
      <?= csrf_field() ?>
      <div><h1 class="text-xl font-bold text-navy">Sign in to the control panel</h1><p class="mt-1 text-sm text-charcoal/60">Manage content, blog posts and leads.</p></div>
      <?= $alerts() ?>
      <div class="adm-field"><label class="adm-label" for="login">Email or username</label><input class="adm-input" id="login" name="login" value="<?= e($email) ?>" autocomplete="username" autocapitalize="none" required autofocus></div>
      <div class="adm-field">
        <div class="flex items-center justify-between"><label class="adm-label" for="password">Password</label><a class="text-xs font-semibold text-steel hover:underline" href="<?= e(url('admin/forgot')) ?>">Forgot password?</a></div>
        <input class="adm-input" id="password" name="password" type="password" autocomplete="current-password" required>
      </div>
      <?php if (!empty($turnstile)): ?><div class="cf-turnstile" data-sitekey="<?= e(setting('turnstile_site_key')) ?>" data-theme="light"></div><?php endif ?>
      <button class="adm-btn adm-btn-primary w-full justify-center py-3">Sign in <?= icon('arrow-right', 'h-4 w-4') ?></button>
      <?php if (otp_enabled()): ?><p class="flex items-center gap-2 text-xs text-charcoal/55"><?= icon('shield-check', 'h-4 w-4 text-emerald-600') ?> Two-step verification is on — a code will be emailed to you.</p><?php endif ?>
    </form>
    <?php endif ?>
    <p class="mt-6 text-center text-xs text-white/40"><a class="hover:text-white" href="<?= e(url('')) ?>">← Back to website</a></p>
  </main>
</body>
</html>
