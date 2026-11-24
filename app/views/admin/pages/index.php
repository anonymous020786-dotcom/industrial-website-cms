<?php /** @var array $rows @var array $counts @var string $status */
$tabs = ['' => 'All', 'published' => 'Published', 'draft' => 'Drafts', 'system' => 'System', 'custom' => 'Custom', 'trash' => 'Trash'];
$trash = $status === 'trash';
?>
<div class="adm-tabs mb-4">
  <?php foreach ($tabs as $k => $l): ?>
  <a href="<?= e(url('admin/pages') . qs(['status' => $k ?: null])) ?>" class="adm-tab<?= $status === $k ? ' is-active' : '' ?>"><?= $l ?> <span><?= (int) $counts[$k] ?></span></a>
  <?php endforeach ?>
</div>

<?php if ($trash && $counts['trash'] > 0): ?>
<div class="mb-4 flex flex-wrap items-center gap-3 text-sm text-charcoal/70"><?= icon('info', 'h-4 w-4') ?> Items in the Trash are deleted permanently after 30 days.
  <form method="post" action="<?= e(url('admin/pages/empty-trash')) ?>" class="ml-auto"><?= csrf_field() ?><button class="adm-btn adm-btn-danger adm-btn-sm" data-confirm="Permanently delete every page in the Trash? This cannot be undone."><?= icon('trash-2', 'h-4 w-4') ?> Empty Trash</button></form>
</div>
<?php endif ?>

<form method="get" class="adm-card mb-4 flex flex-wrap gap-3 p-4">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif ?>
  <input class="adm-input min-w-[220px] flex-1" type="search" name="q" value="<?= e(query('q', '')) ?>" placeholder="Search page titles, URLs and SEO titles…" aria-label="Search pages">
  <button class="adm-btn adm-btn-primary"><?= icon('search', 'h-4 w-4') ?> Search</button>
  <?php if (query('q')): ?><a class="adm-btn adm-btn-light" href="<?= e(url('admin/pages') . qs(['q' => null])) ?>">Clear</a><?php endif ?>
</form>

<form method="post" action="<?= e(url('admin/pages/bulk')) ?>" class="adm-card" data-bulk-form>
  <?= csrf_field() ?>
  <div class="flex flex-wrap items-center gap-3 border-b border-black/10 p-3">
    <span class="text-sm text-charcoal/60" data-bulk-count>0 selected</span>
    <select class="adm-input !w-auto" name="bulk_action" aria-label="Bulk action" data-bulk-action>
      <option value="">Bulk action…</option>
      <?php if ($trash): ?>
      <option value="restore">Restore (as drafts)</option>
      <option value="destroy">Delete permanently</option>
      <?php else: ?>
      <option value="publish">Publish</option>
      <option value="draft">Set to draft</option>
      <option value="noindex">Hide from search engines</option>
      <option value="index">Show in search engines</option>
      <option value="trash">Move to Trash (custom pages)</option>
      <?php endif ?>
    </select>
    <button class="adm-btn adm-btn-light adm-btn-sm" data-confirm="Apply this action to the selected pages?">Apply</button>
    <span class="ml-auto text-sm text-charcoal/55"><?= count($rows) ?> page<?= count($rows) === 1 ? '' : 's' ?></span>
  </div>
  <?php if ($rows): ?>
  <div class="overflow-x-auto"><table class="adm-table">
    <thead><tr><th class="w-10"><input type="checkbox" data-check-all aria-label="Select all"></th><th>Page</th><th>Template</th><th>Status</th><th>SEO</th><th>Updated</th><th class="text-right">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): $seo = $p['meta_description'] ? ($p['noindex'] ? 1 : 2) : 0; $dup = in_array($p['template'], ['default', 'legal'], true); ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" data-check aria-label="Select <?= e($p['title']) ?>"></td>
        <td><a class="font-semibold text-navy hover:text-steel" href="<?= e(url('admin/pages/' . $p['id'] . '/edit')) ?>"><?= e($p['title']) ?></a>
          <div class="text-xs text-charcoal/60"><?= e($p['slug'] === 'home' ? '/' : '/' . $p['slug']) ?><?= $p['is_system'] ? ' · system page' : '' ?><?= $p['category'] ? ' · ' . e($p['category']) : '' ?></div></td>
        <td class="text-sm"><?= e(template_def($p['template'])['label']) ?></td>
        <td><?php if ($p['deleted_at']): ?><span class="adm-badge bg-red-100 text-red-800">Trash</span><?php elseif ($p['status'] === 'published'): ?><span class="adm-badge bg-emerald-100 text-emerald-800">Published</span><?php else: ?><span class="adm-badge bg-gray-200 text-gray-700">Draft</span><?php endif ?></td>
        <td><span class="adm-seo-dot adm-seo-<?= $seo ?>" title="<?= $seo === 2 ? 'Meta description set' : ($seo === 1 ? 'Hidden from search engines' : 'Missing meta description') ?>"></span></td>
        <td class="whitespace-nowrap text-sm text-charcoal/60"><?= e(time_ago($p['updated_at'])) ?></td>
        <td class="whitespace-nowrap text-right">
          <?php if ($p['deleted_at']): ?>
            <button class="adm-btn adm-btn-light adm-btn-sm" formaction="<?= e(url('admin/pages/' . $p['id'] . '/restore')) ?>"><?= icon('rotate-ccw', 'h-3.5 w-3.5') ?> Restore</button>
          <?php else: ?>
            <a class="adm-icon-btn" href="<?= e(page_url($p) . ($p['status'] === 'draft' ? '?preview=1' : '')) ?>" target="_blank" title="<?= $p['status'] === 'draft' ? 'Preview' : 'View' ?>"><?= icon('external-link', 'h-4 w-4') ?></a>
            <a class="adm-icon-btn" href="<?= e(url('admin/pages/' . $p['id'] . '/edit')) ?>" title="Edit"><?= icon('pencil', 'h-4 w-4') ?></a>
            <?php if ($dup): ?><button class="adm-icon-btn" formaction="<?= e(url('admin/pages/' . $p['id'] . '/duplicate')) ?>" title="Duplicate"><?= icon('copy', 'h-4 w-4') ?></button><?php endif ?>
            <?php if (!$p['is_system']): ?><button class="adm-icon-btn text-red-600" formaction="<?= e(url('admin/pages/' . $p['id'] . '/delete')) ?>" title="Move to Trash" data-confirm="Move “<?= e($p['title']) ?>” to the Trash?"><?= icon('trash-2', 'h-4 w-4') ?></button><?php endif ?>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <?php else: ?><p class="p-12 text-center text-sm text-charcoal/60"><?= $trash ? 'The Trash is empty.' : 'No pages found.' ?></p><?php endif ?>
</form>
<p class="mt-4 text-sm text-charcoal/60">System pages (Home, About, Products…) have fixed URLs and layouts, but all their text, images and lists are editable. Create new pages for anything else — careers, services, landing pages or extra policies — and add them to the menu in <a class="adm-link" href="<?= e(url('admin/settings/menus')) ?>">Settings → Menus</a>.</p>
