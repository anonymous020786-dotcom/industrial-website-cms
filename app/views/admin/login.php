<?php
/** @var ?string $error @var string $step  password | otp | forgot | reset */
$flashes = flashes();
$step = $step ?? 'password';
$email = $email ?? '';
$titles = ['password' => 'Sign in', 'otp' => 'Verify sign-in', 'totp' => 'Two-factor sign-in', 'forgot' => 'Forgot password', 'reset' => 'Reset password'];
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
<body class="adm-login" data-base="<?= e(base_path()) ?>">
  <main class="w-full max-w-md">
    <div class="mb-8 flex items-center justify-center gap-3">
      <svg class="h-10 w-10" viewBox="0 0 40 40" aria-hidden="true"><rect width="40" height="40" fill="#fff"/><path d="M9 30 L17 10 H23 L15 30 Z" fill="#F28C28"/><path d="M19 30 L27 10 H33 L25 30 Z" fill="#174A78"/></svg>
      <p class="font-display text-2xl font-bold uppercase tracking-wide text-white"><?= e(company('name', 'Admin')) ?></p>
    </div>

    <?php if ($step === 'otp'): ?>
    <form method="post" action="<?= e(url('admin/login/verify')) ?>" class="adm-card space-y-5 p-8" autocomplete="off">
      <?= csrf_field() ?>
      <div>
        <?php $tg = ($via ?? 'email') === 'telegram'; ?>
        <span class="mb-4 grid h-12 w-12 place-items-center rounded-full <?= $tg ? 'bg-sky-50 text-sky-700' : 'bg-mist text-steel' ?>"><?= icon($tg ? 'send' : 'mail-check', 'h-6 w-6') ?></span>
        <h1 class="text-xl font-bold text-navy"><?= $tg ? 'Check Telegram' : 'Check your email' ?></h1>
        <?php if ($tg): ?>
        <p class="mt-1 text-sm text-charcoal/65">We sent a 6-digit code to your Telegram (<strong class="text-navy"><?= e($tgName ?? 'Telegram') ?></strong>). It expires in 10 minutes.</p>
        <?php else: ?>
        <p class="mt-1 text-sm text-charcoal/65">We sent a 6-digit code to <strong class="text-navy"><?= e($maskedEmail) ?></strong><?= !empty($sentToBackup) ? ' (backup email)' : '' ?>. It expires in 10 minutes.</p>
        <?php if (!empty($tgFailed)): ?><p class="mt-2 text-xs text-amber-700">Telegram could not be reached, so the code was emailed instead.</p><?php endif ?>
        <?php endif ?>
      </div>
      <?php if (!empty($approval)): ?>
      <div class="flex items-center gap-3 rounded-lg bg-sky-50 p-3 text-sm text-sky-900" data-tg-approval role="status"><span class="adm-spinner shrink-0" aria-hidden="true"></span><span data-tg-note>Or tap <strong>Approve</strong> in Telegram — this page continues by itself.</span></div>
      <?php endif ?>
      <?= $alerts() ?>
      <?= $devHint($devCode ?? null) ?>
      <div class="adm-field">
        <label class="adm-label" for="code">Verification code</label>
        <input class="adm-input text-center font-mono text-2xl tracking-[0.5em]" id="code" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required autofocus>
      </div>
      <?php if ($trustDays > 0): ?>
      <label class="adm-check text-sm"><input type="checkbox" name="trust" value="1" checked><span>Don’t ask for a code on this device for <?= (int) $trustDays ?> days</span></label>
      <?php endif ?>
      <button class="adm-btn adm-btn-primary w-full justify-center py-3">Verify &amp; sign in <?= icon('arrow-right', 'h-4 w-4') ?></button>
    </form>
    <div class="mt-4 space-y-2 text-sm">
      <div class="flex items-center justify-between">
        <form method="post" action="<?= e(url('admin/login/resend')) ?>"><?= csrf_field() ?><button class="font-semibold text-white/80 hover:text-white">Resend code<?= !empty($sentToBackup) ? ' to primary email' : '' ?></button></form>
        <a class="text-white/60 hover:text-white" href="<?= e(url('admin/login')) ?>">Use a different account</a>
      </div>
      <?php if (($via ?? 'email') === 'telegram'): ?>
      <form method="post" action="<?= e(url('admin/login/resend')) ?>"><?= csrf_field() ?><input type="hidden" name="to" value="email"><button class="text-white/70 hover:text-white">No access to Telegram? Email the code to <?= e($maskedEmail) ?> instead</button></form>
      <?php elseif (!empty($canTelegram)): ?>
      <form method="post" action="<?= e(url('admin/login/resend')) ?>"><?= csrf_field() ?><input type="hidden" name="to" value="telegram"><button class="text-white/70 hover:text-white">Send the code to Telegram instead</button></form>
      <?php endif ?>
      <?php if (!empty($hasBackup) && empty($sentToBackup)): ?>
      <form method="post" action="<?= e(url('admin/login/resend')) ?>"><?= csrf_field() ?><input type="hidden" name="to" value="backup"><button class="text-white/70 hover:text-white">No access to this inbox? Send the code to <?= e($maskedBackup) ?> instead</button></form>
      <?php endif ?>
    </div>

    <?php elseif ($step === 'totp'): $rec = ($mode ?? 'app') === 'recovery'; ?>
    <form method="post" action="<?= e(url('admin/login/2fa')) ?>" class="adm-card space-y-5 p-8" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="mode" value="<?= $rec ? 'recovery' : 'app' ?>">
      <div>
        <span class="mb-4 grid h-12 w-12 place-items-center rounded-full bg-mist text-steel"><?= icon($rec ? 'life-buoy' : 'smartphone', 'h-6 w-6') ?></span>
        <h1 class="text-xl font-bold text-navy"><?= $rec ? 'Use a recovery code' : 'Enter your authenticator code' ?></h1>
        <p class="mt-1 text-sm text-charcoal/70"><?= $rec ? 'Enter one of the one-time recovery codes you saved when you set up two-factor sign-in.' : 'Open your authenticator app (Google Authenticator, Microsoft Authenticator, 1Password…) and enter the 6-digit code for this site.' ?></p>
      </div>
      <?= $alerts() ?>
      <div class="adm-field">
        <label class="adm-label" for="code"><?= $rec ? 'Recovery code' : 'Authentication code' ?></label>
        <?php if ($rec): ?>
        <input class="adm-input text-center font-mono text-xl tracking-widest" id="code" name="code" maxlength="20" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="xxxxx-xxxxx" required autofocus>
        <?php else: ?>
        <input class="adm-input text-center font-mono text-2xl tracking-[0.5em]" id="code" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required autofocus>
        <?php endif ?>
      </div>
      <?php if (($trustDays ?? 0) > 0): ?>
      <label class="adm-check text-sm"><input type="checkbox" name="trust" value="1" checked><span>Don’t ask for a code on this device for <?= (int) $trustDays ?> days</span></label>
      <?php endif ?>
      <button class="adm-btn adm-btn-primary w-full justify-center py-3">Verify &amp; sign in <?= icon('arrow-right', 'h-4 w-4') ?></button>
    </form>
    <div class="mt-4 flex items-center justify-between text-sm">
      <?php if ($rec): ?><a class="font-semibold text-white/80 hover:text-white" href="<?= e(url('admin/login/2fa')) ?>">Use the authenticator app instead</a>
      <?php else: ?><a class="font-semibold text-white/80 hover:text-white" href="<?= e(url('admin/login/2fa?mode=recovery')) ?>">Lost your phone? Use a recovery code<?= isset($recoveryLeft) ? ' (' . (int) $recoveryLeft . ' left)' : '' ?></a><?php endif ?>
      <a class="text-white/70 hover:text-white" href="<?= e(url('admin/login')) ?>">Cancel</a>
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
      <?php if (!empty($turnstile)): ?><?= turnstile_widget() ?><?php endif ?>
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
      <?php if (!empty($turnstile)): ?><?= turnstile_widget() ?><?php endif ?>
      <button class="adm-btn adm-btn-primary w-full justify-center py-3">Reset password <?= icon('arrow-right', 'h-4 w-4') ?></button>
      <p class="text-xs text-charcoal/55">Resetting signs out every other session and trusted browser on this account.</p>
    </form>
    <p class="mt-4 flex justify-between text-sm"><a class="text-white/70 hover:text-white" href="<?= e(url('admin/forgot')) ?>">Didn’t get a code? Try again</a><a class="text-white/70 hover:text-white" href="<?= e(url('admin/login')) ?>">Back to sign in</a></p>

    <?php else: ?>
    <form method="post" action="<?= e(url('admin/login')) ?>" class="adm-card space-y-5 p-8" novalidate>
      <?= csrf_field() ?>
      <div><h1 class="text-xl font-bold text-navy">Sign in to the control panel</h1><p class="mt-1 text-sm text-charcoal/60">Manage content, blog posts and leads.</p></div>
      <?= $alerts() ?>
      <div class="adm-field"><label class="adm-label" for="login">Email or username</label><input class="adm-input" id="login" name="login" value="<?= e($email) ?>" autocomplete="username webauthn" autocapitalize="none" required autofocus></div>
      <div class="adm-field">
        <div class="flex items-center justify-between"><label class="adm-label" for="password">Password</label><a class="text-xs font-semibold text-steel hover:underline" href="<?= e(url('admin/forgot')) ?>">Forgot password?</a></div>
        <input class="adm-input" id="password" name="password" type="password" autocomplete="current-password" required>
      </div>
      <?php if (!empty($turnstile)): ?><?= turnstile_widget() ?><?php endif ?>
      <?php if (remember_enabled()): ?>
      <label class="adm-check text-sm"><input type="checkbox" name="remember" value="1" data-remember><span>Keep me signed in on this device for <?= (int) setting('remember_days', 30) ?> days</span></label>
      <?php endif ?>
      <button class="adm-btn adm-btn-primary w-full justify-center py-3">Sign in <?= icon('arrow-right', 'h-4 w-4') ?></button>
      <?php if (otp_enabled()): ?><p class="flex items-center gap-2 text-xs text-charcoal/60"><?= icon('shield-check', 'h-4 w-4 text-emerald-600') ?> Two-step verification is on — you will be asked for a code.</p><?php endif ?>
      <div class="space-y-3" data-passkey-box>
        <div class="flex items-center gap-3 text-xs uppercase tracking-wider text-charcoal/60"><span class="h-px flex-1 bg-black/10"></span>or<span class="h-px flex-1 bg-black/10"></span></div>
        <button type="button" class="adm-btn adm-btn-light w-full justify-center py-3" data-passkey-login><?= icon('fingerprint', 'h-5 w-5') ?> Sign in with a passkey</button>
        <p class="text-center text-xs text-red-700" role="alert" data-passkey-error></p>
      </div>
    </form>
    <?php endif ?>
    <p class="mt-6 text-center text-xs text-white/40"><a class="hover:text-white" href="<?= e(url('')) ?>">← Back to website</a></p>
  </main>
  <script src="<?= e(asset('js/passkey.js')) ?>" defer></script>
  <script src="<?= e(asset('js/telegram.js')) ?>" defer></script>
  <script src="<?= e(asset('js/pwtoggle.js')) ?>" defer></script>
</body>
</html>
