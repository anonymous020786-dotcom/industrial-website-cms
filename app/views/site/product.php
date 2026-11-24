<?php /** @var array $p @var string $cat */
$all = products_all();
$related = array_values(array_filter($all, fn($x) => $x['id'] !== $p['id']));
usort($related, fn($a, $b) => ((int) ($b['category_id'] === $p['category_id'])) <=> ((int) ($a['category_id'] === $p['category_id'])));
$related = array_slice($related, 0, 3);
$industries = array_filter(industries_all(), fn($i) => in_array((int) $p['id'], $i['product_ids'], true));
$quoteHref = url('quote') . '?product=' . rawurlencode($p['name']);
?>
<section class="bg-mist">
  <div class="mx-auto max-w-site px-4 pt-8 sm:px-6 lg:px-8"><?= breadcrumbs_html([['label' => 'Products', 'url' => url('products')], ['label' => $p['name'], 'url' => product_url($p)]], false) ?></div>
  <div class="mx-auto grid max-w-site gap-10 px-4 pb-16 pt-8 sm:px-6 lg:grid-cols-12 lg:gap-14 lg:px-8 lg:pb-24">
    <div class="lg:col-span-7">
      <div class="img-frame aspect-[4/3] hero-in" data-fallback-label="<?= e($p['name']) ?>">
        <?php if ($cat): ?><span class="product-tag"><?= e($cat) ?></span><?php endif ?>
        <?= img_tag($p['image'], (string) $p['image_alt'], ['widths' => [640, 1100, 1600], 'sizes' => '(min-width:1024px) 58vw, 100vw', 'lazy' => false]) ?>
      </div>
    </div>
    <div class="lg:col-span-5">
      <?php if ($cat): ?><p class="eyebrow hero-in"><?= e($cat) ?></p><?php endif ?>
      <h1 class="hero-in mt-4 font-display text-5xl font-bold uppercase leading-[0.95] tracking-tight text-navy sm:text-6xl" style="--d:80ms"><?= e($p['name']) ?></h1>
      <p class="hero-in mt-5 text-lg leading-relaxed text-charcoal/75" style="--d:160ms"><?= e($p['summary']) ?></p>
      <ul class="hero-in mt-7 space-y-3" style="--d:200ms">
        <?php foreach (array_slice($p['features'], 0, 4) as $f): ?><li class="flex gap-3 text-charcoal/80"><?= icon('check', 'mt-0.5 h-5 w-5 shrink-0 text-safety') ?><?= e($f) ?></li><?php endforeach ?>
      </ul>
      <div class="hero-in mt-9 flex flex-col gap-3 sm:flex-row" style="--d:240ms">
        <a href="<?= e($quoteHref) ?>" class="btn btn-primary btn-lg justify-center">Request a Quote <?= icon('arrow-right', 'btn-arrow h-5 w-5') ?></a>
        <?php if (company('phone')): ?><a href="tel:<?= e(company('phone_href')) ?>" class="btn btn-outline btn-lg justify-center" data-track="phone"><?= icon('phone', 'h-4 w-4') ?> Speak to an Engineer</a><?php endif ?>
      </div>
      <?php if ($wa = whatsapp_link('Hello, I would like a quote for ' . $p['name'] . '.')): ?>
      <a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="hero-in mt-4 inline-flex items-center gap-2 text-sm font-semibold text-[#128C7E] hover:underline" style="--d:280ms" data-track="whatsapp"><?= icon('message-circle', 'h-4 w-4') ?> Ask about this product on WhatsApp</a>
      <?php endif ?>
    </div>
  </div>
</section>

<div class="sticky-tabs" data-tabs-nav>
  <nav class="mx-auto flex max-w-site gap-8 overflow-x-auto px-4 sm:px-6 lg:px-8" aria-label="On this page">
    <a href="#overview" class="tab-link">Overview</a>
    <?php if ($p['specs']): ?><a href="#specifications" class="tab-link">Specifications</a><?php endif ?>
    <?php if ($p['applications']): ?><a href="#applications" class="tab-link">Applications</a><?php endif ?>
    <a href="#related" class="tab-link">Related Products</a>
  </nav>
</div>

<section id="overview" class="section">
  <div class="mx-auto grid max-w-site gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:gap-16 lg:px-8">
    <div class="lg:col-span-7" data-reveal>
      <p class="eyebrow">Overview</p>
      <h2 class="section-title mt-4">Product Overview</h2>
      <div class="prose-lite prose-lg mt-5"><?= fix_internal_links(render_shortcodes((string) $p['overview'])) ?></div>
    </div>
    <?php if ($p['features']): ?>
    <div class="lg:col-span-5" data-reveal data-reveal-delay="1">
      <div class="border-t-4 border-safety bg-mist p-8">
        <h3 class="font-display text-2xl font-semibold uppercase tracking-wide text-navy">Key Features</h3>
        <ul class="mt-5 space-y-3"><?php foreach ($p['features'] as $f): ?><li class="flex gap-3 text-charcoal/80"><?= icon('circle-check', 'mt-0.5 h-5 w-5 shrink-0 text-steel') ?><?= e($f) ?></li><?php endforeach ?></ul>
      </div>
    </div>
    <?php endif ?>
  </div>
</section>

<?php if ($p['specs']): ?>
<section id="specifications" class="section bg-mist">
  <div class="mx-auto grid max-w-site gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:gap-16 lg:px-8">
    <div class="lg:col-span-4" data-reveal>
      <p class="eyebrow">Specifications</p>
      <h2 class="section-title mt-4">Typical Specifications</h2>
      <p class="mt-5 leading-relaxed text-charcoal/70">Typical ranges shown. Every component can be built to your exact requirements — share your drawing or duty conditions for a confirmed specification.</p>
    </div>
    <div class="lg:col-span-8" data-reveal data-reveal-delay="1">
      <table class="spec-table w-full bg-white"><caption class="sr-only"><?= e($p['name']) ?> typical specifications</caption>
        <tbody><?php foreach ($p['specs'] as $s): ?><tr><th scope="row"><?= e($s['label'] ?? '') ?></th><td><?= e($s['value'] ?? '') ?></td></tr><?php endforeach ?></tbody>
      </table>
    </div>
  </div>
</section>
<?php endif ?>

<?php if ($p['applications']): ?>
<section id="applications" class="section">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?= section_head('Applications', 'Where It’s Used') ?>
    <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach ($p['applications'] as $i => $a): ?><li class="app-chip" data-reveal<?= reveal_delay($i, 4) ?>><?= icon('arrow-up-right', 'h-5 w-5 text-safety') ?><?= e($a) ?></li><?php endforeach ?>
    </ul>
    <?php if ($industries): ?>
    <p class="mt-10 text-sm font-semibold uppercase tracking-widest text-charcoal/50">Industries</p>
    <ul class="mt-4 flex flex-wrap gap-2"><?php foreach ($industries as $i): ?><li><a href="<?= e(url('industries#' . $i['slug'])) ?>" class="tag-link"><?= icon($i['icon'], 'h-4 w-4') ?><?= e($i['name']) ?></a></li><?php endforeach ?></ul>
    <?php endif ?>
  </div>
</section>
<?php endif ?>

<section id="related" class="section bg-mist">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?= section_head('Related', 'Related Products') ?>
    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($related as $i => $x) echo product_card($x, $i) ?></div>
  </div>
</section>

<?= cta_band('Need ' . $p['name'] . '?', 'Send drawings, part numbers or application details and our team will prepare a quotation.') ?>
