<?php
/** @var array $page @var array $data @var array $posts @var array $pager @var string $q @var ?array $category @var array $categories */
$crumbs = [['label' => $page['title'], 'url' => url('blog')]];
if ($category) $crumbs[] = ['label' => $category['name'], 'url' => url('blog/category/' . $category['slug'])];
$title = $category ? $category['name'] : ($page['hero_title'] ?: $page['title']);
$lead = $category ? ($category['description'] ?: 'Articles and guides about ' . $category['name'] . '.') : $page['hero_lead'];
$base = $category ? url('blog/category/' . $category['slug']) : url('blog');
$featured = (!$category && $q === '' && $pager['page'] === 1 && $posts) ? array_shift($posts) : null;
?>
<?= page_hero(['eyebrow' => $category ? 'Blog · Category' : ($page['hero_eyebrow'] ?: 'Blog'), 'title' => $title, 'lead' => $lead, 'image' => $category ? '' : $page['hero_image'], 'crumbs' => $crumbs]) ?>

<section class="border-b border-black/10 bg-white">
  <div class="mx-auto flex max-w-site flex-col gap-4 px-4 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
    <nav aria-label="Blog categories" class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
      <a href="<?= e(url('blog')) ?>" class="chip<?= !$category ? ' is-active' : '' ?>">All</a>
      <?php foreach ($categories as $c): if (!(int) $c['post_count']) continue; ?>
      <a href="<?= e(url('blog/category/' . $c['slug'])) ?>" class="chip<?= $category && (int) $category['id'] === (int) $c['id'] ? ' is-active' : '' ?>"><?= e($c['name']) ?> <span class="opacity-60"><?= (int) $c['post_count'] ?></span></a>
      <?php endforeach ?>
    </nav>
    <form action="<?= e($base) ?>" method="get" class="search-box lg:w-80" role="search">
      <?= icon('search', 'h-4 w-4 text-charcoal/50') ?><label class="sr-only" for="blog-q">Search articles</label>
      <input id="blog-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search articles…">
    </form>
  </div>
</section>

<section class="section bg-mist !pt-12">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?php if ($q !== ''): ?><p class="mb-8 text-charcoal/70"><?= (int) $pager['total'] ?> result<?= $pager['total'] == 1 ? '' : 's' ?> for “<strong class="text-navy"><?= e($q) ?></strong>” · <a class="font-semibold text-steel underline" href="<?= e($base) ?>">Clear search</a></p><?php endif ?>

    <?php if ($featured): ?>
      <div class="mb-6 lg:grid"><?= str_replace('post-card group', 'post-card post-card-featured group', post_card($featured, 0, true)) ?></div>
    <?php endif ?>

    <?php if ($posts): ?>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($posts as $i => $p) echo post_card($p, $i) ?></div>
    <?php elseif (!$featured): ?>
      <?php if ($q !== '' || $category): ?>
      <div class="empty-state"><?= icon('search-x', 'h-8 w-8 text-charcoal/40') ?><p class="mt-4 font-semibold text-navy">No articles found.</p><p class="mt-1 text-sm text-charcoal/60">Try a different search or browse all articles.</p><a href="<?= e(url('blog')) ?>" class="btn btn-primary mt-6">All articles</a></div>
      <?php else: ?>
      <div class="empty-state"><?= icon('newspaper', 'h-8 w-8 text-charcoal/40') ?><p class="mt-4 font-semibold text-navy">New technical guides are on their way.</p><p class="mt-1 max-w-md text-sm text-charcoal/60">Our engineers are preparing practical articles on hydraulics, crane components and maintenance. In the meantime, ask us your question directly.</p><a href="<?= e(url('contact')) ?>" class="btn btn-primary mt-6">Ask an Engineer</a></div>
      <?php endif ?>
    <?php endif ?>

    <?php if ($pager['pages'] > 1): ?>
    <nav class="pagination mt-12" aria-label="Pagination">
      <?php if ($pager['page'] > 1): ?><a href="<?= e($base . qs(['page' => $pager['page'] - 1 ?: null])) ?>" rel="prev" class="page-link"><?= icon('chevron-left', 'h-4 w-4') ?> Prev</a><?php endif ?>
      <?php for ($i = 1; $i <= $pager['pages']; $i++): if ($i > 2 && $i < $pager['pages'] - 1 && abs($i - $pager['page']) > 1) { if ($i === 3 || $i === $pager['pages'] - 2) echo '<span class="page-gap">…</span>'; continue; } ?>
        <a href="<?= e($base . qs(['page' => $i > 1 ? $i : null])) ?>" class="page-link<?= $i === $pager['page'] ? ' is-active' : '' ?>"<?= $i === $pager['page'] ? ' aria-current="page"' : '' ?>><?= $i ?></a>
      <?php endfor ?>
      <?php if ($pager['page'] < $pager['pages']): ?><a href="<?= e($base . qs(['page' => $pager['page'] + 1])) ?>" rel="next" class="page-link">Next <?= icon('chevron-right', 'h-4 w-4') ?></a><?php endif ?>
    </nav>
    <?php endif ?>
  </div>
</section>

<?= cta_band($data['cta_title'] ?? null, $data['cta_text'] ?? null) ?>
