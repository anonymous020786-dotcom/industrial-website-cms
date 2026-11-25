<?php /** @var array $meta @var array $groups @var array $sheets */
$catalogue = (string) setting('catalogue_pdf', '');
$icons = ['Brochures' => 'book-open', 'Datasheets' => 'file-spreadsheet', 'Manuals' => 'book-text', 'CAD drawings' => 'drafting-compass', 'Certificates' => 'badge-check', 'Other' => 'file'];
$labels = ['Manuals' => 'Manuals & guides'];
?>
<?= page_hero(['eyebrow' => 'Resources', 'title' => 'Downloads', 'lead' => 'Brochures, datasheets, manuals, CAD drawings and certificates — everything you need to specify, install and maintain our products.', 'crumbs' => $meta['crumbs']]) ?>

<section class="section bg-mist !pt-12">
  <div class="mx-auto max-w-site space-y-12 px-4 sm:px-6 lg:px-8">
    <?php if (!$groups && !$sheets && $catalogue === ''): ?>
    <p class="bg-white p-8 text-charcoal/75">Documents are being prepared. <a class="font-semibold text-steel underline" href="<?= e(url('contact')) ?>">Ask us</a> and we will email what you need.</p>
    <?php endif ?>

    <?php if ($catalogue !== ''): ?>
    <a href="<?= e(url('catalogue')) ?>" class="group flex items-center gap-5 bg-navy p-6 text-white">
      <span class="grid h-14 w-14 shrink-0 place-items-center bg-safety text-navy"><?= icon('book-open', 'h-7 w-7') ?></span>
      <span class="flex-1"><span class="block font-display text-2xl font-bold uppercase tracking-wide"><?= e((string) setting('catalogue_title', 'Product catalogue') ?: 'Product catalogue') ?></span><span class="text-sm text-white/75">The complete range in one PDF</span></span>
      <?= icon('arrow-right', 'h-6 w-6 transition group-hover:translate-x-1') ?>
    </a>
    <?php endif ?>

    <?php foreach ($groups as $cat => $items): ?>
    <div data-download-group="<?= e($cat) ?>">
      <h2 class="flex items-center gap-3 font-display text-2xl font-bold uppercase tracking-wide text-navy"><?= icon($icons[$cat] ?? 'file', 'h-6 w-6 text-steel') ?><?= e($labels[$cat] ?? $cat) ?></h2>
      <ul class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($items as $d): ?>
        <li class="flex flex-col bg-white p-6">
          <h3 class="font-semibold text-navy"><?= e($d['title']) ?></h3>
          <?php if ($d['description']): ?><p class="mt-2 flex-1 text-sm text-charcoal/70"><?= e($d['description']) ?></p><?php else: ?><span class="flex-1"></span><?php endif ?>
          <a class="btn btn-outline mt-5 self-start" href="<?= e(download_url($d)) ?>" rel="nofollow"><?= icon('file-down', 'h-4 w-4') ?> Download</a>
          <?php if ($d['gated']): ?><p class="mt-2 text-xs text-charcoal/55">Quick form first — once per device.</p><?php endif ?>
        </li>
        <?php endforeach ?>
      </ul>
    </div>
    <?php endforeach ?>

    <?php if ($sheets): ?>
    <div>
      <h2 class="flex items-center gap-3 font-display text-2xl font-bold uppercase tracking-wide text-navy"><?= icon('file-spreadsheet', 'h-6 w-6 text-steel') ?>Product datasheets</h2>
      <ul class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($sheets as $p): ?>
        <li><a class="flex items-center justify-between gap-3 bg-white px-5 py-4 font-semibold text-navy hover:text-steel" href="<?= e(url('download/datasheet/' . $p['slug'])) ?>" rel="nofollow"><?= e($p['name']) ?><?= icon('file-down', 'h-4 w-4 shrink-0') ?></a></li>
        <?php endforeach ?>
      </ul>
    </div>
    <?php endif ?>
  </div>
</section>
<?= cta_band('Need a drawing or document that isn’t here?', 'Our engineers can send specific drawings, test certificates and documentation for your order.') ?>
