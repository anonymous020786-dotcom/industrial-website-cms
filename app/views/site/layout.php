<?php
/** @var array $meta  @var string $body */
$name = company('name');
$title = !empty($meta['is_home']) ? $meta['title'] : trim($meta['title'] . ' | ' . $name, ' |');
$desc = (string) ($meta['description'] ?? '');
$canonical = $meta['canonical'] ?? '';
$og = !empty($meta['og_image']) ? media_url($meta['og_image']) : media_url((string) setting('default_og_image', 'assets/images/og-default.png'));
if ($og && str_contains($og, 'images.unsplash.com')) $og = image_abs_url($og);
$noindex = !empty($meta['noindex']) || !setting('search_indexing', true);

$ld = $meta['jsonld'] ?? [];
if (!empty($meta['is_home'])) {
    $ld[] = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $name, 'legalName' => company('legal_name'), 'url' => abs_url(''),
        'logo' => abs_url('assets/icons/logo-512.png'), 'image' => abs_url('assets/images/og-default.png'), 'email' => company('email'), 'telephone' => company('phone'), 'address' => ['@type' => 'PostalAddress', 'streetAddress' => str_replace("\n", ', ', company('address'))]];
}
if (!empty($meta['crumbs'])) {
    $items = array_merge([['label' => 'Home', 'url' => url('')]], $meta['crumbs']);
    $ld[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_map(fn($b, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $b['label'], 'item' => abs_url(ltrim(substr($b['url'], strlen(base_path())), '/'))], $items, array_keys($items))];
}
$menu = header_menu();
$products = products_all();
$cats = product_categories();
$phone = company('phone'); $phoneHref = company('phone_href'); $email = company('email');
$tagline = company('tagline', 'Precision Engineering | Reliable Hydraulic & Crane Solutions');
[$tagA, $tagB] = array_pad(array_map('trim', explode('|', $tagline, 2)), 2, '');
?><!DOCTYPE html>
<html lang="<?= e(setting('site_language', 'en')) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($desc) ?>">
  <meta name="theme-color" content="#101D32">
  <?php if ($noindex): ?><meta name="robots" content="noindex, follow"><?php elseif ($canonical): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif ?>

  <meta property="og:type" content="<?= e($meta['og_type'] ?? 'website') ?>">
  <meta property="og:site_name" content="<?= e($name) ?>">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($desc) ?>">
  <?php if ($canonical): ?><meta property="og:url" content="<?= e($canonical) ?>"><?php endif ?>
  <?php if ($og): ?><meta property="og:image" content="<?= e($og) ?>"><?php endif ?>
  <meta name="twitter:card" content="summary_large_image">
  <?= view('site/_icons') ?>
  <link rel="alternate" type="application/rss+xml" title="<?= e($name) ?> Blog" href="<?= e(url('feed.xml')) ?>">
  <?php $themeDefault = theme_default(); ?>
  <meta name="color-scheme" content="<?= $themeDefault === 'light' && !setting('theme_toggle', true) ? 'light' : 'light dark' ?>">
  <?php $editorial = setting('heading_style', 'industrial') === 'editorial'; ?>
  <?php if ($editorial): ?><link rel="preload" href="<?= e(url('assets/fonts/playfair-display-latin-700-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin><?php endif ?>
  <script>(function(d,t){d.classList.add('js'<?= $editorial ? ",'headings-editorial'" : '' ?>);try{t=localStorage.getItem('theme')}catch(e){}if(<?= setting('theme_toggle', true) ? 'true' : 'false' ?>===false)t=null;t=t||'<?= $themeDefault ?>';if(t==='dark'||(t==='system'&&matchMedia('(prefers-color-scheme: dark)').matches))d.classList.add('dark')})(document.documentElement)</script>
  <link rel="preload" href="<?= e(url('assets/fonts/barlow-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="<?= e(url('assets/fonts/barlow-condensed-latin-700-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
  <?php foreach ($ld as $obj): ?><script type="application/ld+json"><?= json_encode(array_filter($obj, fn($v) => $v !== null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
  <?php endforeach ?>
  <?= integrations_head() ?>

</head>
<body class="bg-white font-sans text-charcoal antialiased<?= setting('tawk_property_id') && setting('tawk_widget_id') ? ' has-chat' : '' ?>" data-theme-default="<?= e($themeDefault) ?>" data-base="<?= e(base_path()) ?>" data-consent-required="<?= consent_required() ? '1' : '0' ?>">
<a href="#main" class="skip-link">Skip to content</a>
<?php if (($adminUser = site_admin_user()) && setting('admin_bar', true)): ?><?= view('site/_adminbar', ['user' => $adminUser, 'edit' => $meta['edit'] ?? null]) ?><?php endif ?>
<?php if (maintenance_active()): ?><div class="maint-notice" role="status"><?= icon('construction', 'h-4 w-4') ?> <span>Maintenance mode is on — visitors see the <a href="<?= e(url('?maintenance_preview=1')) ?>">maintenance page</a>.</span></div><?php endif ?>

<div class="announce bg-navy text-[13px] text-white/75">
  <div class="mx-auto flex max-w-site items-center justify-between gap-4 px-4 py-2 sm:px-6 lg:px-8">
    <p class="truncate tracking-wide"><span class="font-semibold text-white"><?= e($tagA) ?></span><?php if ($tagB): ?><span class="mx-2 text-safety" aria-hidden="true">|</span><?= e($tagB) ?><?php endif ?></p>
    <div class="hidden items-center gap-6 md:flex">
      <?php if ($phone): ?><a class="flex items-center gap-2 hover:text-white" href="tel:<?= e($phoneHref) ?>" data-track="phone"><?= icon('phone', 'h-3.5 w-3.5') ?><?= e($phone) ?></a><?php endif ?>
      <?php if ($email): ?><a class="flex items-center gap-2 hover:text-white" href="mailto:<?= e($email) ?>" data-track="email"><?= icon('mail', 'h-3.5 w-3.5') ?><?= e($email) ?></a><?php endif ?>
    </div>
  </div>
</div>

<header id="site-header" class="site-header">
  <div class="mx-auto flex h-[72px] max-w-site items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
    <?= view('site/_logo', ['dark' => false]) ?>
    <nav aria-label="Primary" class="hidden h-full xl:block">
      <ul class="flex h-full items-center gap-7">
        <?php foreach ($menu as $item): $active = menu_active($item['url'] ?? ''); ?>
          <?php if (!empty($item['mega'])): ?>
          <li class="has-mega">
            <div class="flex items-center">
              <a class="nav-link<?= $active ? ' is-active' : '' ?>" href="<?= e(menu_href($item['url'])) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
              <button type="button" class="mega-toggle" aria-expanded="false" aria-controls="mega-products" aria-label="Show <?= e($item['label']) ?> menu"><?= icon('chevron-down', 'h-4 w-4') ?></button>
            </div>
            <div class="mega-panel" id="mega-products" role="region" aria-label="Products menu">
              <div class="mx-auto grid max-w-site gap-10 px-8 py-10 lg:grid-cols-12">
                <div class="grid gap-8 lg:col-span-9 lg:grid-cols-3">
                  <?php foreach ($cats as $c): $items = array_filter($products, fn($p) => (int) $p['category_id'] === (int) $c['id']); if (!$items) continue; ?>
                  <div>
                    <p class="mega-heading"><?= e($c['name']) ?></p>
                    <ul class="mt-3 space-y-1">
                      <?php foreach ($items as $p): ?>
                      <li><a class="mega-link" href="<?= e(product_url($p)) ?>"><span class="mega-icon"><?= icon($p['icon'], 'h-5 w-5') ?></span><span><span class="block font-semibold text-navy"><?= e($p['name']) ?></span><span class="mt-0.5 block text-[13px] leading-snug text-charcoal/60"><?= e(str_limit(explode(',', (string) $p['summary'])[0], 60)) ?></span></span></a></li>
                      <?php endforeach ?>
                    </ul>
                  </div>
                  <?php endforeach ?>
                </div>
                <a href="<?= e(url('quote')) ?>" class="mega-feature lg:col-span-3">
                  <span class="text-safety"><?= icon('drafting-compass', 'h-7 w-7') ?></span>
                  <span class="mt-4 block font-display text-2xl font-semibold uppercase leading-tight text-white">Need a custom component?</span>
                  <span class="mt-2 block text-sm leading-relaxed text-white/70">Send drawings or a sample and our engineers will review it.</span>
                  <span class="link-arrow mt-5 text-sm text-safety">Request a quote <?= icon('arrow-right', 'h-4 w-4') ?></span>
                </a>
              </div>
              <div class="border-t border-black/5 bg-mist"><div class="mx-auto flex max-w-site items-center justify-between px-8 py-3 text-sm">
                <span class="text-charcoal/60">Explore the full catalogue with specifications.</span>
                <a href="<?= e(url('products')) ?>" class="link-arrow font-semibold text-steel hover:text-navy">View all products <?= icon('arrow-right', 'h-4 w-4') ?></a>
              </div></div>
            </div>
          </li>
          <?php else: ?>
          <li><a class="nav-link<?= $active ? ' is-active' : '' ?>" href="<?= e(menu_href($item['url'] ?? '')) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a></li>
          <?php endif ?>
        <?php endforeach ?>
      </ul>
    </nav>
    <div class="flex items-center gap-3">
      <a href="<?= e(url('quote')) ?>#items" class="rfq-link" data-rfq-link hidden aria-label="Quote list"><?= icon('clipboard-list', 'h-[18px] w-[18px]') ?><span class="rfq-count" data-rfq-count>0</span></a>
      <a href="<?= e(url('search')) ?>" class="header-search" aria-label="Search" title="Search"><?= icon('search', 'h-[18px] w-[18px]') ?></a>
      <?php if (setting('theme_toggle', true)): ?>
      <button id="theme-toggle" type="button" class="theme-toggle" aria-label="Switch to dark theme" title="Switch theme"><?= icon('moon', 'icon-moon h-[18px] w-[18px]') ?><?= icon('sun', 'icon-sun h-[18px] w-[18px]') ?></button>
      <?php endif ?>
      <a href="<?= e(url('quote')) ?>" class="btn btn-primary hidden sm:inline-flex"><?= e(setting('header_cta_label', 'Get a Quote')) ?> <?= icon('arrow-right', 'btn-arrow h-4 w-4') ?></a>
      <button id="menu-toggle" type="button" class="inline-flex h-11 w-11 items-center justify-center text-navy xl:hidden" aria-controls="mobile-menu" aria-expanded="false" aria-label="Open menu">
        <span class="icon-open"><?= icon('menu', 'h-6 w-6') ?></span><span class="icon-close hidden"><?= icon('x', 'h-6 w-6') ?></span>
      </button>
    </div>
  </div>

  <div id="mobile-menu" class="mobile-menu xl:hidden" hidden>
    <nav aria-label="Mobile" class="px-4 pb-8 pt-2 sm:px-6">
      <form method="get" action="<?= e(url('search')) ?>" role="search" class="mobile-search"><label class="sr-only" for="m-search">Search</label><input id="m-search" type="search" name="q" placeholder="Search products, part numbers…" autocomplete="off"><button aria-label="Search"><?= icon('search', 'h-5 w-5') ?></button></form>
      <ul class="divide-y divide-black/5">
        <?php foreach ($menu as $item): $active = menu_active($item['url'] ?? ''); ?>
        <li>
          <?php if (!empty($item['mega'])): ?>
          <div class="flex items-center justify-between">
            <a class="mobile-link<?= $active ? ' is-active' : '' ?>" href="<?= e(menu_href($item['url'])) ?>"><?= e($item['label']) ?></a>
            <button type="button" class="mobile-sub-toggle" aria-expanded="false" aria-controls="mobile-products" aria-label="Show products"><?= icon('plus', 'h-5 w-5') ?></button>
          </div>
          <ul id="mobile-products" class="mobile-sub" hidden>
            <?php foreach ($products as $p): ?><li><a href="<?= e(product_url($p)) ?>" class="flex items-center gap-3 py-2.5 text-charcoal/80 hover:text-navy"><span class="text-steel"><?= icon($p['icon'], 'h-4 w-4') ?></span><?= e($p['name']) ?></a></li><?php endforeach ?>
          </ul>
          <?php else: ?>
          <a class="mobile-link<?= $active ? ' is-active' : '' ?>" href="<?= e(menu_href($item['url'] ?? '')) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
          <?php endif ?>
        </li>
        <?php endforeach ?>
      </ul>
      <a href="<?= e(url('quote')) ?>" class="btn btn-primary mt-6 w-full justify-center"><?= e(setting('header_cta_label', 'Get a Quote')) ?> <?= icon('arrow-right', 'btn-arrow h-4 w-4') ?></a>
      <div class="mt-6 space-y-2 text-sm text-charcoal/70">
        <?php if ($phone): ?><a class="flex items-center gap-2" href="tel:<?= e($phoneHref) ?>" data-track="phone"><?= icon('phone', 'h-4 w-4 text-steel') ?><?= e($phone) ?></a><?php endif ?>
        <?php if ($email): ?><a class="flex items-center gap-2" href="mailto:<?= e($email) ?>" data-track="email"><?= icon('mail', 'h-4 w-4 text-steel') ?><?= e($email) ?></a><?php endif ?>
      </div>
    </nav>
  </div>
</header>

<main id="main" tabindex="-1">
<?= $body ?>
</main>

<?= view('site/_footer') ?>

<?php if (setting('scroll_buttons', true)): ?>
<div class="scroll-ctrl" id="scroll-ctrl">
  <button id="scroll-down" type="button" class="scroll-btn scroll-down" aria-label="Scroll to next section"><?= icon('chevron-down', 'h-5 w-5') ?></button>
  <button id="back-to-top" type="button" class="scroll-btn scroll-up" aria-label="Back to top">
    <svg class="scroll-ring" viewBox="0 0 48 48" aria-hidden="true"><circle class="ring-bg" cx="24" cy="24" r="22"/><circle class="ring-fg" cx="24" cy="24" r="22"/></svg>
    <?= icon('arrow-up', 'h-5 w-5') ?>
  </button>
</div>
<?php endif ?>
<?= whatsapp_button() ?>
<?php if (setting('mobile_bar', true)): $mbPhone = company('phone_href') ?: preg_replace('~[^\d+]~', '', company('phone')); $mbWa = whatsapp_link(); ?>
<nav class="mobile-bar" aria-label="Quick contact" data-mobile-bar>
  <?php if ($mbPhone): ?><a href="tel:<?= e($mbPhone) ?>" data-track="phone"><?= icon('phone', 'h-5 w-5') ?><span>Call</span></a><?php endif ?>
  <?php if ($mbWa): ?><a href="<?= e($mbWa) ?>" target="_blank" rel="noopener" data-track="whatsapp"><?= icon('message-circle', 'h-5 w-5') ?><span>WhatsApp</span></a><?php endif ?>
  <a href="<?= e(url('service-request')) ?>"><?= icon('wrench', 'h-5 w-5') ?><span>Service</span></a>
  <a href="<?= e(url('quote')) ?>" class="is-primary"><?= icon('file-pen-line', 'h-5 w-5') ?><span>Get a quote</span></a>
</nav>
<?php endif ?>
<div class="toast" role="status" aria-live="polite" data-toast hidden></div>

<?php if (consent_required()): ?>
<div id="cookie-banner" class="cookie-banner" role="dialog" aria-modal="false" aria-labelledby="cookie-title" aria-describedby="cookie-text" hidden>
  <div class="flex items-start gap-4">
    <span class="mt-0.5 text-safety"><?= icon('cookie', 'h-6 w-6') ?></span>
    <div>
      <p id="cookie-title" class="font-display text-lg font-semibold uppercase tracking-wide text-navy"><?= e(setting('cookie_banner_title', 'Your privacy')) ?></p>
      <p id="cookie-text" class="mt-1 text-sm leading-relaxed text-charcoal/75"><?= e(setting('cookie_banner_text', 'We use essential storage to run this website and, with your consent, cookies for analytics and live chat.')) ?> <a href="<?= e(url('cookie-policy')) ?>" class="font-semibold text-steel underline underline-offset-2">Cookie Policy</a></p>
      <div class="mt-4 flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary btn-sm" data-cookie-choice="accepted">Accept</button>
        <button type="button" class="btn btn-outline btn-sm" data-cookie-choice="essential">Essential only</button>
      </div>
    </div>
  </div>
</div>
<?php endif ?>

<script src="<?= e(asset('js/main.js')) ?>" defer></script>
<?= integrations_body_end() ?>

</body>
</html>
