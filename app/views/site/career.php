<?php /** @var array $meta @var array $job @var bool $open */ ?>
<?= page_hero(['eyebrow' => 'Careers' . ($job['department'] ? ' · ' . $job['department'] : ''), 'title' => $job['title'], 'lead' => (string) $job['summary'], 'crumbs' => $meta['crumbs']]) ?>

<section class="section bg-mist !pt-12">
  <div class="mx-auto grid max-w-site gap-8 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
    <article class="bg-white p-8 sm:p-10 lg:col-span-7">
      <dl class="grid gap-4 border-b border-black/10 pb-6 text-sm sm:grid-cols-3">
        <?php foreach (['Location' => $job['location'], 'Type' => $job['job_type'], 'Apply by' => $job['closes_at'] ? date('j M Y', strtotime($job['closes_at'])) : ''] as $l => $v): if (!$v) continue; ?>
        <div><dt class="text-xs font-semibold uppercase tracking-[0.16em] text-charcoal/55"><?= $l ?></dt><dd class="mt-1 font-semibold text-navy"><?= e($v) ?></dd></div>
        <?php endforeach ?>
      </dl>
      <div class="article mt-6"><?= $job['description'] ? sanitize_html($job['description']) : '<p>' . e((string) $job['summary']) . '</p>' ?></div>
    </article>
    <div class="lg:col-span-5" id="apply">
      <?php if (!$open && $job['status'] === 'published'): ?>
      <div class="bg-white p-8" data-job-closed>
        <h2 class="font-display text-2xl font-bold uppercase tracking-wide text-navy">This position is closed</h2>
        <p class="mt-3 text-charcoal/75">Applications are no longer accepted.</p>
        <a class="btn btn-primary mt-6" href="<?= e(url('careers')) ?>">See current openings</a>
      </div>
      <?php else: ?>
      <div class="bg-white p-8 lg:sticky lg:top-28">
        <h2 class="font-display text-2xl font-bold uppercase tracking-wide text-navy">Apply for this job</h2>
        <form class="lead-form mt-6 grid gap-5" method="post" action="<?= e(url('lead')) ?>" enctype="multipart/form-data" novalidate data-success="job-success">
          <?= form_alert() ?>
          <?= form_hidden('job') ?>
          <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
          <?= form_field(['id' => 'fullName', 'label' => 'Full Name', 'required' => true, 'autocomplete' => 'name']) ?>
          <?= form_field(['id' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
          <?= form_field(['id' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel']) ?>
          <?= form_field(['id' => 'location', 'label' => 'Where do you live?', 'autocomplete' => 'address-level2']) ?>
          <div>
            <label class="file-drop" for="cv">
              <?= icon('file-user', 'h-7 w-7 text-steel') ?>
              <span class="mt-3 block font-semibold text-navy">Attach your CV <span class="req" aria-hidden="true">*</span></span>
              <span class="mt-1 block text-xs text-charcoal/55">PDF or Word · up to 10 MB</span>
              <input id="cv" name="attachments[]" type="file" accept=".pdf,.doc,.docx" class="sr-only">
            </label>
            <ul class="file-list" data-file-list></ul>
            <p class="field-error" id="err-attachments"></p>
            <p class="field-error" id="err-job_id"></p>
          </div>
          <?= form_field(['id' => 'message', 'label' => 'Cover note (optional)', 'type' => 'textarea', 'rows' => 4, 'placeholder' => 'Experience, notice period, why this role…']) ?>
          <div class="field"><label class="consent"><input type="checkbox" id="consent" name="consent" value="1" required aria-describedby="err-consent">
            <span>I agree that <?= e(company('name')) ?> may keep my application to assess me for this and similar roles, as described in the <a href="<?= e(url('privacy-policy')) ?>" class="font-semibold text-steel underline underline-offset-2">Privacy Policy</a>. <span class="req" aria-hidden="true">*</span></span></label>
            <p class="field-error" id="err-consent"></p></div>
          <?= turnstile_widget() ?>
          <button type="submit" class="btn btn-primary btn-lg w-full justify-center">Send application <?= icon('send', 'btn-arrow h-4 w-4') ?></button>
        </form>
        <div id="job-success" class="form-success" hidden tabindex="-1" role="status">
          <span class="text-safety"><?= icon('circle-check', 'h-10 w-10') ?></span>
          <h3 class="mt-5 font-display text-3xl font-semibold uppercase tracking-wide text-navy">Application received</h3>
          <p class="mt-3 leading-relaxed text-charcoal/75">Thank you, <span data-success-name></span>. Our team reviews every application and will contact you if your profile fits.</p>
        </div>
      </div>
      <?php endif ?>
    </div>
  </div>
</section>
