<?php /** @var array $row @var array $similar @var int $total */ ?>
<div class="grid gap-6 xl:grid-cols-[1fr_340px]">
  <div class="space-y-6">
    <div class="adm-card p-6">
      <div class="flex flex-wrap items-start gap-3">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-red-50 text-red-700"><?= icon('bug', 'h-5 w-5') ?></span>
        <div class="min-w-0 flex-1">
          <p class="font-mono text-xs text-charcoal/65"><?= e((string) $row['class']) ?> · HTTP <?= (int) $row['status'] ?></p>
          <h2 class="mt-1 break-words text-lg font-bold text-navy"><?= e((string) $row['message']) ?></h2>
          <p class="mt-1 font-mono text-sm text-steel"><?= e((string) $row['location']) ?></p>
        </div>
        <?php if ($row['resolved_at']): ?><span class="adm-badge bg-emerald-100 text-emerald-800">Resolved <?= e(time_ago((string) $row['resolved_at'])) ?></span><?php else: ?><span class="adm-badge bg-red-100 text-red-700">Open</span><?php endif ?>
      </div>
      <dl class="mt-6 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
        <div><dt class="text-charcoal/60">Reference</dt><dd class="font-mono font-bold text-navy"><?= e((string) $row['ref']) ?> <button type="button" class="adm-btn adm-btn-light adm-btn-sm ml-1" data-copy="<?= e((string) $row['ref']) ?>">Copy</button></dd></div>
        <div><dt class="text-charcoal/60">When</dt><dd><?= e(date('j M Y, H:i:s', strtotime((string) $row['created_at']))) ?> (<?= e(time_ago((string) $row['created_at'])) ?>)</dd></div>
        <div class="sm:col-span-2"><dt class="text-charcoal/60">Request</dt><dd class="break-all font-mono"><?= e((string) $row['method']) ?> <?= e((string) $row['path']) ?></dd></div>
        <div><dt class="text-charcoal/60">Signed-in user</dt><dd><?= $row['user_name'] ? e($row['user_name'] . ' (' . $row['user_email'] . ')') : 'Visitor (not signed in)' ?></dd></div>
        <div><dt class="text-charcoal/60">IP address</dt><dd><?= e((string) ($row['ip'] ?: '—')) ?></dd></div>
        <div class="sm:col-span-2"><dt class="text-charcoal/60">Browser</dt><dd class="break-words"><?= e((string) ($row['user_agent'] ?: '—')) ?></dd></div>
      </dl>
    </div>
    <div class="adm-card p-6">
      <h3 class="adm-card-title"><?= icon('list-tree', 'h-5 w-5 text-steel') ?> Stack trace</h3>
      <?php if (trim((string) $row['trace']) !== ''): ?>
      <pre class="adm-code mt-3 max-h-[28rem] overflow-auto whitespace-pre text-xs leading-relaxed"><?= e((string) $row['trace']) ?></pre>
      <p class="mt-2 text-xs text-charcoal/60">Argument values are never stored, so passwords and personal data can’t leak into this log.</p>
      <?php else: ?><p class="mt-2 text-sm text-charcoal/70">No trace is available for fatal errors (for example running out of memory or time). See <code>app/storage/logs/php-error.log</code> around the time above.</p><?php endif ?>
    </div>
  </div>

  <aside class="space-y-6">
    <div class="adm-card p-6">
      <p class="text-3xl font-bold text-navy"><?= $total ?></p>
      <p class="text-sm text-charcoal/70">time<?= $total === 1 ? '' : 's' ?> this error has happened (last 60 days)</p>
      <form method="post" action="<?= e(url('admin/errors/resolve')) ?>" class="mt-4"><?= csrf_field() ?><input type="hidden" name="fingerprint" value="<?= e((string) $row['fingerprint']) ?>">
        <?php if ($row['resolved_at']): ?><button class="adm-btn adm-btn-light w-full justify-center" name="reopen" value="1">Reopen</button>
        <?php else: ?><button class="adm-btn adm-btn-primary w-full justify-center"><?= icon('check', 'h-4 w-4') ?> Mark as resolved</button><?php endif ?>
      </form>
    </div>
    <div class="adm-card">
      <h3 class="adm-card-title p-5 pb-2">Recent occurrences</h3>
      <ul class="divide-y divide-black/5">
        <?php foreach ($similar as $s): ?>
        <li><a href="<?= e(url('admin/errors/' . $s['id'])) ?>" class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm hover:bg-mist<?= (int) $s['id'] === (int) $row['id'] ? ' bg-mist font-semibold' : '' ?>">
          <span class="font-mono text-xs text-navy"><?= e((string) $s['ref']) ?></span><span class="truncate text-xs text-charcoal/65"><?= e(time_ago((string) $s['created_at'])) ?></span></a></li>
        <?php endforeach ?>
      </ul>
    </div>
  </aside>
</div>
