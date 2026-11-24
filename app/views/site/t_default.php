<?php /** @var array $page @var array $data @var array $meta */
[$content, $toc] = add_heading_ids(fix_internal_links(render_shortcodes((string) $page['content'])));
$showToc = !empty($data['show_toc']) && count($toc) > 1;
?>
<?= page_hero(['eyebrow' => $page['hero_eyebrow'], 'title' => $page['hero_title'] ?: $page['title'], 'lead' => $page['hero_lead'], 'image' => $page['hero_image'], 'crumbs' => $meta['crumbs']]) ?>

<section class="section">
  <div class="mx-auto grid max-w-site gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
    <?php if ($showToc): ?>
    <aside class="lg:col-span-3"><nav class="toc" aria-label="On this page"><p class="toc-title">On this page</p>
      <ol><?php foreach ($toc as $i => $h): ?><li><a href="#<?= e($h['id']) ?>" data-toc-link><span><?= pad2($i + 1) ?></span><?= e($h['text']) ?></a></li><?php endforeach ?></ol></nav></aside>
    <?php endif ?>
    <div class="article <?= $showToc ? 'lg:col-span-9' : 'lg:col-span-10 lg:col-start-2' ?>"><?= $content ?></div>
  </div>
</section>

<?php if (!empty($data['show_cta'])) echo cta_band($data['cta_title'] ?? null, $data['cta_text'] ?? null); ?>
