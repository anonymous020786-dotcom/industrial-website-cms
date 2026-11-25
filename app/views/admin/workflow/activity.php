<?php /** @var array $rows @var array $pager @var array $users */ ?>
<form method="get" class="adm-card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
  <input class="adm-input" type="search" name="q" value="<?= e(query('q', '')) ?>" placeholder="Search by item name…" aria-label="Search">
  <select class="adm-input" name="entity" aria-label="Section"><option value="">All sections</option><?php foreach (ACTIVITY_ENTITIES as $k => $l): ?><option value="<?= $k ?>"<?= query('entity') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select>
  <select class="adm-input" name="user" aria-label="Person"><option value="">Everyone</option><?php foreach ($users as $id => $n): ?><option value="<?= (int) $id ?>"<?= query('user') === (string) $id ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach ?></select>
  <select class="adm-input" name="action" aria-label="Action"><option value="">Any action</option><?php foreach (HISTORY_ACTIONS as $k => [$l]): ?><option value="<?= $k ?>"<?= query('action') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select>
  <button class="adm-btn adm-btn-primary"><?= icon('filter', 'h-4 w-4') ?> Filter</button>
</form>
<div class="adm-card">
  <?php if ($rows): ?>
  <div class="overflow-x-auto">
    <table class="adm-table">
      <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Item</th><th>Details</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): [$al, $ac] = HISTORY_ACTIONS[$r['action']] ?? [ucfirst($r['action']), 'bg-gray-100 text-gray-700']; $link = activity_link($r); ?>
        <tr data-activity="<?= e($r['entity'] . ':' . $r['action']) ?>">
          <td class="whitespace-nowrap text-sm text-charcoal/60" title="<?= e($r['created_at']) ?>"><?= e(format_date($r['created_at'], 'j M Y H:i')) ?></td>
          <td class="text-sm"><?= e($r['user_name'] ?: 'System / visitor') ?></td>
          <td><span class="adm-badge <?= $ac ?>"><?= e($al) ?></span></td>
          <td><span class="text-xs text-charcoal/50"><?= e(ACTIVITY_ENTITIES[$r['entity']] ?? $r['entity']) ?></span><br>
            <?php if ($link): ?><a class="font-semibold text-navy hover:text-steel" href="<?= e($link) ?>"><?= e((string) $r['title']) ?></a><?php else: ?><span class="font-semibold"><?= e((string) $r['title']) ?></span><?php endif ?></td>
          <td class="max-w-md text-sm text-charcoal/70"><?= e((string) $r['detail']) ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <p class="p-10 text-center text-sm text-charcoal/60">No activity recorded yet.</p>
  <?php endif ?>
</div>
<?= view('admin/_pager', ['pager' => $pager]) ?>
