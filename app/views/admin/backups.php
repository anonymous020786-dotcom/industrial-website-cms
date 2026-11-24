<?php /** @var array $list @var array $cron @var float|false $free @var bool $zip */
$kb = fn(int $b) => $b >= 1073741824 ? round($b / 1073741824, 1) . ' GB' : ($b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB');
$last = $cron['last_run'] ? strtotime((string) $cron['last_run']) : 0;
$cronOk = $last && time() - $last < 2 * 3600;
$labels = ['cleanup' => 'Clean-up (rate limits, expired codes, old logs)', 'trash' => 'Empty Trash (items older than 30 days)', 'digest' => 'Daily lead follow-up email', 'backup' => 'Daily backup', 'certificates' => 'Certificate expiry warnings'];
?>
<div class="grid gap-6 xl:grid-cols-[1fr_24rem]">
  <div class="space-y-6">
    <div class="adm-card">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-black/10 p-4">
        <h2 class="adm-card-title"><?= icon('database-backup', 'h-5 w-5 text-steel') ?> Backups</h2>
        <form method="post" action="<?= e(url('admin/backups/create')) ?>" class="flex flex-wrap items-center gap-3"><?= csrf_field() ?>
          <?php if ($zip): ?><label class="adm-check text-sm"><input type="checkbox" name="uploads" value="1"><span>Include uploaded files</span></label><?php endif ?>
          <button class="adm-btn adm-btn-primary"><?= icon('plus', 'h-4 w-4') ?> Back up now</button>
        </form>
      </div>
      <?php if ($list): ?>
      <div class="overflow-x-auto"><table class="adm-table">
        <thead><tr><th>File</th><th>Contents</th><th>Size</th><th>Created</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($list as $b): ?>
          <tr>
            <td class="font-mono text-xs"><?= e($b['file']) ?></td>
            <td class="text-sm"><?= e($b['kind']) ?></td>
            <td class="whitespace-nowrap text-sm"><?= $kb($b['size']) ?></td>
            <td class="whitespace-nowrap text-sm text-charcoal/70" title="<?= e(date('j M Y H:i', $b['time'])) ?>"><?= e(time_ago(date('Y-m-d H:i:s', $b['time']))) ?></td>
            <td class="whitespace-nowrap text-right">
              <details class="inline-block text-left">
                <summary class="adm-btn adm-btn-light adm-btn-sm cursor-pointer list-none"><?= icon('download', 'h-3.5 w-3.5') ?> Download</summary>
                <form method="post" action="<?= e(url('admin/backups/download')) ?>" class="absolute right-6 z-10 mt-2 w-72 space-y-2 rounded-lg border border-black/10 bg-white p-3 shadow-lg"><?= csrf_field() ?>
                  <input type="hidden" name="file" value="<?= e($b['file']) ?>">
                  <label class="adm-label" for="pw-<?= e(md5($b['file'])) ?>">Confirm your password</label>
                  <input id="pw-<?= e(md5($b['file'])) ?>" type="password" name="password" class="adm-input" autocomplete="current-password" required>
                  <p class="text-[11px] text-charcoal/60">Backups contain every lead and user record. Store them securely.</p>
                  <button class="adm-btn adm-btn-primary adm-btn-sm w-full justify-center">Download</button>
                </form>
              </details>
              <form method="post" action="<?= e(url('admin/backups/delete')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="file" value="<?= e($b['file']) ?>"><button class="adm-icon-btn text-red-600" title="Delete" data-confirm="Delete this backup?"><?= icon('trash-2', 'h-4 w-4') ?></button></form>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table></div>
      <?php else: ?><p class="p-12 text-center text-sm text-charcoal/60">No backups yet. Click <strong>Back up now</strong>, or set up the scheduled task so backups run every night.</p><?php endif ?>
      <p class="border-t border-black/5 p-3 text-xs text-charcoal/60">Stored privately in <code>app/storage/backups</code> (not web-accessible)<?= $free !== false ? ' · ' . $kb((int) $free) . ' free on disk' : '' ?>. To restore on any server: <code>php app/cli.php restore backup-….sql.gz --yes</code>. Keep a copy off the server too.</p>
    </div>

    <div class="adm-card">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-black/10 p-4">
        <h2 class="adm-card-title"><?= icon('clock', 'h-5 w-5 text-steel') ?> Scheduled tasks</h2>
        <form method="post" action="<?= e(url('admin/cron/run')) ?>" class="flex items-center gap-3"><?= csrf_field() ?>
          <label class="adm-check text-sm"><input type="checkbox" name="force" value="1"><span>Force all (send digest &amp; back up now)</span></label>
          <button class="adm-btn adm-btn-light"><?= icon('play', 'h-4 w-4') ?> Run now</button>
        </form>
      </div>
      <ul class="divide-y divide-black/5">
        <?php foreach ($labels as $k => $l): ?>
        <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm"><span><?= e($l) ?></span><span class="text-charcoal/60"><?= $cron['tasks'][$k] ? e(time_ago($cron['tasks'][$k])) : 'never' ?></span></li>
        <?php endforeach ?>
      </ul>
    </div>
  </div>

  <aside class="space-y-6">
    <div class="adm-card p-6">
      <h2 class="adm-card-title"><?= icon($cronOk ? 'circle-check' : 'triangle-alert', 'h-5 w-5 ' . ($cronOk ? 'text-emerald-600' : 'text-amber-600')) ?> Cron status</h2>
      <p class="mt-2 text-sm <?= $cronOk ? 'text-emerald-700' : 'text-amber-700' ?>"><?= $last ? 'Last run ' . e(time_ago((string) $cron['last_run'])) . '.' : 'Scheduled tasks have never run.' ?> <?= $cronOk ? '' : 'Set up one of the options below so backups and follow-up emails happen automatically.' ?></p>
      <h3 class="mt-5 text-xs font-bold uppercase tracking-wide text-charcoal/60">Option 1 — server cron (recommended)</h3>
      <p class="mt-1 text-sm text-charcoal/70">In hPanel/cPanel → Cron Jobs, run every 10 minutes:</p>
      <code class="mt-2 block break-all rounded bg-mist p-2 text-xs"><?= e('php ' . str_replace('\\', '/', APP_DIR) . '/cli.php cron') ?></code>
      <h3 class="mt-5 text-xs font-bold uppercase tracking-wide text-charcoal/60">Option 2 — URL cron</h3>
      <p class="mt-1 text-sm text-charcoal/70">For hosts or monitoring services that can only call a URL (keep it secret):</p>
      <code class="mt-2 block break-all rounded bg-mist p-2 text-xs"><?= e(abs_url('cron/' . $cron['key'])) ?></code>
      <button type="button" class="adm-link mt-2 text-xs" data-copy="<?= e(abs_url('cron/' . $cron['key'])) ?>">Copy URL</button>
    </div>
    <div class="adm-card p-6 text-sm text-charcoal/70">
      <h2 class="adm-card-title mb-2"><?= icon('settings', 'h-5 w-5 text-steel') ?> Settings</h2>
      Backup time, how many to keep and the daily digest are configured in <a class="adm-link" href="<?= e(url('admin/settings/automation')) ?>">Settings → Automation</a>.
    </div>
  </aside>
</div>
