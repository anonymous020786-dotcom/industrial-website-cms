<?php /** @var array $lead @var array $files @var array $notes @var array $others @var array $nav @var array $users */
$phoneDigits = preg_replace('~\D~', '', (string) $lead['phone']);
$noteIcons = ['note' => 'sticky-note', 'call' => 'phone-call', 'email' => 'mail', 'status' => 'git-commit-horizontal', 'system' => 'bot'];
$row = fn($label, $val) => ($val === null || $val === '') ? '' : '<div class="py-2.5 sm:grid sm:grid-cols-3 sm:gap-4"><dt class="text-sm text-charcoal/55">' . e($label) . '</dt><dd class="mt-1 text-sm text-navy sm:col-span-2 sm:mt-0">' . $val . '</dd></div>';
?>
<div class="mb-4 flex flex-wrap items-center gap-2">
  <?= lead_status_badge($lead['status']) ?>
  <span class="adm-badge bg-mist text-charcoal/70 capitalize"><?= e($lead['type']) ?></span>
  <?php if ($lead['priority'] === 'high'): ?><span class="adm-badge bg-red-100 text-red-700">High priority</span><?php endif ?>
  <span class="text-sm text-charcoal/55">Received <?= e(format_date($lead['created_at'], 'j M Y, H:i')) ?> (<?= e(time_ago($lead['created_at'])) ?>)</span>
  <span class="ml-auto flex gap-2">
    <?php if ($nav['prev']): ?><a class="adm-btn adm-btn-light adm-btn-sm" href="<?= e(url('admin/leads/' . $nav['prev'])) ?>" title="Newer lead"><?= icon('chevron-left', 'h-4 w-4') ?></a><?php endif ?>
    <?php if ($nav['next']): ?><a class="adm-btn adm-btn-light adm-btn-sm" href="<?= e(url('admin/leads/' . $nav['next'])) ?>" title="Older lead"><?= icon('chevron-right', 'h-4 w-4') ?></a><?php endif ?>
  </span>
</div>

