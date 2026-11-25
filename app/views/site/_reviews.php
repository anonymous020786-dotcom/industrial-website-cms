<?php /** @var ?array $product  (null = all reviews page) */
$pid = $product ? (int) $product['id'] : null;
$stats = reviews_stats($pid);
$list = reviews_for($pid, $product ? 20 : 100);
$state = (string) query('review', '');
?>
<section id="reviews" class="section<?= $product ? ' bg-mist' : '' ?>" aria-labelledby="reviews-title">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <div class="grid gap-10 lg:grid-cols-12">
      <div class="lg:col-span-4">
        <p class="eyebrow">Customer reviews</p>
        <h2 id="reviews-title" class="mt-3 font-display text-4xl font-bold uppercase tracking-tight text-navy"><?= $product ? 'What buyers say' : 'Reviews from our customers' ?></h2>
        <?php if ($stats['count']): ?>
        <div class="mt-6 flex items-center gap-4" data-review-summary>
          <span class="font-display text-6xl font-bold text-navy"><?= e(number_format($stats['avg'], 1)) ?></span>
          <span><span class="block text-safety"><?= stars_html($stats['avg'], 'h-6 w-6') ?></span><span class="text-sm text-charcoal/65"><?= $stats['count'] ?> review<?= $stats['count'] === 1 ? '' : 's' ?></span></span>
        </div>
        <ul class="mt-5 space-y-1.5 text-sm">
          <?php foreach ($stats['dist'] as $star => $n): ?>
          <li class="flex items-center gap-3"><span class="w-8 text-charcoal/70"><?= $star ?> ★</span><span class="h-2 flex-1 bg-black/10"><span class="block h-2 bg-safety" style="width:<?= $stats['count'] ? round($n / $stats['count'] * 100) : 0 ?>%"></span></span><span class="w-6 text-right text-charcoal/60"><?= $n ?></span></li>
          <?php endforeach ?>
        </ul>
        <?php else: ?>
        <p class="mt-4 text-charcoal/70">No reviews yet<?= $product ? ' for ' . e($product['name']) : '' ?>. Bought from us? Your experience helps other engineers decide.</p>
        <?php endif ?>
        <?php if (reviews_enabled()): ?><a href="#write-review" class="btn btn-primary mt-8" data-review-open><?= icon('star', 'h-4 w-4') ?> Write a review</a><?php endif ?>
      </div>

      <div class="space-y-5 lg:col-span-8">
        <?php if ($state === 'pending'): ?><div class="comment-notice" role="status" data-review-state="pending"><?= icon('hourglass', 'h-5 w-5') ?> Thank you for your review — it will appear after a quick check.</div><?php endif ?>
        <?php foreach ($list as $r): ?>
        <article class="bg-white p-6" data-review>
          <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="text-safety"><?= stars_html((float) $r['rating']) ?></span>
            <?php if ($r['title']): ?><h3 class="font-semibold text-navy"><?= e($r['title']) ?></h3><?php endif ?>
          </div>
          <p class="mt-3 whitespace-pre-line leading-relaxed text-charcoal/85"><?= e($r['body']) ?></p>
          <p class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-charcoal/60">
            <strong class="text-navy"><?= e($r['name']) ?></strong><?= $r['company'] ? '<span>' . e($r['company']) . '</span>' : '' ?>
            <?php if ($r['verified']): ?><span class="review-verified"><?= icon('badge-check', 'h-4 w-4') ?> Verified customer</span><?php endif ?>
            <span><?= e(format_date((string) $r['created_at'], 'j M Y')) ?></span>
            <?php if (!$product && $r['product_name']): ?><a class="font-semibold text-steel hover:underline" href="<?= e(url('products/' . $r['product_slug'])) ?>"><?= e($r['product_name']) ?></a><?php endif ?>
          </p>
          <?php if ($r['reply']): ?>
          <div class="mt-4 border-l-4 border-safety bg-mist p-4 text-sm"><p class="font-semibold text-navy">Reply from <?= e(company('short_name') ?: company('name')) ?></p><p class="mt-1 whitespace-pre-line text-charcoal/80"><?= e($r['reply']) ?></p></div>
          <?php endif ?>
        </article>
        <?php endforeach ?>

        <?php if (reviews_enabled()): ?>
        <form method="post" action="<?= e(url('reviews/submit')) ?>" class="review-form bg-white p-6 sm:p-8" id="write-review" novalidate>
          <h3 class="font-display text-2xl font-semibold uppercase tracking-wide text-navy">Write a review</h3>
          <?= form_hidden('review') ?>
          <input type="hidden" name="product_id" value="<?= (int) $pid ?>">
          <fieldset class="mt-5"><legend class="text-sm font-semibold text-navy">Your rating <span class="req" aria-hidden="true">*</span></legend>
            <div class="star-input mt-2" role="radiogroup" aria-label="Rating">
              <?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" id="rv-<?= $i ?>" name="rating" value="<?= $i ?>" required><label for="rv-<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><span class="sr-only"><?= $i ?> stars</span><?= icon('star', 'h-8 w-8') ?></label><?php endfor ?>
            </div></fieldset>
          <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <?= form_field(['id' => 'title', 'label' => 'Headline', 'placeholder' => 'e.g. Delivered in 9 days, perfect fit', 'full' => true]) ?>
            <?= form_field(['id' => 'body', 'label' => 'Your review', 'type' => 'textarea', 'required' => true, 'full' => true, 'rows' => 5, 'placeholder' => 'Quality, fit, delivery time, support — what should other buyers know?']) ?>
            <?= form_field(['id' => 'fullName', 'label' => 'Name (shown)', 'required' => true, 'autocomplete' => 'name']) ?>
            <?= form_field(['id' => 'company', 'label' => 'Company (shown, optional)', 'autocomplete' => 'organization']) ?>
            <?= form_field(['id' => 'email', 'label' => 'Email (not shown)', 'type' => 'email', 'required' => true, 'autocomplete' => 'email', 'full' => true]) ?>
            <div class="field sm:col-span-2"><label class="consent"><input type="checkbox" name="consent" value="1" required><span>Publish my review with my name and company. My email is only used to verify it (<a class="font-semibold text-steel underline" href="<?= e(url('privacy-policy')) ?>">Privacy Policy</a>). <span class="req" aria-hidden="true">*</span></span></label></div>
            <?= turnstile_widget('sm:col-span-2') ?>
          </div>
          <button class="btn btn-primary mt-6"><?= icon('send', 'h-4 w-4') ?> Submit review</button>
          <p class="mt-3 text-xs text-charcoal/55">Reviews are checked before they appear. We publish positive and negative reviews alike.</p>
        </form>
        <?php endif ?>
      </div>
    </div>
  </div>
</section>
