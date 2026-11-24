<?php /** @var array $page @var array $data @var array $meta */
[$content, $toc] = add_heading_ids(fix_internal_links(render_shortcodes((string) $page['content'])));
// Number the headings to match the table of contents
$n = 0;
$content = preg_replace_callback('~<h2([^>]*)>~', function ($m) use (&$n) { $n++; return '<h2' . $m[1] . '><span>' . pad2($n) . '</span>'; }, $content);
$updated = format_date((string) setting('legal_updated', date('Y-m-d', strtotime($page['updated_at']))), 'j F Y');
?>
<section class="blueprint bg-navy text-white">
  <div class="mx-auto max-w-site px-4 pb-14 pt-12 sm:px-6 lg:px-8 lg:pb-16">
    <?= breadcrumbs_html($meta['crumbs']) ?>
    <h1 class="hero-in mt-10 font-display text-5xl font-bold uppercase leading-none tracking-tight sm:text-6xl"><?= e($page['hero_title'] ?: $page['title']) ?></h1>
    <p class="hero-in mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-white/70" style="--d:100ms">
      <span class="flex items-center gap-2"><?= icon('calendar', 'h-4 w-4 text-safety') ?>Last updated: <time><?= e($updated) ?></time></span>
      <button type="button" class="flex items-center gap-2 hover:text-white" data-print><?= icon('printer', 'h-4 w-4 text-safety') ?>Print this page</button>
    </p>
  </div>
</section>
<div class="reading-progress" aria-hidden="true"><span></span></div>

<section class="section !pt-12">
  <div class="mx-auto grid max-w-site gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
    <aside class="lg:col-span-3">
      <?php if ($toc): ?>
      <nav class="toc" aria-label="On this page">
        <p class="toc-title">On this page</p>
        <ol><?php foreach ($toc as $i => $h): ?><li><a href="#<?= e($h['id']) ?>" data-toc-link><span><?= pad2($i + 1) ?></span><?= e($h['text']) ?></a></li><?php endforeach ?></ol>
      </nav>
      <?php endif ?>
    </aside>
    <article class="legal lg:col-span-9">
      <?php if (setting('legal_template_notice', true)): ?>
      <div class="template-note" role="note"><?= icon('triangle-alert', 'h-5 w-5 shrink-0') ?>
        <p><strong>Template notice:</strong> this page is a general template. It must be reviewed and adapted by a qualified legal professional for <?= e(company('name')) ?> and its jurisdiction before the website goes live. (Turn this notice off in Admin → Settings → Legal &amp; Cookies.)</p>
      </div>
      <?php endif ?>
      <?= $content ?>
    </article>
  </div>
</section>
