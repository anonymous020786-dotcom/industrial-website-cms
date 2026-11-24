<?php /** @var string $tab @var array $rows @var array $counts @var string $q @var ?array $edit @var array $prefill */
$f = $edit ?: $prefill;
?>
<div class="adm-tabs mb-4">
  <a class="adm-tab<?= $tab === 'rules' ? ' is-active' : '' ?>" href="<?= e(url('admin/redirects')) ?>">Redirects <span><?= $counts['rules'] ?></span></a>
  <a class="adm-tab<?= $tab === '404' ? ' is-active' : '' ?>" href="<?= e(url('admin/redirects?tab=404')) ?>">404 log <span><?= $counts['404'] ?></span></a>
</div>

<?php if ($tab === 'rules'): ?>
<div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
  <div class="space-y-4">
    <form method="get" class="adm-card flex flex-wrap gap-3 p-4">
      <input class="adm-input min-w-[220px] flex-1" type="search" name="q" value="<?= e($q) ?>" placeholder="Search old or new addresses…" aria-label="Search redirects">
      <button class="adm-btn adm-btn-primary"><?= icon('search', 'h-4 w-4') ?> Search</button>
      <a class="adm-btn adm-btn-light" href="<?= e(url('admin/redirects/export')) ?>"><?= icon('download', 'h-4 w-4') ?> Export CSV</a>
    </form>
    <div class="adm-card">
      <?php if ($rows): ?>
      <div class="overflow-x-auto"><table class="adm-table">
        <thead><tr><th>Old address</th><th>Goes to</th><th>Type</th><th class="text-right">Hits</th><th>Last used</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="max-w-xs break-all font-mono text-xs"><?= e($r['source']) ?><?php if ($r['note']): ?><div class="mt-1 font-sans text-[11px] text-charcoal/60"><?= e($r['note']) ?></div><?php endif ?></td>
            <td class="max-w-xs break-all font-mono text-xs"><?= e($r['target']) ?></td>
            <td><span class="adm-badge <?= in_array((int) $r['code'], [301, 308], true) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>"><?= (int) $r['code'] ?></span></td>
            <td class="text-right text-sm"><?= (int) $r['hits'] ?></td>
            <td class="whitespace-nowrap text-sm text-charcoal/60"><?= $r['last_hit_at'] ? e(time_ago($r['last_hit_at'])) : 'Never' ?></td>
            <td class="whitespace-nowrap text-right">
              <a class="adm-icon-btn" href="<?= e(url('admin/redirects?edit=' . $r['id'])) ?>" title="Edit"><?= icon('pencil', 'h-4 w-4') ?></a>
              <form method="post" action="<?= e(url('admin/redirects/' . $r['id'] . '/delete')) ?>" class="inline"><?= csrf_field() ?><button class="adm-icon-btn text-red-600" title="Delete" data-confirm="Delete the redirect from <?= e($r['source']) ?>?"><?= icon('trash-2', 'h-4 w-4') ?></button></form>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table></div>
      <?php else: ?><p class="p-12 text-center text-sm text-charcoal/60"><?= $q ? 'No redirects match.' : 'No redirects yet. Add one, import a CSV, or create them from the 404 log.' ?></p><?php endif ?>
    </div>
  </div>

  <aside class="space-y-6">
    <form method="post" action="<?= e(url('admin/redirects/save')) ?>" class="adm-card space-y-4 p-6" id="redirect-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <h2 class="adm-card-title"><?= icon('route', 'h-5 w-5 text-steel') ?> <?= $edit ? 'Edit redirect' : 'Add redirect' ?></h2>
      <div class="adm-field"><label class="adm-label" for="r_src">Old address</label><input id="r_src" class="adm-input font-mono text-sm" name="source" value="<?= e($f['source']) ?>" placeholder="/old-page.html" required>
        <p class="adm-help">A path on this site. End with <code>/*</code> to redirect a whole section, e.g. <code>/old-blog/*</code>.</p></div>
      <div class="adm-field"><label class="adm-label" for="r_dst">New address</label><input id="r_dst" class="adm-input font-mono text-sm" name="target" value="<?= e($f['target']) ?>" placeholder="/products/hydraulic-cylinders" required>
        <p class="adm-help">A path or full URL. With a <code>/*</code> rule, <code>*</code> in the target is replaced by the rest of the old path.</p></div>
      <div class="adm-field"><label class="adm-label" for="r_code">Type</label><select id="r_code" class="adm-input" name="code"><?php foreach (REDIRECT_CODES as $c => $l): ?><option value="<?= $c ?>"<?= (int) $f['code'] === $c ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select>
        <p class="adm-help">Use <strong>301</strong> for pages that moved for good — it passes search ranking to the new page.</p></div>
      <div class="adm-field"><label class="adm-label" for="r_note">Note <span class="font-normal text-charcoal/60">(optional)</span></label><input id="r_note" class="adm-input" name="note" value="<?= e((string) $f['note']) ?>" maxlength="190"></div>
      <div class="flex gap-2"><button class="adm-btn adm-btn-primary flex-1 justify-center"><?= icon('save', 'h-4 w-4') ?> Save redirect</button><?php if ($edit): ?><a class="adm-btn adm-btn-light" href="<?= e(url('admin/redirects')) ?>">Cancel</a><?php endif ?></div>
    </form>
    <form method="post" action="<?= e(url('admin/redirects/import')) ?>" enctype="multipart/form-data" class="adm-card space-y-3 p-6">
      <?= csrf_field() ?>
      <h2 class="adm-card-title"><?= icon('file-up', 'h-5 w-5 text-steel') ?> Import from CSV</h2>
      <p class="text-sm text-charcoal/70">Columns: <code>source,target,code,note</code> (code and note optional). Moving from an old website? Export its URL list and map each to a new page.</p>
      <input type="file" name="csv" accept=".csv,text/csv" class="adm-input" required aria-label="CSV file">
      <button class="adm-btn adm-btn-light w-full justify-center"><?= icon('upload', 'h-4 w-4') ?> Import</button>
    </form>
    <div class="adm-card p-6 text-sm text-charcoal/70"><h2 class="adm-card-title mb-2"><?= icon('info', 'h-5 w-5 text-steel') ?> Automatic redirects</h2>Changing the URL of a published page, blog post, product or project adds a 301 from the old address automatically, so links and search rankings keep working.</div>
  </aside>
</div>

<?php else: ?>
<div class="adm-flash adm-flash-info mb-4"><?= icon('lightbulb', 'h-5 w-5 shrink-0') ?><span>Addresses visitors or search engines requested that do not exist. Redirect the useful ones (old pages, typos in links) to the right page; ignore scanner noise. Static files and admin paths are not logged.</span></div>
<form method="post" action="<?= e(url('admin/redirects/404')) ?>" class="adm-card" data-bulk-form>
  <?= csrf_field() ?>
  <div class="flex flex-wrap items-center gap-3 border-b border-black/10 p-3">
    <span class="text-sm text-charcoal/60" data-bulk-count>0 selected</span>
    <button class="adm-btn adm-btn-light adm-btn-sm" name="action" value="ignore"><?= icon('eye-off', 'h-4 w-4') ?> Ignore</button>
    <button class="adm-btn adm-btn-light adm-btn-sm" name="action" value="delete"><?= icon('x', 'h-4 w-4') ?> Remove</button>
    <button class="adm-btn adm-btn-light adm-btn-sm ml-auto !text-red-700" name="action" value="clear" data-confirm="Clear the whole 404 log?"><?= icon('trash-2', 'h-4 w-4') ?> Clear log</button>
  </div>
  <?php if ($rows): ?>
  <div class="overflow-x-auto"><table class="adm-table">
    <thead><tr><th class="w-10"><input type="checkbox" data-check-all aria-label="Select all"></th><th>Missing address</th><th class="text-right">Hits</th><th>Referrer</th><th>Last seen</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" data-check aria-label="Select"></td>
        <td class="max-w-sm break-all font-mono text-xs"><?= e($r['path']) ?></td>
        <td class="text-right text-sm font-semibold"><?= (int) $r['hits'] ?></td>
        <td class="max-w-[14rem] truncate text-xs text-charcoal/60" title="<?= e((string) $r['referrer']) ?>"><?= e($r['referrer'] ? (string) parse_url($r['referrer'], PHP_URL_HOST) . (string) parse_url($r['referrer'], PHP_URL_PATH) : '—') ?></td>
        <td class="whitespace-nowrap text-sm text-charcoal/60"><?= e(time_ago($r['last_seen'])) ?></td>
        <td class="text-right"><a class="adm-btn adm-btn-light adm-btn-sm" href="<?= e(url('admin/redirects') . qs(['from' => $r['path'], 'tab' => null])) ?>#redirect-form"><?= icon('route', 'h-3.5 w-3.5') ?> Redirect</a></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <?php else: ?><p class="p-12 text-center text-sm text-charcoal/60">No broken addresses recorded.</p><?php endif ?>
</form>
<?php endif ?>
