<?php
/** @var array $post @var string $content @var array $toc @var array $related @var ?array $prev @var ?array $next @var string $url */
$crumbs = [['label' => 'Blog', 'url' => url('blog')]];
if ($post['category_name']) $crumbs[] = ['label' => $post['category_name'], 'url' => url('blog/category/' . $post['category_slug'])];
$crumbs[] = ['label' => $post['title'], 'url' => post_url($post)];
$tags = array_filter(array_map('trim', explode(',', (string) $post['tags'])));
$share = rawurlencode($url);
$shareTitle = rawurlencode($post['title']);
?>
<?php if ($post['status'] !== 'published' || ($post['published_at'] && strtotime($post['published_at']) > time())): ?>
<div class="bg-amber-100 px-4 py-3 text-center text-sm font-semibold text-amber-900">Preview — this article is <?= e($post['status']) ?> and not visible to the public.</div>
<?php endif ?>

<section class="blueprint bg-navy text-white">
  <div class="mx-auto max-w-site px-4 pb-14 pt-12 sm:px-6 lg:px-8 lg:pb-16">
    <?= breadcrumbs_html($crumbs) ?>
    <div class="mt-10 max-w-4xl">
      <?php if ($post['category_name']): ?><a href="<?= e(url('blog/category/' . $post['category_slug'])) ?>" class="eyebrow eyebrow-light hero-in hover:text-white"><?= e($post['category_name']) ?></a><?php endif ?>
      <h1 class="hero-in mt-4 font-display text-4xl font-bold uppercase leading-[1] tracking-tight sm:text-5xl lg:text-6xl" style="--d:80ms"><?= e($post['title']) ?></h1>
      <p class="hero-in mt-6 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-white/70" style="--d:160ms">
        <span class="flex items-center gap-2"><?= icon('calendar', 'h-4 w-4 text-safety') ?><time datetime="<?= e(date('c', strtotime($post['published_at'] ?? $post['created_at']))) ?>"><?= e(format_date($post['published_at'] ?? $post['created_at'], 'j F Y')) ?></time></span>
        <span class="flex items-center gap-2"><?= icon('clock', 'h-4 w-4 text-safety') ?><?= (int) $post['reading_minutes'] ?> min read</span>
        <span class="flex items-center gap-2"><?= icon('user', 'h-4 w-4 text-safety') ?><?= e($post['author_name'] ?: company('name') . ' Engineering Team') ?></span>
      </p>
    </div>
  </div>
</section>
<div class="reading-progress" aria-hidden="true"><span></span></div>

<article class="section !pt-12">
  <div class="mx-auto grid max-w-site gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
    <div class="lg:col-span-8">
      <?php if ($post['featured_image']): ?>
      <figure class="img-frame mb-10 aspect-[16/9]" data-fallback-label="<?= e($post['category_name'] ?? '') ?>"><?= img_tag($post['featured_image'], (string) ($post['featured_alt'] ?: $post['title']), ['widths' => [640, 1100, 1600], 'sizes' => '(min-width:1024px) 66vw, 100vw', 'lazy' => false]) ?></figure>
      <?php endif ?>
      <?php if ($post['excerpt']): ?><p class="article-lead"><?= e($post['excerpt']) ?></p><?php endif ?>
      <div class="article"><?= $content ?></div>

      <?php if ($tags): ?>
      <ul class="mt-12 flex flex-wrap gap-2" aria-label="Tags"><?php foreach ($tags as $t): ?><li><a class="chip" href="<?= e(url('blog') . '?q=' . rawurlencode($t)) ?>">#<?= e($t) ?></a></li><?php endforeach ?></ul>
      <?php endif ?>

      <div class="mt-10 flex flex-wrap items-center gap-3 border-y border-black/10 py-5">
        <span class="text-sm font-semibold uppercase tracking-widest text-charcoal/55">Share</span>
        <a class="share-btn" target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $share ?>" aria-label="Share on LinkedIn"><?= icon('linkedin', 'h-4 w-4') ?></a>
        <a class="share-btn" target="_blank" rel="noopener" href="https://wa.me/?text=<?= $shareTitle ?>%20<?= $share ?>" aria-label="Share on WhatsApp"><?= icon('message-circle', 'h-4 w-4') ?></a>
        <a class="share-btn" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?url=<?= $share ?>&amp;text=<?= $shareTitle ?>" aria-label="Share on X"><?= icon('twitter', 'h-4 w-4') ?></a>
        <a class="share-btn" href="mailto:?subject=<?= $shareTitle ?>&amp;body=<?= $share ?>" aria-label="Share by email"><?= icon('mail', 'h-4 w-4') ?></a>
        <button type="button" class="share-btn" data-copy="<?= e($url) ?>" aria-label="Copy link"><?= icon('link', 'h-4 w-4') ?></button>
      </div>

      <?php if ($prev || $next): ?>
      <nav class="mt-10 grid gap-4 sm:grid-cols-2" aria-label="More articles">
        <?php if ($prev): ?><a href="<?= e(url('blog/' . $prev['slug'])) ?>" class="post-nav"><span class="post-nav-label"><?= icon('arrow-left', 'h-4 w-4') ?> Previous</span><span class="post-nav-title"><?= e($prev['title']) ?></span></a><?php else: ?><span></span><?php endif ?>
        <?php if ($next): ?><a href="<?= e(url('blog/' . $next['slug'])) ?>" class="post-nav text-right"><span class="post-nav-label justify-end">Next <?= icon('arrow-right', 'h-4 w-4') ?></span><span class="post-nav-title"><?= e($next['title']) ?></span></a><?php endif ?>
      </nav>
      <?php endif ?>
    </div>

    <aside class="lg:col-span-4">
      <div class="space-y-6 lg:sticky lg:top-28">
        <?php if (count($toc) > 1): ?>
        <nav class="toc" aria-label="In this article">
          <p class="toc-title">In this article</p>
          <ol><?php foreach ($toc as $i => $h): ?><li><a href="#<?= e($h['id']) ?>" data-toc-link><span><?= pad2($i + 1) ?></span><?= e($h['text']) ?></a></li><?php endforeach ?></ol>
        </nav>
        <?php endif ?>
        <div class="blueprint bg-navy p-7 text-white">
          <p class="font-display text-2xl font-semibold uppercase leading-tight">Need help with your equipment?</p>
          <p class="mt-2 text-sm text-white/70">Talk to our engineers about components, repairs or custom manufacturing.</p>
          <a href="<?= e(url('quote')) ?>" class="btn btn-primary mt-5 w-full justify-center">Request a Quote <?= icon('arrow-right', 'btn-arrow h-4 w-4') ?></a>
          <?php if ($wa = whatsapp_link('Hello, I read your article "' . $post['title'] . '" and have a question.')): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="btn btn-ghost-light mt-3 w-full justify-center" data-track="whatsapp"><?= icon('message-circle', 'h-4 w-4') ?> Ask on WhatsApp</a><?php endif ?>
        </div>
      </div>
    </aside>
  </div>
</article>

<?php if ($related): ?>
<section class="section bg-mist">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?= section_head('Keep Reading', 'Related Articles') ?>
    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($related as $i => $r) echo post_card($r, $i) ?></div>
  </div>
</section>
<?php endif ?>
