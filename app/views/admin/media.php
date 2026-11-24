<?php /** @var array $rows @var string $q */ ?>
<form method="get" class="mb-4 flex gap-2"><input class="adm-input max-w-sm" type="search" name="q" value="<?= e($q) ?>" placeholder="Search file name or alt text…" aria-label="Search media"><button class="adm-btn adm-btn-light"><?= icon('search', 'h-4 w-4') ?></button></form>
<div class="adm-dropzone mb-6" data-library-drop><?= icon('upload-cloud', 'h-8 w-8') ?><p class="mt-2 font-semibold text-navy">Drop images here to upload</p><p class="text-xs text-charcoal/55">JPG, PNG, WebP or GIF · max 8 MB each</p><p class="mt-2 text-sm text-steel" data-upload-status></p></div>
<?php if ($rows): ?>
<div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5 2xl:grid-cols-6">
  <?php foreach ($rows as $m): $u = media_url($m['path']); ?>
  <div class="adm-card overflow-hidden">
    <a href="<?= e($u) ?>" target="_blank" class="block aspect-square bg-mist"><img src="<?= e($u) ?>" alt="<?= e($m['alt']) ?>" loading="lazy" class="h-full w-full object-cover"></a>
    <div class="space-y-2 p-3">
      <p class="truncate text-xs font-semibold text-navy" title="<?= e($m['original_name']) ?>"><?= e($m['original_name']) ?></p>
      <p class="text-[11px] text-charcoal/50"><?= (int) $m['width'] ?>×<?= (int) $m['height'] ?> · <?= round($m['size'] / 1024) ?> KB</p>
      <form method="post" action="<?= e(url('admin/media/' . $m['id'] . '/update')) ?>" class="flex gap-1"><?= csrf_field() ?><input name="alt" class="adm-input !py-1 text-xs" value="<?= e($m['alt']) ?>" placeholder="Alt text" aria-label="Alt text"><button class="adm-icon-btn" title="Save alt text"><?= icon('check', 'h-4 w-4') ?></button></form>
      <div class="flex justify-between">
        <button type="button" class="adm-link text-xs" data-copy="<?= e($m['path']) ?>">Copy path</button>
        <form method="post" action="<?= e(url('admin/media/' . $m['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="text-xs font-semibold text-red-600 hover:underline" data-confirm="Delete this image? Pages using it will show a placeholder.">Delete</button></form>
      </div>
    </div>
  </div>
  <?php endforeach ?>
</div>
<?php else: ?><p class="py-10 text-center text-sm text-charcoal/55">No images yet. Upload your company photos, product shots and logos here.</p><?php endif ?>
