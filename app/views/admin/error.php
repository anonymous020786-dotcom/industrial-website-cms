<?php
/** @var int $status @var string $title @var string $text @var string $icon @var ?string $ref @var int $retryAfter @var string $back @var string $path @var mixed $debug */
$user = current_user();
$tone = $status >= 500 ? 'bg-red-50 text-red-700' : ($status === 403 ? 'bg-amber-50 text-amber-700' : 'bg-mist text-steel');
$admins = $status === 403 ? db_all("SELECT name, email FROM users WHERE role = 'admin' AND active = 1 ORDER BY id LIMIT 3") : [];
?>
<div class="adm-card mx-auto max-w-2xl p-8 text-center sm:p-12">
  <span class="mx-auto grid h-16 w-16 place-items-center rounded-full <?= $tone ?>"><?= icon($icon, 'h-8 w-8') ?></span>
  <p class="mt-5 text-xs font-bold uppercase tracking-[0.2em] text-charcoal/60">Error <?= (int) $status ?></p>
  <h2 class="mt-1 text-2xl font-bold text-navy"><?= e($title) ?></h2>
  <p class="mx-auto mt-3 max-w-lg leading-relaxed text-charcoal/75"><?= e($text) ?></p>

  <?php if ($status === 403 && $user): ?>
  <p class="mx-auto mt-4 max-w-lg rounded-lg bg-mist px-4 py-3 text-sm text-charcoal/80">You are signed in as <strong><?= e($user['name']) ?></strong> with the <strong><?= e(ucfirst((string) $user['role'])) ?></strong> role.
    <?php if ($admins): ?>To get access, ask an administrator: <?= implode(', ', array_map(fn($a) => '<a class="adm-link" href="mailto:' . e($a['email']) . '?subject=' . rawurlencode('Admin access request: ' . $path) . '">' . e($a['name']) . '</a>', $admins)) ?>.<?php endif ?></p>
  <?php endif ?>

  <?php if ($status === 429 && $retryAfter): ?><p class="mt-3 text-sm text-charcoal/75">Try again in about <?= $retryAfter < 90 ? (int) $retryAfter . ' seconds' : (int) ceil($retryAfter / 60) . ' minutes' ?>.</p><?php endif ?>

  <?php if ($ref): ?>
  <div class="mx-auto mt-6 max-w-md rounded-lg border border-black/10 p-4 text-left text-sm">
    <p class="flex items-center justify-between gap-3"><span class="text-charcoal/70">Reference</span><code class="font-mono font-bold text-navy"><?= e($ref) ?></code><button type="button" class="adm-btn adm-btn-light adm-btn-sm" data-copy="<?= e($ref) ?>">Copy</button></p>
    <?php if (can('settings')): ?><p class="mt-2 text-charcoal/70">Details are in the <a class="adm-link" href="<?= e(url('admin/errors?ref=' . rawurlencode($ref))) ?>">error log</a>.</p><?php else: ?><p class="mt-2 text-charcoal/70">Administrators have been alerted.</p><?php endif ?>
  </div>
  <?php endif ?>

  <div class="mt-8 flex flex-wrap justify-center gap-3">
    <?php if ($back): ?><a href="<?= e($back) ?>" class="adm-btn adm-btn-primary"><?= icon('arrow-left', 'h-4 w-4') ?> <?= $status === 419 ? 'Go back and try again' : 'Go back' ?></a><?php endif ?>
    <?php if ($status >= 500 && $retryable): ?><a href="<?= e(url(ltrim($path, '/'))) ?>" class="adm-btn <?= $back ? 'adm-btn-light' : 'adm-btn-primary' ?>"><?= icon('refresh-cw', 'h-4 w-4') ?> Try again</a><?php endif ?>
    <a href="<?= e(url('admin')) ?>" class="adm-btn adm-btn-light"><?= icon('layout-dashboard', 'h-4 w-4') ?> Dashboard</a>
  </div>
</div>
<?php if ($debug): ?>
<div class="adm-card mx-auto mt-6 max-w-4xl p-6"><p class="text-sm font-bold text-red-700">Debug details (debug mode only)</p><pre class="adm-code mt-3 max-h-96 overflow-auto whitespace-pre-wrap text-xs"><?= e((string) $debug) ?></pre></div>
<?php endif ?>
