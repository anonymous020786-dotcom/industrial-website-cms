<?php /** @var array $page @var array $data @var array $meta */
$products = products_all();
$cats = product_categories();
?>
<?= page_hero(['eyebrow' => $page['hero_eyebrow'], 'title' => $page['hero_title'] ?: $page['title'], 'lead' => $page['hero_lead'], 'image' => $page['hero_image'], 'crumbs' => $meta['crumbs']]) ?>

<section class="section bg-mist !pt-12">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <div class="catalog-toolbar" data-reveal>
      <div class="filter-bar" role="group" aria-label="Filter products by category">
        <button type="button" class="filter-btn" data-filter="all" aria-pressed="true">All Products <span class="filter-count"><?= count($products) ?></span></button>
        <?php foreach ($cats as $c): $n = count(array_filter($products, fn($p) => (int) $p['category_id'] === (int) $c['id'])); if (!$n) continue; ?>
        <button type="button" class="filter-btn" data-filter="<?= (int) $c['id'] ?>" aria-pressed="false"><?= e($c['name']) ?> <span class="filter-count"><?= $n ?></span></button>
        <?php endforeach ?>
      </div>
      <label class="search-box"><span class="sr-only">Search products</span><?= icon('search', 'h-4 w-4 text-charcoal/50') ?><input type="search" id="product-search" placeholder="Search products…" autocomplete="off"></label>
    </div>
    <p class="mt-6 text-sm text-charcoal/60" id="product-count" aria-live="polite">Showing <?= count($products) ?> products</p>
    <div id="product-grid" class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($products as $i => $p) echo product_card($p, $i) ?></div>
    <div id="product-empty" class="empty-state" hidden>
      <?= icon('search-x', 'h-8 w-8 text-charcoal/40') ?>
      <p class="mt-4 font-semibold text-navy">No products match your search.</p>
      <p class="mt-1 text-sm text-charcoal/60">Can’t find what you need? We manufacture custom parts to drawing or sample.</p>
      <a href="<?= e(url('quote')) ?>" class="btn btn-primary mt-6">Request a Custom Part</a>
    </div>
  </div>
</section>

<?php if (!empty($data['intro_items'])): ?>
<section class="section">
  <div class="mx-auto grid max-w-site gap-10 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
    <?php foreach ($data['intro_items'] as $n => $it): ?>
    <div class="flex gap-5" data-reveal<?= reveal_delay($n) ?>>
      <span class="grid h-12 w-12 shrink-0 place-items-center bg-mist text-steel"><?= icon($it['icon'] ?? 'check', 'h-6 w-6') ?></span>
      <div><h2 class="font-display text-2xl font-semibold uppercase tracking-wide text-navy"><?= e($it['title'] ?? '') ?></h2><p class="mt-2 leading-relaxed text-charcoal/70"><?= e($it['text'] ?? '') ?></p></div>
    </div>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>

<?= cta_band($data['cta_title'] ?? null, $data['cta_text'] ?? null) ?>
