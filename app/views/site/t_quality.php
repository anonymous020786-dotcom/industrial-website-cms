<?php /** @var array $page @var array $data @var array $meta */ ?>
<?= page_hero(['eyebrow' => $page['hero_eyebrow'], 'title' => $page['hero_title'] ?: $page['title'], 'lead' => $page['hero_lead'], 'image' => $page['hero_image'], 'crumbs' => $meta['crumbs']]) ?>

<section class="section">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?= section_head('Inspection Plan', $data['stages_title']) ?>
    <ol class="quality-timeline mt-14">
      <?php foreach ($data['stages'] ?? [] as $n => $s): ?>
      <li class="quality-stage" data-reveal>
        <span class="quality-dot"><?= icon($s['icon'] ?? 'check', 'h-6 w-6') ?></span>
        <div>
          <p class="font-display text-sm font-semibold tracking-[0.2em] text-safety">GATE <?= pad2($n + 1) ?></p>
          <h3 class="mt-1 font-display text-2xl font-semibold uppercase tracking-wide text-navy"><?= e($s['title'] ?? '') ?></h3>
          <p class="mt-2 max-w-2xl leading-relaxed text-charcoal/70"><?= e($s['text'] ?? '') ?></p>
        </div>
      </li>
      <?php endforeach ?>
    </ol>
  </div>
</section>

<section class="section bg-mist">
  <div class="mx-auto grid max-w-site items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
    <div data-reveal>
      <p class="eyebrow">Documentation</p>
      <h2 class="section-title mt-4"><?= e($data['docs_title']) ?></h2>
      <p class="mt-5 text-lg leading-relaxed text-charcoal/70"><?= e($data['docs_text']) ?></p>
      <ul class="mt-8 space-y-4"><?php foreach ($data['docs_items'] ?? [] as $t): ?><li class="flex gap-3 text-charcoal/80"><?= icon('file-check', 'mt-0.5 h-5 w-5 shrink-0 text-safety') ?><?= e($t) ?></li><?php endforeach ?></ul>
    </div>
    <div class="img-frame aspect-[4/3]" data-reveal data-reveal-delay="1"><?= img_tag($data['docs_image'], 'Engineers reviewing technical documentation', ['widths' => [640, 1100, 1500], 'sizes' => '(min-width:1024px) 50vw, 100vw']) ?></div>
  </div>
</section>

<?php $certs = certificates_valid(); if ($certs): ?>
<section class="section" id="certificates">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?= section_head('Certification', 'Certificates & Approvals', 'Current certificates issued to our company. Each one links to the certificate document.') ?>
    <ul class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($certs as $i => $c): ?>
      <li class="contact-card flex flex-col" data-reveal<?= reveal_delay($i) ?>>
        <span class="text-steel"><?= icon('award', 'h-8 w-8') ?></span>
        <?php if ($c['standard']): ?><p class="mt-4 font-display text-2xl font-bold uppercase tracking-wide text-navy"><?= e($c['standard']) ?></p><?php endif ?>
        <p class="<?= $c['standard'] ? 'mt-1' : 'mt-4 font-semibold text-navy' ?>"><?= e($c['name']) ?></p>
        <?php if ($c['scope']): ?><p class="mt-3 text-sm leading-relaxed text-charcoal/75"><?= e($c['scope']) ?></p><?php endif ?>
        <dl class="mt-4 flex-1 space-y-1 text-sm text-charcoal/75">
          <?php if ($c['issuer']): ?><div><dt class="inline font-semibold">Issued by:</dt> <dd class="inline"><?= e($c['issuer']) ?></dd></div><?php endif ?>
          <?php if ($c['number']): ?><div><dt class="inline font-semibold">Certificate no.:</dt> <dd class="inline"><?= e($c['number']) ?></dd></div><?php endif ?>
          <?php if ($c['expires_at']): ?><div><dt class="inline font-semibold">Valid until:</dt> <dd class="inline"><?= e(date('j F Y', strtotime($c['expires_at']))) ?></dd></div><?php endif ?>
        </dl>
        <?php if ($c['file']): ?><a href="<?= e(media_url($c['file'])) ?>" target="_blank" rel="noopener" class="link-arrow mt-5 text-steel hover:text-navy">View certificate <?= icon('arrow-up-right', 'h-4 w-4') ?></a><?php endif ?>
      </li>
      <?php endforeach ?>
    </ul>
  </div>
</section>
<?php endif ?>

<section class="section">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <div class="standards-note" data-reveal>
      <?= icon('info', 'h-6 w-6 shrink-0 text-steel') ?>
      <div>
        <h2 class="font-display text-2xl font-semibold uppercase tracking-wide text-navy"><?= e($data['standards_title']) ?></h2>
        <p class="mt-2 leading-relaxed text-charcoal/70"><?= e($data['standards_text']) ?></p>
      </div>
    </div>
  </div>
</section>

<?= cta_band($data['cta_title'] ?? null, $data['cta_text'] ?? null) ?>
