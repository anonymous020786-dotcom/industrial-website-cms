<?php /** @var array $rows @var string $q @var string $type @var int $pending @var bool $gd */ ?>
<div class="mb-4 flex flex-wrap items-center gap-3">
  <div class="adm-tabs !mb-0">
    <?php foreach (['' => 'All', 'image' => 'Images', 'pdf' => 'PDF documents'] as $k => $l): ?><a class="adm-tab<?= $type === $k ? ' is-active' : '' ?>" href="<?= e(url('admin/media') . qs(['type' => $k ?: null])) ?>"><?= $l ?></a><?php endforeach ?>
  </div>
  <form method="get" class="ml-auto flex gap-2"><?php if ($type): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif ?><input class="adm-input max-w-xs" type="search" name="q" value="<?= e($q) ?>" placeholder="Search file name or alt text…" aria-label="Search media"><button class="adm-btn adm-btn-light" aria-label="Search"><?= icon('search', 'h-4 w-4') ?></button></form>
</div>

<?php if (!$gd): ?>
<div class="adm-flash adm-flash-info mb-4"><?= icon('info', 'h-5 w-5 shrink-0') ?><span>Automatic image optimisation is off because the PHP <strong>GD</strong> extension (with WebP) is not enabled on this server. Uploads still work; ask your host to enable GD for faster pages.</span></div>
<?php elseif ($pending > 0): ?>
<div class="adm-flash adm-flash-info mb-4 flex-wrap"><?= icon('zap', 'h-5 w-5 shrink-0') ?><span class="flex-1"><strong><?= $pending ?> image(s)</strong> were uploaded before optimisation and are served at full size.</span>
  <form method="post" action="<?= e(url('admin/media/optimize')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn-light adm-btn-sm"><?= icon('zap', 'h-4 w-4') ?> Optimise now</button></form></div>
<?php endif ?>

<div class="adm-dropzone mb-6" data-library-drop><?= icon('upload-cloud', 'h-8 w-8') ?><p class="mt-2 font-semibold text-navy">Drop images or PDFs here to upload</p><p class="text-xs text-charcoal/60">JPG, PNG, WebP or GIF up to 8 MB — resized, stripped of location data and converted to fast WebP sizes automatically · PDF up to 25 MB (datasheets, catalogues, certificates)</p><p class="mt-2 text-sm text-steel" data-upload-status></p></div>
<?php if ($rows): ?>
<div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5 2xl:grid-cols-6">
  <?php foreach ($rows as $m): $u = media_url($m['path']); $pdf = $m['mime'] === 'application/pdf'; $vars = json_decode((string) $m['variants'], true) ?: []; ?>
  <div class="adm-card overflow-hidden">
    <a href="<?= e($u) ?>" target="_blank" class="block aspect-square bg-mist">
      <?php if ($pdf): ?><span class="grid h-full place-items-center text-red-700"><span class="text-center"><?= icon('file-text', 'mx-auto h-12 w-12') ?><span class="mt-2 block text-xs font-bold">PDF</span></span></span>
      <?php else: ?><img src="<?= e($u) ?>" alt="<?= e($m['alt']) ?>" loading="lazy" class="h-full w-full object-cover"><?php endif ?>
    </a>
    <div class="space-y-2 p-3">
      <p class="truncate text-xs font-semibold text-navy" title="<?= e($m['original_name']) ?>"><?= e($m['original_name']) ?></p>
      <p class="text-[11px] text-charcoal/60"><?= $pdf ? 'PDF' : (int) $m['width'] . '×' . (int) $m['height'] ?> · <?= round($m['size'] / 1024) ?> KB<?php if ($vars): ?> · <span class="font-semibold text-emerald-700" title="WebP sizes: <?= e(implode(', ', $vars)) ?> px">optimised</span><?php endif ?></p>
      <?php if (!$pdf): ?>
      <form method="post" action="<?= e(url('admin/media/' . $m['id'] . '/update')) ?>" class="flex gap-1"><?= csrf_field() ?><input name="alt" class="adm-input !py-1 text-xs" value="<?= e($m['alt']) ?>" placeholder="Alt text" aria-label="Alt text"><button class="adm-icon-btn" title="Save alt text"><?= icon('check', 'h-4 w-4') ?></button></form>
      <?php endif ?>
      <div class="flex justify-between">
        <button type="button" class="adm-link text-xs" data-copy="<?= e($m['path']) ?>">Copy path</button>
        <form method="post" action="<?= e(url('admin/media/' . $m['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="text-xs font-semibold text-red-600 hover:underline" data-confirm="Delete this file? Pages using it will show a placeholder.">Delete</button></form>
      </div>
    </div>
  </div>
  <?php endforeach ?>
</div>
<?php else: ?><p class="py-10 text-center text-sm text-charcoal/60">Nothing here yet. Upload company photos, product shots, logos, datasheets and certificates.</p><?php endif ?>
