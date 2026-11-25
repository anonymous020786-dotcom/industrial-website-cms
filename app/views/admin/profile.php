<?php
/** @var string $tgLinkUrl @var array $devices @var string $currentDevice @var array $user @var array $errors @var array $old @var array $events @var ?string $pendingEmail @var ?string $pendingBackup @var ?string $devCode
 *  @var array $passkeys @var bool $totpOn @var ?string $totpPending @var ?array $recoveryOnce @var int $recoveryLeft */
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
    'passkey_added' => 'Passkey added', 'passkey_removed' => 'Passkey removed', 'passkey_login' => 'Signed in with a passkey', 'passkey_failed' => 'Passkey check failed',
    'totp_enabled' => 'Authenticator app enabled', 'totp_disabled' => 'Authenticator app turned off', 'totp_failed' => 'Wrong authenticator code',
    'recovery_code_used' => 'Recovery code used', 'recovery_codes_regenerated' => 'New recovery codes', 'backup_downloaded' => 'Backup downloaded',
    'login_remembered' => 'Signed in (kept signed in)', 'device_trusted' => 'Device trusted for codes', 'device_untrusted' => 'Stopped trusting a device', 'device_removed' => 'Device removed', 'signout_everywhere' => 'Signed out of all other devices', 'remember_token_mismatch' => 'Blocked an invalid sign-in token',
    'telegram_linked' => 'Connected Telegram', 'telegram_unlinked' => 'Disconnected Telegram', 'telegram_approved' => 'Approved a sign-in in Telegram', 'telegram_denied' => 'Blocked a sign-in in Telegram',
    'login_telegram_approval' => 'Signed in (approved in Telegram)', 'telegram_2fa_on' => 'Turned on Telegram sign-in code', 'telegram_2fa_off' => 'Turned off Telegram sign-in code',
    'maintenance_on' => 'Turned on maintenance mode', 'maintenance_off' => 'Turned off maintenance mode',
    'stepup_ok' => 'Confirmed a sensitive action', 'stepup_required' => 'Confirmation requested', 'stepup_failed' => 'Wrong confirmation code',
    'avatar_changed' => 'Profile picture changed', 'leads_exported' => 'Leads exported', 'backup_created' => 'Backup created', 'backup_deleted' => 'Backup deleted', 'stepup_code_sent' => 'Confirmation code sent',
];
$transportLabel = fn(?string $t) => str_contains((string) $t, 'internal') ? 'This device (built-in)' : (str_contains((string) $t, 'hybrid') ? 'Phone' : ($t ? 'Security key' : 'Passkey'));
?>
<div class="grid gap-6 xl:grid-cols-[1fr_20rem]">
<div class="space-y-6">

  <!-- Profile picture -->
  <section class="adm-card flex flex-wrap items-center gap-5 p-6" id="picture" aria-labelledby="pic-title">
    <?= user_avatar_html($user, 'adm-avatar-lg') ?>
    <div class="min-w-0 flex-1">
      <h2 class="adm-subheading" id="pic-title">Profile picture</h2>
      <p class="mt-1 text-sm text-charcoal/75">Shown in the admin header and next to your name. JPG, PNG or WebP up to 5 MB — cropped to a square and cleaned of hidden data (EXIF/GPS).</p>
      <div class="mt-3 flex flex-wrap items-center gap-3">
        <form method="post" action="<?= e(url('admin/avatar')) ?>" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2" data-avatar-form>
          <?= csrf_field() ?>
          <label class="adm-btn adm-btn-light cursor-pointer"><?= icon('upload', 'h-4 w-4') ?> <?= !empty($user['avatar']) ? 'Change picture' : 'Upload picture' ?><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only" data-avatar-input required></label>
        </form>
        <?php if (!empty($user['avatar'])): ?><form method="post" action="<?= e(url('admin/avatar/remove')) ?>"><?= csrf_field() ?><button class="adm-link text-sm" data-confirm="Remove your profile picture?">Remove</button></form><?php endif ?>
      </div>
    </div>
  </section>

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

  <!-- Passkeys -->
  <section class="adm-card space-y-4 p-6" id="passkeys">
    <div>
      <h2 class="adm-subheading flex items-center gap-2"><?= icon('fingerprint', 'h-5 w-5 text-steel') ?> Passkeys</h2>
      <p class="mt-1 text-sm text-charcoal/70">Sign in with your fingerprint, face or device PIN instead of a password — Windows Hello, Touch ID / Face ID, Android or a security key. Passkeys cannot be phished or reused on other sites, and include two-factor protection by themselves.</p>
    </div>
    <?php if ($passkeys): ?>
    <ul class="divide-y divide-black/5 rounded-lg border border-black/10">
      <?php foreach ($passkeys as $pk): ?>
      <li class="flex flex-wrap items-center gap-3 p-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-mist text-steel"><?= icon('key-round', 'h-4 w-4') ?></span>
        <div class="min-w-0 flex-1">
          <form method="post" action="<?= e(url('admin/passkeys/' . $pk['id'] . '/rename')) ?>" class="flex items-center gap-2"><?= csrf_field() ?>
            <input name="name" value="<?= e($pk['name']) ?>" class="adm-input !py-1 text-sm font-semibold" maxlength="120" aria-label="Passkey name"><button class="adm-icon-btn" title="Save name"><?= icon('check', 'h-4 w-4') ?></button>
          </form>
          <p class="mt-1 text-xs text-charcoal/60"><?= e($transportLabel($pk['transports'])) ?><?= $pk['backed_up'] ? ' · synced' : '' ?> · added <?= e(date('j M Y', strtotime($pk['created_at']))) ?> · <?= $pk['last_used_at'] ? 'last used ' . e(time_ago($pk['last_used_at'])) : 'not used yet' ?></p>
        </div>
        <details class="text-sm">
          <summary class="cursor-pointer font-semibold text-red-700">Remove</summary>
          <form method="post" action="<?= e(url('admin/passkeys/' . $pk['id'] . '/delete')) ?>" class="mt-2 flex flex-wrap items-end gap-2"><?= csrf_field() ?>
            <input type="password" name="password" class="adm-input !w-48" placeholder="Current password" autocomplete="current-password" required aria-label="Current password">
            <button class="adm-btn adm-btn-danger adm-btn-sm">Remove passkey</button>
          </form>
        </details>
      </li>
      <?php endforeach ?>
    </ul>
    <?php endif ?>
    <form class="space-y-3 rounded-lg border border-dashed border-black/15 p-4" data-passkey-add novalidate>
      <p class="text-sm font-semibold text-navy"><?= $passkeys ? 'Add another passkey' : 'Add a passkey' ?></p>
      <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="adm-label" for="pk_name">Name</label><input id="pk_name" name="pk_name" class="adm-input" maxlength="120" placeholder="e.g. Work laptop, iPhone"></div>
        <div><label class="adm-label" for="pk_pw">Current password</label><input id="pk_pw" name="password" type="password" class="adm-input" autocomplete="current-password" required></div>
      </div>
      <div class="flex flex-wrap items-center gap-3"><button type="submit" class="adm-btn adm-btn-primary"><?= icon('plus', 'h-4 w-4') ?> Create passkey</button><p class="text-sm text-red-700" role="alert" data-passkey-add-error></p></div>
      <p class="text-xs text-charcoal/60">Passkeys work on this exact website address (<?= e(webauthn_rp_id()) ?>). After moving to a new domain, add them again.</p>
    </form>
  </section>

  <!-- Authenticator app -->
  <section class="adm-card space-y-4 p-6" id="two-factor">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="adm-subheading flex items-center gap-2"><?= icon('smartphone', 'h-5 w-5 text-steel') ?> Authenticator app</h2>
        <p class="mt-1 text-sm text-charcoal/70">Ask for a 6-digit code from Google Authenticator, Microsoft Authenticator, 1Password, Bitwarden or Authy after your password. Stronger than emailed codes — it still works if your email is compromised.</p>
      </div>
      <?= $totpOn ? '<span class="adm-badge bg-emerald-100 text-emerald-800">On</span>' : '<span class="adm-badge bg-gray-200 text-gray-700">Off</span>' ?>
    </div>

    <?php if ($recoveryOnce): ?>
    <div class="rounded-lg border-2 border-amber-300 bg-amber-50 p-4">
      <p class="font-semibold text-amber-900"><?= icon('triangle-alert', 'mr-1 inline h-4 w-4') ?> Save these recovery codes now — they will not be shown again</p>
      <p class="mt-1 text-sm text-amber-900">Each code works once, if you lose your phone. Store them in a password manager or print them.</p>
      <ol class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-5"><?php foreach ($recoveryOnce as $c): ?><li class="rounded bg-white px-2 py-1 text-center"><?= e($c) ?></li><?php endforeach ?></ol>
      <button type="button" class="adm-btn adm-btn-light adm-btn-sm mt-3" data-copy="<?= e(implode("\n", $recoveryOnce)) ?>"><?= icon('copy', 'h-3.5 w-3.5') ?> Copy all</button>
    </div>
    <?php endif ?>

    <?php if ($totpOn): ?>
      <p class="text-sm text-charcoal/70"><?= icon('circle-check', 'mr-1 inline h-4 w-4 text-emerald-600') ?> On since <?= e(date('j M Y', strtotime((string) $user['totp_enabled_at']))) ?> · <strong><?= (int) $recoveryLeft ?></strong> unused recovery code<?= $recoveryLeft === 1 ? '' : 's' ?><?= $recoveryLeft <= 3 ? ' — create new ones soon' : '' ?>.</p>
      <div class="flex flex-wrap gap-4">
        <details class="text-sm"><summary class="cursor-pointer font-semibold text-steel">New recovery codes</summary>
          <form method="post" action="<?= e(url('admin/2fa/recovery')) ?>" class="mt-2 flex flex-wrap items-end gap-2"><?= csrf_field() ?><input type="password" name="password" class="adm-input !w-48" placeholder="Current password" autocomplete="current-password" required aria-label="Current password"><button class="adm-btn adm-btn-light adm-btn-sm">Generate</button></form></details>
        <details class="text-sm"><summary class="cursor-pointer font-semibold text-red-700">Turn off</summary>
          <form method="post" action="<?= e(url('admin/2fa/disable')) ?>" class="mt-2 flex flex-wrap items-end gap-2"><?= csrf_field() ?><input type="password" name="password" class="adm-input !w-48" placeholder="Current password" autocomplete="current-password" required aria-label="Current password"><button class="adm-btn adm-btn-danger adm-btn-sm" data-confirm="Turn off authenticator-app sign-in?">Turn off</button></form></details>
      </div>
    <?php elseif ($totpPending): $uri = totp_uri($user, $totpPending); ?>
      <div class="grid gap-6 rounded-lg border border-steel/20 bg-mist/60 p-4 sm:grid-cols-[auto_1fr]">
        <div class="grid h-[200px] w-[200px] place-items-center bg-white" data-qr="<?= e($uri) ?>"><span class="text-xs text-charcoal/60">QR code</span></div>
        <div class="space-y-3 text-sm">
          <p><strong>1.</strong> Scan the QR code with your authenticator app.</p>
          <p class="text-charcoal/70">Can’t scan? Enter this key manually (time-based, 6 digits):</p>
          <code class="block break-all rounded bg-white px-3 py-2 font-mono text-sm tracking-wider"><?= e(trim(chunk_split($totpPending, 4, ' '))) ?></code>
          <form method="post" action="<?= e(url('admin/2fa/confirm')) ?>" class="space-y-2"><?= csrf_field() ?>
            <label class="block" for="totp_code"><strong>2.</strong> Enter the 6-digit code it shows:</label>
            <div class="flex flex-wrap items-center gap-3"><input id="totp_code" class="adm-input max-w-[12rem] text-center font-mono text-xl tracking-[0.4em]" name="code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required><button class="adm-btn adm-btn-primary"><?= icon('check', 'h-4 w-4') ?> Turn on</button></div>
          </form>
        </div>
      </div>
      <script src="<?= e(url('assets/vendor/qrcode/qrcode.js')) ?>"></script>
    <?php else: ?>
      <form method="post" action="<?= e(url('admin/2fa/setup')) ?>" class="flex flex-wrap items-end gap-3"><?= csrf_field() ?>
        <div><label class="adm-label" for="totp_pw">Current password</label><input id="totp_pw" type="password" name="password" class="adm-input" autocomplete="current-password" required></div>
        <button class="adm-btn adm-btn-primary"><?= icon('qr-code', 'h-4 w-4') ?> Set up authenticator app</button>
      </form>
    <?php endif ?>
  </section>
