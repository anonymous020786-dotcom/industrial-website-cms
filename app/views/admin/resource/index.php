<?php /** @var array $r @var string $res @var array $rows */
$fmt = function (string $col, array $row) use ($r) {
    $v = $row[$col] ?? '';
    return match ($col) {
        'status' => $v === 'published' ? '<span class="adm-badge bg-emerald-100 text-emerald-800">Published</span>' : '<span class="adm-badge bg-gray-200 text-gray-700">Draft</span>',
        'rating' => $v ? '<span class="text-amber-600" aria-label="' . (int) $v . ' stars">' . str_repeat('★', (int) $v) . '</span>' : '—',
        'permission' => $v ? '<span class="adm-badge bg-emerald-100 text-emerald-800">Confirmed</span>' : '<span class="adm-badge bg-amber-100 text-amber-800">Not confirmed</span>',
        'active' => $v ? '<span class="adm-badge bg-emerald-100 text-emerald-800">Active</span>' : '<span class="adm-badge bg-gray-200 text-gray-700">Disabled</span>',
        'category_id' => e(product_category_name((int) $v) ?: '—'),
        'industry_id' => e($v ? (string) db_val('SELECT name FROM industries WHERE id = ?', [(int) $v]) : '—'),
        'completed_at' => e($v ? date('M Y', strtotime((string) $v)) : '—'),
        'expires_at' => !$v ? '<span class="text-charcoal/60">No expiry</span>' : (strtotime((string) $v) < strtotime('today')
            ? '<span class="adm-badge bg-red-100 text-red-800">Expired ' . e(date('j M Y', strtotime((string) $v))) . '</span>'
            : (strtotime((string) $v) <= strtotime('+30 days') ? '<span class="adm-badge bg-amber-100 text-amber-800">Expires ' . e(date('j M Y', strtotime((string) $v))) . '</span>'
            : '<span class="adm-badge bg-emerald-100 text-emerald-800">' . e(date('j M Y', strtotime((string) $v))) . '</span>')),
        'type' => e($v === 'post' ? 'Blog posts' : 'Pages'),
        'role' => e(ucfirst((string) $v)),
        'last_login_at' => e($v ? time_ago($v) : 'Never'),
        default => e($v),
    };
};
?>
<?php if ($res === 'categories'): ?>
<div class="adm-tabs mb-4">
  <?php foreach (['' => 'All', 'post' => 'Blog categories', 'page' => 'Page categories'] as $k => $l): ?><a class="adm-tab<?= (string) query('type', '') === $k ? ' is-active' : '' ?>" href="<?= e(url('admin/r/categories') . ($k ? '?type=' . $k : '')) ?>"><?= $l ?></a><?php endforeach ?>
</div>
<?php endif ?>
<div class="adm-card">
  <?php if ($rows): ?>
  <div class="overflow-x-auto"><table class="adm-table">
    <thead><tr><?php foreach ($r['columns'] as $c => $l): ?><th><?= e($l) ?></th><?php endforeach ?><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <?php $first = true; foreach ($r['columns'] as $c => $l): ?>
          <td class="<?= $first ? '' : 'text-sm' ?>"><?php if ($first): ?><a class="font-semibold text-navy hover:text-steel" href="<?= e(url("admin/r/{$res}/{$row['id']}/edit")) ?>"><?= $fmt($c, $row) ?></a><?php else: ?><?= $fmt($c, $row) ?><?php endif ?></td>
        <?php $first = false; endforeach ?>
        <td class="whitespace-nowrap text-right">
          <?php if (isset($r['view']) && ($v = ($r['view'])($row))): ?><a class="adm-icon-btn" href="<?= e($v) ?>" target="_blank" title="View"><?= icon('external-link', 'h-4 w-4') ?></a><?php endif ?>
          <a class="adm-icon-btn" href="<?= e(url("admin/r/{$res}/{$row['id']}/edit")) ?>" title="Edit"><?= icon('pencil', 'h-4 w-4') ?></a>
          <?php if (in_array($res, ['products', 'industries', 'categories', 'product_categories'], true)): ?>
          <form method="post" action="<?= e(url("admin/r/{$res}/{$row['id']}/duplicate")) ?>" class="inline"><?= csrf_field() ?><button class="adm-icon-btn" title="Duplicate"><?= icon('copy', 'h-4 w-4') ?></button></form>
          <?php endif ?>
          <form method="post" action="<?= e(url("admin/r/{$res}/{$row['id']}/delete")) ?>" class="inline"><?= csrf_field() ?><button class="adm-icon-btn text-red-600" title="Delete" data-confirm="Delete “<?= e($row['name'] ?? $row['title'] ?? $row['author'] ?? '') ?>”?"><?= icon('trash-2', 'h-4 w-4') ?></button></form>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <?php else: ?><p class="p-12 text-center text-sm text-charcoal/55">Nothing here yet.</p><?php endif ?>
</div>
