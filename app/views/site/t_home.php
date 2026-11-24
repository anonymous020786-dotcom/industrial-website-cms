<?php
/** @var array $page @var array $data */
$products = products_all();
$industries = industries_all();
$caps = page_data(page_by_slug('manufacturing', false) ?? ['template' => 'manufacturing'])['capabilities'] ?? [];
$latest = posts_query(['limit' => 3])['rows'];
?>
<section class="relative isolate flex min-h-[calc(100svh-108px)] items-end overflow-hidden bg-navy text-white lg:min-h-[740px]">
  <?= img_tag($page['hero_image'], $data['hero_image_alt'] ?? '', ['class' => 'absolute inset-0 -z-20 h-full w-full object-cover', 'widths' => [960, 1600, 2400], 'lazy' => false]) ?>
  <div class="hero-overlay absolute inset-0 -z-10" aria-hidden="true"></div>
  <div class="mx-auto w-full max-w-site px-4 pb-14 pt-24 sm:px-6 lg:px-8 lg:pb-20">
    <div class="max-w-3xl">
      <?php if ($page['hero_eyebrow']): ?><p class="eyebrow eyebrow-light hero-in"><?= e($page['hero_eyebrow']) ?></p><?php endif ?>
      <?php [$h1a, $h1b] = array_pad(preg_split('~(?<=\.)\s+~', (string) $page['hero_title'], 2), 2, ''); ?>
      <h1 class="hero-in mt-5 font-display text-[44px] font-bold uppercase leading-[0.95] tracking-tight sm:text-6xl lg:text-[84px]" style="--d:80ms"><?= e($h1a) ?><?php if ($h1b): ?><br><span class="text-safety"><?= e($h1b) ?></span><?php endif ?></h1>
      <?php if ($page['hero_lead']): ?><p class="hero-in mt-6 max-w-xl text-lg leading-relaxed text-white/80 sm:text-xl" style="--d:160ms"><?= e($page['hero_lead']) ?></p><?php endif ?>
      <div class="hero-in mt-9 flex flex-col gap-3 sm:flex-row" style="--d:240ms">
        <a href="<?= e(url('products')) ?>" class="btn btn-primary btn-lg">Explore Our Products <?= icon('arrow-right', 'btn-arrow h-5 w-5') ?></a>
        <a href="<?= e(url('quote')) ?>" class="btn btn-ghost-light btn-lg">Request a Quote</a>
      </div>
    </div>
    <?php if (!empty($data['hero_trust'])): $trustIcons = ['crosshair', 'shield-check', 'drafting-compass', 'badge-check']; ?>
    <ul class="hero-in mt-14 grid border-t border-white/15 pt-6 sm:grid-cols-3 sm:gap-8" style="--d:320ms">
      <?php foreach ($data['hero_trust'] as $i => $t): ?><li class="flex items-center gap-3 py-2 text-sm font-semibold uppercase tracking-wider text-white/90"><?= icon($trustIcons[$i % 4], 'h-5 w-5 text-safety') ?> <?= e($t) ?></li><?php endforeach ?>
    </ul>
    <?php endif ?>
  </div>
</section>

<section class="section">
  <div class="mx-auto grid max-w-site items-center gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:gap-16 lg:px-8">
    <div class="relative lg:col-span-6" data-reveal>
      <div class="img-frame aspect-[4/5] sm:aspect-[4/3] lg:aspect-[4/5]"><?= img_tag($data['about_image'], 'Our engineering workshop', ['widths' => [640, 1100, 1500], 'sizes' => '(min-width:1024px) 50vw, 100vw']) ?></div>
      <div class="absolute -bottom-px left-0 h-1.5 w-1/3 bg-safety" aria-hidden="true"></div>
    </div>
    <div class="lg:col-span-6" data-reveal data-reveal-delay="1">
      <p class="eyebrow">About Us</p>
      <h2 class="section-title mt-4"><?= e($data['about_title']) ?></h2>
      <div class="prose-lite mt-6"><?= render_shortcodes((string) $data['about_text']) ?></div>
      <dl class="mt-8 grid gap-x-8 gap-y-6 sm:grid-cols-2">
        <?php foreach ($data['about_features'] ?? [] as $f): ?>
        <div class="feature-line"><dt class="flex items-center gap-2 font-semibold text-navy"><?= icon($f['icon'] ?? 'check', 'h-5 w-5 text-steel') ?> <?= e($f['title'] ?? '') ?></dt><dd class="mt-1.5 text-sm leading-relaxed text-charcoal/70"><?= e($f['text'] ?? '') ?></dd></div>
        <?php endforeach ?>
      </dl>
      <a href="<?= e(url('about')) ?>" class="btn btn-outline mt-10">Read More About Us <?= icon('arrow-right', 'btn-arrow h-4 w-4') ?></a>
    </div>
  </div>