</div>

<!-- Telegram -->
<section class="adm-card space-y-4 p-6 xl:col-start-1" id="telegram">
  <?php $tgOn = telegram_enabled(); $tgLinked = telegram_linked($user); $tgp = telegram_prefs($user); ?>
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <h2 class="adm-subheading flex items-center gap-2"><?= icon('send', 'h-5 w-5 text-steel') ?> Telegram</h2>
      <p class="mt-1 text-sm text-charcoal/75">Get sign-in codes, approve sign-ins with one tap, and receive security and lead alerts in Telegram — faster than email and works on any phone.</p>
    </div>
    <?php if ($tgLinked): ?><span class="adm-badge bg-sky-100 text-sky-800"><?= icon('circle-check', 'mr-1 inline h-3.5 w-3.5') ?>Connected<?= $user['telegram_username'] ? ' · @' . e((string) $user['telegram_username']) : '' ?></span><?php endif ?>
  </div>

  <?php if (!$tgOn): ?>
    <p class="rounded-lg bg-mist p-4 text-sm text-charcoal/80">Telegram isn’t set up on this site yet.<?php if (can('settings')): ?> Add your bot in <a class="adm-link" href="<?= e(url('admin/settings/telegram')) ?>">Settings → Telegram</a> (takes two minutes with @BotFather).<?php else: ?> Ask an administrator to add a Telegram bot.<?php endif ?></p>
  <?php elseif (!$tgLinked && $tgLinkUrl !== ''): ?>
    <div class="grid gap-5 rounded-lg border border-sky-200 bg-sky-50/60 p-5 sm:grid-cols-[auto_1fr]" data-tg-wait>
      <div class="h-fit rounded-lg bg-white p-2" data-qr="<?= e($tgLinkUrl) ?>" data-qr-size="150" aria-label="QR code to open the bot on your phone"></div>
      <script src="<?= e(url('assets/vendor/qrcode/qrcode.js')) ?>"></script>
      <div>
        <p class="font-semibold text-navy">1. Open the bot and press <span class="rounded bg-white px-1.5 font-mono text-sm">Start</span></p>
        <p class="mt-1 text-sm text-charcoal/75">On this computer use the button, or scan the QR code with your phone’s camera. The link works once, for 10 minutes.</p>
        <a href="<?= e($tgLinkUrl) ?>" target="_blank" rel="noopener" class="adm-btn adm-btn-primary mt-3"><?= icon('send', 'h-4 w-4') ?> Open @<?= e((string) setting('telegram_bot_username')) ?> in Telegram</a>
        <p class="mt-4 flex items-center gap-2 text-sm text-charcoal/75" role="status"><span class="adm-spinner" aria-hidden="true"></span> 2. Waiting for Telegram… this page updates by itself.</p>
      </div>
    </div>
  <?php elseif (!$tgLinked): ?>
    <form method="post" action="<?= e(url('admin/telegram/link')) ?>" class="flex flex-wrap items-center gap-3"><?= csrf_field() ?>
      <button class="adm-btn adm-btn-primary"><?= icon('send', 'h-4 w-4') ?> Connect Telegram</button>
      <span class="text-sm text-charcoal/70">You’ll confirm it’s you, then press Start in our bot @<?= e((string) setting('telegram_bot_username')) ?>.</span>
    </form>
  <?php else: ?>
    <form method="post" action="<?= e(url('admin/telegram/prefs')) ?>" class="space-y-3"><?= csrf_field() ?>
      <?php
      $rows = [
          'codes' => ['Sign-in and confirmation codes', 'Send one-time codes to Telegram instead of email. If Telegram is unreachable, the code is emailed.'],
          'approve' => ['One-tap sign-in approval', 'Sign-in codes come with Approve / Deny buttons — tap Approve and the browser continues by itself.'],
          'require' => ['Ask for a Telegram code at every sign-in', 'Personal two-step sign-in. Devices you trust skip it for ' . (int) setting('otp_trust_days', 30) . ' days.'],
          'alerts' => ['Security alerts', 'New-device sign-ins, password and email changes, blocked sign-ins' . (can('settings') ? ', site errors and maintenance' : '') . '.'],
      ];
      if (can('leads')) $rows['leads'] = ['New lead alerts', 'Each new enquiry or quote request, with a button to open it.'];
      foreach ($rows as $k => [$l, $h]): ?>
      <label class="flex items-start gap-3 rounded-lg border border-black/10 p-3 hover:bg-mist/60">
        <input type="checkbox" class="mt-1" name="tg[<?= $k ?>]" value="1"<?= $tgp[$k] ? ' checked' : '' ?><?= $k === 'approve' && !setting('telegram_login_approval', true) ? ' disabled' : '' ?>>
        <span><span class="block font-semibold text-navy"><?= e($l) ?></span><span class="block text-sm text-charcoal/70"><?= e($h) ?></span></span>
      </label>
      <?php endforeach ?>
      <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
        <span class="text-xs text-charcoal/60">Connected <?= e(time_ago((string) $user['telegram_linked_at'])) ?>.</span>
        <button class="adm-btn adm-btn-primary"><?= icon('save', 'h-4 w-4') ?> Save Telegram settings</button>
      </div>
    </form>
    <div class="flex flex-wrap gap-2 border-t border-black/5 pt-4">
      <form method="post" action="<?= e(url('admin/telegram/test')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn-light adm-btn-sm"><?= icon('send', 'h-4 w-4') ?> Send a test message</button></form>
      <form method="post" action="<?= e(url('admin/telegram/unlink')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn-light adm-btn-sm !text-red-700" data-confirm="Disconnect Telegram? Codes and alerts will be emailed instead."><?= icon('unlink', 'h-4 w-4') ?> Disconnect</button></form>
    </div>
  <?php endif ?>
