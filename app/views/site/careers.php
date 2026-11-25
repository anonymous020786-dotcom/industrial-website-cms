<?php /** @var array $meta @var array $jobs */
$email = (string) (setting('careers_email') ?: company('email'));
?>
<?= page_hero(['eyebrow' => 'Careers', 'title' => 'Build things that lift industry', 'lead' => 'Machinists, welders, fitters, service engineers and designers — join a team that makes heavy equipment safer and more reliable.', 'crumbs' => $meta['crumbs']]) ?>

<section class="section bg-mist !pt-12">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <h2 class="font-display text-3xl font-bold uppercase tracking-wide text-navy">Current openings</h2>
    <?php if ($jobs): ?>
    <ul class="mt-6 space-y-4">
      <?php foreach ($jobs as $j): ?>
      <li><a class="group flex flex-col gap-4 bg-white p-6 sm:flex-row sm:items-center" href="<?= e(url('careers/' . $j['slug'])) ?>" data-job>
        <span class="flex-1">
          <span class="block font-display text-xl font-bold uppercase tracking-wide text-navy group-hover:text-steel"><?= e($j['title']) ?></span>
          <span class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-charcoal/65">
            <?php if ($j['department']): ?><span class="inline-flex items-center gap-1.5"><?= icon('users', 'h-4 w-4') ?><?= e($j['department']) ?></span><?php endif ?>
            <?php if ($j['location']): ?><span class="inline-flex items-center gap-1.5"><?= icon('map-pin', 'h-4 w-4') ?><?= e($j['location']) ?></span><?php endif ?>
            <?php if ($j['job_type']): ?><span class="inline-flex items-center gap-1.5"><?= icon('clock', 'h-4 w-4') ?><?= e($j['job_type']) ?></span><?php endif ?>
            <?php if ($j['closes_at']): ?><span>Apply by <?= e(date('j M Y', strtotime($j['closes_at']))) ?></span><?php endif ?>
          </span>
          <?php if ($j['summary']): ?><span class="mt-3 block text-charcoal/75"><?= e($j['summary']) ?></span><?php endif ?>
        </span>
        <span class="btn btn-primary self-start sm:self-center">View &amp; apply <?= icon('arrow-right', 'btn-arrow h-4 w-4') ?></span>
      </a></li>
      <?php endforeach ?>
    </ul>
    <?php else: ?>
    <p class="mt-6 bg-white p-8 text-charcoal/75" data-no-jobs>There are no openings right now. We are always glad to hear from skilled people — send your CV to <?php if ($email): ?><a class="font-semibold text-steel underline" href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php else: ?>us<?php endif ?> and we will contact you when a role opens.</p>
    <?php endif ?>
  </div>
</section>