</section>

<section class="section bg-mist">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
      <?= section_head('Products & Solutions', $data['products_title'], $data['products_lead']) ?>
      <a href="<?= e(url('products')) ?>" class="link-arrow shrink-0 font-semibold text-steel hover:text-navy" data-reveal>View full catalogue <?= icon('arrow-right', 'h-4 w-4') ?></a>
    </div>
    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($products as $i => $p) echo product_card($p, $i) ?></div>
  </div>
</section>

<section class="section blueprint bg-navy text-white">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <div class="grid gap-10 lg:grid-cols-12">
      <div class="lg:col-span-4" data-reveal>
        <p class="eyebrow eyebrow-light">Why Choose Us</p>
        <h2 class="section-title mt-4 text-white"><?= e($data['why_title']) ?></h2>
        <p class="mt-5 leading-relaxed text-white/70"><?= e($data['why_text']) ?></p>
        <a href="<?= e(url('quality')) ?>" class="link-arrow mt-8 text-safety">Our quality approach <?= icon('arrow-right', 'h-4 w-4') ?></a>
      </div>
      <ol class="why-grid grid sm:grid-cols-2 lg:col-span-8">
        <?php foreach ($data['why_items'] ?? [] as $n => $w): ?>
        <li class="why-item" data-reveal<?= $n % 2 ? ' data-reveal-delay="1"' : '' ?>>
          <span class="why-num"><?= pad2($n + 1) ?></span>
          <?= icon($w['icon'] ?? 'check', 'h-7 w-7 text-safety') ?>
          <h3 class="mt-5 font-display text-2xl font-semibold uppercase tracking-wide"><?= e($w['title'] ?? '') ?></h3>
          <p class="mt-2 text-sm leading-relaxed text-white/65"><?= e($w['text'] ?? '') ?></p>
        </li>
        <?php endforeach ?>
      </ol>
    </div>
  </div>
</section>

<section class="section">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?= section_head('How We Work', $data['process_title'], 'A clear, documented process so you always know where your order stands.') ?>
    <div class="mt-12"><?= process_steps() ?></div>
  </div>
</section>

<section class="section bg-mist">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
      <?= section_head('Industries We Serve', $data['industries_title']) ?>
      <a href="<?= e(url('industries')) ?>" class="link-arrow shrink-0 font-semibold text-steel hover:text-navy" data-reveal>All industries <?= icon('arrow-right', 'h-4 w-4') ?></a>
    </div>
    <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($industries as $i => $ind) echo industry_tile($ind, $i) ?></div>
  </div>
</section>

<section class="section">
  <div class="mx-auto grid max-w-site items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
    <div class="img-frame aspect-[4/3]" data-reveal><?= img_tag($data['mfg_image'], 'Fabrication in our machine shop', ['widths' => [640, 1100, 1500], 'sizes' => '(min-width:1024px) 50vw, 100vw']) ?></div>
    <div data-reveal data-reveal-delay="1">
      <p class="eyebrow">Manufacturing</p>
      <h2 class="section-title mt-4"><?= e($data['mfg_title']) ?></h2>
      <p class="mt-5 text-lg leading-relaxed text-charcoal/70"><?= e($data['mfg_text']) ?></p>
      <ul class="mt-8 grid gap-x-8 gap-y-4 sm:grid-cols-2">
        <?php foreach ($caps as $c): ?><li class="flex items-center gap-3 border-b border-black/10 pb-3 font-semibold text-navy"><?= icon($c['icon'] ?? 'cog', 'h-5 w-5 text-steel') ?><?= e($c['name'] ?? '') ?></li><?php endforeach ?>
      </ul>
      <a href="<?= e(url('manufacturing')) ?>" class="btn btn-outline mt-10">Explore Capabilities <?= icon('arrow-right', 'btn-arrow h-4 w-4') ?></a>
    </div>
  </div>
</section>

<?= stats_band() ?>

<?php if ($latest): ?>
<section class="section">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
      <?= section_head('Blog', $data['blog_title'] ?? 'Insights & Technical Guides') ?>
      <a href="<?= e(url('blog')) ?>" class="link-arrow shrink-0 font-semibold text-steel hover:text-navy" data-reveal>All articles <?= icon('arrow-right', 'h-4 w-4') ?></a>
    </div>
    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($latest as $i => $p) echo post_card($p, $i) ?></div>
  </div>
</section>
<?php endif ?>

<?= cta_band($data['cta_title'] ?? null, $data['cta_text'] ?? null) ?>
