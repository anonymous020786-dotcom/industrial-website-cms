<?php /** @var array $rows @var array $pager @var array $counts @var int $total */
$st = (string) query('status', '');
?>
<div class="adm-tabs mb-4">
  <a href="<?= e(url('admin/subscribers') . qs(['status' => null, 'page' => null])) ?>" class="adm-tab<?= $st === '' ? ' is-active' : '' ?>">All <span><?= array_sum($counts) ?></span></a>
  <?php foreach (SUBSCRIBER_STATUSES as $k => [$l]): ?>
  <a href="<?= e(url('admin/subscribers') . qs(['status' => $k, 'page' => null])) ?>" class="adm-tab<?= $st === $k ? ' is-active' : '' ?>"><?= e($l) ?> <span><?= (int) ($counts[$k] ?? 0) ?></span></a>
  <?php endforeach ?>
</div>
<div class="grid gap-6 xl:grid-cols-3">
  <div class="adm-card xl:col-span-2">
    <form method="get" class="flex gap-3 border-b border-black/10 p-4"><?php if ($st): ?><input type="hidden" name="status" value="<?= e($st) ?>"><?php endif ?>
      <input class="adm-input flex-1" type="search" name="q" value="<?= e(query('q', '')) ?>" placeholder="Search email or name…" aria-label="Search"><button class="adm-btn adm-btn-light"><?= icon('search', 'h-4 w-4') ?></button></form>
    <?php if ($rows): ?>
    <div class="overflow-x-auto"><table class="adm-table">
      <thead><tr><th>Subscriber</th><th>Status</th><th>Source</th><th>Joined</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $s): [$sl, $sc] = SUBSCRIBER_STATUSES[$s['status']] ?? [$s['status'], '']; ?>
        <tr data-subscriber="<?= e($s['email']) ?>">
          <td><span class="font-semibold text-navy"><?= e($s['email']) ?></span><?php if ($s['name']): ?><div class="text-xs text-charcoal/55"><?= e($s['name']) ?></div><?php endif ?></td>
          <td><span class="adm-badge <?= $sc ?>"><?= e($sl) ?></span></td>
          <td class="text-sm"><?= e(ucfirst((string) $s['source'])) ?></td>
          <td class="whitespace-nowrap text-sm text-charcoal/60"><?= e(format_date($s['created_at'], 'j M Y')) ?></td>
          <td class="whitespace-nowrap text-right">
            <?php foreach (array_filter(['resend' => $s['status'] === 'pending' ? ['Resend confirmation', 'mail'] : null, 'unsubscribe' => $s['status'] !== 'unsubscribed' ? ['Unsubscribe', 'mail-x'] : null, 'delete' => ['Delete all data', 'trash-2']]) as $a => [$t, $ic]): ?>
            <form method="post" action="<?= e(url('admin/subscribers/' . $s['id'] . '/action')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>">
              <button class="adm-icon-btn<?= $a === 'delete' ? ' text-red-600' : '' ?>" title="<?= e($t) ?>"<?= $a !== 'resend' ? ' data-confirm="' . e($t) . ' — ' . e($s['email']) . '?"' : '' ?>><?= icon($ic, 'h-4 w-4') ?></button></form>
            <?php endforeach ?>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table></div>
    <?php else: ?><p class="p-10 text-center text-sm text-charcoal/60">No subscribers<?= $st ? ' with this status' : ' yet — the sign-up form is in the website footer and on blog articles' ?>.</p><?php endif ?>
    <?= view('admin/_pager', ['pager' => $pager]) ?>
  </div>
  <aside class="space-y-6">
    <form method="post" action="<?= e(url('admin/subscribers/add')) ?>" class="adm-card space-y-3 p-6">
      <?= csrf_field() ?>
      <h2 class="adm-card-title"><?= icon('user-plus', 'h-5 w-5 text-steel') ?> Add subscriber</h2>
      <input class="adm-input" type="email" name="email" required placeholder="email@company.com" aria-label="Email">
      <input class="adm-input" name="name" placeholder="Name (optional)" aria-label="Name">
      <label class="adm-check"><input type="checkbox" name="consent" value="1" required><span>This person agreed to receive the newsletter</span></label>
      <button class="adm-btn adm-btn-primary"><?= icon('plus', 'h-4 w-4') ?> Add</button>
    </form>
    <?php if (can('settings')): ?>
    <form method="post" action="<?= e(url('admin/subscribers/import')) ?>" enctype="multipart/form-data" class="adm-card space-y-3 p-6">
      <?= csrf_field() ?>
      <h2 class="adm-card-title"><?= icon('upload', 'h-5 w-5 text-steel') ?> Import CSV</h2>
      <p class="adm-help">A column named <code>email</code> (and optionally <code>name</code>), or one address per line. Existing subscribers are skipped.</p>
      <input type="file" name="csv" accept=".csv,text/csv" required class="adm-input">
      <label class="adm-check"><input type="checkbox" name="consent" value="1" required><span>Everyone in this file agreed to receive emails from us</span></label>
      <button class="adm-btn adm-btn-light"><?= icon('upload', 'h-4 w-4') ?> Import</button>
    </form>
    <?php endif ?>
  </aside>
</div>
