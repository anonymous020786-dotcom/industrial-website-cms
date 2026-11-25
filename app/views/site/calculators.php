<?php /** @var array $meta @var string $active @var array $values @var array $results */
$products = array_column(products_all(), null, 'slug');
?>
<?= page_hero(['eyebrow' => 'Engineering tools', 'title' => 'Hydraulic calculators', 'lead' => 'Quick sizing for cylinders, flow and pump power — free tools from our engineers. Results are estimates; we confirm every design before manufacture.', 'crumbs' => $meta['crumbs']]) ?>

<section class="section bg-mist !pt-12">
  <div class="mx-auto grid max-w-site gap-6 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
    <?php foreach (calculators() as $type => $c):
      $vals = $active === $type ? $values : array_map(fn($i) => $i[2], $c['inputs']);
      $res = $active === $type ? $results : calc_run($type, $vals);
      $prod = $products[$c['product']] ?? null; ?>
    <form method="get" action="<?= e(url('calculators')) ?>#<?= $type ?>" class="calc-card bg-white p-7" id="<?= $type ?>" data-calc="<?= $type ?>">
      <input type="hidden" name="c" value="<?= $type ?>">
      <div class="flex items-start gap-4">
        <span class="grid h-12 w-12 shrink-0 place-items-center bg-navy text-safety"><?= icon($c['icon'], 'h-6 w-6') ?></span>
        <div><h2 class="font-display text-2xl font-bold uppercase tracking-wide text-navy"><?= e($c['title']) ?></h2><p class="text-sm text-charcoal/75"><?= e($c['intro']) ?></p></div>
      </div>
      <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <?php foreach ($c['inputs'] as $k => [$label, $unit]): ?>
        <div class="field"><label for="<?= $type . '-' . $k ?>"><?= e($label) ?> <span class="font-normal text-charcoal/60">(<?= e($unit) ?>)</span></label>
          <input id="<?= $type . '-' . $k ?>" name="<?= $k ?>" type="number" step="any" min="0" inputmode="decimal" value="<?= e((string) $vals[$k]) ?>" data-in="<?= $k ?>"></div>
        <?php endforeach ?>
      </div>
      <dl class="calc-results mt-6 divide-y divide-black/5 border-t-2 border-safety" data-out aria-live="polite">
        <?php if ($res): foreach ($res as [$l, $v]): ?>
        <div class="flex items-baseline justify-between gap-4 py-2.5"><dt class="text-sm text-charcoal/75"><?= e($l) ?></dt><dd class="text-right font-display text-xl font-bold text-navy"><?= e($v) ?></dd></div>
        <?php endforeach; else: ?><p class="py-3 text-sm text-red-700">Check the values (the rod must be smaller than the bore).</p><?php endif ?>
      </dl>
      <div class="mt-6 flex flex-wrap gap-3">
        <noscript><button class="btn btn-outline">Calculate</button></noscript>
        <a class="btn btn-primary" data-quote-link href="<?= e(url('quote') . '?' . http_build_query(['product' => $prod['name'] ?? '', 'specs' => calc_summary($type, $vals, $res)])) ?>">Request a quote with these numbers <?= icon('arrow-right', 'btn-arrow h-4 w-4') ?></a>
        <?php if ($prod): ?><a class="btn btn-outline" href="<?= e(product_url($prod)) ?>"><?= e($prod['name']) ?></a><?php endif ?>
      </div>
    </form>
    <?php endforeach ?>
  </div>
  <p class="mx-auto mt-8 max-w-site px-4 text-sm text-charcoal/70 sm:px-6 lg:px-8">Formulas: force = pressure × area; speed = flow ÷ area; power = flow × pressure ÷ 600 ÷ efficiency. Real systems lose pressure in valves and hoses and need margins for dynamic loads — talk to us before ordering.</p>
</section>
