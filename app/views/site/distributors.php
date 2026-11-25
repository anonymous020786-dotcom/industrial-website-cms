<?php /** @var array $meta @var array $groups @var array $regions @var string $region @var array $all */
$maps = fn($d) => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(implode(', ', array_filter([$d['name'], str_replace("\n", ', ', (string) $d['address']), $d['city'], $d['country']])));
?>
<?= page_hero(['eyebrow' => 'Where to buy', 'title' => 'Distributors & dealers', 'lead' => 'Buy locally through our authorised partners — or work with our factory directly for engineered and custom parts.', 'crumbs' => $meta['crumbs']]) ?>

<section class="section bg-mist !pt-12">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?php if (count($regions) > 1): ?>
    <form method="get" action="<?= e(url('distributors')) ?>" class="flex flex-wrap items-end gap-3 bg-white p-5">
      <div class="field min-w-[14rem]"><label for="dist-region">Region</label>
        <select id="dist-region" name="region"><option value="">All regions (<?= count($all) ?>)</option>
          <?php foreach ($regions as $r): ?><option value="<?= e($r) ?>"<?= $r === $region ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach ?>
        </select></div>
      <button class="btn btn-outline">Show</button>
    </form>
    <?php endif ?>

    <?php if (!$groups): ?>
    <p class="mt-6 bg-white p-8 text-charcoal/75">We are appointing distributors now. Until then, <a class="font-semibold text-steel underline" href="<?= e(url('contact')) ?>">contact our factory directly</a> — we ship everywhere.</p>
    <?php endif ?>

    <?php foreach ($groups as $reg => $items): ?>
    <h2 class="mt-10 font-display text-2xl font-bold uppercase tracking-wide text-navy"><?= e($reg) ?></h2>
    <ul class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($items as $d): ?>
      <li class="flex flex-col bg-white p-6" data-distributor>
        <h3 class="font-semibold text-navy"><?= e($d['name']) ?></h3>
        <?php if ($d['city'] || $d['country']): ?><p class="text-sm text-charcoal/60"><?= e(implode(', ', array_filter([$d['city'], $d['country']]))) ?></p><?php endif ?>
        <?php if ($d['address']): ?><p class="mt-3 whitespace-pre-line text-sm text-charcoal/75"><?= e($d['address']) ?></p><?php endif ?>
        <?php if ($d['products']): ?><p class="mt-3 text-sm"><span class="font-semibold text-navy">Supplies:</span> <?= e($d['products']) ?></p><?php endif ?>
        <div class="mt-5 flex flex-1 flex-wrap items-end gap-x-5 gap-y-2 text-sm font-semibold text-steel">
          <?php if ($d['phone']): ?><a href="tel:<?= e(preg_replace('~[^\d+]~', '', $d['phone'])) ?>" class="inline-flex items-center gap-1.5"><?= icon('phone', 'h-4 w-4') ?><?= e($d['phone']) ?></a><?php endif ?>
          <?php if ($d['email']): ?><a href="mailto:<?= e($d['email']) ?>" class="inline-flex items-center gap-1.5"><?= icon('mail', 'h-4 w-4') ?>Email</a><?php endif ?>
          <?php if ($d['website'] && preg_match('~^https?://~i', $d['website'])): ?><a href="<?= e($d['website']) ?>" target="_blank" rel="noopener nofollow" class="inline-flex items-center gap-1.5"><?= icon('globe', 'h-4 w-4') ?>Website</a><?php endif ?>
          <a href="<?= e($maps($d)) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5"><?= icon('map-pin', 'h-4 w-4') ?>Map</a>
        </div>
      </li>
      <?php endforeach ?>
    </ul>
    <?php endforeach ?>
  </div>
</section>

<section class="section bg-white" id="become">
  <div class="mx-auto grid max-w-site gap-10 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
    <div class="lg:col-span-5">
      <?= section_head('Partner with us', 'Become a distributor', 'Sell factory-made crane components and hydraulics in your region. We support partners with pricing, technical training, stock planning and leads from your area.') ?>
      <ul class="mt-6 space-y-3 text-charcoal/80">
        <?php foreach (['Protected territory and referral of local enquiries', 'Dealer pricing and technical documentation', 'Engineering support on every quotation'] as $x): ?>
        <li class="flex gap-3"><?= icon('circle-check', 'mt-0.5 h-5 w-5 shrink-0 text-safety') ?><?= e($x) ?></li>
        <?php endforeach ?>
      </ul>
    </div>
    <div class="lg:col-span-7">
      <form class="lead-form grid gap-5 sm:grid-cols-2" method="post" action="<?= e(url('lead')) ?>" novalidate data-success="dist-success">
        <?= form_alert() ?>
        <?= form_hidden('distributor') ?>
        <?= form_field(['id' => 'company', 'label' => 'Company Name', 'required' => true, 'autocomplete' => 'organization']) ?>
        <?= form_field(['id' => 'location', 'label' => 'City / region you cover', 'required' => true]) ?>
        <?= form_field(['id' => 'fullName', 'label' => 'Your name', 'required' => true, 'autocomplete' => 'name']) ?>
        <?= form_field(['id' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel']) ?>
        <?= form_field(['id' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'autocomplete' => 'email', 'full' => true]) ?>
        <?= form_field(['id' => 'message', 'label' => 'About your business', 'type' => 'textarea', 'required' => true, 'full' => true, 'rows' => 4, 'placeholder' => 'Products you sell today, customers you serve, team size, warehouse…']) ?>
        <?= consent_field() ?>
        <?= turnstile_widget('sm:col-span-2') ?>
        <div class="sm:col-span-2"><button type="submit" class="btn btn-primary btn-lg w-full justify-center sm:w-auto">Apply to become a distributor <?= icon('send', 'btn-arrow h-4 w-4') ?></button></div>
      </form>
      <?= form_success('dist-success') ?>
    </div>
  </div>
</section>
