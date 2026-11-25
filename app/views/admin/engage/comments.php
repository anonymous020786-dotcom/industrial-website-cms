<?php /** @var array $rows @var array $pager @var array $counts @var string $status @var int $total */ ?>
<div class="adm-tabs mb-4">
  <?php foreach (COMMENT_STATUSES as $k => [$l]): ?>
  <a href="<?= e(url('admin/comments') . qs(['status' => $k, 'page' => null])) ?>" class="adm-tab<?= $status === $k ? ' is-active' : '' ?>"><?= e($l) ?> <span><?= (int) ($counts[$k] ?? 0) ?></span></a>
  <?php endforeach ?>
</div>

<div data-bulk-form>
<form method="post" action="<?= e(url('admin/comments/bulk')) ?>" class="adm-card" id="comments-bulk">
  <?= csrf_field() ?>
  <div class="flex flex-wrap items-center gap-3 p-3">
    <input type="checkbox" data-check-all aria-label="Select all">
    <span class="text-sm text-charcoal/60" data-bulk-count>0 selected</span>
    <select class="adm-input !w-auto" name="bulk_action" aria-label="Bulk action">
      <option value="">Bulk action…</option>
      <?php if ($status !== 'approved'): ?><option value="approve">Approve</option><?php endif ?>
      <?php if ($status !== 'spam'): ?><option value="spam">Mark as spam</option><?php endif ?>
      <?php if ($status !== 'trash'): ?><option value="trash">Move to trash</option><?php endif ?>
      <?php if ($status !== 'pending'): ?><option value="pending">Back to pending</option><?php endif ?>
      <?php if (in_array($status, ['spam', 'trash'], true)): ?><option value="delete">Delete permanently</option><?php endif ?>
    </select>
    <button class="adm-btn adm-btn-light adm-btn-sm" data-confirm="Apply this action to the selected comments?">Apply</button>
    <span class="ml-auto text-sm text-charcoal/55"><?= $total ?> comment<?= $total === 1 ? '' : 's' ?></span>
  </div>
</form>

<?php if ($rows): ?>
<div class="mt-4 space-y-4">
  <?php foreach ($rows as $c): ?>
  <div class="adm-card p-5" data-mod-comment="<?= (int) $c['id'] ?>">
    <div class="flex flex-wrap items-start gap-4">
      <input type="checkbox" name="ids[]" value="<?= (int) $c['id'] ?>" form="comments-bulk" data-check aria-label="Select comment <?= (int) $c['id'] ?>" class="mt-1">
      <div class="min-w-0 flex-1">
        <p class="text-sm"><strong class="text-navy"><?= e($c['name']) ?></strong>
          <?php if ($c['user_id']): ?><span class="adm-badge bg-amber-100 text-amber-800">Team</span><?php endif ?>
          <span class="text-charcoal/55"> · <?= e((string) $c['email']) ?> · <?= e((string) $c['ip']) ?> · <?= e(time_ago($c['created_at'])) ?></span>
          <?php if (!$c['user_id'] && (int) $c['approved_before'] > 0 && $c['status'] !== 'approved'): ?><span class="adm-badge bg-emerald-100 text-emerald-800">Approved before</span><?php endif ?>
        </p>
        <p class="mt-1 text-xs text-charcoal/55">On <a class="font-semibold text-steel hover:underline" href="<?= e(url('blog/' . $c['post_slug']) . '#comment-' . $c['id']) ?>" target="_blank"><?= e((string) $c['post_title']) ?></a><?= $c['parent_name'] ? ' · reply to ' . e($c['parent_name']) : '' ?><?= $c['spam_reason'] ? ' · <span class="font-semibold text-red-700">' . e($c['spam_reason']) . '</span>' : '' ?></p>
        <div class="mt-3 whitespace-pre-line rounded bg-black/[.03] p-3 text-sm leading-relaxed"><?= e($c['body']) ?></div>
        <div class="mt-3 flex flex-wrap gap-2">
          <?php foreach (['approve' => ['Approve', 'check', 'adm-btn-primary'], 'pending' => ['Unapprove', 'undo-2', 'adm-btn-light'], 'spam' => ['Spam', 'shield-alert', 'adm-btn-light'], 'trash' => ['Trash', 'trash-2', 'adm-btn-light'], 'delete' => ['Delete', 'x', 'adm-btn-light']] as $act => [$label, $ic, $cls]):
            if ($act === 'approve' && $c['status'] === 'approved' || $act === 'pending' && $c['status'] !== 'approved' || $act === $c['status'] || $act === 'delete' && !in_array($c['status'], ['spam', 'trash'], true)) continue; ?>
          <form method="post" action="<?= e(url('admin/comments/' . $c['id'] . '/action')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $act ?>">
            <button class="adm-btn <?= $cls ?> adm-btn-sm"<?= $act === 'delete' ? ' data-confirm="Delete this comment (and its replies) permanently?"' : '' ?>><?= icon($ic, 'h-4 w-4') ?> <?= $label ?></button></form>
          <?php endforeach ?>
          <?php if (in_array($c['status'], ['pending', 'approved'], true)): ?>
          <details class="w-full"><summary class="cursor-pointer text-sm font-semibold text-steel"><?= icon('reply', 'inline h-4 w-4') ?> Reply as the team</summary>
            <form method="post" action="<?= e(url('admin/comments/' . $c['id'] . '/reply')) ?>" class="mt-2 space-y-2"><?= csrf_field() ?>
              <textarea name="reply" rows="3" class="adm-input" required placeholder="Your reply is published at once<?= $c['status'] === 'pending' ? ' and approves this comment' : '' ?>."></textarea>
              <button class="adm-btn adm-btn-primary adm-btn-sm"><?= icon('send', 'h-4 w-4') ?> Publish reply</button></form>
          </details>
          <?php endif ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach ?>
</div>
<?= view('admin/_pager', ['pager' => $pager]) ?>
<?php else: ?>
<div class="adm-card mt-4 p-12 text-center"><span class="mx-auto block w-fit text-charcoal/30"><?= icon('message-square', 'h-10 w-10') ?></span><p class="mt-3 font-semibold text-navy"><?= $status === 'pending' ? 'Nothing waiting for moderation' : 'No comments here' ?></p></div>
<?php endif ?>
</div>
