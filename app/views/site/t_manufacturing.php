<?php /** @var array $page @var array $data @var array $meta */ ?>
<?= page_hero(['eyebrow' => $page['hero_eyebrow'], 'title' => $page['hero_title'] ?: $page['title'], 'lead' => $page['hero_lead'], 'image' => $page['hero_image'], 'crumbs' => $meta['crumbs']]) ?>

<?php if (!empty($data['capabilities'])): ?>
<section class="section">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?= section_head('Capabilities', $data['capabilities_title']) ?>
    <div class="mt-12 grid gap-px bg-black/10 md:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($data['capabilities'] as $i => $c): ?>
      <article class="capability-card" data-reveal<?= reveal_delay($i) ?>>
        <div class="flex items-start justify-between">
          <span class="grid h-14 w-14 place-items-center bg-mist text-steel"><?= icon($c['icon'] ?? 'cog', 'h-7 w-7') ?></span>
          <span class="font-display text-3xl font-bold text-safety"><?= pad2($i + 1) ?></span>
        </div>
        <h3 class="mt-6 font-display text-2xl font-semibold uppercase tracking-wide text-navy"><?= e($c['name'] ?? '') ?></h3>
        <p class="mt-3 leading-relaxed text-charcoal/70"><?= e($c['text'] ?? '') ?></p>
        <?php if (!empty($c['points'])): ?><ul class="mt-5 space-y-2 text-sm"><?php foreach ($c['points'] as $pt): ?><li class="flex items-center gap-2 text-charcoal/75"><?= icon('check', 'h-4 w-4 text-safety') ?><?= e($pt) ?></li><?php endforeach ?></ul><?php endif ?>
      </article>
      <?php endforeach ?>
    </div>
  </div>
</section>
<?php endif ?>

<section class="section blueprint bg-navy text-white">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?= section_head('Production Process', $data['process_title'], '', true) ?>
    <div class="mt-12"><?= process_steps(true) ?></div>
  </div>
</section>

<section class="section">
  <div class="mx-auto grid max-w-site items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
    <div class="img-frame aspect-[4/3]" data-reveal><?= img_tag($data['facility_image'], 'Production floor', ['widths' => [640, 1100, 1500], 'sizes' => '(min-width:1024px) 50vw, 100vw']) ?></div>
    <div data-reveal data-reveal-delay="1">
      <p class="eyebrow">Facility</p>
      <h2 class="section-title mt-4"><?= e($data['facility_title']) ?></h2>
      <p class="mt-5 leading-relaxed text-charcoal/70"><?= e($data['facility_text']) ?></p>
      <?php if (!empty($data['equipment'])): ?>
      <table class="spec-table mt-6 w-full"><caption class="sr-only">Plant equipment</caption>
        <tbody><?php foreach ($data['equipment'] as $eq): ?><tr><th scope="row"><?= e($eq['label'] ?? '') ?></th><td><?= e($eq['value'] ?? '') ?></td></tr><?php endforeach ?></tbody>
      </table>
      <?php endif ?>
    </div>
  </div>
</section>

<?= stats_band() ?>
<?= cta_band($data['cta_title'] ?? null, $data['cta_text'] ?? null) ?>
