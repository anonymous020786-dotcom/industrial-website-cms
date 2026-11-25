<?php /** @var array $meta @var string $q @var array $results @var array $parts @var ?string $suggestion */
$words = search_words($q);
// Escape first, then highlight the search words
$hl = function (string $s) use ($words): string {
    $h = e($s);
    if (!$words) return $h;
    $re = '~(' . implode('|', array_map(fn($w) => preg_quote(e($w), '~'), $words)) . ')~iu';
    return preg_replace($re, '<mark>$1</mark>', $h) ?? $h;
};
$icons = ['Product' => 'package', 'Page' => 'file-text', 'Article' => 'newspaper', 'Project' => 'briefcase', 'Job opening' => 'user-round-check'];
?>
<?= page_hero(['eyebrow' => 'Search', 'title' => $q !== '' ? 'Results for “' . $q . '”' : 'Search the site', 'lead' => 'Products, part numbers, articles, projects, downloads and jobs.', 'crumbs' => $meta['crumbs']]) ?>

<section class="section bg-mist !pt-12">
  <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
    <form method="get" action="<?= e(url('search')) ?>" role="search" class="flex flex-col gap-3 bg-white p-5 sm:flex-row">
      <label class="sr-only" for="site-q">Search</label>
      <input id="site-q" class="flex-1 border border-black/15 px-4 py-3 text-lg" type="search" name="q" value="<?= e($q) ?>" placeholder="e.g. hydraulic cylinder, wire rope drum, HC-63" autocomplete="off" <?= $q === '' ? 'autofocus' : '' ?> maxlength="100">
      <button class="btn btn-primary btn-lg justify-center"><?= icon('search', 'h-5 w-5') ?> Search</button>
    </form>

    <?php if ($suggestion): ?>
    <p class="mt-6 text-lg" data-search-suggestion>Did you mean <a class="font-semibold text-steel underline underline-offset-4" href="<?= e(url('search') . '?q=' . rawurlencode($suggestion)) ?>"><?= e($suggestion) ?></a>?</p>
    <?php endif ?>

    <?php if ($parts): ?>
    <div class="mt-8 border-l-4 border-safety bg-white p-6" data-search-parts>
      <h2 class="font-display text-xl font-bold uppercase tracking-wide text-navy">Part number matches</h2>
      <ul class="mt-3 space-y-2">
        <?php foreach ($parts as $m): ?>
        <li><a class="font-semibold text-navy hover:text-steel" href="<?= e(url('parts') . '?q=' . rawurlencode($m['ref_number'])) ?>"><span class="font-mono"><?= e($m['ref_number']) ?></span><?= $m['brand'] ? ' (' . e($m['brand']) . ')' : '' ?> → <?= e($m['product']['name'] ?? 'made to order') ?><?= $m['our_part'] ? ' · ' . e($m['our_part']) : '' ?></a></li>
        <?php endforeach ?>
      </ul>
    </div>
    <?php endif ?>

    <?php if ($q !== ''): ?>
      <?php if ($results): ?>
      <p class="mt-8 text-sm text-charcoal/60"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?></p>
      <ol class="mt-3 space-y-3">
        <?php foreach ($results as $r): ?>
        <li class="search-hit bg-white p-6" data-search-hit="<?= e($r['type']) ?>">
          <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-steel"><?= icon($icons[$r['type']] ?? 'file-down', 'h-4 w-4') ?><?= e($r['type']) ?></p>
          <a class="mt-1 block font-display text-xl font-bold text-navy hover:text-steel" href="<?= e($r['url']) ?>"><?= $hl($r['title']) ?></a>
          <?php if ($r['snippet'] !== ''): ?><p class="mt-2 text-sm leading-relaxed text-charcoal/75"><?= $hl($r['snippet']) ?></p><?php endif ?>
        </li>
        <?php endforeach ?>
      </ol>
      <?php elseif (!$parts): ?>
      <div class="mt-8 bg-white p-8" data-search-empty>
        <h2 class="font-display text-2xl font-bold uppercase tracking-wide text-navy">Nothing found</h2>
        <p class="mt-2 text-charcoal/75">Try fewer or different words. Looking for a specific part? Our engineers can identify it from a number, photo or drawing.</p>
        <div class="mt-6 flex flex-wrap gap-3">
          <a class="btn btn-primary" href="<?= e(url('parts') . '?q=' . rawurlencode($q)) ?>#request">Ask us to find it</a>
          <a class="btn btn-outline" href="<?= e(url('products')) ?>">Browse products</a>
        </div>
      </div>
      <?php endif ?>
    <?php endif ?>
  </div>
</section>
