<?php /** @var array $page @var array $data @var array $meta */
$hours = setting('company_hours', []) ?: [];
$sent = !empty($_GET['sent']);
$card = fn($i, $label, $body) => '<div class="contact-card" data-reveal><span class="text-safety">' . icon($i, 'h-6 w-6') . '</span><p class="contact-label mt-4">' . e($label) . '</p><div class="mt-1 text-navy">' . $body . '</div></div>';
?>
<?= page_hero(['eyebrow' => $page['hero_eyebrow'], 'title' => $page['hero_title'] ?: $page['title'], 'lead' => $page['hero_lead'], 'image' => $page['hero_image'], 'crumbs' => $meta['crumbs']]) ?>

<section class="relative z-10 bg-mist pt-12">
  <div class="mx-auto grid max-w-site gap-4 px-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
    <?= $card('phone', 'Call Us', '<a href="tel:' . e(company('phone_href')) . '" class="font-semibold hover:text-steel" data-track="phone">' . e(company('phone')) . '</a>') ?>
    <?= $card('mail', 'Email', '<a href="mailto:' . e(company('email')) . '" class="break-all font-semibold hover:text-steel" data-track="email">' . e(company('email')) . '</a>') ?>
    <?= $card('map-pin', 'Visit', '<address class="not-italic text-sm leading-relaxed">' . nl2br(e(company('address'))) . '</address>') ?>
    <?= $card('clock', 'Business Hours', '<ul class="text-sm leading-relaxed">' . implode('', array_map(fn($h) => '<li><span class="text-charcoal/60">' . e($h['day'] ?? '') . ':</span> ' . e($h['time'] ?? '') . '</li>', $hours)) . '</ul>') ?>
  </div>
</section>

<section class="section bg-mist !pt-12" id="form">
  <div class="mx-auto grid max-w-site gap-8 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
    <div class="bg-white p-8 sm:p-10 lg:col-span-7" data-reveal>
      <h2 class="font-display text-3xl font-bold uppercase tracking-wide text-navy"><?= e($data['form_title']) ?></h2>
      <p class="mt-2 text-charcoal/65">For detailed quotations with drawings, use the <a href="<?= e(url('quote')) ?>" class="font-semibold text-steel underline underline-offset-2">quote request form</a>.</p>
      <?php if ($sent): ?><div class="form-alert form-alert-ok mt-6" role="status">Thank you — your message has been received. We will be in touch shortly.</div><?php endif ?>
      <form class="lead-form mt-8 grid gap-5 sm:grid-cols-2" method="post" action="<?= e(url('lead')) ?>" novalidate data-success="contact-success">
        <?= form_alert() ?>
        <?= form_hidden('contact') ?>
        <?= form_field(['id' => 'fullName', 'label' => 'Full Name', 'required' => true, 'autocomplete' => 'name']) ?>
        <?= form_field(['id' => 'company', 'label' => 'Company Name', 'autocomplete' => 'organization']) ?>
        <?= form_field(['id' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
        <?= form_field(['id' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'autocomplete' => 'tel']) ?>
        <?= form_field(['id' => 'product', 'label' => 'Product / Service Required', 'type' => 'select', 'required' => true, 'full' => true, 'options' => enquiry_options()]) ?>
        <?= form_field(['id' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true, 'full' => true, 'placeholder' => 'How can we help?']) ?>
        <?= consent_field() ?>
        <?= turnstile_widget('sm:col-span-2') ?>
        <div class="flex flex-col gap-4 sm:col-span-2 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-xs text-charcoal/55">Fields marked <span class="req">*</span> are required.</p>
          <button type="submit" class="btn btn-primary btn-lg justify-center">Send Message <?= icon('send', 'btn-arrow h-4 w-4') ?></button>
        </div>
      </form>
      <?= form_success('contact-success') ?>
    </div>

    <div class="space-y-8 lg:col-span-5">
      <?php if (!empty($data['map_embed']) && preg_match('~^https://(www\.)?google\.[a-z.]+/maps/embed~', $data['map_embed'])): ?>
        <div class="aspect-[16/10] bg-navy" data-reveal><iframe src="<?= e($data['map_embed']) ?>" class="h-full w-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map showing our location" allowfullscreen></iframe></div>
      <?php else: ?>
        <div class="map-placeholder" data-reveal><?= icon('map', 'h-8 w-8') ?><p class="mt-3 font-semibold">Map coming soon</p><p class="mt-1 max-w-xs text-center text-xs text-white/60"><?= nl2br(e(company('address'))) ?></p></div>
      <?php endif ?>
      <?php if ($wa = whatsapp_link()): ?>
        <a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="wa-card" data-reveal data-track="whatsapp"><?= icon('message-circle', 'h-6 w-6') ?><span><strong class="block">Prefer WhatsApp?</strong><span class="text-sm opacity-80">Chat with our team directly.</span></span><?= icon('arrow-right', 'ml-auto h-5 w-5') ?></a>
      <?php endif ?>
      <?php if (!empty($data['faq'])): ?>
      <div data-reveal>
        <h2 class="font-display text-3xl font-bold uppercase tracking-wide text-navy"><?= e($data['faq_title']) ?></h2>
        <div class="faq mt-5">
          <?php foreach ($data['faq'] as $f): ?>
          <details class="faq-item"><summary><?= e($f['q'] ?? '') ?><span class="faq-icon"><?= icon('plus', 'h-5 w-5') ?></span></summary><p><?= e($f['a'] ?? '') ?></p></details>
          <?php endforeach ?>
        </div>
      </div>
      <?php endif ?>
    </div>
  </div>
</section>
<?php if (!empty($data['faq'])): ?>
<script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f['q'] ?? '', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a'] ?? '']], $data['faq'])], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endif ?>
