<?php /** @var array $rows @var array $pager @var array $counts @var array $cats */
$status = (string) query('status', '');
$badge = function (array $p) {
    if ($p['deleted_at']) return '<span class="adm-badge bg-red-100 text-red-800">Trash</span>';
    if ($p['status'] === 'draft') return '<span class="adm-badge bg-gray-200 text-gray-700">Draft</span>';
    if ($p['published_at'] && strtotime($p['published_at']) > time()) return '<span class="adm-badge bg-amber-100 text-amber-800">Scheduled</span>';
    return '<span class="adm-badge bg-emerald-100 text-emerald-800">Published</span>';
};
?>
<div class="adm-tabs mb-4">
  <?php foreach (['' => 'All', 'published' => 'Published', 'scheduled' => 'Scheduled', 'draft' => 'Drafts', 'trash' => 'Trash'] as $k => $l): ?>
  <a href="<?= e(url('admin/posts') . qs(['status' => $k ?: null, 'page' => null])) ?>" class="adm-tab<?= $status === $k ? ' is-active' : '' ?>"><?= $l ?> <span><?= (int) $counts[$k] ?></span></a>
  <?php endforeach ?>
</div>

<?php if ($status === 'trash' && $counts['trash'] > 0): ?>
<div class="mb-4 flex flex-wrap items-center gap-3 text-sm text-charcoal/70"><?= icon('info', 'h-4 w-4') ?> Items in the Trash are deleted permanently after 30 days.
  <form method="post" action="<?= e(url('admin/posts/empty-trash')) ?>" class="ml-auto"><?= csrf_field() ?><button class="adm-btn adm-btn-danger adm-btn-sm" data-confirm="Permanently delete every post in the Trash? This cannot be undone."><?= icon('trash-2', 'h-4 w-4') ?> Empty Trash</button></form>
</div>
<?php endif ?>
<?php if ($counts['draft'] > 0 && $status !== 'trash'): ?>
<div class="adm-flash adm-flash-info mb-4"><?= icon('lightbulb', 'h-5 w-5 shrink-0') ?><span><strong><?= (int) $counts['draft'] ?> drafts</strong> are waiting for review. Have an engineer check technical details, then select them and use <strong>Schedule (drip-publish)</strong> to release 2–3 per week — a steady publishing rhythm is better for SEO than publishing everything at once.</span></div>
<?php endif ?>

<form method="get" class="adm-card mb-4 flex flex-wrap gap-3 p-4">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif ?>
  <input class="adm-input min-w-[200px] flex-1" type="search" name="q" value="<?= e(query('q', '')) ?>" placeholder="Search titles and tags…" aria-label="Search">
  <select class="adm-input !w-auto" name="category" aria-label="Category"><option value="">All categories</option><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) query('category') === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select>
  <select class="adm-input !w-auto" name="sort" aria-label="Sort"><option value="">Newest</option><option value="title"<?= query('sort') === 'title' ? ' selected' : '' ?>>Title A–Z</option><option value="views"<?= query('sort') === 'views' ? ' selected' : '' ?>>Most viewed</option></select>
  <button class="adm-btn adm-btn-primary"><?= icon('filter', 'h-4 w-4') ?> Filter</button>
</form>

<form method="post" action="<?= e(url('admin/posts/bulk')) ?>" class="adm-card" data-bulk-form>
  <?= csrf_field() ?>
  <div class="flex flex-wrap items-center gap-3 border-b border-black/10 p-3">
    <span class="text-sm text-charcoal/60" data-bulk-count>0 selected</span>
    <select class="adm-input !w-auto" name="bulk_action" aria-label="Bulk action" data-bulk-action>
      <option value="">Bulk action…</option>
      <?php if ($status === 'trash'): ?>
      <option value="restore">Restore (as drafts)</option>
      <option value="destroy">Delete permanently</option>
      <?php else: ?>
      <option value="schedule">Schedule (drip-publish)</option>
      <option value="publish">Publish now</option>
      <option value="draft">Move to drafts</option>
      <option value="duplicate">Duplicate as drafts</option>
      <optgroup label="Set category"><?php foreach ($cats as $c): ?><option value="category:<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach ?></optgroup>
      <option value="trash">Move to Trash</option>
      <?php endif ?>
    </select>
    <span class="hidden flex-wrap items-center gap-2 text-sm" data-schedule-fields>
      starting <input type="date" name="start_date" class="adm-input !w-auto" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
      at <input type="time" name="start_time" class="adm-input !w-auto" value="09:00">
      every <input type="number" name="every_days" min="1" max="30" value="3" class="adm-input !w-20"> days
    </span>
    <button class="adm-btn adm-btn-light adm-btn-sm" data-confirm="Apply this action to the selected posts?">Apply</button>
    <span class="ml-auto text-sm text-charcoal/55"><?= (int) $pager['total'] ?> posts</span>
  </div>
  <?php if ($rows): ?>
  <div class="overflow-x-auto"><table class="adm-table">
    <thead><tr><th class="w-10"><input type="checkbox" data-check-all aria-label="Select all"></th><th>Title</th><th>Category</th><th>Status</th><th>Date</th><th>SEO</th><th class="text-right">Views</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): $seo = ($p['meta_description'] ? 1 : 0) + ($p['focus_keyword'] ? 1 : 0); ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" data-check aria-label="Select"></td>
        <td class="max-w-md"><a class="font-semibold text-navy hover:text-steel" href="<?= e(url('admin/posts/' . $p['id'] . '/edit')) ?>"><?= e($p['title']) ?></a><div class="text-xs text-charcoal/50">/blog/<?= e($p['slug']) ?> · <?= (int) $p['reading_minutes'] ?> min</div></td>
        <td class="text-sm"><?= e($p['category'] ?: '—') ?></td>
        <td><?= $badge($p) ?></td>
        <td class="whitespace-nowrap text-sm text-charcoal/60"><?= $p['published_at'] ? e(format_date($p['published_at'], 'j M Y')) : '—' ?></td>
        <td><span class="adm-seo-dot adm-seo-<?= $seo ?>" title="<?= $seo === 2 ? 'Meta description and focus keyword set' : 'Missing SEO fields' ?>"></span></td>
        <td class="text-right text-sm"><?= (int) $p['views'] ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <?php else: ?><p class="p-12 text-center text-sm text-charcoal/55">No posts found.</p><?php endif ?>
</form>
<?= view('admin/_pager', ['pager' => $pager]) ?>
