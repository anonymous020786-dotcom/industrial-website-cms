<?php /** @var array $post */
$thread = comments_thread((int) $post['id']);
$count = array_sum(array_map(fn($c) => 1 + count($c['replies']), $thread));
$open = comments_open($post);
$state = (string) query('comment', '');
$me = function_exists('site_admin_user') ? site_admin_user() : null;
$one = function (array $c, bool $reply = false): string {
    $team = $c['user_id'] !== null;
    $h = '<article class="comment' . ($reply ? ' comment-reply' : '') . '" id="comment-' . (int) $c['id'] . '" data-comment>'
        . '<span class="comment-avatar' . ($team ? ' is-team' : '') . '" aria-hidden="true">' . e(avatar_initials((string) $c['name'])) . '</span><div class="min-w-0 flex-1">'
        . '<header class="flex flex-wrap items-baseline gap-x-3 gap-y-1"><strong class="text-navy">' . e((string) $c['name']) . '</strong>'
        . ($team ? '<span class="comment-badge">' . e(company('short_name') ?: company('name')) . ' team</span>' : '')
        . '<a class="text-xs text-charcoal/55 hover:underline" href="#comment-' . (int) $c['id'] . '"><time datetime="' . e(date('c', strtotime((string) $c['created_at']))) . '">' . e(format_date((string) $c['created_at'], 'j M Y')) . '</time></a></header>'
        . '<div class="comment-body">' . comment_html((string) $c['body']) . '</div>';
    return $h;
};
?>
<section class="mt-12" id="comments" aria-labelledby="comments-title">
  <h2 id="comments-title" class="font-display text-3xl font-semibold uppercase tracking-wide text-navy"><?= $count ? $count . ' comment' . ($count === 1 ? '' : 's') : 'Comments' ?></h2>

  <?php if ($state === 'pending'): ?><div class="comment-notice mt-6" role="status" data-comment-state="pending"><?= icon('hourglass', 'h-5 w-5') ?> Thank you — your comment is waiting for moderation and will appear once approved.</div>
  <?php elseif ($state === 'posted'): ?><div class="comment-notice is-ok mt-6" role="status" data-comment-state="posted"><?= icon('circle-check', 'h-5 w-5') ?> Thank you — your comment is published.</div><?php endif ?>

  <?php if ($thread): ?>
  <div class="mt-8 space-y-8">
    <?php foreach ($thread as $c): ?>
      <?= $one($c) ?>
        <?php if ($open): ?><button type="button" class="comment-reply-btn" data-reply-to="<?= (int) $c['id'] ?>" data-reply-name="<?= e((string) $c['name']) ?>"><?= icon('reply', 'h-4 w-4') ?> Reply</button><?php endif ?>
        <?php foreach ($c['replies'] as $r): ?><?= $one($r, true) ?></div></article><?php endforeach ?>
      </div></article>
    <?php endforeach ?>
  </div>
  <?php elseif ($open): ?>
  <p class="mt-4 text-charcoal/70">No comments yet. Questions about this topic? Ask below — our engineers reply.</p>
  <?php endif ?>

  <?php if ($open): ?>
  <form method="post" action="<?= e(url('blog/' . $post['slug'] . '/comment')) ?>" class="comment-form mt-10 grid gap-5 bg-mist p-6 sm:grid-cols-2 sm:p-8" id="comment-form" novalidate>
    <h3 class="font-display text-2xl font-semibold uppercase tracking-wide text-navy sm:col-span-2">Leave a comment</h3>
    <p class="-mt-3 text-sm text-charcoal/65 sm:col-span-2" data-reply-info hidden>Replying to <strong data-reply-label></strong> · <button type="button" class="font-semibold text-steel underline" data-reply-cancel>cancel</button></p>
    <?= form_hidden('comment') ?>
    <input type="hidden" name="parent_id" value="" data-reply-parent>
    <?php if ($me): ?>
    <?= csrf_field() ?>
    <p class="text-sm text-charcoal/70 sm:col-span-2">Commenting as <strong><?= e((string) $me['name']) ?></strong> (team) — published straight away.</p>
    <?php else: ?>
    <?= form_field(['id' => 'fullName', 'label' => 'Name', 'required' => true, 'autocomplete' => 'name']) ?>
    <?= form_field(['id' => 'email', 'label' => 'Email (not published)', 'type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
    <?php endif ?>
    <?= form_field(['id' => 'comment', 'label' => 'Comment', 'type' => 'textarea', 'required' => true, 'full' => true, 'rows' => 5, 'placeholder' => 'Share your experience or ask a question…']) ?>
    <?php if (!$me): ?>
    <div class="field sm:col-span-2"><label class="consent"><input type="checkbox" name="consent" value="1" required><span>Publish my name and comment. My email is only used to contact me about it (<a class="font-semibold text-steel underline" href="<?= e(url('privacy-policy')) ?>">Privacy Policy</a>). <span class="req" aria-hidden="true">*</span></span></label></div>
    <?php if (setting('newsletter_enabled', true)): ?><div class="field sm:col-span-2"><label class="consent"><input type="checkbox" name="newsletter" value="1"><span>Also send me the newsletter (confirm by email, unsubscribe any time).</span></label></div><?php endif ?>
    <?= turnstile_widget('sm:col-span-2') ?>
    <?php endif ?>
    <p class="text-xs text-charcoal/55 sm:col-span-2"><?= setting('comments_moderation', 'first') === 'none' ? 'Comments are published straight away; spam is filtered.' : 'Comments are moderated and appear after approval.' ?> Please keep it professional.</p>
    <div class="sm:col-span-2"><button class="btn btn-primary"><?= icon('send', 'h-4 w-4') ?> Post comment</button></div>
  </form>
  <?php elseif (setting('comments_enabled', true) && $post['allow_comments']): ?>
  <p class="mt-6 text-sm text-charcoal/60">Comments are closed for this article. <a class="font-semibold text-steel underline" href="<?= e(url('contact')) ?>">Contact us</a> with any questions.</p>
  <?php endif ?>
</section>
