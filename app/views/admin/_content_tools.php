<?php
/**
 * Sidebar tools for a post or page (rendered INSIDE the edit form — buttons use formaction,
 * so they submit the form's CSRF token to their own endpoint without nesting forms).
 * @var string $type posts|pages @var array $item @var array $revisions @var bool $canTrash @var bool $canDuplicate
 */
$base = "admin/{$type}/{$item['id']}";
$noun = $type === 'posts' ? 'post' : 'page';
?>
<div class="adm-card p-6">
  <h2 class="adm-card-title"><?= icon('wrench', 'h-5 w-5 text-steel') ?> Actions</h2>
  <div class="mt-4 grid gap-2">
    <?php if ($canDuplicate): ?>
    <button class="adm-btn adm-btn-light justify-center" formaction="<?= e(url("{$base}/duplicate")) ?>" formnovalidate data-confirm="Create a copy of this <?= $noun ?> as a new draft? Unsaved changes on this screen are not copied."><?= icon('copy', 'h-4 w-4') ?> Duplicate as draft</button>
    <?php endif ?>
    <?php if ($canTrash): ?>
    <button class="adm-btn adm-btn-light justify-center !text-red-700" formaction="<?= e(url("{$base}/delete")) ?>" formnovalidate data-confirm="Move this <?= $noun ?> to the Trash? It is unpublished immediately and can be restored for 30 days."><?= icon('trash-2', 'h-4 w-4') ?> Move to Trash</button>
    <?php endif ?>
  </div>
</div>

<div class="adm-card p-6" id="revisions">
  <h2 class="adm-card-title"><?= icon('history', 'h-5 w-5 text-steel') ?> Revision history <span class="ml-auto text-xs font-normal text-charcoal/55"><?= count($revisions) ?></span></h2>
  <?php if (!$revisions): ?>
    <p class="mt-3 text-sm text-charcoal/60">Every save keeps the previous version here, so you can roll back at any time.</p>
  <?php else: ?>
  <ol class="mt-4 max-h-80 space-y-3 overflow-y-auto pr-1 text-sm">
    <?php foreach ($revisions as $i => $rev): ?>
    <li class="flex items-start justify-between gap-3 border-b border-black/5 pb-3 last:border-0">
      <div class="min-w-0">
        <p class="font-medium text-navy" title="<?= e(format_date($rev['created_at'], 'j M Y H:i')) ?>"><?= e(time_ago($rev['created_at'])) ?><?= $rev['user_name'] ? ' · ' . e($rev['user_name']) : '' ?></p>
        <p class="truncate text-xs text-charcoal/60"><?= $rev['changed'] ? 'Differs in: ' . e(implode(', ', array_slice($rev['changed'], 0, 4))) : 'Same as current' ?></p>
      </div>
      <?php if ($rev['changed']): ?>
      <button class="adm-btn adm-btn-light adm-btn-sm shrink-0" formaction="<?= e(url("{$base}/revisions/{$rev['id']}/restore")) ?>" formnovalidate data-confirm="Restore this version? The current version is saved to the history first, and the publish status is not changed."><?= icon('rotate-ccw', 'h-3.5 w-3.5') ?> Restore</button>
      <?php endif ?>
    </li>
    <?php endforeach ?>
  </ol>
  <?php endif ?>
</div>
