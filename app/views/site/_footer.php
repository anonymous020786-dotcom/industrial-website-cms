<?php
$products = products_all();
$footerMenu = footer_menu();
$social = setting('social_links', []) ?: [];
$hours = setting('company_hours', []) ?: [];
?>
<footer class="bg-navy text-white/70">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-6 border-b border-white/10 py-10 md:flex-row md:items-center md:justify-between">
      <p class="font-display text-2xl font-semibold uppercase tracking-wide text-white sm:text-3xl"><?= e(setting('footer_cta_text', 'Have a drawing or part number?')) ?> <span class="text-safety">Get a quote.</span></p>
      <div class="flex flex-wrap gap-3">
        <a href="<?= e(url('quote')) ?>" class="btn btn-primary">Request a Quote <?= icon('arrow-right', 'btn-arrow h-4 w-4') ?></a>
        <?php if (company('phone')): ?><a href="tel:<?= e(company('phone_href')) ?>" class="btn btn-ghost-light" data-track="phone"><?= icon('phone', 'h-4 w-4') ?> Call Us</a><?php endif ?>
      </div>
    </div>

    <div class="grid gap-12 py-14 sm:grid-cols-2 lg:grid-cols-12">
      <div class="lg:col-span-4">
        <?= view('site/_logo', ['dark' => true]) ?>
        <p class="mt-6 max-w-sm text-sm leading-relaxed"><?= e(company('description')) ?></p>
        <?php if ($social): ?>
        <ul class="mt-6 flex gap-2">
          <?php foreach ($social as $s): if (empty($s['url'])) continue; ?>
          <li><a class="social-btn" href="<?= e($s['url']) ?>" aria-label="<?= e($s['label'] ?? '') ?>" target="_blank" rel="noopener"><?= icon($s['icon'] ?? 'link', 'h-4 w-4') ?></a></li>
          <?php endforeach ?>
        </ul>
        <?php endif ?>
      </div>
      <div class="lg:col-span-2">
        <h2 class="footer-heading">Company</h2>
        <ul class="mt-5 space-y-3 text-sm">
          <?php foreach ($footerMenu as $l): ?><li><a class="footer-link" href="<?= e(menu_href($l['url'] ?? '')) ?>"><?= e($l['label'] ?? '') ?></a></li><?php endforeach ?>
          <?php if (projects_published(null, 1)): ?><li><a class="footer-link" href="<?= e(url('projects')) ?>">Projects &amp; case studies</a></li><?php endif ?>
          <?php if (setting('catalogue_pdf')): ?><li><a class="footer-link" href="<?= e(url('catalogue')) ?>"><?= e((string) setting('catalogue_title', 'Product catalogue')) ?></a></li><?php endif ?>
        </ul>
      </div>
      <div class="lg:col-span-3">
        <h2 class="footer-heading">Products</h2>
        <ul class="mt-5 space-y-3 text-sm">
          <?php foreach ($products as $p): ?><li><a class="footer-link" href="<?= e(product_url($p)) ?>"><?= e($p['name']) ?></a></li><?php endforeach ?>
        </ul>
      </div>
      <div class="lg:col-span-3">
        <h2 class="footer-heading">Contact</h2>
        <ul class="mt-5 space-y-3 text-sm">
          <?php if (company('address')): ?><li class="flex gap-3"><?= icon('map-pin', 'mt-0.5 h-4 w-4 shrink-0 text-safety') ?><address class="not-italic"><?= nl2br(e(company('address'))) ?></address></li><?php endif ?>
          <?php if (company('phone')): ?><li class="flex gap-3"><?= icon('phone', 'mt-0.5 h-4 w-4 shrink-0 text-safety') ?><a href="tel:<?= e(company('phone_href')) ?>" class="hover:text-white" data-track="phone"><?= e(company('phone')) ?></a></li><?php endif ?>
          <?php if (company('email')): ?><li class="flex gap-3"><?= icon('mail', 'mt-0.5 h-4 w-4 shrink-0 text-safety') ?><a href="mailto:<?= e(company('email')) ?>" class="break-all hover:text-white" data-track="email"><?= e(company('email')) ?></a></li><?php endif ?>
          <?php if ($hours): ?><li class="flex gap-3"><?= icon('clock', 'mt-0.5 h-4 w-4 shrink-0 text-safety') ?><span><?= e(($hours[0]['day'] ?? '') . ': ' . ($hours[0]['time'] ?? '')) ?></span></li><?php endif ?>
        </ul>
      </div>
    </div>

    <div class="flex flex-col gap-4 border-t border-white/10 py-8 text-xs lg:flex-row lg:items-center lg:justify-between">
      <p>&copy; <?= date('Y') ?> <?= e(company('legal_name', company('name'))) ?>. All rights reserved. <span class="text-white/40"><?= e(company('registration')) ?></span></p>
      <ul class="flex flex-wrap gap-x-5 gap-y-2">
        <?php foreach (legal_pages() as $l): ?><li><a class="footer-link" href="<?= e(url($l['slug'])) ?>"><?= e($l['title']) ?></a></li><?php endforeach ?>
        <li><a class="footer-link" href="<?= e(url('sitemap')) ?>">Sitemap</a></li>
        <?php if (consent_required()): ?><li><button type="button" class="footer-link" data-cookie-settings>Cookie Settings</button></li><?php endif ?>
      </ul>
    </div>
    <?php if ($note = (string) setting('footer_note', '')): ?><p class="pb-8 text-[11px] text-white/35"><?= e($note) ?></p><?php endif ?>
  </div>
</footer>
