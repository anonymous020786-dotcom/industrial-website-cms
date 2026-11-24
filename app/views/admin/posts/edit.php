<?php /** @var ?array $post @var array $schema @var array $values @var array $errors @var array $revisions */
$pick = fn(array $keys) => array_intersect_key($schema, array_flip($keys));
$previewUrl = abs_url('blog/' . ($values['slug'] ?? 'your-post'));
?>
<?php if ($post && $post['deleted_at']): ?><?= view('admin/_trash_banner', ['type' => 'posts', 'item' => $post]) ?><?php endif ?>
<form method="post" class="grid gap-6 xl:grid-cols-3" data-seo-form data-seo-url="<?= e($previewUrl) ?>" data-site-name="<?= e(company('name')) ?>">
  <?= csrf_field() ?>
  <div class="space-y-6 xl:col-span-2">
    <div class="adm-card grid gap-4 p-6 sm:grid-cols-2"><?= render_fields($pick(['title', 'slug']), $values, $errors) ?></div>
    <div class="adm-card p-6"><?= render_fields($pick(['content']), $values, $errors) ?><p class="adm-help mt-2">Use <strong>Heading 2</strong> for main sections — they build the article’s table of contents. Link to products with paths like <code>/products/hydraulic-cylinders</code>.</p></div>
    <div class="adm-card grid gap-4 p-6"><?= render_fields($pick(['excerpt']), $values, $errors) ?></div>

    <div class="adm-card p-6">
      <h2 class="adm-card-title"><?= icon('search', 'h-5 w-5 text-steel') ?> SEO</h2>
      <div class="adm-serp mt-4" aria-label="Google search preview">
        <p class="adm-serp-url" data-serp-url></p>
        <p class="adm-serp-title" data-serp-title></p>
        <p class="adm-serp-desc" data-serp-desc></p>
      </div>
      <div class="mt-5 grid gap-4 sm:grid-cols-2"><?= render_fields($pick(['focus_keyword', 'meta_title', 'meta_description', 'canonical_url', 'noindex']), $values, $errors) ?></div>
      <ul class="adm-seo-checks mt-5" data-seo-checks></ul>
    </div>
  </div>

  <aside class="adm-aside space-y-6">
    <div class="adm-card space-y-4 p-6">
      <h2 class="adm-card-title">Publish</h2>
      <?= render_fields($pick(['status', 'published_at']), $values, $errors) ?>
      <div class="flex gap-2 pt-2">
        <button class="adm-btn adm-btn-primary flex-1 justify-center"><?= icon('save', 'h-4 w-4') ?> Save</button>
      </div>
      <?php if ($post): ?>
      <p class="text-xs text-charcoal/50">Last saved <?= e(time_ago($post['updated_at'])) ?> · <?= (int) $post['views'] ?> views</p>
      <?php endif ?>
    </div>
    <div class="adm-card space-y-4 p-6"><h2 class="adm-card-title">Organise</h2><?= render_fields($pick(['category_id', 'tags', 'author_id']), $values, $errors) ?></div>
    <div class="adm-card space-y-4 p-6"><h2 class="adm-card-title">Featured image</h2><?= render_fields($pick(['featured_image', 'featured_alt']), $values, $errors) ?></div>
    <?php if ($post): ?><?= view('admin/_content_tools', ['type' => 'posts', 'item' => $post, 'revisions' => $revisions ?? [], 'canTrash' => !$post['deleted_at'], 'canDuplicate' => true]) ?><?php endif ?>
  </aside>
</form>

