<?php /** @var array $meta */
$phone = company('phone'); $phoneHref = company('phone_href') ?: preg_replace('~[^\d+]~', '', $phone);
$wa = setting('whatsapp_number') ? preg_replace('~\D~', '', (string) setting('whatsapp_number')) : '';
?>
<?= page_hero(['eyebrow' => 'Service & repairs', 'title' => 'Breakdown or service request', 'lead' => 'Crane or hydraulic system down? Tell us what happened — our service engineers respond fast, and you can follow your request online.', 'crumbs' => $meta['crumbs']]) ?>

<section class="section bg-mist !pt-12" id="form">
  <div class="mx-auto grid max-w-site gap-8 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
    <div class="bg-white p-8 sm:p-10 lg:col-span-8">
      <form class="lead-form" method="post" action="<?= e(url('lead')) ?>" enctype="multipart/form-data" novalidate data-success="service-success">
        <?= form_alert() ?>
        <?= form_hidden('service') ?>
        <fieldset class="form-step">
          <legend><span class="step-num">1</span> How urgent is it?</legend>
          <div class="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Urgency">
            <?php foreach (['urgent' => ['Breakdown — urgent', 'Equipment is stopped', 'siren'], 'high' => ['Soon', 'Working, but failing', 'triangle-alert'], 'normal' => ['Planned service', 'Maintenance or inspection', 'calendar-clock']] as $k => [$l, $h, $ic]): ?>
            <label class="urgency-option"><input type="radio" name="urgency" value="<?= $k ?>"<?= $k === 'urgent' ? ' checked' : '' ?>>
              <span><?= icon($ic, 'h-5 w-5') ?><strong><?= e($l) ?></strong><small><?= e($h) ?></small></span></label>
            <?php endforeach ?>
          </div>
        </fieldset>
        <fieldset class="form-step">
          <legend><span class="step-num">2</span> Equipment &amp; problem</legend>
          <div class="grid gap-5 sm:grid-cols-2">
            <?= form_field(['id' => 'equipment', 'label' => 'Equipment', 'required' => true, 'placeholder' => 'e.g. 50 t EOT crane hoist, hydraulic press cylinder']) ?>
            <?= form_field(['id' => 'location', 'label' => 'Site location', 'required' => true, 'placeholder' => 'Plant / site, city', 'autocomplete' => 'address-level2']) ?>
            <?= form_field(['id' => 'message', 'label' => 'What happened?', 'type' => 'textarea', 'required' => true, 'full' => true, 'rows' => 5, 'placeholder' => 'Symptoms, error codes, when it started, model / serial number…']) ?>
          </div>
          <label class="file-drop mt-5" for="attachments">
            <?= icon('camera', 'h-7 w-7 text-steel') ?>
            <span class="mt-3 block font-semibold text-navy">Add photos or videos stills of the problem (optional)</span>
            <span class="mt-1 block text-xs text-charcoal/55">JPG, PNG or PDF · up to 10 MB each · max 5 files</span>
            <input id="attachments" name="attachments[]" type="file" multiple accept=".jpg,.jpeg,.png,.pdf" class="sr-only">
          </label>
          <ul class="file-list" data-file-list></ul>
          <p class="field-error" id="err-attachments"></p>
        </fieldset>
        <fieldset class="form-step">
          <legend><span class="step-num">3</span> Your details</legend>
          <div class="grid gap-5 sm:grid-cols-2">
            <?= form_field(['id' => 'fullName', 'label' => 'Full Name', 'required' => true, 'autocomplete' => 'name']) ?>
            <?= form_field(['id' => 'company', 'label' => 'Company Name', 'autocomplete' => 'organization']) ?>
            <?= form_field(['id' => 'phone', 'label' => 'Phone (we will call you)', 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel']) ?>
            <?= form_field(['id' => 'email', 'label' => 'Email (for the tracking link)', 'type' => 'email', 'autocomplete' => 'email']) ?>
          </div>
        </fieldset>
        <div class="grid gap-5 sm:grid-cols-2"><?= consent_field() ?><?= turnstile_widget('sm:col-span-2') ?></div>
        <button type="submit" class="btn btn-primary btn-lg mt-6 w-full justify-center sm:w-auto">Send service request <?= icon('send', 'btn-arrow h-4 w-4') ?></button>
      </form>
      <div id="service-success" class="form-success" hidden tabindex="-1" role="status">
        <span class="text-safety"><?= icon('circle-check', 'h-10 w-10') ?></span>
        <h3 class="mt-5 font-display text-3xl font-semibold uppercase tracking-wide text-navy">Request received</h3>
        <p class="mt-3 leading-relaxed text-charcoal/75">Thank you, <span data-success-name></span>. <span data-success-refwrap hidden>Your reference is <strong class="font-mono text-navy" data-success-ref></strong>.</span> An engineer will call you shortly.</p>
        <a class="btn btn-primary mt-8" href="#" data-success-track hidden><?= icon('radar', 'h-4 w-4') ?> Track your request</a>
      </div>
    </div>
    <aside class="space-y-6 lg:col-span-4">
      <?php if ($phone): ?>
      <div class="bg-navy p-8 text-white">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-safety">Emergency line</p>
        <p class="mt-2 text-white/80">For a breakdown, calling is fastest.</p>
        <a href="tel:<?= e($phoneHref) ?>" class="btn btn-primary mt-5 w-full justify-center"><?= icon('phone', 'h-4 w-4') ?> <?= e($phone) ?></a>
        <?php if ($wa): ?><a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" class="btn btn-ghost-light mt-3 w-full justify-center"><?= icon('message-circle', 'h-4 w-4') ?> WhatsApp photos</a><?php endif ?>
      </div>
      <?php endif ?>
      <div class="bg-white p-8">
        <h2 class="font-display text-xl font-bold uppercase text-navy">What happens next</h2>
        <ol class="mt-4 space-y-3 text-sm text-charcoal/80">
          <li><strong class="text-navy">1.</strong> You get a reference number and a private tracking link.</li>
          <li><strong class="text-navy">2.</strong> An engineer calls you to diagnose and plan the fix.</li>
          <li><strong class="text-navy">3.</strong> We post every update on your tracking page and by email.</li>
        </ol>
      </div>
    </aside>
  </div>
</section>