</section>

<!-- Devices & sessions -->
<section class="adm-card space-y-4 p-6 xl:col-start-1" id="devices">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <h2 class="adm-subheading flex items-center gap-2"><?= icon('monitor-smartphone', 'h-5 w-5 text-steel') ?> Devices &amp; sessions</h2>
      <p class="mt-1 text-sm text-charcoal/75">Browsers that have signed in to your account. <strong>Trusted</strong> devices skip the sign-in code; <strong>kept signed in</strong> devices open the admin without a password. Sensitive actions always ask for a one-time code.</p>
    </div>
    <?php if (count($devices) > 1): ?>
    <form method="post" action="<?= e(url('admin/devices/signout-all')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn-danger adm-btn-sm" data-confirm="Sign out every other browser and device? They will need your password (and code) again."><?= icon('log-out', 'h-4 w-4') ?> Sign out all other devices</button></form>
    <?php endif ?>
  </div>
  <?php if (!$devices): ?><p class="text-sm text-charcoal/75">No devices recorded yet.</p><?php else: ?>
  <?php
    // This browser first, then most recently active; older ones fold away after five
    usort($devices, fn ($a, $b) => [$currentDevice !== '' && hash_equals((string) $b['device_hash'], $currentDevice), $b['last_seen']] <=> [$currentDevice !== '' && hash_equals((string) $a['device_hash'], $currentDevice), $a['last_seen']]);
    $shown = 5; ?>
  <ul class="divide-y divide-black/5 rounded-lg border border-black/10">
    <?php foreach ($devices as $i => $d):
      if ($i === $shown): ?>
  </ul>
  <details class="group">
    <summary class="cursor-pointer select-none py-1 text-sm font-semibold text-steel hover:text-navy">Show <?= count($devices) - $shown ?> older device<?= count($devices) - $shown === 1 ? '' : 's' ?></summary>
  <ul class="mt-2 divide-y divide-black/5 rounded-lg border border-black/10">
      <?php endif;
      $cur = $currentDevice !== '' && hash_equals((string) $d['device_hash'], $currentDevice);
      $trusted = $d['trusted_until'] && strtotime((string) $d['trusted_until']) > time();
      $kept = $d['remember_until'] && strtotime((string) $d['remember_until']) > time();
      $ic = str_contains((string) $d['label'], 'Android') || str_contains((string) $d['label'], 'iOS') ? 'smartphone' : 'monitor'; ?>
    <li class="flex flex-wrap items-center gap-3 p-3">
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-mist text-steel"><?= icon($ic, 'h-5 w-5') ?></span>
      <div class="min-w-0 flex-1">
        <p class="flex flex-wrap items-center gap-2 font-semibold text-navy"><?= e((string) $d['label']) ?>
          <?php if ($cur): ?><span class="adm-badge bg-navy text-white">This device</span><?php endif ?>
          <?php if ($trusted): ?><span class="adm-badge bg-emerald-100 text-emerald-800" title="No sign-in code until <?= e(date('j M Y', strtotime($d['trusted_until']))) ?>">Trusted</span><?php endif ?>
          <?php if ($kept): ?><span class="adm-badge bg-sky-100 text-sky-800" title="Stays signed in until <?= e(date('j M Y', strtotime($d['remember_until']))) ?>">Kept signed in</span><?php endif ?>
        </p>
        <p class="text-xs text-charcoal/75">Last active <?= e(time_ago($d['last_seen'])) ?> · <?= e((string) $d['ip']) ?> · first seen <?= e(date('j M Y', strtotime($d['first_seen']))) ?><?= $trusted ? ' · trusted until ' . e(date('j M Y', strtotime($d['trusted_until']))) : '' ?></p>
      </div>
      <form method="post" action="<?= e(url('admin/devices/' . $d['id'])) ?>" class="flex gap-2">
        <?= csrf_field() ?>
        <?php if ($trusted): ?><button class="adm-btn adm-btn-light adm-btn-sm" name="action" value="untrust">Stop trusting</button><?php endif ?>
        <button class="adm-btn adm-btn-light adm-btn-sm !text-red-700" name="action" value="remove" data-confirm="Remove <?= e((string) $d['label']) ?>? It will no longer be trusted or kept signed in.">Remove</button>
      </form>
    </li>
    <?php endforeach ?>
  </ul>
  <?php if (count($devices) > $shown): ?></details><?php endif ?>
  <?php endif ?>
