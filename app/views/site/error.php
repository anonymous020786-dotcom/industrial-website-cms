<?php
/** @var int $status @var string $title @var string $text @var string $icon @var ?string $ref @var int $retryAfter @var string $back @var string $path @var mixed $debug @var array $suggestions */
$suggestions ??= [];
$phone = company('phone'); $phoneHref = company('phone_href'); $email = company('email');
$serverSide = $status >= 500;
$links = [
    ['Products', 'Hydraulic cylinders, power packs and crane parts', 'products', 'package'],
    ['Industries', 'Construction, mining, ports, logistics and more', 'industries', 'factory'],
    ['Projects', 'Recent work and case studies', 'projects', 'briefcase-business'],
    ['Request a quote', 'Tell us what you need — we reply within one working day', 'quote', 'file-text'],
];
?>
<section class="blueprint relative overflow-hidden bg-navy text-white">
  <div class="mx-auto grid w-full max-w-site items-center gap-10 px-4 py-20 sm:px-6 lg:grid-cols-[1fr_auto] lg:px-8 lg:py-28">
    <div>
      <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white/75"><?= icon($icon, 'h-4 w-4 text-safety') ?> Error <?= (int) $status ?></p>
      <h1 class="mt-5 font-display text-4xl font-bold uppercase tracking-tight sm:text-6xl"><?= e($title) ?></h1>
      <p class="mt-4 max-w-2xl text-lg leading-relaxed text-white/80"><?= e($text) ?></p>

      <?php if ($status === 429 && $retryAfter): ?>
        <p class="mt-3 text-white/75">You can try again in about <strong class="text-white"><?= $retryAfter < 90 ? (int) $retryAfter . ' seconds' : (int) ceil($retryAfter / 60) . ' minutes' ?></strong>.</p>
      <?php endif ?>

      <div class="mt-8 flex flex-wrap gap-3">
        <?php if (in_array($status, [400, 405, 413, 419, 422], true) && $back): ?>
          <a href="<?= e($back) ?>" class="btn btn-primary"><?= icon('arrow-left', 'h-4 w-4') ?> Go back and try again</a>
          <a href="<?= e(url('')) ?>" class="btn btn-ghost-light">Home page</a>
        <?php elseif (($serverSide || $status === 429 || $status === 408) && $retryable): ?>
          <a href="<?= e($path !== '' ? url(ltrim($path, '/')) : url('')) ?>" class="btn btn-primary"><?= icon('refresh-cw', 'h-4 w-4') ?> Try again</a>
          <a href="<?= e(url('')) ?>" class="btn btn-ghost-light">Home page</a>
        <?php else: ?>
          <a href="<?= e(url('')) ?>" class="btn btn-primary">Back to home <?= icon('arrow-right', 'btn-arrow h-4 w-4') ?></a>
          <a href="<?= e(url('contact')) ?>" class="btn btn-ghost-light">Contact us</a>
        <?php endif ?>
      </div>

      <?php if ($ref): ?>
      <div class="mt-8 max-w-xl rounded-lg border border-white/15 bg-white/5 p-4 text-sm text-white/80">
        <p class="flex flex-wrap items-center gap-2"><?= icon('bug', 'h-4 w-4 text-safety') ?> Error reference: <code class="select-all rounded bg-white/10 px-2 py-0.5 font-mono font-bold text-white"><?= e($ref) ?></code></p>
        <p class="mt-1">Our team has been alerted. If you contact us, quote this reference so we can find exactly what happened.</p>
      </div>
      <?php endif ?>
      <?php if ($status === 404): ?>
      <p class="mt-8 flex items-center gap-2 text-sm text-white/70"><?= icon('circle-check', 'h-4 w-4 shrink-0 text-safety') ?> We’ve recorded this broken address so our team can fix or redirect it.</p>
      <?php endif ?>
    </div>
    <p class="pointer-events-none hidden select-none font-display text-[220px] font-bold leading-none text-white/[0.07] lg:block lg:text-[280px]" aria-hidden="true"><?= (int) $status ?></p>
  </div>
</section>

<?php if ($debug): ?>
<section class="bg-red-50 py-6"><div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8"><p class="text-sm font-bold text-red-800">Debug mode — details are visible only because debug is on in app/config.php</p><pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap rounded bg-white p-4 text-xs text-red-900"><?= e((string) $debug) ?></pre></div></section>
<?php endif ?>

<section class="bg-white py-16">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?php if ($suggestions): ?>
    <h2 class="font-display text-2xl font-bold uppercase tracking-tight text-navy">Were you looking for…</h2>
    <ul class="mt-6 grid gap-4 sm:grid-cols-2">
      <?php foreach ($suggestions as $s): ?>
      <li><a href="<?= e($s['url']) ?>" class="group flex items-center gap-4 rounded-lg border border-black/10 p-5 transition hover:border-safety hover:shadow-md">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-safety/10 text-safety"><?= icon('arrow-right', 'h-5 w-5') ?></span>
        <span class="min-w-0"><span class="block text-xs font-semibold uppercase tracking-wider text-steel"><?= e($s['kind']) ?></span><span class="block truncate font-semibold text-navy group-hover:text-safety"><?= e($s['title']) ?></span></span>
      </a></li>
      <?php endforeach ?>
    </ul>
    <h2 class="mt-14 font-display text-2xl font-bold uppercase tracking-tight text-navy">Or start here</h2>
    <?php else: ?>
    <h2 class="font-display text-2xl font-bold uppercase tracking-tight text-navy"><?= $serverSide ? 'While we fix this' : 'Popular pages' ?></h2>
    <?php endif ?>
    <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach ($links as [$l, $d, $u, $ic]): ?>
      <li><a href="<?= e(url($u)) ?>" class="group block h-full rounded-lg border border-black/10 p-5 transition hover:border-safety hover:shadow-md">
        <?= icon($ic, 'h-6 w-6 text-steel group-hover:text-safety') ?>
        <span class="mt-3 block font-semibold text-navy"><?= e($l) ?></span>
        <span class="mt-1 block text-sm text-charcoal/75"><?= e($d) ?></span>
      </a></li>
      <?php endforeach ?>
    </ul>

    <?php if ($phone || $email): ?>
    <div class="mt-10 flex flex-col gap-4 rounded-lg bg-mist p-6 sm:flex-row sm:items-center sm:justify-between">
      <p class="text-charcoal/80"><strong class="text-navy">Need a part or a quote urgently?</strong> Our engineers can help directly.</p>
      <div class="flex flex-wrap gap-3">
        <?php if ($phone): ?><a href="tel:<?= e($phoneHref ?: preg_replace('~[^\d+]~', '', $phone)) ?>" class="btn btn-primary"><?= icon('phone', 'h-4 w-4') ?> <?= e($phone) ?></a><?php endif ?>
        <?php if ($email): ?><a href="mailto:<?= e($email) ?><?= $ref ? '?subject=' . rawurlencode('Website error ' . $ref) : '' ?>" class="btn btn-outline"><?= icon('mail', 'h-4 w-4') ?> Email us</a><?php endif ?>
      </div>
    </div>
    <?php endif ?>
  </div>
</section>
