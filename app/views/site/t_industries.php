<?php /** @var array $page @var array $data @var array $meta */
$industries = industries_all();
$byId = array_column(products_all(), null, 'id');
?>
<?= page_hero(['eyebrow' => $page['hero_eyebrow'], 'title' => $page['hero_title'] ?: $page['title'], 'lead' => $page['hero_lead'], 'image' => $page['hero_image'], 'crumbs' => $meta['crumbs']]) ?>

<div class="sticky-tabs" data-tabs-nav>
  <nav class="mx-auto flex max-w-site gap-8 overflow-x-auto px-4 sm:px-6 lg:px-8" aria-label="Industries">
    <?php foreach ($industries as $i): ?><a href="#<?= e($i['slug']) ?>" class="tab-link"><?= e($i['name']) ?></a><?php endforeach ?>
  </nav>
</div>

<?php foreach ($industries as $n => $ind): ?>
<section id="<?= e($ind['slug']) ?>" class="section <?= $n % 2 ? 'bg-mist' : '' ?>">
  <div class="mx-auto grid max-w-site items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
    <div class="img-frame aspect-[4/3] <?= $n % 2 ? 'lg:order-2' : '' ?>" data-reveal data-fallback-label="<?= e($ind['name']) ?>">
      <?= img_tag($ind['image'], (string) $ind['image_alt'], ['widths' => [640, 1100, 1500], 'sizes' => '(min-width:1024px) 50vw, 100vw']) ?>
    </div>
    <div data-reveal data-reveal-delay="1">
      <p class="eyebrow"><?= icon($ind['icon'], 'h-4 w-4') ?> Industry <?= pad2($n + 1) ?></p>
      <h2 class="section-title mt-4"><?= e($ind['name']) ?></h2>
      <p class="mt-5 text-lg leading-relaxed text-charcoal/75"><?= e($ind['body'] ?: $ind['summary']) ?></p>
      <?php $rel = array_filter(array_map(fn($id) => $byId[$id] ?? null, $ind['product_ids'])); if ($rel): ?>
      <p class="mt-8 text-sm font-semibold uppercase tracking-widest text-charcoal/50">Relevant products</p>
      <ul class="mt-4 divide-y divide-black/10 border-y border-black/10">
        <?php foreach ($rel as $p): ?><li><a href="<?= e(product_url($p)) ?>" class="related-row"><?= icon($p['icon'], 'h-5 w-5 text-steel') ?><span class="flex-1 font-semibold text-navy"><?= e($p['name']) ?></span><?= icon('arrow-right', 'h-4 w-4 text-charcoal/40') ?></a></li><?php endforeach ?>
      </ul>
      <?php endif ?>
      <?php $nProj = count(projects_published((int) $ind['id'])); if ($nProj): ?>
      <a href="<?= e(url('projects?industry=' . $ind['slug'])) ?>" class="link-arrow mt-6 text-steel hover:text-navy"><?= icon('briefcase-business', 'h-4 w-4') ?> <?= $nProj ?> project<?= $nProj === 1 ? '' : 's' ?> in <?= e(strtolower($ind['name'])) ?> <?= icon('arrow-right', 'h-4 w-4') ?></a>
      <?php endif ?>
    </div>
  </div>
</section>
<?php endforeach ?>

<?= cta_band($data['cta_title'] ?? null, $data['cta_text'] ?? null) ?>
