<?php /** @var ?array $pending @var string $entity */
if (!$pending) return;
$mine = (int) $pending['user_id'] === (int) (current_user()['id'] ?? 0);
?>
<div class="adm-card mb-6 flex flex-wrap items-center gap-4 border-l-4 border-amber-500 p-4" data-review-pending>
  <span class="text-amber-600"><?= icon('hourglass', 'h-6 w-6') ?></span>
  <div class="min-w-0 flex-1 text-sm">
    <p class="font-semibold text-navy"><?= $mine ? 'Your changes are waiting for approval' : 'Changes waiting for approval' ?></p>
    <p class="text-charcoal/70"><?= $mine ? 'You are editing the version you sent on ' . e(format_date($pending['created_at'], 'j M Y H:i')) . '. Saving again updates that request. The live version is unchanged until an administrator approves it.' : e((string) $pending['user_name']) . ' sent changes on ' . e(format_date($pending['created_at'], 'j M Y H:i')) . '.' ?></p>
  </div>
  <?php if (can('settings')): ?><a class="adm-btn adm-btn-primary adm-btn-sm" href="<?= e(url('admin/reviews/' . $pending['id'])) ?>"><?= icon('git-compare', 'h-4 w-4') ?> Review changes</a><?php endif ?>
</div>
