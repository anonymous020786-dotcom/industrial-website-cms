<?php /** @var array $groups @var string $show @var array $stats @var string $ref */ ?>
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <?php foreach ([['Open error types', $stats['open'], 'bug', $stats['open'] ? 'text-red-700' : 'text-emerald-700'], ['Errors in the last 24 h', $stats['24h'], 'clock', 'text-navy'], ['Errors in the last 7 days', $stats['7d'], 'chart-column', 'text-navy'], ['Broken links (404) this week', $stats['404'], 'route', 'text-navy']] as [$l, $n, $ic, $c]): ?>
  <div class="adm-card flex items-center gap-4 p-5"><span class="grid h-11 w-11 place-items-center rounded-full bg-mist text-steel"><?= icon($ic, 'h-5 w-5') ?></span><div><p class="text-2xl font-bold <?= $c ?>"><?= (int) $n ?></p><p class="text-sm text-charcoal/70"><?= e($l) ?></p></div></div>
  <?php endforeach ?>
</div>

<div class="adm-card">
  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-black/5 p-4">
    <nav class="flex gap-1" aria-label="Filter">
      <a href="<?= e(url('admin/errors')) ?>" class="adm-btn adm-btn-sm <?= $show === 'open' ? 'adm-btn-primary' : 'adm-btn-light' ?>">Open</a>
      <a href="<?= e(url('admin/errors?show=resolved')) ?>" class="adm-btn adm-btn-sm <?= $show === 'resolved' ? 'adm-btn-primary' : 'adm-btn-light' ?>">Resolved</a>
    </nav>
    <form method="get" action="<?= e(url('admin/errors')) ?>" class="flex gap-2">
      <label class="sr-only" for="err-ref">Error reference</label>
      <input id="err-ref" name="ref" value="<?= e($ref) ?>" class="adm-input !w-56 font-mono" placeholder="E-7K2M9QXA">
      <button class="adm-btn adm-btn-light adm-btn-sm"><?= icon('search', 'h-4 w-4') ?> Find</button>
    </form>
  </div>
  <?php if (!$groups): ?>
    <div class="p-12 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-emerald-700"><?= icon('circle-check', 'h-7 w-7') ?></span>
      <p class="mt-4 font-semibold text-navy"><?= $show === 'open' ? 'No open errors' : 'Nothing resolved yet' ?></p>
      <p class="mt-1 text-sm text-charcoal/70">When a visitor hits an unexpected error, it appears here with the reference they were shown. Broken links are listed under <a class="adm-link" href="<?= e(url('admin/redirects')) ?>">Redirects &amp; 404s</a>.</p></div>
  <?php else: ?>
  <div class="overflow-x-auto"><table class="adm-table">
    <thead><tr><th>Error</th><th>Where</th><th class="text-right">Times</th><th>Last seen</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($groups as $g): ?>
      <tr>
        <td class="max-w-md"><a class="font-semibold text-navy hover:text-steel" href="<?= e(url('admin/errors/' . $g['id'])) ?>"><?= e(str_limit((string) $g['message'], 110)) ?></a>
          <p class="mt-0.5 font-mono text-xs text-charcoal/65"><?= e((string) $g['class']) ?> · <?= e((string) $g['location']) ?></p></td>
        <td class="max-w-[16rem] truncate text-sm text-charcoal/75" title="<?= e((string) $g['path']) ?>"><span class="adm-badge bg-gray-100 text-gray-700"><?= e((string) $g['method']) ?></span> <?= e((string) $g['path']) ?></td>
        <td class="text-right"><span class="font-bold text-navy"><?= (int) $g['n'] ?></span><span class="block text-xs text-charcoal/60"><?= (int) $g['visitors'] ?> visitor<?= (int) $g['visitors'] === 1 ? '' : 's' ?></span></td>
        <td class="whitespace-nowrap text-sm text-charcoal/70" title="<?= e((string) $g['created_at']) ?>"><?= e(time_ago((string) $g['created_at'])) ?><span class="block text-xs text-charcoal/55">first <?= e(time_ago((string) $g['first_seen'])) ?></span></td>
        <td class="text-right">
          <form method="post" action="<?= e(url('admin/errors/resolve')) ?>"><?= csrf_field() ?><input type="hidden" name="fingerprint" value="<?= e((string) $g['fingerprint']) ?>">
            <?php if ($show === 'open'): ?><button class="adm-btn adm-btn-light adm-btn-sm"><?= icon('check', 'h-4 w-4') ?> Resolve</button>
            <?php else: ?><button class="adm-btn adm-btn-light adm-btn-sm" name="reopen" value="1">Reopen</button><?php endif ?>
          </form>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <?php endif ?>
</div>
<p class="mt-4 text-xs text-charcoal/60">Identical errors are grouped. Administrators get one alert per error type per hour (My profile → Notifications → Site errors &amp; maintenance). Entries are kept for 60 days. The full PHP log is in <code>app/storage/logs/php-error.log</code>.</p>