</section>

<!-- Notification preferences (full width under the security sections) -->
<?php $prefs = notify_prefs($user); ?>
<form method="post" action="<?= e(url('admin/notify-prefs')) ?>" class="adm-card space-y-4 p-6 xl:col-start-1" id="notifications">
  <?= csrf_field() ?>
  <div>
    <h2 class="adm-subheading flex items-center gap-2"><?= icon('bell', 'h-5 w-5 text-steel') ?> Notifications</h2>
    <p class="mt-1 text-sm text-charcoal/75">Every alert also appears under the bell in the header. Choose which ones are emailed to you.</p>
  </div>
  <ul class="space-y-3">
    <li class="flex gap-3 rounded-lg border border-black/10 bg-mist/60 p-3"><input type="checkbox" checked disabled class="mt-1" aria-label="Account security alerts (always on)"><span><span class="block font-semibold text-navy">Account security — always on</span><span class="text-sm text-charcoal/75">Password, email, backup email, passkeys, authenticator app and recovery codes, plus changes an administrator makes to your account. Sent to your sign-in email and verified backup email.</span></span></li>
    <?php foreach (NOTIFY_CATEGORIES as $k => [$label, $desc]): if ($k === 'admin' && $user['role'] !== 'admin') continue; ?>
    <li><label class="flex cursor-pointer gap-3 rounded-lg border border-black/10 p-3 hover:bg-mist/60"><input type="checkbox" name="notify[<?= e($k) ?>]" value="1" class="mt-1"<?= $prefs[$k] ? ' checked' : '' ?>><span><span class="block font-semibold text-navy">Email me: <?= e($label) ?></span><span class="text-sm text-charcoal/75"><?= e($desc) ?></span></span></label></li>
    <?php endforeach ?>
  </ul>
  <div class="flex justify-end"><button class="adm-btn adm-btn-primary"><?= icon('save', 'h-4 w-4') ?> Save preferences</button></div>
</form>

<!-- Recent activity -->
<aside class="adm-card h-max p-6 xl:row-span-2 xl:row-start-1 xl:col-start-2" id="security-activity">
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
<script src="<?= e(asset('js/passkey.js')) ?>" defer></script>
<script src="<?= e(asset('js/telegram.js')) ?>" defer></script>
