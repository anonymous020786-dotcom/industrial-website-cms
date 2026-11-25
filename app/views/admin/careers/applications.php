<?php /** @var array $rows @var array $pager @var array $jobs @var int $total @var array $stageCounts */
$curStage = (string) query('stage', ''); ?>
<div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6" data-hiring-pipeline>
  <?php foreach (HIRING_STAGES as $k => [$l, $cls]): ?>
  <a href="<?= e(url('admin/careers/applications') . qs(['stage' => $curStage === $k ? null : $k, 'page' => null])) ?>" class="adm-card block p-4 transition hover:-translate-y-0.5<?= $curStage === $k ? ' ring-2 ring-steel' : '' ?>">
    <span class="adm-badge <?= $cls ?>"><?= e($l) ?></span><span class="mt-2 block font-display text-3xl font-bold text-navy"><?= (int) ($stageCounts[$k] ?? 0) ?></span></a>
  <?php endforeach ?>
</div>
<div class="adm-card mb-4 flex items-start gap-3 p-4 text-sm text-charcoal/75">
  <?= icon('shield-check', 'mt-0.5 h-5 w-5 shrink-0 text-emerald-600') ?>
  <p>Applications contain personal data (CVs). They are kept out of the sales leads list and only administrators can open them. Delete applications you no longer need.</p>
</div>
<form method="get" class="adm-card mb-4 flex flex-wrap items-center gap-3 p-4">
  <select class="adm-input !w-auto" name="job" aria-label="Position"><option value="">All positions</option>
    <?php foreach ($jobs as $j): ?><option value="<?= (int) $j['id'] ?>"<?= query('job') === (string) $j['id'] ? ' selected' : '' ?>><?= e($j['title']) ?><?= $j['status'] === 'draft' ? ' (draft)' : '' ?></option><?php endforeach ?>
  </select>
  <select class="adm-input !w-auto" name="stage" aria-label="Stage"><option value="">Any stage</option>
    <?php foreach (HIRING_STAGES as $k => [$l]): ?><option value="<?= $k ?>"<?= query('stage') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?>
  </select>
  <button class="adm-btn adm-btn-primary adm-btn-sm"><?= icon('filter', 'h-4 w-4') ?> Filter</button>
  <span class="ml-auto text-sm text-charcoal/55"><?= $total ?> application<?= $total === 1 ? '' : 's' ?></span>
</form>
<div class="adm-card">
  <?php if ($rows): ?>
  <div class="overflow-x-auto">
    <table class="adm-table">
      <thead><tr><th>Applicant</th><th>Position</th><th>Location</th><th>CV</th><th>Stage</th><th>Received</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $l): ?>
        <tr class="<?= $l['status'] === 'new' ? 'is-new' : '' ?>">
          <td><a class="font-semibold text-navy hover:text-steel" href="<?= e(url('admin/leads/' . $l['id'])) ?>" data-application><?= e($l['name']) ?></a><div class="text-xs text-charcoal/55"><?= e($l['email']) ?> · <?= e($l['phone']) ?></div></td>
          <td class="text-sm"><?= e($l['job_title'] ?: 'Removed position') ?></td>
          <td class="text-sm"><?= e($l['location'] ?: '—') ?></td>
          <td class="text-sm"><?= $l['files'] ? icon('paperclip', 'h-4 w-4') : '—' ?></td>
          <td><?= hiring_badge(hiring_stage($l)) ?></td>
          <td class="whitespace-nowrap text-sm text-charcoal/60" title="<?= e($l['created_at']) ?>"><?= e(time_ago($l['created_at'])) ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="p-12 text-center"><span class="mx-auto block w-fit text-charcoal/30"><?= icon('user-round-check', 'h-10 w-10') ?></span><p class="mt-3 font-semibold text-navy">No applications yet</p><p class="mt-1 text-sm text-charcoal/55">Publish a job opening — applications from the careers page appear here.</p></div>
  <?php endif ?>
</div>
<?= view('admin/_pager', ['pager' => $pager]) ?>
