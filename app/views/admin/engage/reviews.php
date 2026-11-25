<?php /** @var array $rows @var array $pager @var array $counts @var string $status @var array $stats @var int $total */ ?>
<div class="mb-4 flex flex-wrap items-center gap-3">
  <div class="adm-tabs">
    <?php foreach (REVIEW_STATUSES as $k => [$l]): ?><a href="<?= e(url('admin/reviews-moderation') . qs(['status' => $k, 'page' => null])) ?>" class="adm-tab<?= $status === $k ? ' is-active' : '' ?>"><?= e($l) ?> <span><?= (int) ($counts[$k] ?? 0) ?></span></a><?php endforeach ?>
  </div>
  <span class="ml-auto inline-flex items-center gap-2 text-sm text-charcoal/65"><span class="text-amber-500"><?= stars_html($stats['avg']) ?></span><?= $stats['count'] ? e(number_format($stats['avg'], 1)) . ' average from ' . $stats['count'] . ' published' : 'No published reviews yet' ?> · <a class="adm-link" href="<?= e(url('reviews')) ?>" target="_blank">/reviews</a></span>
</div>
<?php if ($rows): ?>
<div class="space-y-4">
  <?php foreach ($rows as $r): ?>
  <div class="adm-card p-5" data-mod-review="<?= (int) $r['id'] ?>">
    <div class="flex flex-wrap items-center gap-3">
      <span class="text-amber-500"><?= stars_html((float) $r['rating']) ?></span>
      <strong class="text-navy"><?= e((string) ($r['title'] ?: '(no headline)')) ?></strong>
      <?php if ($r['verified']): ?><span class="adm-badge bg-emerald-100 text-emerald-800">Verified customer</span><?php endif ?>
      <?php if ((int) $r['rating'] <= 2): ?><span class="adm-badge bg-red-100 text-red-700">Low rating — consider replying</span><?php endif ?>
    </div>
    <p class="mt-1 text-xs text-charcoal/55"><?= e($r['name']) ?><?= $r['company'] ? ' · ' . e($r['company']) : '' ?> · <?= e((string) $r['email']) ?> · <?= e(time_ago($r['created_at'])) ?> · <?= $r['product_name'] ? 'on <a class="adm-link" target="_blank" href="' . e(url('products/' . $r['product_slug']) . '#reviews') . '">' . e($r['product_name']) . '</a>' : 'company review' ?></p>
    <p class="mt-3 whitespace-pre-line rounded bg-black/[.03] p-3 text-sm leading-relaxed"><?= e($r['body']) ?></p>
    <div class="mt-3 flex flex-wrap gap-2">
      <?php foreach (array_filter(['approve' => $r['status'] !== 'approved' ? ['Approve & publish', 'check', 'adm-btn-primary'] : null, 'reject' => $r['status'] !== 'rejected' ? ['Reject', 'x', 'adm-btn-light'] : null,
        'verify' => [$r['verified'] ? 'Remove verified badge' : 'Mark verified', 'badge-check', 'adm-btn-light'], 'testimonial' => $r['status'] === 'approved' && $r['rating'] >= 4 ? ['Use as testimonial', 'quote', 'adm-btn-light'] : null,
        'delete' => ['Delete', 'trash-2', 'adm-btn-light']]) as $act => [$label, $ic, $cls]): ?>
      <form method="post" action="<?= e(url('admin/reviews-moderation/' . $r['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $act ?>">
        <button class="adm-btn <?= $cls ?> adm-btn-sm"<?= $act === 'delete' ? ' data-confirm="Delete this review permanently?"' : '' ?>><?= icon($ic, 'h-4 w-4') ?> <?= e($label) ?></button></form>
      <?php endforeach ?>
    </div>
    <form method="post" action="<?= e(url('admin/reviews-moderation/' . $r['id'])) ?>" class="mt-3 space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="reply">
      <label class="adm-label" for="rr-<?= (int) $r['id'] ?>">Public reply from the team</label>
      <textarea id="rr-<?= (int) $r['id'] ?>" name="reply" rows="2" class="adm-input" placeholder="Thank them, or explain how you fixed the problem — shown under the review."><?= e((string) $r['reply']) ?></textarea>
      <button class="adm-btn adm-btn-light adm-btn-sm"><?= icon('reply', 'h-4 w-4') ?> Save reply</button></form>
  </div>
  <?php endforeach ?>
</div>
<?= view('admin/_pager', ['pager' => $pager]) ?>
<?php else: ?>
<div class="adm-card p-12 text-center"><span class="mx-auto block w-fit text-charcoal/30"><?= icon('star', 'h-10 w-10') ?></span><p class="mt-3 font-semibold text-navy"><?= $status === 'pending' ? 'No reviews waiting' : 'No reviews here' ?></p><p class="mt-1 text-sm text-charcoal/55">Ask happy customers to review the product they bought — the link is on every product page.</p></div>
<?php endif ?>
