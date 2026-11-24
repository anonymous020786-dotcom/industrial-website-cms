<?php /** @var string $title @var array $errors @var ?string $message @var array $old @var string $next @var array $meta */
$isSheet = $next !== '';
$f = fn($id, $label, $type = 'text', $req = true, $ac = '') => form_field(['id' => $id, 'label' => $label, 'type' => $type, 'required' => $req, 'autocomplete' => $ac, 'value' => (string) ($old[$id] ?? ''), 'error' => $errors[$id] ?? '']);
?>
<?= page_hero(['eyebrow' => 'Downloads', 'title' => $isSheet ? 'Download the Datasheet' : $title, 'lead' => $isSheet ? 'Tell us who you are and the download starts straight away. You will not need to do this again on this device.' : 'Specifications, sizes and options for our crane components and hydraulic products in one PDF.', 'crumbs' => $meta['crumbs']]) ?>

<section class="section bg-mist !pt-12">
  <div class="mx-auto grid max-w-site gap-8 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
    <div class="bg-white p-8 sm:p-10 lg:col-span-7">
      <h2 class="font-display text-3xl font-semibold uppercase tracking-wide text-navy">Get instant access</h2>
      <p class="mt-2 text-charcoal/75">We will only use your details to help with your enquiry — no newsletters unless you ask.</p>
      <?php if ($message): ?><div class="form-alert mt-6" role="alert"><?= e($message) ?></div><?php endif ?>
      <form method="post" action="<?= e(url('catalogue')) ?>" class="mt-6 grid gap-5 sm:grid-cols-2" novalidate>
        <?= form_hidden('download') ?>
        <?php if ($isSheet): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif ?>
        <?= $f('fullName', 'Full Name', 'text', true, 'name') ?>
        <?= $f('company', 'Company', 'text', true, 'organization') ?>
        <?= $f('email', 'Work Email', 'email', true, 'email') ?>
        <?= $f('phone', 'Phone (optional)', 'tel', false, 'tel') ?>
        <?= consent_field() ?>
        <?php if (!empty($errors['consent'])): ?><p class="field-error sm:col-span-2"><?= e($errors['consent']) ?></p><?php endif ?>
        <?= turnstile_widget('sm:col-span-2') ?>
        <div class="sm:col-span-2"><button type="submit" class="btn btn-primary btn-lg w-full justify-center sm:w-auto"><?= icon('file-down', 'h-5 w-5') ?> <?= $isSheet ? 'Download datasheet' : 'Download catalogue' ?></button></div>
      </form>
    </div>
    <aside class="blueprint bg-navy p-8 text-white lg:col-span-5">
      <h2 class="font-display text-2xl font-semibold uppercase tracking-wide">What’s inside</h2>
      <ul class="mt-6 space-y-4 text-white/80">
        <?php foreach (['Product ranges, sizes and typical specifications', 'Materials, finishes and testing standards', 'How to specify a part — and what to send us for a fast quote'] as $x): ?>
        <li class="flex gap-3"><?= icon('circle-check', 'mt-0.5 h-5 w-5 shrink-0 text-safety') ?><?= e($x) ?></li>
        <?php endforeach ?>
      </ul>
      <p class="mt-8 text-sm text-white/70">Prefer to talk? <a class="font-semibold text-white underline underline-offset-2" href="<?= e(url('contact')) ?>">Contact our engineers</a>.</p>
    </aside>
  </div>
</section>
