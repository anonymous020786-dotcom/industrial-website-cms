<?php /** @var array $rows @var string $filter @var int $unread */
$sev = ['critical' => ['bg-red-100 text-red-800', 'shield-alert', 'Critical'], 'warning' => ['bg-amber-100 text-amber-800', 'triangle-alert', 'Warning'], 'info' => ['bg-sky-100 text-sky-800', 'info', 'Info']];
?>
<div class="adm-tabs mb-4">
  <a class="adm-tab<?= $filter === 'all' ? ' is-active' : '' ?>" href="<?= e(url('admin/notifications')) ?>">All</a>
  <a class="adm-tab<?= $filter === 'unread' ? ' is-active' : '' ?>" href="<?= e(url('admin/notifications?show=unread')) ?>">Unread <span><?= $unread ?></span></a>
</div>
<div class="adm-card divide-y divide-black/5">
  <?php if (!$rows): ?><p class="p-12 text-center text-sm text-charcoal/75"><?= $filter === 'unread' ? 'You’re all caught up.' : 'No notifications yet. Security alerts and important admin activity will appear here.' ?></p><?php endif ?>
  <?php foreach ($rows as $n): [$cls, $ic, $lab] = $sev[$n['severity']] ?? $sev['info']; ?>
  <div class="flex gap-4 p-5<?= $n['read_at'] ? '' : ' bg-sky-50/40' ?>">
    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full <?= $cls ?>"><?= icon($ic, 'h-5 w-5') ?></span>
    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-2">
        <p class="font-semibold text-navy"><?= e($n['title']) ?></p>
        <span class="adm-badge <?= $cls ?>"><?= $lab ?></span>
        <?php if (!$n['read_at']): ?><span class="adm-badge bg-navy text-white">New</span><?php endif ?>
      </div>
      <p class="mt-1 text-sm leading-relaxed text-charcoal/75"><?= e((string) $n['body']) ?></p>
      <p class="mt-2 text-xs text-charcoal/75"><?= e(date('j M Y, H:i', strtotime($n['created_at']))) ?> · <?= e(device_label($n['user_agent'])) ?> · <?= e((string) $n['ip']) ?><?= $n['emailed'] ? ' · emailed' : '' ?></p>
    </div>
    <div class="flex shrink-0 flex-col items-end gap-2">
      <?php if ($n['link']): ?><a class="adm-btn adm-btn-light adm-btn-sm" href="<?= e(url(ltrim($n['link'], '/'))) ?>">Review</a><?php endif ?>
      <?php if (!$n['read_at']): ?><form method="post" action="<?= e(url('admin/notifications/read')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><button class="adm-link text-xs">Mark read</button></form><?php endif ?>
    </div>
  </div>
  <?php endforeach ?>
</div>
<p class="mt-4 text-sm text-charcoal/75">Choose which alerts are emailed in <a class="adm-link" href="<?= e(url('admin/profile#notifications')) ?>">My profile → Notifications</a>.</p>
