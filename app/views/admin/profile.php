<?php
/** @var array $user @var array $errors @var array $old @var array $events @var ?string $pendingEmail @var ?string $pendingBackup @var ?string $devCode */
$err = fn($k) => isset($errors[$k]) ? '<p class="adm-error">' . e($errors[$k]) . '</p>' : '';
$val = fn($k, $d = '') => e((string) ($old[$k] ?? $d));
$codeInput = fn($name = 'code') => '<input class="adm-input max-w-[12rem] text-center font-mono text-xl tracking-[0.4em]" name="' . $name . '" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required aria-label="6-digit code">';
$devHint = $devCode ? '<p class="mt-2 rounded bg-amber-50 px-3 py-2 text-xs text-amber-900">' . icon('bug', 'mr-1 inline h-3.5 w-3.5') . 'Debug mode — code: <code class="font-bold">' . e($devCode) . '</code> (never shown when debug is off)</p>' : '';
$labels = [
    'login_success' => 'Signed in', 'login_failed' => 'Failed sign-in', 'otp_sent' => 'Sign-in code sent', 'otp_failed' => 'Wrong code entered',
    'password_changed' => 'Password changed', 'password_reset' => 'Password reset', 'reset_code_sent' => 'Reset code sent',
    'email_changed' => 'Sign-in email changed', 'email_change_code_sent' => 'Email change code sent', 'backup_email_added' => 'Backup email added',
    'backup_email_changed' => 'Backup email changed', 'backup_email_removed' => 'Backup email removed', 'backup_email_code_sent' => 'Backup email code sent',
    'username_changed' => 'Username changed', 'username_removed' => 'Username removed', 'reauth_failed' => 'Wrong current password', 'logout' => 'Signed out',
    'session_timeout' => 'Session timed out', 'login_locked' => 'Sign-in locked',
];
?>
<div class="grid gap-6 xl:grid-cols-[1fr_20rem]">
<div class="space-y-6">

  <!-- Profile -->
  <form method="post" class="adm-card space-y-4 p-6" id="profile">
    <?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <h2 class="adm-subheading flex items-center gap-2"><?= icon('user-round', 'h-5 w-5 text-steel') ?> Profile</h2>
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="adm-field"><label class="adm-label" for="p_name">Name</label><input id="p_name" class="adm-input" name="name" value="<?= $val('name', $user['name']) ?>" required maxlength="120"><?= $err('name') ?></div>
      <div class="adm-field"><label class="adm-label" for="p_user">Username <span class="font-normal text-charcoal/50">(optional)</span></label><input id="p_user" class="adm-input" name="username" value="<?= $val('username', (string) $user['username']) ?>" maxlength="60" autocapitalize="none" spellcheck="false" placeholder="e.g. jsmith"><p class="adm-help">You can sign in with your username or email. Lowercase letters, numbers, dot, dash, underscore.</p><?= $err('username') ?></div>
    </div>
    <p class="text-sm text-charcoal/60">Role: <strong><?= e(ucfirst($user['role'])) ?></strong> · Last sign-in: <?= $user['last_login_at'] ? e(date('j M Y H:i', strtotime($user['last_login_at']))) : '—' ?></p>
    <div class="flex justify-end"><button class="adm-btn adm-btn-primary"><?= icon('save', 'h-4 w-4') ?> Save profile</button></div>
  </form>

  <!-- Password -->
  <form method="post" class="adm-card space-y-4 p-6" id="password">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <div>
      <h2 class="adm-subheading flex items-center gap-2"><?= icon('key-round', 'h-5 w-5 text-steel') ?> Change password</h2>
      <p class="mt-1 text-sm text-charcoal/60">Last changed: <?= $user['password_changed_at'] ? e(date('j M Y', strtotime($user['password_changed_at']))) : 'never' ?>. Changing it signs out all other sessions and trusted browsers.</p>
    </div>
    <div class="adm-field"><label class="adm-label" for="p_cur">Current password</label><input id="p_cur" class="adm-input" type="password" name="current_password" autocomplete="current-password" required><?= $err('current_password') ?></div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="adm-field"><label class="adm-label" for="p_new">New password</label><input id="p_new" class="adm-input" type="password" name="new_password" autocomplete="new-password" required minlength="10"><p class="adm-help">At least 10 characters with letters and numbers.</p><?= $err('new_password') ?></div>
      <div class="adm-field"><label class="adm-label" for="p_conf">Confirm new password</label><input id="p_conf" class="adm-input" type="password" name="confirm_password" autocomplete="new-password" required><?= $err('confirm_password') ?></div>
    </div>
    <?php if (otp_enabled()): ?><label class="adm-check text-sm"><input type="checkbox" name="keep_device" value="1" checked><span>Keep trusting this browser for two-step verification</span></label><?php endif ?>
    <div class="flex justify-end"><button class="adm-btn adm-btn-primary"><?= icon('lock-keyhole', 'h-4 w-4') ?> Change password</button></div>
  </form>

  <!-- Primary email -->
  <section class="adm-card space-y-4 p-6" id="email">
    <div>
      <h2 class="adm-subheading flex items-center gap-2"><?= icon('mail', 'h-5 w-5 text-steel') ?> Sign-in email</h2>
      <p class="mt-1 text-sm text-charcoal/60">Current: <strong class="text-navy"><?= e($user['email']) ?></strong>. To change it, we email a code to the <em>new</em> address; the old address gets a notice.</p>
    </div>
    <?php if ($pendingEmail): ?>
      <form method="post" class="rounded-lg border border-steel/20 bg-mist/60 p-4">
        <?= csrf_field() ?><input type="hidden" name="action" value="email_verify">
        <p class="text-sm">Enter the code sent to <strong><?= e($pendingEmail) ?></strong>:</p>
        <div class="mt-3 flex flex-wrap items-center gap-3"><?= $codeInput() ?><button class="adm-btn adm-btn-primary"><?= icon('check', 'h-4 w-4') ?> Confirm new email</button></div>
        <?= $err('email_code') ?><?= $devHint ?>
      </form>
      <div class="flex gap-4 text-sm">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><input type="hidden" name="which" value="email"><button class="font-semibold text-steel hover:underline">Resend code</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="email_cancel"><button class="text-charcoal/60 hover:underline">Cancel change</button></form>
      </div>
    <?php else: ?>
      <form method="post" class="space-y-4">
        <?= csrf_field() ?><input type="hidden" name="action" value="email_start">
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="adm-field"><label class="adm-label" for="p_email">New email</label><input id="p_email" class="adm-input" type="email" name="new_email" value="<?= $val('new_email') ?>" autocomplete="email" required><?= $err('new_email') ?></div>
          <div class="adm-field"><label class="adm-label" for="p_epw">Current password</label><input id="p_epw" class="adm-input" type="password" name="email_password" autocomplete="current-password" required><?= $err('email_password') ?></div>
        </div>
        <div class="flex justify-end"><button class="adm-btn adm-btn-light"><?= icon('send', 'h-4 w-4') ?> Send verification code</button></div>
      </form>
    <?php endif ?>
  </section>

  <!-- Backup email -->
  <section class="adm-card space-y-4 p-6" id="backup">
    <div>
      <h2 class="adm-subheading flex items-center gap-2"><?= icon('life-buoy', 'h-5 w-5 text-steel') ?> Backup email</h2>
      <p class="mt-1 text-sm text-charcoal/60">A verified backup email can receive sign-in codes and password-reset codes if you lose access to your main inbox. Security notices go to both.</p>
    </div>
    <?php if ($user['backup_email']): ?>
      <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-charcoal/10 p-4">
        <p class="text-sm"><strong class="text-navy"><?= e($user['backup_email']) ?></strong>
          <?php if ($user['backup_email_verified_at']): ?><span class="ml-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"><?= icon('badge-check', 'h-3.5 w-3.5') ?> Verified</span>
          <?php else: ?><span class="ml-2 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">Not verified</span><?php endif ?>
        </p>
        <details class="text-sm" <?= isset($errors['remove_password']) ? 'open' : '' ?>>
          <summary class="cursor-pointer font-semibold text-red-700">Remove</summary>
          <form method="post" class="mt-3 flex flex-wrap items-end gap-2">
            <?= csrf_field() ?><input type="hidden" name="action" value="backup_remove">
            <div><label class="adm-label" for="p_rpw">Current password</label><input id="p_rpw" class="adm-input" type="password" name="remove_password" autocomplete="current-password" required></div>
            <button class="adm-btn adm-btn-danger"><?= icon('trash-2', 'h-4 w-4') ?> Remove backup email</button>
          </form>
          <?= $err('remove_password') ?>
        </details>
      </div>
    <?php endif ?>
    <?php if ($pendingBackup): ?>
      <form method="post" class="rounded-lg border border-steel/20 bg-mist/60 p-4">
        <?= csrf_field() ?><input type="hidden" name="action" value="backup_verify">
        <p class="text-sm">Enter the code sent to <strong><?= e($pendingBackup) ?></strong>:</p>
        <div class="mt-3 flex flex-wrap items-center gap-3"><?= $codeInput() ?><button class="adm-btn adm-btn-primary"><?= icon('check', 'h-4 w-4') ?> Verify backup email</button></div>
        <?= $err('backup_code') ?><?= $devHint ?>
      </form>
      <div class="flex gap-4 text-sm">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><input type="hidden" name="which" value="backup"><button class="font-semibold text-steel hover:underline">Resend code</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="backup_cancel"><button class="text-charcoal/60 hover:underline">Cancel</button></form>
      </div>
    <?php else: ?>
      <form method="post" class="space-y-4">
        <?= csrf_field() ?><input type="hidden" name="action" value="backup_start">
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="adm-field"><label class="adm-label" for="p_bk"><?= $user['backup_email'] ? 'New backup email' : 'Backup email' ?></label><input id="p_bk" class="adm-input" type="email" name="backup_email" value="<?= $val('backup_email') ?>" autocomplete="email" required><?= $err('backup_email') ?></div>
          <div class="adm-field"><label class="adm-label" for="p_bpw">Current password</label><input id="p_bpw" class="adm-input" type="password" name="backup_password" autocomplete="current-password" required><?= $err('backup_password') ?></div>
        </div>
        <div class="flex justify-end"><button class="adm-btn adm-btn-light"><?= icon('send', 'h-4 w-4') ?> <?= $user['backup_email'] ? 'Change &amp; verify' : 'Add &amp; verify' ?></button></div>
      </form>
    <?php endif ?>
  </section>
</div>

<!-- Recent activity -->
<aside class="adm-card h-max p-6">
  <h2 class="adm-subheading flex items-center gap-2"><?= icon('history', 'h-5 w-5 text-steel') ?> Recent security activity</h2>
  <?php if (!$events): ?><p class="mt-3 text-sm text-charcoal/60">No activity yet.</p><?php else: ?>
  <ul class="mt-4 space-y-3 text-sm">
    <?php foreach ($events as $ev): $bad = str_contains($ev['event'], 'fail') || str_contains($ev['event'], 'locked'); ?>
    <li class="flex gap-2"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= $bad ? 'bg-red-500' : 'bg-emerald-500' ?>"></span>
      <span><span class="font-medium text-navy"><?= e($labels[$ev['event']] ?? ucfirst(str_replace('_', ' ', $ev['event']))) ?></span><br><span class="text-xs text-charcoal/55"><?= e(date('j M H:i', strtotime($ev['created_at']))) ?> · <?= e((string) $ev['ip']) ?></span></span></li>
    <?php endforeach ?>
  </ul><?php endif ?>
  <p class="mt-5 text-xs text-charcoal/50">Don’t recognise something? Change your password now — it signs out every other session.</p>
</aside>
</div>