<div class="grid gap-6 xl:grid-cols-3">
  <div class="space-y-6 xl:col-span-2">
    <div class="adm-card p-6">
      <div class="flex flex-wrap gap-2">
        <?php if ($lead['email']): ?><a class="adm-btn adm-btn-primary adm-btn-sm" href="mailto:<?= e($lead['email']) ?>?subject=<?= rawurlencode('Re: your enquiry — ' . ($lead['product'] ?: company('name'))) ?>"><?= icon('mail', 'h-4 w-4') ?> Email</a><?php endif ?>
        <?php if ($lead['phone']): ?><a class="adm-btn adm-btn-light adm-btn-sm" href="tel:<?= e($phoneDigits) ?>"><?= icon('phone', 'h-4 w-4') ?> Call</a>
        <a class="adm-btn adm-btn-light adm-btn-sm" target="_blank" rel="noopener" href="https://wa.me/<?= e($phoneDigits) ?>?text=<?= rawurlencode('Hello ' . $lead['name'] . ', this is ' . company('name') . ' regarding your enquiry about ' . ($lead['product'] ?: 'our products') . '.') ?>"><?= icon('message-circle', 'h-4 w-4') ?> WhatsApp</a><?php endif ?>
      </div>
      <dl class="mt-4 divide-y divide-black/5">
        <?= $row('Name', e($lead['name'])) ?>
        <?= $row('Company', e($lead['company'])) ?>
        <?= $row('Email', $lead['email'] ? '<a class="text-steel hover:underline" href="mailto:' . e($lead['email']) . '">' . e($lead['email']) . '</a>' : '') ?>
        <?= $row('Phone', e($lead['phone'])) ?>
        <?= $row('Product / service', e($lead['product'])) ?>
        <?= $row('Quantity', $lead['quantity'] !== null ? (string) (int) $lead['quantity'] : '') ?>
        <?= $row('Required by', $lead['required_by'] ? e(format_date($lead['required_by'], 'j M Y')) : '') ?>
        <?= $row('Part / drawing no.', e($lead['part_number'])) ?>
      </dl>
      <?php if ($lead['message']): ?>
      <p class="mt-4 text-sm text-charcoal/55">Message</p>
      <div class="mt-2 whitespace-pre-wrap bg-mist p-4 text-sm leading-relaxed text-charcoal"><?= e($lead['message']) ?></div>
      <?php endif ?>
      <?php if ($files): ?>
      <p class="mt-5 text-sm text-charcoal/55">Attachments</p>
      <ul class="mt-2 grid gap-2 sm:grid-cols-2">
        <?php foreach ($files as $f): ?>
        <li><a class="flex items-center gap-3 border border-black/10 p-3 text-sm hover:border-steel" href="<?= e(url('admin/leads/file/' . $f['id'])) ?>"><?= icon('file-down', 'h-5 w-5 text-steel') ?><span class="min-w-0 flex-1 truncate font-medium text-navy"><?= e($f['original_name']) ?></span><span class="text-xs text-charcoal/50"><?= round($f['size'] / 1024) ?> KB</span></a></li>
        <?php endforeach ?>
      </ul>
      <?php endif ?>
    </div>

    <div class="adm-card p-6" id="timeline">
      <h2 class="adm-card-title">Activity timeline</h2>
      <form method="post" action="<?= e(url('admin/leads/' . $lead['id'] . '/note')) ?>" class="mt-4">
        <?= csrf_field() ?>
        <div class="adm-segment mb-2" role="radiogroup" aria-label="Activity type">
          <?php foreach (['note' => 'Note', 'call' => 'Call logged', 'email' => 'Email sent'] as $k => $l): ?><label><input type="radio" name="kind" value="<?= $k ?>"<?= $k === 'note' ? ' checked' : '' ?>><span><?= $l ?></span></label><?php endforeach ?>
        </div>
        <textarea name="body" rows="3" class="adm-input" placeholder="Add a note, call summary or email summary…" required></textarea>
        <div class="mt-2 flex justify-end"><button class="adm-btn adm-btn-primary adm-btn-sm"><?= icon('plus', 'h-4 w-4') ?> Add to timeline</button></div>
      </form>
      <ol class="adm-timeline mt-6">
        <?php foreach ($notes as $n): ?>
        <li class="adm-timeline-item adm-tl-<?= e($n['kind']) ?>">
          <span class="adm-timeline-dot"><?= icon($noteIcons[$n['kind']] ?? 'circle', 'h-3.5 w-3.5') ?></span>
          <div class="min-w-0 flex-1">
            <p class="text-xs text-charcoal/55"><strong class="text-navy"><?= e($n['user_name'] ?: 'System') ?></strong> · <?= e(ucfirst($n['kind'])) ?> · <span title="<?= e($n['created_at']) ?>"><?= e(time_ago($n['created_at'])) ?></span></p>
            <p class="mt-1 whitespace-pre-wrap text-sm text-charcoal"><?= e($n['body']) ?></p>
          </div>
        </li>
        <?php endforeach ?>
      </ol>
    </div>
  </div>

  <div class="space-y-6">
    <form method="post" action="<?= e(url('admin/leads/' . $lead['id'])) ?>" class="adm-card space-y-4 p-6">
      <?= csrf_field() ?>
      <h2 class="adm-card-title">Pipeline</h2>
      <div class="adm-field"><label class="adm-label" for="status">Status</label><select id="status" name="status" class="adm-input"><?php foreach (LEAD_STATUSES as $k => [$l]): ?><option value="<?= $k ?>"<?= $lead['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></div>
      <div class="grid grid-cols-2 gap-3">
        <div class="adm-field"><label class="adm-label" for="priority">Priority</label><select id="priority" name="priority" class="adm-input"><?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'] as $k => $l): ?><option value="<?= $k ?>"<?= $lead['priority'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
        <div class="adm-field"><label class="adm-label" for="value">Est. value</label><input id="value" name="value" type="number" step="0.01" min="0" class="adm-input" value="<?= e($lead['value']) ?>"></div>
      </div>
      <div class="adm-field"><label class="adm-label" for="assigned_to">Owner</label><select id="assigned_to" name="assigned_to" class="adm-input"><option value="">Unassigned</option><?php foreach ($users as $id => $n): ?><option value="<?= (int) $id ?>"<?= (int) $lead['assigned_to'] === (int) $id ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach ?></select></div>
      <div class="adm-field"><label class="adm-label" for="follow_up_at">Follow-up date</label><input id="follow_up_at" name="follow_up_at" type="date" class="adm-input" value="<?= e($lead['follow_up_at']) ?>">
        <div class="mt-2 flex flex-wrap gap-1"><?php foreach (['Tomorrow' => '+1 day', 'In 3 days' => '+3 days', 'Next week' => '+7 days'] as $l => $off): ?><button type="button" class="adm-chip" data-set-date="#follow_up_at" data-date="<?= date('Y-m-d', strtotime($off)) ?>"><?= $l ?></button><?php endforeach ?></div></div>
      <div class="adm-field"><label class="adm-label" for="tags">Tags</label><input id="tags" name="tags" class="adm-input" value="<?= e($lead['tags']) ?>" placeholder="e.g. urgent, oem, export"></div>
      <details class="adm-details"><summary>Edit contact details</summary>
        <div class="mt-3 space-y-3">
          <?php foreach (['name' => 'Name', 'company' => 'Company', 'email' => 'Email', 'phone' => 'Phone', 'product' => 'Product / service'] as $k => $l): ?>
          <div class="adm-field"><label class="adm-label" for="c_<?= $k ?>"><?= $l ?></label><input id="c_<?= $k ?>" name="<?= $k ?>" class="adm-input" value="<?= e($lead[$k]) ?>"></div>
          <?php endforeach ?>
        </div>
      </details>
      <button class="adm-btn adm-btn-primary w-full justify-center"><?= icon('save', 'h-4 w-4') ?> Save changes</button>
    </form>

    <div class="adm-card p-6">
      <h2 class="adm-card-title">Source &amp; tracking</h2>
      <dl class="mt-3 space-y-2 text-sm">
        <?php foreach (['Source page' => $lead['source_page'], 'Referrer' => $lead['referrer'], 'UTM source' => $lead['utm_source'], 'UTM medium' => $lead['utm_medium'], 'UTM campaign' => $lead['utm_campaign'], 'IP address' => $lead['ip'], 'Consent given' => $lead['consent'] ? 'Yes — privacy policy accepted' : 'No (manual entry)'] as $k => $val): if (!$val) continue; ?>
        <div><dt class="text-xs text-charcoal/50"><?= e($k) ?></dt><dd class="break-all text-navy"><?= e($val) ?></dd></div>
        <?php endforeach ?>
      </dl>
    </div>

    <?php if ($others): ?>
    <div class="adm-card p-6">
      <h2 class="adm-card-title">Other enquiries from this contact</h2>
      <ul class="mt-3 space-y-2 text-sm"><?php foreach ($others as $o): ?><li class="flex items-center justify-between gap-2"><a class="truncate text-steel hover:underline" href="<?= e(url('admin/leads/' . $o['id'])) ?>">#<?= (int) $o['id'] ?> <?= e($o['product']) ?></a><?= lead_status_badge($o['status']) ?></li><?php endforeach ?></ul>
    </div>
    <?php endif ?>

    <?php if (current_user()['role'] === 'admin'): ?>
    <form method="post" action="<?= e(url('admin/leads/' . $lead['id'] . '/delete')) ?>" class="text-right"><?= csrf_field() ?><button class="adm-btn adm-btn-danger adm-btn-sm" data-confirm="Permanently delete this lead and its attachments? This cannot be undone."><?= icon('trash-2', 'h-4 w-4') ?> Delete lead</button></form>
    <?php endif ?>
  </div>
</div>
