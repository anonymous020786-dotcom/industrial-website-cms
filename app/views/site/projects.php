<?php /** @var array $projects @var array $industries @var ?array $active @var array $meta */ ?>
<?= page_hero(['eyebrow' => 'Projects', 'title' => 'Proven Where It Matters', 'lead' => 'Real problems, the components we engineered to solve them, and the results — told with our clients’ permission.', 'crumbs' => $meta['crumbs']]) ?>

<section class="section bg-mist !pt-12">
  <div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8">
    <?php if (count($industries) > 1): ?>
    <nav class="mb-10 flex flex-wrap gap-2" aria-label="Filter by industry">
      <a href="<?= e(url('projects')) ?>" class="chip<?= $active ? '' : ' is-active' ?>"<?= $active ? '' : ' aria-current="page"' ?>>All industries</a>
      <?php foreach ($industries as $i): $on = $active && $active['id'] === $i['id']; ?>
      <a href="<?= e(url('projects?industry=' . $i['slug'])) ?>" class="chip<?= $on ? ' is-active' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= e($i['name']) ?></a>
      <?php endforeach ?>
    </nav>
    <?php endif ?>
    <?php if ($projects): ?>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($projects as $i => $p) echo project_card($p, $i) ?></div>
    <?php else: ?>
    <div class="empty-state"><p class="font-semibold text-navy">No projects for this industry yet.</p><a class="btn btn-outline mt-4" href="<?= e(url('projects')) ?>">See all projects</a></div>
    <?php endif ?>
  </div>
</section>

<?= cta_band('Have a Similar Challenge?', 'Tell us about your equipment and duty conditions — we will propose a solution and a quotation.') ?>
