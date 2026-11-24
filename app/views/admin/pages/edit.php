<?php /** @var ?array $page @var array $base @var array $tdef @var array $values @var array $errors @var string $template @var array $revisions */
$pick = fn(array $keys) => array_intersect_key($base, array_flip($keys));
$dataErrors = [];
foreach ($errors as $k => $v) if (str_starts_with($k, 'data.')) $dataErrors[substr($k, 5)] = $v;
?>
<?php if (!$page): ?>
<div class="adm-flash adm-flash-info mb-4"><?= icon('info', 'h-5 w-5 shrink-0') ?><span>Choose <strong>Standard page</strong> for general content or <strong>Legal / policy page</strong> for policies with a numbered table of contents.</span></div>
<?php endif ?>
<?php if ($page && $page['deleted_at']): ?><?= view('admin/_trash_banner', ['type' => 'pages', 'item' => $page]) ?><?php endif ?>
<form method="post" class="grid gap-6 xl:grid-cols-3" data-seo-form data-seo-url="<?= e(abs_url($values['slug'] ?? '')) ?>" data-site-name="<?= e(company('name')) ?>">
  <?= csrf_field() ?>
  <div class="space-y-6 xl:col-span-2">
    <div class="adm-card grid gap-4 p-6 sm:grid-cols-2"><?= render_fields($pick(['title', 'slug']), $values, $errors) ?></div>

    <div class="adm-card p-6">
      <h2 class="adm-card-title">Page banner</h2>
      <div class="mt-4 grid gap-4 sm:grid-cols-2"><?= render_fields($pick(['hero_eyebrow', 'hero_title', 'hero_lead', 'hero_image']), $values, $errors) ?></div>
    </div>

    <?php if ($tdef['content']): ?>
    <div class="adm-card p-6">
      <h2 class="adm-card-title"><?= e($tdef['content_label'] ?? 'Content') ?></h2>
      <div class="mt-4"><textarea name="content" class="adm-input js-editor" data-editor="full" rows="14"><?= e($values['content'] ?? '') ?></textarea></div>
      <?php if (!empty($tdef['content_help'])): ?><p class="adm-help mt-2"><?= $tdef['content_help'] ?></p><?php endif ?>
    </div>
    <?php endif ?>

    <?php if ($tdef['fields']): ?>
    <div class="adm-card p-6">
      <h2 class="adm-card-title"><?= e($tdef['label']) ?> — sections</h2>
      <div class="mt-4 grid gap-4 sm:grid-cols-2"><?= render_fields($tdef['fields'], $values['data'] ?? [], $dataErrors, 'data') ?></div>
    </div>
    <?php endif ?>

    <div class="adm-card p-6">
      <h2 class="adm-card-title"><?= icon('search', 'h-5 w-5 text-steel') ?> SEO &amp; sharing</h2>
      <div class="adm-serp mt-4"><p class="adm-serp-url" data-serp-url></p><p class="adm-serp-title" data-serp-title></p><p class="adm-serp-desc" data-serp-desc></p></div>
      <div class="mt-5 grid gap-4 sm:grid-cols-2"><?= render_fields($pick(['meta_title', 'meta_description', 'og_image', 'noindex']), $values, $errors) ?></div>
    </div>
  </div>

  <aside class="adm-aside space-y-6">
    <div class="adm-card space-y-4 p-6">
      <h2 class="adm-card-title">Publish</h2>
      <?= render_fields($pick(['status', 'template', 'category_id']), $values, $errors) ?>
      <?php if (!$page): ?><label class="adm-check"><input type="checkbox" name="add_to_menu" value="1"><span>Add to the header menu</span></label><?php endif ?>
      <button class="adm-btn adm-btn-primary w-full justify-center"><?= icon('save', 'h-4 w-4') ?> Save page</button>
      <?php if ($page): ?><p class="text-xs text-charcoal/50">Last saved <?= e(time_ago($page['updated_at'])) ?></p><?php endif ?>
    </div>
    <?php if ($page): ?><?= view('admin/_content_tools', ['type' => 'pages', 'item' => $page, 'revisions' => $revisions ?? [], 'canTrash' => !$page['is_system'] && !$page['deleted_at'], 'canDuplicate' => in_array($page['template'], ['default', 'legal'], true)]) ?><?php endif ?>
  </aside>
</form>

