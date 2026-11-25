<?php /** @var array $pending @var array $recent */
$approvalOn = (bool) setting('content_approval', false);
?>
<?php if (!$approvalOn): ?>
<div class="adm-card mb-6 flex flex-wrap items-center gap-4 p-4 text-sm">
  <?= icon('info', 'h-5 w-5 text-steel') ?>
  <p class="flex-1 text-charcoal/75">Approval is <strong>off</strong>: editors publish directly. Turn it on so editors’ changes to live pages and posts wait for an administrator.</p>
  <a class="adm-btn adm-btn-light adm-btn-sm" href="<?= e(url('admin/settings/workflow')) ?>">Turn on</a>
</div>
<?php endif ?>

<div class="adm-card">
  <h2 class="adm-card-title border-b border-black/10 p-5"><?= icon('hourglass', 'h-5 w-5 text-amber-600') ?> Waiting for approval <span class="adm-badge bg-amber-100 text-amber-800"><?= count($pending) ?></span></h2>
  <?php if ($pending): ?>
  <table class="adm-table">
    <thead><tr><th>Item</th><th>Type</th><th>Sent by</th><th>Sent</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pending as $r): ?>
      <tr data-review-row>
        <td class="font-semibold text-navy"><?= e($r['title']) ?></td>
        <td class="text-sm"><?= e(REVIEW_ENTITIES[$r['entity']][1] ?? $r['entity']) ?></td>
        <td class="text-sm"><?= e($r['user_name'] ?: '—') ?></td>
        <td class="whitespace-nowrap text-sm text-charcoal/60" title="<?= e($r['created_at']) ?>"><?= e(time_ago($r['created_at'])) ?></td>
        <td class="text-right"><a class="adm-btn adm-btn-primary adm-btn-sm" href="<?= e(url('admin/reviews/' . $r['id'])) ?>"><?= icon('git-compare', 'h-4 w-4') ?> Review</a></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="p-8 text-center text-sm text-charcoal/60">Nothing to review.</p>
  <?php endif ?>
</div>

<?php if ($recent): ?>
<div class="adm-card mt-6">
  <h2 class="adm-card-title border-b border-black/10 p-5"><?= icon('history', 'h-5 w-5 text-steel') ?> Recent decisions</h2>
  <table class="adm-table">
    <thead><tr><th>Item</th><th>Sent by</th><th>Decision</th><th>Note</th><th>When</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><a class="font-semibold text-navy hover:text-steel" href="<?= e(url('admin/reviews/' . $r['id'])) ?>"><?= e($r['title']) ?></a></td>
        <td class="text-sm"><?= e($r['user_name'] ?: '—') ?></td>
        <td><?= $r['status'] === 'approved' ? '<span class="adm-badge bg-emerald-100 text-emerald-800">Approved</span>' : '<span class="adm-badge bg-red-100 text-red-700">Rejected</span>' ?> <span class="text-xs text-charcoal/55">by <?= e($r['decided_name'] ?: '—') ?></span></td>
        <td class="max-w-xs truncate text-sm text-charcoal/70"><?= e((string) $r['note']) ?></td>
        <td class="whitespace-nowrap text-sm text-charcoal/60"><?= e(time_ago((string) $r['decided_at'])) ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php endif ?>
