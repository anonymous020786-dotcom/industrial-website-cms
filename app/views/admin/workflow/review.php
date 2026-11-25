<?php /** @var array $review @var ?array $row @var array $changes @var bool $stale @var string $label @var string $editBase @var ?string $preview */ ?>
<div class="grid gap-6 xl:grid-cols-3">
  <div class="space-y-6 xl:col-span-2">
    <?php if (!$row): ?>
    <div class="adm-card p-6 text-sm text-red-700">This <?= e(strtolower($label)) ?> no longer exists.</div>
    <?php endif ?>
    <?php if ($stale): ?>
    <div class="adm-card flex gap-3 border-l-4 border-amber-500 p-4 text-sm" data-review-stale>
      <?= icon('triangle-alert', 'h-5 w-5 shrink-0 text-amber-600') ?>
      <p>The live <?= e(strtolower($label)) ?> was edited after this change was sent. Approving replaces those edits for the fields listed below — check them first.</p>
    </div>
    <?php endif ?>
    <?php if ($row && !$changes): ?>
    <div class="adm-card p-6 text-sm text-charcoal/70">No differences from the live version.</div>
    <?php endif ?>
    <?php foreach ($changes as $c): ?>
    <div class="adm-card p-6" data-review-change="<?= e($c['label']) ?>">
      <h2 class="adm-card-title"><?= e($c['label']) ?></h2>
      <div class="review-diff mt-3 text-sm leading-relaxed"><?= $c['diff'] ?></div>
    </div>
    <?php endforeach ?>
  </div>

  <aside class="space-y-6">
    <div class="adm-card p-6 text-sm">
      <dl class="space-y-3">
        <div><dt class="text-charcoal/55"><?= e($label) ?></dt><dd class="font-semibold text-navy"><?= e($review['title']) ?></dd></div>
        <div><dt class="text-charcoal/55">Sent by</dt><dd><?= e($review['user_name'] ?: '—') ?> · <?= e(format_date($review['created_at'], 'j M Y H:i')) ?></dd></div>
        <?php if ($review['status'] !== 'pending'): ?>
        <div><dt class="text-charcoal/55">Decision</dt><dd><?= $review['status'] === 'approved' ? '<span class="adm-badge bg-emerald-100 text-emerald-800">Approved</span>' : '<span class="adm-badge bg-red-100 text-red-700">Rejected</span>' ?> by <?= e($review['decided_name'] ?: '—') ?>, <?= e(format_date((string) $review['decided_at'], 'j M Y H:i')) ?></dd></div>
        <?php if ($review['note']): ?><div><dt class="text-charcoal/55">Note</dt><dd class="whitespace-pre-line"><?= e($review['note']) ?></dd></div><?php endif ?>
        <?php endif ?>
      </dl>
      <div class="mt-5 flex flex-wrap gap-2">
        <?php if ($row): ?><a class="adm-btn adm-btn-light adm-btn-sm" href="<?= e(url($editBase . $row['id'] . '/edit')) ?>"><?= icon('pencil', 'h-4 w-4') ?> Open editor</a><?php endif ?>
        <?php if ($preview && content_is_live($row)): ?><a class="adm-btn adm-btn-light adm-btn-sm" target="_blank" href="<?= e($preview) ?>"><?= icon('external-link', 'h-4 w-4') ?> Live version</a><?php endif ?>
      </div>
    </div>

    <?php if ($review['status'] === 'pending' && $row): ?>
    <form method="post" action="<?= e(url('admin/reviews/' . $review['id'] . '/decide')) ?>" class="adm-card space-y-4 p-6">
      <?= csrf_field() ?>
      <div><label class="adm-label" for="rv-note">Note to <?= e($review['user_name'] ?: 'the editor') ?></label>
        <textarea id="rv-note" name="note" rows="3" class="adm-input" placeholder="Required when sending back — what should change?"></textarea></div>
      <div class="flex flex-wrap gap-2">
        <button class="adm-btn adm-btn-primary" name="decision" value="approve"><?= icon('check', 'h-4 w-4') ?> Approve &amp; publish</button>
        <button class="adm-btn adm-btn-light" name="decision" value="reject"><?= icon('x', 'h-4 w-4') ?> Send back</button>
      </div>
    </form>
    <?php endif ?>
  </aside>
</div>
