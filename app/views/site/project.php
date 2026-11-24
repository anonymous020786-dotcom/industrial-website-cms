<?php /** @var array $p @var array $products @var array $results @var array $more @var array $meta */
$facts = array_filter(['Client' => $p['client'], 'Industry' => $p['industry'], 'Location' => $p['location'], 'Completed' => $p['completed_at'] ? date('F Y', strtotime((string) $p['completed_at'])) : null]);
?>
<?php if ($p['status'] !== 'published'): ?><div class="bg-amber-100 px-4 py-2 text-center text-sm font-semibold text-amber-900">Draft preview — not visible to visitors</div><?php endif ?>
<?= page_hero(['eyebrow' => $p['industry'] ? 'Case study · ' . $p['industry'] : 'Case study', 'title' => $p['title'], 'lead' => (string) $p['summary'], 'image' => $p['image'], 'crumbs' => $meta['crumbs']]) ?>

<?php if ($facts || $results): ?>
<section class="border-b border-black/10 bg-white">
  <div class="mx-auto grid max-w-site gap-8 px-4 py-10 sm:px-6 lg:grid-cols-12 lg:px-8">
    <?php if ($facts): ?>
    <dl class="grid grid-cols-2 gap-6 lg:col-span-5">
      <?php foreach ($facts as $k => $v): ?><div><dt class="text-xs font-bold uppercase tracking-[0.18em] text-charcoal/60"><?= e($k) ?></dt><dd class="mt-1 font-semibold text-navy"><?= e((string) $v) ?></dd></div><?php endforeach ?>
    </dl>
    <?php endif ?>
    <?php if ($results): ?>
    <ul class="grid grid-cols-2 gap-6 <?= [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3', 4 => 'sm:grid-cols-4'][min(4, count($results))] ?> lg:col-span-7">
      <?php foreach ($results as $r): ?><li class="border-l-4 border-safety pl-4"><p class="font-display text-4xl font-bold text-navy"><?= e($r['value']) ?></p><p class="mt-1 text-sm text-charcoal/75"><?= e($r['label'] ?? '') ?></p></li><?php endforeach ?>
    </ul>
    <?php endif ?>
  </div>
</section>
<?php endif ?>

<section class="section">
  <div class="mx-auto grid max-w-site gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:gap-16 lg:px-8">
    <article class="space-y-12 lg:col-span-8">
      <?php if (trim(strip_tags((string) $p['challenge'])) !== ''): ?>
      <div data-reveal><p class="eyebrow">The challenge</p><div class="prose-lite prose-lg mt-5"><?= fix_internal_links((string) $p['challenge']) ?></div></div>
      <?php endif ?>
      <?php if (trim(strip_tags((string) $p['solution'])) !== ''): ?>
      <div data-reveal><p class="eyebrow">Our solution</p><div class="prose-lite prose-lg mt-5"><?= fix_internal_links((string) $p['solution']) ?></div></div>
      <?php endif ?>
      <?php if ($p['testimonial']): ?>
      <figure class="border-l-4 border-steel bg-mist p-8" data-reveal>
        <?= icon('quote', 'h-8 w-8 text-steel') ?>
        <blockquote class="mt-4 text-xl leading-relaxed text-navy">“<?= e($p['testimonial']) ?>”</blockquote>
        <?php if ($p['testimonial_by']): ?><figcaption class="mt-4 text-sm font-semibold text-charcoal/75">— <?= e($p['testimonial_by']) ?></figcaption><?php endif ?>
      </figure>
      <?php endif ?>
    </article>
    <aside class="space-y-6 lg:col-span-4">
      <div class="blueprint bg-navy p-8 text-white">
        <h2 class="font-display text-2xl font-semibold uppercase tracking-wide">Facing something similar?</h2>
        <p class="mt-3 text-sm leading-relaxed text-white/75">Share your drawings, part numbers or duty conditions and an engineer will come back with a solution.</p>
        <a href="<?= e(url('quote')) ?>" class="btn btn-primary mt-6 w-full justify-center">Request a Quote <?= icon('arrow-right', 'btn-arrow h-4 w-4') ?></a>
      </div>
      <?php if ($products): ?>
      <div class="border border-black/10 p-6">
        <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-charcoal/60">Products supplied</h2>
        <ul class="mt-4 space-y-3"><?php foreach ($products as $x): ?><li><a class="flex items-center gap-3 font-semibold text-navy hover:text-steel" href="<?= e(product_url($x)) ?>"><?= icon($x['icon'] ?: 'package', 'h-5 w-5 text-steel') ?><?= e($x['name']) ?></a></li><?php endforeach ?></ul>
      </div>
      <?php endif ?>
    </aside>
  </div>
</section>

<?php if ($more): ?>
<section class="section bg-mist">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?= section_head('More projects', 'Other Case Studies') ?>
    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($more as $i => $x) echo project_card($x, $i) ?></div>
  </div>
</section>
<?php endif ?>
