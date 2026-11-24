<?php
/** Maintenance page (HTTP 503). @var array $state @var bool $preview */
$name = company('name');
$title = (string) setting('maintenance_title', '') ?: 'We’ll be back shortly';
$message = (string) setting('maintenance_message', '') ?: 'We’re carrying out planned improvements to our website. Thank you for your patience — everything will be back online very soon.';
$until = $state['until'];
$late = $until && $until <= time();
$phone = company('phone'); $phoneHref = company('phone_href'); $email = company('email');
$wa = setting('whatsapp_number') ? preg_replace('~\D~', '', (string) setting('whatsapp_number')) : '';
$showContact = setting('maintenance_show_contact', true) && ($phone || $email || $wa);
$note = (string) setting('maintenance_contact_note', '') ?: 'Urgent breakdown or spare-part request? Our team is still available.';
?><!DOCTYPE html>
<html lang="<?= e(setting('site_language', 'en')) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#101D32">
  <title><?= e($title) ?> · <?= e($name) ?></title>
  <meta name="description" content="<?= e(str_limit($message, 155)) ?>">
  <?= view('site/_icons') ?>
  <link rel="preload" href="<?= e(url('assets/fonts/barlow-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="<?= e(url('assets/fonts/barlow-condensed-latin-700-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
</head>
<body class="blueprint min-h-screen bg-navy font-sans text-white antialiased" data-maintenance data-preview="<?= $preview ? '1' : '0' ?>"<?= $until ? ' data-until="' . ($until * 1000) . '"' : '' ?>>
  <?php if ($preview): ?>
  <div class="bg-safety px-4 py-2 text-center text-sm font-semibold text-navy">Preview — this is what visitors see<?= $state['active'] ? '' : ' when maintenance mode is on' ?>. <a class="underline" href="<?= e(url('admin/settings/maintenance')) ?>">Back to settings</a></div>
  <?php endif ?>
  <div class="mx-auto flex min-h-screen w-full max-w-3xl flex-col px-5 py-10 sm:px-8">
    <header><?= view('site/_logo', ['dark' => true]) ?></header>

    <main class="flex flex-1 flex-col justify-center py-14">
      <p class="inline-flex w-fit items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white/80">
        <span class="maint-pulse h-2 w-2 rounded-full bg-safety" aria-hidden="true"></span> <?= $state['source'] === 'scheduled' ? 'Scheduled maintenance' : 'Maintenance in progress' ?>
      </p>
      <h1 class="mt-6 font-display text-5xl font-bold uppercase leading-[0.95] tracking-tight sm:text-7xl"><?= e($title) ?></h1>
      <p class="mt-6 max-w-2xl text-lg leading-relaxed text-white/80"><?= nl2br(e($message)) ?></p>

      <?php if ($until): ?>
      <div class="mt-10" aria-live="polite">
        <?php if ($late): ?>
          <p class="text-white/80"><?= icon('clock', 'mr-1 inline h-5 w-5 text-safety') ?> This is taking a little longer than planned — we’re nearly done.</p>
        <?php else: ?>
          <p class="text-sm font-semibold uppercase tracking-[0.16em] text-white/65">Expected back <time datetime="<?= e(date('c', $until)) ?>"><?= e(maintenance_time($until)) ?></time> <span class="normal-case tracking-normal">(<?= e(date_default_timezone_get()) ?>)</span></p>
          <div class="mt-4 flex gap-3" data-countdown hidden>
            <?php foreach (['d' => 'Days', 'h' => 'Hours', 'm' => 'Minutes', 's' => 'Seconds'] as $k => $l): ?>
            <div class="maint-cell w-20 rounded-lg border border-white/15 bg-white/5 py-3 text-center sm:w-24"<?= $k === 'd' ? ' data-days' : '' ?>><span class="block font-display text-4xl font-bold tabular-nums" data-unit="<?= $k ?>">00</span><span class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/60"><?= $l ?></span></div>
            <?php endforeach ?>
          </div>
        <?php endif ?>
      </div>
      <?php endif ?>

      <div class="maint-bar mt-10 h-1 w-full max-w-md overflow-hidden rounded-full bg-white/10" aria-hidden="true"><span></span></div>
      <p class="mt-3 text-sm text-white/65">This page reloads by itself as soon as we’re back online.</p>

      <?php if ($showContact): ?>
      <div class="mt-12 rounded-xl border border-white/15 bg-white/5 p-6">
        <p class="font-semibold"><?= e($note) ?></p>
        <div class="mt-4 flex flex-wrap gap-3">
          <?php if ($phone): ?><a href="tel:<?= e($phoneHref ?: preg_replace('~[^\d+]~', '', $phone)) ?>" class="btn btn-primary"><?= icon('phone', 'h-4 w-4') ?> <?= e($phone) ?></a><?php endif ?>
          <?php if ($email): ?><a href="mailto:<?= e($email) ?>" class="btn btn-ghost-light"><?= icon('mail', 'h-4 w-4') ?> <?= e($email) ?></a><?php endif ?>
          <?php if ($wa): ?><a href="https://wa.me/<?= e($wa) ?>" class="btn btn-ghost-light" rel="noopener" target="_blank"><?= icon('message-circle', 'h-4 w-4') ?> WhatsApp</a><?php endif ?>
        </div>
      </div>
      <?php endif ?>
    </main>

    <footer class="border-t border-white/10 pt-6 text-sm text-white/60">© <?= date('Y') ?> <?= e(company('legal_name') ?: $name) ?></footer>
  </div>
  <script src="<?= e(asset('js/maintenance.js')) ?>" defer></script>
</body>
</html>
