<?php /** @var array $methods @var ?string $error @var bool $emailSent @var string $masked @var string $label @var string $back @var ?string $devCode */ ?>
<div class="mx-auto max-w-xl">
  <div class="adm-card p-8">
    <span class="grid h-12 w-12 place-items-center rounded-full bg-mist text-steel"><?= icon('shield-check', 'h-6 w-6') ?></span>
    <h2 class="mt-4 text-xl font-bold text-navy">Confirm it’s you</h2>
    <p class="mt-1 text-sm text-charcoal/75"><strong class="text-navy"><?= e($label) ?></strong> is a sensitive action. Verify with a one-time code<?= $methods['passkey'] ? ' or a passkey' : '' ?> — you won’t be asked again for 10 minutes.</p>

    <?php if ($error): ?><div class="adm-flash adm-flash-error mt-5" role="alert"><?= icon('circle-alert', 'h-5 w-5 shrink-0') ?><span><?= e($error) ?></span></div><?php endif ?>

    <?php if ($methods['passkey']): ?>
    <div class="mt-6" data-stepup-passkey-box>
      <button type="button" class="adm-btn adm-btn-primary w-full justify-center py-3" data-stepup-passkey><?= icon('fingerprint', 'h-5 w-5') ?> Use a passkey</button>
      <p class="mt-2 text-center text-xs text-red-700" role="alert" data-stepup-passkey-error></p>
    </div>
    <?php endif ?>

    <?php if ($methods['totp']): ?>
    <form method="post" class="mt-6 space-y-3 border-t border-black/5 pt-6" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="method" value="totp">
      <label class="adm-label" for="totp_code">Code from your authenticator app</label>
      <div class="flex gap-2"><input id="totp_code" name="code" class="adm-input text-center font-mono text-xl tracking-[0.4em]" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required <?= $methods['passkey'] ? '' : 'autofocus' ?>><button class="adm-btn adm-btn-primary shrink-0">Confirm</button></div>
    </form>
    <?php endif ?>

    <div class="mt-6 border-t border-black/5 pt-6">
      <?php if ($emailSent): ?>
      <form method="post" class="space-y-3" autocomplete="off">
        <?= csrf_field() ?><input type="hidden" name="method" value="email">
        <label class="adm-label" for="email_code"><?= ($via ?? 'email') === 'telegram' ? 'Code we sent to your Telegram' : 'Code we emailed to ' . e($masked) ?></label>
        <div class="flex gap-2"><input id="email_code" name="code" class="adm-input text-center font-mono text-xl tracking-[0.4em]" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required <?= $methods['passkey'] || $methods['totp'] ? '' : 'autofocus' ?>><button class="adm-btn adm-btn-primary shrink-0">Confirm</button></div>
        <?php if ($devCode): ?><p class="rounded bg-amber-50 px-3 py-2 text-xs text-amber-900"><?= icon('bug', 'mr-1 inline h-3.5 w-3.5') ?>Debug mode — code: <code class="font-bold"><?= e($devCode) ?></code> (never shown when debug is off)</p><?php endif ?>
      </form>
      <form method="post" class="mt-3"><?= csrf_field() ?><input type="hidden" name="method" value="send"><button class="adm-link text-sm">Send a new code</button></form>
      <?php else: ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="method" value="send"><button class="adm-btn adm-btn-light w-full justify-center"><?= icon(!empty($tgCodes) ? 'send' : 'mail', 'h-4 w-4') ?> <?= !empty($tgCodes) ? 'Send a code to my Telegram' : 'Email a code to ' . e($masked) ?></button></form>
      <?php endif ?>
    </div>
    <p class="mt-6 text-center text-sm"><a class="text-charcoal/75 hover:text-navy" href="<?= e($back) ?>">Cancel</a></p>
  </div>
  <p class="mt-4 text-center text-xs text-charcoal/75">Codes expire after 10 minutes and allow 5 attempts. Every confirmation is recorded in your security activity.</p>
</div>
<script src="<?= e(asset('js/passkey.js')) ?>" defer></script>
