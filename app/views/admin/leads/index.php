<?php /** @var array $rows @var array $pager @var array $counts @var array $users */
$status = (string) query('status', '');
$tabs = ['' => ['All', array_sum($counts) - (int) ($counts['spam'] ?? 0)], 'open' => ['Open', array_sum(array_intersect_key($counts, array_flip(LEAD_OPEN)))]];
foreach (LEAD_STATUSES as $k => [$l]) $tabs[$k] = [$l, (int) ($counts[$k] ?? 0)];
?>
<div class="adm-tabs mb-4">
  <?php foreach ($tabs as $k => [$l, $n]): ?>
  <a href="<?= e(url('admin/leads') . qs(['status' => $k ?: null, 'page' => null])) ?>" class="adm-tab<?= $status === (string) $k ? ' is-active' : '' ?>"><?= e($l) ?> <span><?= (int) $n ?></span></a>
  <?php endforeach ?>
</div>

<form method="get" class="adm-card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-7">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif ?>
  <div class="lg:col-span-2"><label class="sr-only" for="lq">Search</label><input id="lq" class="adm-input" type="search" name="q" value="<?= e(query('q', '')) ?>" placeholder="Search name, company, email, product…"></div>
  <select class="adm-input" name="type" aria-label="Type"><option value="">All types</option><?php foreach (['contact' => 'Contact form', 'quote' => 'Quote request', 'service' => 'Service / breakdown', 'part' => 'Part request', 'distributor' => 'Distributor application', 'download' => 'Download', 'manual' => 'Manual'] as $k => $l): ?><option value="<?= $k ?>"<?= query('type') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach ?></select>
  <select class="adm-input" name="assigned" aria-label="Owner"><option value="">Any owner</option><option value="me"<?= query('assigned') === 'me' ? ' selected' : '' ?>>Assigned to me</option><option value="none"<?= query('assigned') === 'none' ? ' selected' : '' ?>>Unassigned</option><?php foreach ($users as $id => $n): ?><option value="<?= (int) $id ?>"<?= query('assigned') === (string) $id ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach ?></select>
  <input class="adm-input" type="date" name="from" value="<?= e(query('from', '')) ?>" aria-label="From date">
  <input class="adm-input" type="date" name="to" value="<?= e(query('to', '')) ?>" aria-label="To date">
  <div class="flex gap-2">
    <select class="adm-input" name="sort" aria-label="Sort"><?php foreach (['newest' => 'Newest', 'oldest' => 'Oldest', 'followup' => 'Follow-up', 'value' => 'Value', 'name' => 'Name'] as $k => $l): ?><option value="<?= $k ?>"<?= query('sort', 'newest') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach ?></select>
    <button class="adm-btn adm-btn-primary" aria-label="Apply filters"><?= icon('filter', 'h-4 w-4') ?></button>
  </div>
</form>

<form method="post" action="<?= e(url('admin/leads/bulk')) ?>" class="adm-card" data-bulk-form>
  <?= csrf_field() ?>
  <div class="flex flex-wrap items-center gap-3 border-b border-black/10 p-3">
    <span class="text-sm text-charcoal/60" data-bulk-count>0 selected</span>
    <select class="adm-input !w-auto" name="bulk_action" aria-label="Bulk action">
      <option value="">Bulk action…</option>
      <optgroup label="Set status"><?php foreach (LEAD_STATUSES as $k => [$l]): ?><option value="status:<?= $k ?>"><?= e($l) ?></option><?php endforeach ?></optgroup>
      <optgroup label="Assign to"><option value="assign:none">Unassigned</option><?php foreach ($users as $id => $n): ?><option value="assign:<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach ?></optgroup>
      <?php if (current_user()['role'] === 'admin'): ?><option value="delete">Delete permanently</option><?php endif ?>
    </select>
    <button class="adm-btn adm-btn-light adm-btn-sm" data-confirm="Apply this action to the selected leads?">Apply</button>
    <span class="ml-auto text-sm text-charcoal/55"><?= (int) $pager['total'] ?> lead<?= $pager['total'] === 1 ? '' : 's' ?></span>
  </div>
  <?php if ($rows): ?>
  <div class="overflow-x-auto">
    <table class="adm-table">
      <thead><tr><th class="w-10"><input type="checkbox" data-check-all aria-label="Select all"></th><th>Contact</th><th>Requirement</th><th>Status</th><th>Owner</th><th>Follow-up</th><th>Value</th><th>Received</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $l): $overdue = $l['follow_up_at'] && $l['follow_up_at'] < date('Y-m-d') && in_array($l['status'], LEAD_OPEN, true); ?>
        <tr class="<?= $l['status'] === 'new' ? 'is-new' : '' ?>">
          <td><input type="checkbox" name="ids[]" value="<?= (int) $l['id'] ?>" data-check aria-label="Select lead <?= (int) $l['id'] ?>"></td>
          <td><a class="font-semibold text-navy hover:text-steel" href="<?= e(url('admin/leads/' . $l['id'])) ?>"><?= e($l['name']) ?></a>
            <?php if ($l['priority'] === 'high'): ?><span class="ml-1 text-xs font-bold text-red-600">● High</span><?php endif ?>
            <div class="text-xs text-charcoal/55"><?= e($l['company'] ?: $l['email']) ?></div></td>
          <td class="max-w-xs"><div class="truncate text-sm"><?= e($l['product']) ?></div><div class="flex items-center gap-2 text-xs text-charcoal/50"><?php if ($l['type'] === 'service'): ?><span class="adm-badge <?= $l['priority'] === 'high' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800' ?>"><?= e(service_ref($l)) ?> · <?= e(SERVICE_STATUSES[$l['service_status'] ?: 'received'][0]) ?></span><?php else: ?><span class="capitalize"><?= e(LEAD_FORMS[$l['type']] ?? $l['type']) ?></span><?php endif ?><?php if ($l['files']): ?><span class="flex items-center gap-1"><?= icon('paperclip', 'h-3 w-3') ?><?= (int) $l['files'] ?></span><?php endif ?><?php if ($l['tags']): ?><span class="truncate">#<?= e(str_replace(', ', ' #', $l['tags'])) ?></span><?php endif ?></div></td>
          <td><?= lead_status_badge($l['status']) ?></td>
          <td class="text-sm"><?= e($l['assignee'] ?: '—') ?></td>
          <td class="whitespace-nowrap text-sm <?= $overdue ? 'font-semibold text-red-600' : 'text-charcoal/70' ?>"><?= $l['follow_up_at'] ? e(format_date($l['follow_up_at'], 'j M')) : '—' ?></td>
          <td class="text-sm"><?= $l['value'] !== null ? e(number_format((float) $l['value'], 0)) : '—' ?></td>
          <td class="whitespace-nowrap text-sm text-charcoal/60" title="<?= e($l['created_at']) ?>"><?= e(time_ago($l['created_at'])) ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="p-12 text-center"><span class="mx-auto block w-fit text-charcoal/30"><?= icon('inbox', 'h-10 w-10') ?></span><p class="mt-3 font-semibold text-navy">No leads found</p><p class="mt-1 text-sm text-charcoal/55">Try changing the filters, or wait for new enquiries from the website forms.</p></div>
  <?php endif ?>
</form>
<?= view('admin/_pager', ['pager' => $pager]) ?>
