<?php /** @var array $meta @var string $q @var array $matches */ ?>
<?= page_hero(['eyebrow' => 'Spare parts', 'title' => 'Find a part by part number', 'lead' => 'Type the number from the old part, the original crane maker or another supplier — we will show our equivalent. Spaces, dashes and dots don’t matter.', 'crumbs' => $meta['crumbs']]) ?>

<section class="section bg-mist !pt-12">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <form method="get" action="<?= e(url('parts')) ?>" class="flex flex-col gap-3 bg-white p-6 sm:flex-row sm:items-end" role="search">
      <div class="field flex-1"><label for="part-q">Part number</label>
        <input id="part-q" name="q" type="search" value="<?= e($q) ?>" placeholder="e.g. 4A-2231-80 or HC 63/36" autocomplete="off" required minlength="3" maxlength="80"></div>
      <button class="btn btn-primary btn-lg justify-center"><?= icon('search', 'h-5 w-5') ?> Find part</button>
    </form>

    <?php if ($q !== ''): ?>
    <div class="mt-8" data-part-results>
      <?php if ($matches): ?>
      <h2 class="font-display text-2xl font-bold uppercase tracking-wide text-navy"><?= count($matches) ?> match<?= count($matches) === 1 ? '' : 'es' ?> for “<?= e($q) ?>”</h2>
      <div class="mt-5 grid gap-4 md:grid-cols-2">
        <?php foreach ($matches as $m): ?>
        <article class="bg-white p-6" data-part-match>
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-lg font-bold text-navy"><?= e($m['ref_number']) ?></span>
            <?php if ($m['brand']): ?><span class="bg-mist px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-charcoal/70"><?= e($m['brand']) ?></span><?php endif ?>
            <?php if ($m['exact']): ?><span class="bg-safety/15 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-navy">Exact match</span><?php endif ?>
          </div>
          <p class="mt-3 text-charcoal/80">Our equivalent: <strong class="text-navy"><?= e($m['product']['name'] ?? 'made to order') ?></strong><?php if ($m['our_part']): ?> · part no. <span class="font-mono font-semibold"><?= e($m['our_part']) ?></span><?php endif ?></p>
          <?php if ($m['notes']): ?><p class="mt-2 text-sm text-charcoal/70"><?= e($m['notes']) ?></p><?php endif ?>
          <div class="mt-5 flex flex-wrap gap-3">
            <a class="btn btn-primary" href="<?= e(url('quote') . '?' . http_build_query(['product' => $m['product']['name'] ?? '', 'specs' => 'Part number ' . $m['ref_number'] . ($m['brand'] ? ' (' . $m['brand'] . ')' : '') . ($m['our_part'] ? ' — your part no. ' . $m['our_part'] : '')])) ?>">Request a quote <?= icon('arrow-right', 'btn-arrow h-4 w-4') ?></a>
            <?php if ($m['product']): ?><a class="btn btn-outline" href="<?= e(product_url($m['product'])) ?>">View product</a><?php endif ?>
          </div>
        </article>
        <?php endforeach ?>
      </div>
      <p class="mt-6 text-sm text-charcoal/70">Not the right one? Send us the details below and we will check it for you.</p>
      <?php else: ?>
      <div class="bg-white p-6" data-part-none>
        <h2 class="font-display text-2xl font-bold uppercase tracking-wide text-navy">No match for “<?= e($q) ?>” yet</h2>
        <p class="mt-2 text-charcoal/75">That doesn’t mean we can’t make it — most of our parts are built to drawing or sample. Send the details and an engineer will reply, usually within one business day.</p>
      </div>
      <?php endif ?>
    </div>
    <?php endif ?>
  </div>
</section>

<section class="section bg-white" id="request">
  <div class="mx-auto grid max-w-site gap-10 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
    <div class="lg:col-span-4">
      <?= section_head('Find me this part', 'Can’t find it? We will.', 'Send the part number, a photo or a drawing. We identify the part, confirm fit and quote — even for discontinued and imported equipment.') ?>
    </div>
    <div class="lg:col-span-8">
      <form class="lead-form grid gap-5 sm:grid-cols-2" method="post" action="<?= e(url('lead')) ?>" enctype="multipart/form-data" novalidate data-success="part-success">
        <?= form_alert() ?>
        <?= form_hidden('part') ?>
        <?= form_field(['id' => 'partNumber', 'label' => 'Part number', 'required' => true, 'value' => $q, 'placeholder' => 'As stamped on the part or in the manual']) ?>
        <?= form_field(['id' => 'product', 'label' => 'Machine / brand (if known)', 'placeholder' => 'e.g. 20 t EOT crane, original maker']) ?>
        <?= form_field(['id' => 'message', 'label' => 'Details', 'type' => 'textarea', 'required' => true, 'full' => true, 'rows' => 4, 'placeholder' => 'Quantity, dimensions, what the part does, how urgent…']) ?>
        <div class="sm:col-span-2">
          <label class="file-drop" for="part-attachments">
            <?= icon('paperclip', 'h-7 w-7 text-steel') ?>
            <span class="mt-3 block font-semibold text-navy">Photo, drawing or nameplate (optional)</span>
            <span class="mt-1 block text-xs text-charcoal/55">JPG, PNG, PDF, DWG or DXF · up to 10 MB each</span>
            <input id="part-attachments" name="attachments[]" type="file" multiple accept=".jpg,.jpeg,.png,.pdf,.dwg,.dxf" class="sr-only">
          </label>
          <ul class="file-list" data-file-list></ul>
          <p class="field-error" id="err-attachments"></p>
        </div>
        <?= form_field(['id' => 'fullName', 'label' => 'Full Name', 'required' => true, 'autocomplete' => 'name']) ?>
        <?= form_field(['id' => 'company', 'label' => 'Company Name', 'autocomplete' => 'organization']) ?>
        <?= form_field(['id' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
        <?= form_field(['id' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'autocomplete' => 'tel']) ?>
        <?= consent_field() ?>
        <?= turnstile_widget('sm:col-span-2') ?>
        <div class="sm:col-span-2"><button type="submit" class="btn btn-primary btn-lg w-full justify-center sm:w-auto">Find this part for me <?= icon('send', 'btn-arrow h-4 w-4') ?></button></div>
      </form>
      <?= form_success('part-success') ?>
    </div>
  </div>
</section>
