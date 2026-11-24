<?php /** @var array $page @var array $data @var array $meta */ ?>
<?= page_hero(['eyebrow' => $page['hero_eyebrow'], 'title' => $page['hero_title'] ?: $page['title'], 'lead' => $page['hero_lead'], 'image' => $page['hero_image'], 'crumbs' => $meta['crumbs']]) ?>

<section class="section">
  <div class="mx-auto grid max-w-site gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:gap-16 lg:px-8">
    <div class="lg:col-span-5" data-reveal>
      <p class="eyebrow">Who We Are</p>
      <h2 class="section-title mt-4"><?= e($data['story_title']) ?></h2>
    </div>
    <div class="prose-lite prose-lg lg:col-span-7" data-reveal data-reveal-delay="1"><?= fix_internal_links(render_shortcodes((string) $page['content'])) ?></div>
  </div>
</section>

<section class="section bg-mist">
  <div class="mx-auto grid max-w-site gap-6 px-4 sm:px-6 md:grid-cols-2 lg:px-8">
    <div class="mv-card" data-reveal>
      <?= icon('target', 'h-8 w-8 text-safety') ?>
      <h2 class="mt-6 font-display text-3xl font-bold uppercase tracking-wide text-navy">Our Mission</h2>
      <p class="mt-4 leading-relaxed text-charcoal/75"><?= e($data['mission']) ?></p>
    </div>
    <div class="mv-card mv-card-dark" data-reveal data-reveal-delay="1">
      <?= icon('telescope', 'h-8 w-8 text-safety') ?>
      <h2 class="mt-6 font-display text-3xl font-bold uppercase tracking-wide text-white">Our Vision</h2>
      <p class="mt-4 leading-relaxed text-white/75"><?= e($data['vision']) ?></p>
    </div>
  </div>
</section>

<?php if (!empty($data['values'])): ?>
<section class="section">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?= section_head('Our Values', 'What We Stand For') ?>
    <div class="mt-12 grid gap-px bg-black/10 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach ($data['values'] as $n => $v): ?>
      <div class="bg-white p-8" data-reveal<?= reveal_delay($n, 4) ?>>
        <?= icon($v['icon'] ?? 'check', 'h-7 w-7 text-steel') ?>
        <h3 class="mt-5 font-display text-2xl font-semibold uppercase tracking-wide text-navy"><?= e($v['title'] ?? '') ?></h3>
        <p class="mt-2 text-sm leading-relaxed text-charcoal/70"><?= e($v['text'] ?? '') ?></p>
      </div>
      <?php endforeach ?>
    </div>
  </div>
</section>
<?php endif ?>

<?= stats_band() ?>

<section class="section">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?= section_head('Our Approach', $data['approach_title'], 'Every order follows the same documented path, whether it is a single urgent spare or a complete hydraulic system.') ?>
    <div class="mt-12"><?= process_steps() ?></div>
  </div>
</section>

<?= cta_band($data['cta_title'] ?? null, $data['cta_text'] ?? null) ?>
