<?php /** @var array $page @var array $meta */
$pages = db_all("SELECT slug, title, template FROM pages WHERE status = 'published' AND noindex = 0 AND template NOT IN ('legal','sitemap') ORDER BY FIELD(slug,'home') DESC, id");
$groups = [
    'Company' => array_map(fn($p) => [$p['title'], page_url($p)], $pages),
    'Products' => array_merge([['All Products', url('products')]], array_map(fn($p) => [$p['name'], product_url($p)], products_all())),
    'Industries' => array_map(fn($i) => [$i['name'], url('industries#' . $i['slug'])], industries_all()),
    'Blog' => array_map(fn($c) => [$c['name'], url('blog/category/' . $c['slug'])], array_filter(post_categories(true), fn($c) => (int) $c['post_count'] > 0)),
    'Legal' => array_map(fn($l) => [$l['title'], url($l['slug'])], legal_pages()),
];
?>
<section class="blueprint bg-navy text-white">
  <div class="mx-auto max-w-site px-4 pb-14 pt-12 sm:px-6 lg:px-8">
    <?= breadcrumbs_html($meta['crumbs']) ?>
    <h1 class="hero-in mt-10 font-display text-5xl font-bold uppercase leading-none tracking-tight sm:text-6xl"><?= e($page['hero_title'] ?: $page['title']) ?></h1>
  </div>
</section>
<section class="section">
  <div class="mx-auto grid max-w-site gap-12 px-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-5 lg:px-8">
    <?php foreach ($groups as $title => $links): if (!$links) continue; ?>
    <div data-reveal>
      <h2 class="footer-heading !text-navy"><?= e($title) ?></h2>
      <ul class="mt-5 space-y-3"><?php foreach ($links as [$l, $h]): ?><li><a href="<?= e($h) ?>" class="sitemap-link"><?= icon('chevron-right', 'h-4 w-4 text-safety') ?><?= e($l) ?></a></li><?php endforeach ?></ul>
    </div>
    <?php endforeach ?>
  </div>
</section>
