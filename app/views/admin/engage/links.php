<?php /** @var array $rows @var array $pager @var array $byNet @var array $byPage @var int $total */
$nets = share_networks() + ['copy' => ['Copy link', 'link', ''], 'native' => ['Phone share sheet', 'share-2', '']];
?>
<div class="grid gap-6 xl:grid-cols-3">
  <div class="space-y-6 xl:col-span-2">
    <form method="post" action="<?= e(url('admin/links/save')) ?>" class="adm-card grid gap-4 p-6 sm:grid-cols-6">
      <?= csrf_field() ?>
      <h2 class="adm-card-title sm:col-span-6"><?= icon('link-2', 'h-5 w-5 text-steel') ?> New short link</h2>
      <div class="sm:col-span-6"><label class="adm-label" for="sl-target">Destination</label>
        <input id="sl-target" name="target" class="adm-input" required placeholder="/products/hydraulic-cylinders or https://…" maxlength="1000"></div>
      <div class="sm:col-span-2"><label class="adm-label" for="sl-code">Short code (optional)</label>
        <div class="flex items-center gap-1"><span class="text-sm text-charcoal/55">/s/</span><input id="sl-code" name="code" class="adm-input" pattern="[A-Za-z0-9][A-Za-z0-9_\-]{1,31}" placeholder="expo2026"></div></div>
      <div class="sm:col-span-3"><label class="adm-label" for="sl-title">Label</label><input id="sl-title" name="title" class="adm-input" placeholder="e.g. Trade fair flyer QR code"></div>
      <div class="flex items-end sm:col-span-1"><button class="adm-btn adm-btn-primary w-full justify-center"><?= icon('plus', 'h-4 w-4') ?> Create</button></div>
      <p class="adm-help sm:col-span-6">Use short links on flyers, business cards, QR codes and in WhatsApp messages. Share buttons on the website create one per page automatically.</p>
    </form>

    <div class="adm-card">
      <form method="get" class="flex flex-wrap items-center gap-3 border-b border-black/10 p-4">
        <input class="adm-input !w-auto flex-1" type="search" name="q" value="<?= e(query('q', '')) ?>" placeholder="Search code, page or label…" aria-label="Search">
        <select class="adm-input !w-auto" name="kind" aria-label="Kind"><option value="">All links</option><option value="manual"<?= query('kind') === 'manual' ? ' selected' : '' ?>>Created by hand</option><option value="auto"<?= query('kind') === 'auto' ? ' selected' : '' ?>>From share buttons</option></select>
        <button class="adm-btn adm-btn-light adm-btn-sm"><?= icon('filter', 'h-4 w-4') ?></button>
        <span class="ml-auto text-sm text-charcoal/55"><?= $total ?> link<?= $total === 1 ? '' : 's' ?></span>
      </form>
      <?php if ($rows): ?>
      <div class="overflow-x-auto">
        <table class="adm-table">
          <thead><tr><th>Short link</th><th>Destination</th><th class="text-right">Clicks</th><th>Last click</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $l): $short = abs_url('s/' . $l['code']); ?>
            <tr data-short-link="<?= e($l['code']) ?>">
              <td class="whitespace-nowrap"><code class="font-semibold text-navy">/s/<?= e($l['code']) ?></code><?php if ($l['auto']): ?> <span class="adm-badge bg-gray-100 text-gray-700">Share</span><?php endif ?>
                <?php if ($l['title']): ?><div class="max-w-xs truncate text-xs text-charcoal/55"><?= e($l['title']) ?></div><?php endif ?></td>
              <td class="max-w-sm truncate text-sm"><a class="text-steel hover:underline" href="<?= e(str_starts_with($l['target'], '/') ? url(ltrim($l['target'], '/')) : $l['target']) ?>" target="_blank" rel="noopener"><?= e($l['target']) ?></a></td>
              <td class="text-right font-semibold"><?= (int) $l['clicks'] ?></td>
              <td class="whitespace-nowrap text-sm text-charcoal/60"><?= $l['last_click_at'] ? e(time_ago($l['last_click_at'])) : '—' ?></td>
              <td class="whitespace-nowrap text-right">
                <button type="button" class="adm-icon-btn" data-copy-text="<?= e($short) ?>" title="Copy <?= e($short) ?>"><?= icon('copy', 'h-4 w-4') ?></button>
                <form method="post" action="<?= e(url('admin/links/' . $l['id'] . '/delete')) ?>" class="inline"><?= csrf_field() ?><button class="adm-icon-btn text-red-600" data-confirm="Delete /s/<?= e($l['code']) ?>? Printed copies of this link will stop working." title="Delete"><?= icon('trash-2', 'h-4 w-4') ?></button></form>
              </td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <p class="p-10 text-center text-sm text-charcoal/60">No short links yet. They appear here when visitors use the share buttons, or create one above.</p>
      <?php endif ?>
    </div>
    <?= view('admin/_pager', ['pager' => $pager]) ?>
  </div>

  <aside class="space-y-6">
    <div class="adm-card p-6">
      <h2 class="adm-card-title"><?= icon('share-2', 'h-5 w-5 text-steel') ?> Shares, last 30 days</h2>
      <?php if ($byNet): $max = max($byNet); ?>
      <ul class="mt-4 space-y-3 text-sm">
        <?php foreach ($byNet as $net => $n): ?>
        <li><div class="flex items-center justify-between gap-3"><span class="flex items-center gap-2"><?= icon($nets[$net][1] ?? 'share-2', 'h-4 w-4 text-steel') ?><?= e($nets[$net][0] ?? ucfirst($net)) ?></span><strong><?= $n ?></strong></div>
          <div class="mt-1 h-1.5 bg-black/5"><div class="h-1.5 bg-steel" style="width:<?= round($n / $max * 100) ?>%"></div></div></li>
        <?php endforeach ?>
      </ul>
      <?php else: ?><p class="mt-3 text-sm text-charcoal/60">No shares recorded yet.</p><?php endif ?>
    </div>
    <?php if ($byPage): ?>
    <div class="adm-card p-6">
      <h2 class="adm-card-title"><?= icon('trending-up', 'h-5 w-5 text-steel') ?> Most shared pages</h2>
      <ol class="mt-4 space-y-2 text-sm"><?php foreach ($byPage as $p => $n): ?><li class="flex justify-between gap-3"><a class="truncate text-steel hover:underline" href="<?= e(url(ltrim($p, '/'))) ?>" target="_blank"><?= e($p) ?></a><strong><?= $n ?></strong></li><?php endforeach ?></ol>
    </div>
    <?php endif ?>
  </aside>
</div>
