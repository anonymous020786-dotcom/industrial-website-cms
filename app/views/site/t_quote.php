<?php /** @var array $page @var array $data @var array $meta */
$preset = (string) query('product', '');
$sent = !empty($_GET['sent']);
?>
<?= page_hero(['eyebrow' => $page['hero_eyebrow'], 'title' => $page['hero_title'] ?: $page['title'], 'lead' => $page['hero_lead'], 'image' => $page['hero_image'], 'crumbs' => $meta['crumbs']]) ?>

<section class="section bg-mist !pt-12" id="form">
  <div class="mx-auto grid max-w-site gap-8 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
    <div class="bg-white p-8 sm:p-10 lg:col-span-8">
      <?php if ($sent): ?><div class="form-alert form-alert-ok mb-8" role="status">Thank you — your quote request has been received. An engineer will review it shortly.</div><?php endif ?>
      <form class="lead-form" method="post" action="<?= e(url('lead')) ?>" enctype="multipart/form-data" novalidate data-success="quote-success">
        <?= form_alert() ?>
        <?= form_hidden('quote') ?>
        <fieldset class="form-step">
          <legend><span class="step-num">1</span> Your details</legend>
          <div class="grid gap-5 sm:grid-cols-2">
            <?= form_field(['id' => 'fullName', 'label' => 'Full Name', 'required' => true, 'autocomplete' => 'name']) ?>
            <?= form_field(['id' => 'company', 'label' => 'Company Name', 'required' => true, 'autocomplete' => 'organization']) ?>
            <?= form_field(['id' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
            <?= form_field(['id' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel']) ?>
          </div>
        </fieldset>
        <fieldset class="form-step" id="items">
          <legend><span class="step-num">2</span> Requirement</legend>
          <div class="rfq mb-6" data-rfq data-products="<?= e(json_encode(array_map(fn($x) => ['slug' => $x['slug'], 'name' => $x['name']], products_all()), JSON_UNESCAPED_UNICODE)) ?>">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <p class="text-sm font-semibold text-navy">Items to quote</p>
              <div class="flex flex-wrap gap-2">
                <select class="rfq-input !w-auto" data-rfq-picker aria-label="Add a product to the list">
                  <option value="">+ Add a product…</option>
                  <?php foreach (products_all() as $x): ?><option value="<?= e($x['slug']) ?>"><?= e($x['name']) ?></option><?php endforeach ?>
                </select>
                <button type="button" class="btn btn-outline btn-sm" data-rfq-custom><?= icon('plus', 'h-4 w-4') ?> Custom part</button>
              </div>
            </div>
            <p class="mt-3 text-sm text-charcoal/70" data-rfq-empty>Need several parts? Add each one here with its part number and quantity — or just describe your requirement below.</p>
            <div class="rfq-rows mt-3" data-rfq-rows hidden></div>
          </div>
          <div class="grid gap-5 sm:grid-cols-2">
            <?= form_field(['id' => 'product', 'label' => 'Product / Service Required', 'type' => 'select', 'required' => true, 'full' => true, 'options' => enquiry_options($preset)]) ?>
            <?= form_field(['id' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'placeholder' => 'e.g. 4']) ?>
            <?= form_field(['id' => 'requiredBy', 'label' => 'Required By', 'type' => 'date']) ?>
            <?= form_field(['id' => 'partNumber', 'label' => 'Part / Drawing Number', 'full' => true, 'placeholder' => 'If available']) ?>
            <?= form_field(['id' => 'message', 'label' => 'Technical Details', 'type' => 'textarea', 'required' => true, 'full' => true, 'rows' => 6, 'placeholder' => 'Dimensions, operating pressure, load, material, application, condition of existing part…', 'value' => mb_substr((string) query('specs', ''), 0, 1000)]) ?>
          </div>
        </fieldset>
        <fieldset class="form-step">
          <legend><span class="step-num">3</span> Attachments</legend>
          <label class="file-drop" for="attachments">
            <?= icon('upload', 'h-7 w-7 text-steel') ?>
            <span class="mt-3 block font-semibold text-navy">Drag drawings or photos here, or click to browse</span>
            <span class="mt-1 block text-xs text-charcoal/55">PDF, DWG, DXF, JPG or PNG · up to 10 MB each · max 5 files</span>
            <input id="attachments" name="attachments[]" type="file" multiple accept=".pdf,.dwg,.dxf,.jpg,.jpeg,.png" class="sr-only">
          </label>
          <ul class="file-list" data-file-list></ul>
          <p class="field-error" id="err-attachments"></p>
        </fieldset>
        <div class="grid gap-5 sm:grid-cols-2"><?= consent_field() ?><?= turnstile_widget('sm:col-span-2') ?></div>
        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-xs text-charcoal/55">Fields marked <span class="req">*</span> are required.</p>
          <button type="submit" class="btn btn-primary btn-lg justify-center">Submit Quote Request <?= icon('send', 'btn-arrow h-4 w-4') ?></button>
        </div>
      </form>
      <?= form_success('quote-success') ?>
    </div>

    <aside class="space-y-6 lg:col-span-4">
      <div class="blueprint bg-navy p-8 text-white">
        <h2 class="font-display text-2xl font-semibold uppercase tracking-wide"><?= e($data['next_title']) ?></h2>
        <ol class="mt-6 space-y-5">
          <?php foreach ($data['next_steps'] ?? [] as $i => $s): ?>
          <li class="flex gap-4"><span class="step-num step-num-light"><?= $i + 1 ?></span><div><p class="font-semibold"><?= e($s['title'] ?? '') ?></p><p class="text-sm text-white/65"><?= e($s['text'] ?? '') ?></p></div></li>
          <?php endforeach ?>
        </ol>
      </div>
      <div class="border border-black/10 bg-white p-8">
        <h2 class="font-display text-2xl font-semibold uppercase tracking-wide text-navy"><?= e($data['urgent_title']) ?></h2>
        <p class="mt-2 text-sm leading-relaxed text-charcoal/70"><?= e($data['urgent_text']) ?></p>
        <?php if (company('phone')): ?><a href="tel:<?= e(company('phone_href')) ?>" class="btn btn-primary mt-5 w-full justify-center" data-track="phone"><?= icon('phone', 'h-4 w-4') ?> <?= e(company('phone')) ?></a><?php endif ?>
        <?php if ($wa = whatsapp_link()): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="btn btn-outline mt-3 w-full justify-center" data-track="whatsapp"><?= icon('message-circle', 'h-4 w-4') ?> WhatsApp Us</a><?php endif ?>
      </div>
    </aside>
  </div>
</section>
