<?php
/** Admin: posts, pages, generic resources (products, industries, categories, users) and media. */
declare(strict_types=1);
require_once __DIR__ . '/admin.php';

/* ================================================================== */
/* SHARED: revisions, trash, duplicate (posts + pages)                 */
/* ================================================================== */

const REVISIONS_KEEP = 30;   // per item
const TRASH_DAYS = 30;       // trashed items are purged after this many days
const CONTENT_TYPES = ['posts' => 'post', 'pages' => 'page'];

function content_table(string $type): string
{
    if (!isset(CONTENT_TYPES[$type])) abort(404);
    return $type;
}

/** Store a snapshot of a row before it changes. Identical consecutive snapshots are skipped. */
function revision_save(string $entity, array $row): void
{
    $snap = $row;
    unset($snap['id'], $snap['created_at'], $snap['updated_at'], $snap['views'], $snap['deleted_at']);
    $json = json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $last = db_val('SELECT snapshot FROM revisions WHERE entity = ? AND entity_id = ? ORDER BY id DESC LIMIT 1', [$entity, (int) $row['id']]);
    if ($last === $json) return;
    db_insert('revisions', ['entity' => $entity, 'entity_id' => (int) $row['id'], 'user_id' => current_user()['id'] ?? null,
        'title' => mb_substr((string) ($row['title'] ?? ''), 0, 255), 'snapshot' => $json]);
    db_query('DELETE FROM revisions WHERE entity = ? AND entity_id = ? AND id NOT IN (SELECT id FROM (SELECT id FROM revisions WHERE entity = ? AND entity_id = ? ORDER BY id DESC LIMIT ' . REVISIONS_KEEP . ') keep_rows)',
        [$entity, (int) $row['id'], $entity, (int) $row['id']]);
}

/** Revisions with a list of fields that differ from the current row. */
function revisions_for(string $entity, array $current): array
{
    $rows = db_all('SELECT r.id, r.title, r.snapshot, r.created_at, u.name AS user_name FROM revisions r LEFT JOIN users u ON u.id = r.user_id
        WHERE r.entity = ? AND r.entity_id = ? ORDER BY r.id DESC LIMIT ' . REVISIONS_KEEP, [$entity, (int) $current['id']]);
    $labels = ['title' => 'title', 'content' => 'content', 'excerpt' => 'excerpt', 'data' => 'sections', 'meta_title' => 'SEO title', 'meta_description' => 'meta description',
        'featured_image' => 'image', 'hero_image' => 'banner', 'hero_title' => 'banner', 'hero_lead' => 'banner', 'slug' => 'URL', 'status' => 'status', 'category_id' => 'category', 'tags' => 'tags'];
    foreach ($rows as &$r) {
        $snap = json_decode($r['snapshot'], true) ?: [];
        $changed = [];
        foreach ($snap as $k => $v) {
            if (array_key_exists($k, $current) && (string) $current[$k] !== (string) $v && isset($labels[$k])) $changed[$labels[$k]] = true;
        }
        $r['changed'] = array_keys($changed);
        unset($r['snapshot']);
    }
    unset($r);
    return $rows;
}

function admin_revision_restore(string $type, string $id, string $rid): void
{
    require_login('content');
    csrf_check();
    $table = content_table($type);
    $entity = CONTENT_TYPES[$type];
    $row = db_one("SELECT * FROM `{$table}` WHERE id = ?", [(int) $id]);
    $rev = db_one('SELECT * FROM revisions WHERE id = ? AND entity = ? AND entity_id = ?', [(int) $rid, $entity, (int) $id]);
    if (!$row || !$rev) abort(404);
    $snap = json_decode($rev['snapshot'], true) ?: [];
    // Restore content only: keep the current publishing state, system-page URL/template and identity columns
    $skip = ['id', 'status', 'published_at', 'created_at', 'updated_at', 'views', 'deleted_at', 'is_system'];
    if ($entity === 'page' && $row['is_system']) array_push($skip, 'slug', 'template');
    $data = array_diff_key(array_intersect_key($snap, $row), array_flip($skip));
    if (isset($data['slug'])) $data['slug'] = unique_slug($table, (string) $data['slug'], (int) $id);
    revision_save($entity, $row);
    if ($data) db_update($table, $data, (int) $id);
    flash('success', 'Restored the version from ' . format_date($rev['created_at'], 'j M Y H:i') . '. The version you replaced is kept in the history.');
    redirect("admin/{$type}/{$id}/edit");
}

/** Move to Trash: hidden from the site immediately (status -> draft) and restorable for TRASH_DAYS days. */
function content_trash(string $type, array $ids): int
{
    if (!$ids) return 0;
    $in = implode(',', array_fill(0, count($ids), '?'));
    $extra = $type === 'pages' ? ' AND is_system = 0' : '';
    return db_query("UPDATE `{$type}` SET deleted_at = NOW(), status = 'draft' WHERE deleted_at IS NULL AND id IN ({$in}){$extra}", $ids)->rowCount();
}

function content_restore(string $type, array $ids): int
{
    if (!$ids) return 0;
    $in = implode(',', array_fill(0, count($ids), '?'));
    return db_query("UPDATE `{$type}` SET deleted_at = NULL WHERE deleted_at IS NOT NULL AND id IN ({$in})", $ids)->rowCount();
}

function content_destroy(string $type, array $ids): int
{
    if (!$ids) return 0;
    $in = implode(',', array_fill(0, count($ids), '?'));
    $ids = array_map('intval', array_column(db_all("SELECT id FROM `{$type}` WHERE deleted_at IS NOT NULL AND id IN ({$in})", $ids), 'id'));
    if (!$ids) return 0;
    $in = implode(',', array_fill(0, count($ids), '?'));
    $titles = array_column(db_all("SELECT title FROM `{$type}` WHERE id IN ({$in})", $ids), 'title');
    db_query("DELETE FROM revisions WHERE entity = '" . CONTENT_TYPES[$type] . "' AND entity_id IN ({$in})", $ids);
    $n = db_query("DELETE FROM `{$type}` WHERE id IN ({$in})", $ids)->rowCount();
    if ($n && current_user()) {
        notify_admins('content_deleted', $n . ' ' . ($type === 'posts' ? 'post' : 'page') . ($n === 1 ? '' : 's') . ' deleted permanently',
            actor_name() . ' permanently deleted: ' . implode(', ', array_slice($titles, 0, 8)) . (count($titles) > 8 ? '…' : '') . '.',
            ['severity' => 'info', 'category' => 'content', 'link' => 'admin/' . $type, 'include_actor' => true]);
    }
    return $n;
}

function content_purge_old_trash(string $type): void
{
    $old = array_map('intval', array_column(db_all("SELECT id FROM `{$type}` WHERE deleted_at IS NOT NULL AND deleted_at < NOW() - INTERVAL " . TRASH_DAYS . ' DAY'), 'id'));
    if ($old) content_destroy($type, $old);
}

function admin_content_restore(string $type, string $id): void
{
    require_login('content');
    csrf_check();
    content_restore(content_table($type), [(int) $id]);
    flash('success', ($type === 'posts' ? 'Post' : 'Page') . ' restored as a draft.');
    redirect("admin/{$type}/{$id}/edit");
}

function admin_content_destroy(string $type, string $id): void
{
    require_login('content');
    csrf_check();
    require_stepup('destroy');
    $n = content_destroy(content_table($type), [(int) $id]);
    flash($n ? 'success' : 'error', $n ? 'Permanently deleted.' : 'Only items in the Trash can be deleted permanently.');
    redirect("admin/{$type}?status=trash");
}

function admin_content_empty_trash(string $type): void
{
    require_login('content');
    csrf_check();
    require_stepup('destroy');
    $table = content_table($type);
    $ids = array_map('intval', array_column(db_all("SELECT id FROM `{$table}` WHERE deleted_at IS NOT NULL"), 'id'));
    $n = content_destroy($table, $ids);
    flash('success', "Trash emptied ({$n} item" . ($n === 1 ? '' : 's') . ' deleted permanently).');
    redirect("admin/{$type}?status=trash");
}

/** Copy a row as a new draft; returns the new id. */
function content_copy(string $type, array $row): int
{
    $copy = array_diff_key($row, array_flip(['id', 'created_at', 'updated_at', 'views', 'deleted_at']));
    $copy['title'] = mb_substr($row['title'] . ' (copy)', 0, $type === 'posts' ? 255 : 190);
    $copy['slug'] = unique_slug($type, $row['slug'] . '-copy');
    $copy['status'] = 'draft';
    if ($type === 'posts') $copy['published_at'] = null;
    if ($type === 'pages') $copy['is_system'] = 0;
    return db_insert($type, $copy);
}

function admin_content_duplicate(string $type, string $id): void
{
    require_login('content');
    csrf_check();
    $table = content_table($type);
    $row = db_one("SELECT * FROM `{$table}` WHERE id = ?", [(int) $id]);
    if (!$row) abort(404);
    if ($type === 'pages' && !in_array($row['template'], ['default', 'legal'], true)) {
        flash('error', 'System pages have one fixed layout each and cannot be duplicated. Create a new page instead.');
        redirect('admin/pages');
    }
    $newId = content_copy($table, $row);
    flash('success', 'Duplicated as a new draft. Review the title and URL before publishing.');
    redirect("admin/{$type}/{$newId}/edit");
}

/* ================================================================== */
/* POSTS                                                               */
/* ================================================================== */

function post_schema(): array
{
    $cats = fn() => array_column(post_categories(), 'name', 'id');
    $authors = fn() => array_column(db_all('SELECT id, name FROM users WHERE active = 1 ORDER BY name'), 'name', 'id');
    return [
        'title' => ['type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 255, 'attrs' => 'data-slug-source data-seo-title-source'],
        'slug' => ['type' => 'slug', 'label' => 'URL slug', 'help' => 'Leave blank to generate from the title.'],
        'content' => ['type' => 'html', 'label' => 'Content'],
        'excerpt' => ['type' => 'textarea', 'label' => 'Excerpt / intro', 'rows' => 3, 'help' => 'Shown on blog cards and at the top of the article.', 'counter' => 300],
        // sidebar
        'status' => ['type' => 'select', 'label' => 'Status', 'options' => ['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled']],
        'published_at' => ['type' => 'datetime', 'label' => 'Publish date', 'help' => 'For scheduled posts, the date it goes live.'],
        'category_id' => ['type' => 'select', 'label' => 'Category', 'options' => $cats, 'empty' => '— None —'],
        'tags' => ['type' => 'text', 'label' => 'Tags', 'placeholder' => 'comma, separated'],
        'author_id' => ['type' => 'select', 'label' => 'Author', 'options' => $authors, 'empty' => '— Company —'],
        'featured_image' => ['type' => 'image', 'label' => 'Featured image'],
        'featured_alt' => ['type' => 'text', 'label' => 'Featured image alt text'],
        // SEO
        'focus_keyword' => ['type' => 'text', 'label' => 'Focus keyword', 'attrs' => 'data-seo-keyword'],
        'meta_title' => ['type' => 'text', 'label' => 'SEO title', 'max' => 190, 'counter' => 60, 'attrs' => 'data-seo-title'],
        'meta_description' => ['type' => 'textarea', 'label' => 'Meta description', 'rows' => 3, 'counter' => 160, 'attrs' => 'data-seo-desc'],
        'canonical_url' => ['type' => 'url', 'label' => 'Canonical URL', 'help' => 'Only if this article was first published elsewhere.'],
        'noindex' => ['type' => 'checkbox', 'label' => 'Search engines', 'text' => 'Hide from search engines (noindex)'],
    ];
}

function admin_posts(): void
{
    require_login('content');
    if (random_int(1, 20) === 1) content_purge_old_trash('posts');
    $status = (string) query('status', '');
    $where = [$status === 'trash' ? 'p.deleted_at IS NOT NULL' : 'p.deleted_at IS NULL'];
    $params = [];
    if ($status === 'scheduled') $where[] = "p.status = 'scheduled' AND p.published_at > NOW()";
    elseif ($status === 'published') $where[] = POST_VISIBLE;
    elseif ($status === 'draft') $where[] = "p.status = 'draft'";
    if ($c = (int) query('category', 0)) { $where[] = 'p.category_id = ?'; $params[] = $c; }
    if ($q = (string) query('q', '')) { $where[] = '(p.title LIKE ? OR p.tags LIKE ?)'; $params[] = "%{$q}%"; $params[] = "%{$q}%"; }
    $w = implode(' AND ', $where);
    $total = (int) db_val("SELECT COUNT(*) FROM posts p WHERE {$w}", $params);
    $pager = paginate($total, 30, (int) query('page', 1));
    $sort = query('sort') === 'views' ? 'p.views DESC' : (query('sort') === 'title' ? 'p.title ASC' : 'COALESCE(p.published_at, p.updated_at) DESC');
    $rows = db_all("SELECT p.id, p.title, p.slug, p.status, p.published_at, p.updated_at, p.views, p.reading_minutes, p.meta_description, p.focus_keyword, p.deleted_at, c.name AS category
        FROM posts p LEFT JOIN categories c ON c.id = p.category_id WHERE {$w} ORDER BY {$sort} LIMIT {$pager['per']} OFFSET {$pager['offset']}", $params);
    $counts = [
        '' => (int) db_val('SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL'),
        'published' => (int) db_val('SELECT COUNT(*) FROM posts p WHERE p.deleted_at IS NULL AND ' . POST_VISIBLE),
        'scheduled' => (int) db_val("SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL AND status = 'scheduled' AND published_at > NOW()"),
        'draft' => (int) db_val("SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL AND status = 'draft'"),
        'trash' => (int) db_val('SELECT COUNT(*) FROM posts WHERE deleted_at IS NOT NULL'),
    ];
    $actions = '<a class="adm-btn adm-btn-primary" href="' . e(url('admin/posts/new')) . '">' . icon('plus', 'h-4 w-4') . ' New post</a>';
    admin_render('Blog Posts', 'posts/index', compact('rows', 'pager', 'counts') + ['cats' => post_categories()], ['actions' => $actions, 'wide' => true]);
}

function admin_post_edit(?string $id = null): void
{
    $user = require_login('content');
    $post = $id ? db_one('SELECT * FROM posts WHERE id = ?', [(int) $id]) : null;
    if ($id && !$post) abort(404);
    $schema = post_schema();
    $values = $post ?? ['status' => 'draft', 'author_id' => $user['id']];
    $errors = [];

    if (is_post()) {
        csrf_check();
        [$v, $errors] = parse_fields($schema, $_POST);
        $v['slug'] = unique_slug('posts', $v['slug'] ?: slugify($v['title'] ?? 'post'), $post['id'] ?? null);
        $v['category_id'] = $v['category_id'] !== null ? (int) $v['category_id'] : null;
        $v['author_id'] = $v['author_id'] !== null ? (int) $v['author_id'] : null;
        $v['noindex'] = (int) $v['noindex'];
        $v['status'] = $v['status'] ?: 'draft';
        if ($v['status'] === 'published' && !$v['published_at']) $v['published_at'] = now();
        if ($v['status'] === 'scheduled') {
            if (!$v['published_at']) $errors['published_at'] = 'Choose when the post should go live.';
            elseif (strtotime($v['published_at']) <= time()) $v['status'] = 'published';
        }
        $v['reading_minutes'] = reading_minutes((string) $v['content']);
        if (!$errors) {
            if ($post) {
                revision_save('post', $post);
                db_update('posts', $v, (int) $post['id']);
                $pid = (int) $post['id'];
                if ($v['slug'] !== $post['slug'] && $post['status'] !== 'draft') {
                    redirect_auto('/blog/' . $post['slug'], '/blog/' . $v['slug'], 'Automatic: post URL changed');
                    flash('info', 'The URL changed, so a 301 redirect from the old address was added automatically.');
                }
            }
            else $pid = db_insert('posts', $v);
            flash('success', 'Post saved' . ($v['status'] === 'published' ? ' and published.' : ($v['status'] === 'scheduled' ? ' — scheduled for ' . format_date($v['published_at'], 'j M Y H:i') . '.' : ' as draft.')));
            redirect('admin/posts/' . $pid . '/edit');
        }
        $values = array_merge($values, $v);
        flash('error', 'Please fix the errors below.');
    }

    $actions = $post ? '<a class="adm-btn adm-btn-light" target="_blank" href="' . e(url('blog/' . $post['slug']) . ($post['status'] === 'published' && strtotime((string) $post['published_at']) <= time() ? '' : '?preview=1')) . '">' . icon('eye', 'h-4 w-4') . ' View</a>' : '';
    $revisions = $post ? revisions_for('post', $post) : [];
    admin_render($post ? 'Edit post' : 'New post', 'posts/edit', ['post' => $post, 'schema' => $schema, 'values' => $values, 'errors' => $errors, 'revisions' => $revisions], ['actions' => $actions, 'crumbs' => [['Blog Posts', 'admin/posts']], 'wide' => true]);
}

function admin_post_delete(string $id): void
{
    require_login('content');
    csrf_check();
    content_trash('posts', [(int) $id]);
    flash('success', 'Post moved to the Trash and unpublished. You can restore it for ' . TRASH_DAYS . ' days.');
    redirect('admin/posts');
}

function admin_posts_bulk(): void
{
    require_login('content');
    csrf_check();
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
    $action = (string) ($_POST['bulk_action'] ?? '');
    if (!$ids) { flash('error', 'Select at least one post.'); back('admin/posts'); }
    $in = implode(',', array_fill(0, count($ids), '?'));
    switch ($action) {
        case 'publish':
            db_query("UPDATE posts SET status = 'published', published_at = COALESCE(LEAST(published_at, NOW()), NOW()) WHERE id IN ({$in})", $ids);
            flash('success', count($ids) . ' post(s) published.');
            break;
        case 'draft':
            db_query("UPDATE posts SET status = 'draft' WHERE id IN ({$in})", $ids);
            flash('success', count($ids) . ' post(s) moved to drafts.');
            break;
        case 'schedule':
            // Drip-publish: one post every N days starting on the chosen date, at the chosen time.
            $every = max(1, min(30, (int) ($_POST['every_days'] ?? 3)));
            $start = strtotime(($_POST['start_date'] ?? date('Y-m-d', strtotime('+1 day'))) . ' ' . ($_POST['start_time'] ?? '09:00'));
            if (!$start || $start < time()) $start = strtotime('tomorrow 09:00');
            $ordered = db_all("SELECT id FROM posts WHERE id IN ({$in}) ORDER BY id", $ids);
            foreach ($ordered as $i => $row) {
                db_query("UPDATE posts SET status = 'scheduled', published_at = ? WHERE id = ?", [date('Y-m-d H:i:s', $start + $i * $every * 86400), $row['id']]);
            }
            flash('success', count($ordered) . ' post(s) scheduled — one every ' . $every . ' day(s) from ' . date('j M Y', $start) . '.');
            break;
        case 'delete':
        case 'trash':
            flash('success', content_trash('posts', $ids) . ' post(s) moved to the Trash.');
            break;
        case 'restore':
            flash('success', content_restore('posts', $ids) . ' post(s) restored as drafts.');
            break;
        case 'destroy':
            require_stepup('destroy');
            flash('success', content_destroy('posts', $ids) . ' post(s) permanently deleted.');
            break;
        case 'duplicate':
            foreach (db_all("SELECT * FROM posts WHERE id IN ({$in})", $ids) as $row) content_copy('posts', $row);
            flash('success', count($ids) . ' post(s) duplicated as drafts.');
            break;
        default:
            if (str_starts_with($action, 'category:')) {
                db_query("UPDATE posts SET category_id = ? WHERE id IN ({$in})", array_merge([(int) substr($action, 9) ?: null], $ids));
                flash('success', 'Category updated.');
            } else flash('error', 'Choose a bulk action.');
    }
    back('admin/posts');
}

/* ================================================================== */
/* PAGES                                                               */
/* ================================================================== */

function page_base_schema(?array $page): array
{
    $system = $page && $page['is_system'];
    $templates = array_map(fn($t) => $t['label'], array_filter(page_templates(), fn($t, $k) => in_array($k, ['default', 'legal'], true), ARRAY_FILTER_USE_BOTH));
    $pageCats = fn() => array_column(db_all("SELECT id, name FROM categories WHERE type = 'page' ORDER BY name"), 'name', 'id');
    return [
        'title' => ['type' => 'text', 'label' => 'Page title', 'required' => true, 'attrs' => 'data-slug-source data-seo-title-source'],
        'slug' => $system ? ['type' => 'text', 'label' => 'URL', 'attrs' => 'readonly', 'help' => 'System page URLs are fixed.'] : ['type' => 'slug', 'label' => 'URL slug', 'help' => 'The page address, e.g. <code>/careers</code>.'],
        'template' => $system ? ['type' => 'text', 'label' => 'Template', 'attrs' => 'readonly'] : ['type' => 'select', 'label' => 'Template', 'options' => $templates],
        'status' => ['type' => 'select', 'label' => 'Status', 'options' => ['published' => 'Published', 'draft' => 'Draft']],
        'category_id' => ['type' => 'select', 'label' => 'Page category', 'options' => $pageCats, 'empty' => '— None —'],
        'hero_eyebrow' => ['type' => 'text', 'label' => 'Banner eyebrow'],
        'hero_title' => ['type' => 'text', 'label' => 'Banner heading', 'help' => 'Defaults to the page title.'],
        'hero_lead' => ['type' => 'textarea', 'label' => 'Banner intro text', 'rows' => 2],
        'hero_image' => ['type' => 'image', 'label' => 'Banner image'],
        'meta_title' => ['type' => 'text', 'label' => 'SEO title', 'counter' => 60, 'attrs' => 'data-seo-title'],
        'meta_description' => ['type' => 'textarea', 'label' => 'Meta description', 'rows' => 3, 'counter' => 160, 'attrs' => 'data-seo-desc'],
        'og_image' => ['type' => 'image', 'label' => 'Social share image', 'help' => 'Defaults to the banner image.'],
        'noindex' => ['type' => 'checkbox', 'label' => 'Search engines', 'text' => 'Hide from search engines (noindex)'],
    ];
}

function admin_pages(): void
{
    require_login('content');
    if (random_int(1, 20) === 1) content_purge_old_trash('pages');
    $status = (string) query('status', '');
    $where = [$status === 'trash' ? 'p.deleted_at IS NOT NULL' : 'p.deleted_at IS NULL'];
    $params = [];
    if (in_array($status, ['published', 'draft'], true)) { $where[] = 'p.status = ?'; $params[] = $status; }
    if ($status === 'system') $where[] = 'p.is_system = 1';
    if ($status === 'custom') $where[] = 'p.is_system = 0';
    if ($q = trim((string) query('q', ''))) { $where[] = '(p.title LIKE ? OR p.slug LIKE ? OR p.meta_title LIKE ?)'; array_push($params, "%{$q}%", "%{$q}%", "%{$q}%"); }
    $w = implode(' AND ', $where);
    $rows = db_all("SELECT p.id, p.slug, p.title, p.template, p.status, p.is_system, p.meta_description, p.noindex, p.updated_at, p.deleted_at, c.name AS category
        FROM pages p LEFT JOIN categories c ON c.id = p.category_id WHERE {$w}
        ORDER BY p.is_system DESC, FIELD(p.template,'home','about','products','industries','manufacturing','quality','blog','contact','quote','default','legal','sitemap'), p.title", $params);
    $counts = [];
    foreach (['' => 'deleted_at IS NULL', 'published' => "deleted_at IS NULL AND status = 'published'", 'draft' => "deleted_at IS NULL AND status = 'draft'",
        'system' => 'deleted_at IS NULL AND is_system = 1', 'custom' => 'deleted_at IS NULL AND is_system = 0', 'trash' => 'deleted_at IS NOT NULL'] as $k => $cond) {
        $counts[$k] = (int) db_val("SELECT COUNT(*) FROM pages WHERE {$cond}");
    }
    $actions = '<a class="adm-btn adm-btn-primary" href="' . e(url('admin/pages/new')) . '">' . icon('plus', 'h-4 w-4') . ' New page</a>';
    admin_render('Pages', 'pages/index', compact('rows', 'counts', 'status'), ['actions' => $actions, 'wide' => true]);
}

function admin_pages_bulk(): void
{
    require_login('content');
    csrf_check();
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
    $action = (string) ($_POST['bulk_action'] ?? '');
    if (!$ids) { flash('error', 'Select at least one page.'); back('admin/pages'); }
    $in = implode(',', array_fill(0, count($ids), '?'));
    switch ($action) {
        case 'publish':
            db_query("UPDATE pages SET status = 'published' WHERE deleted_at IS NULL AND id IN ({$in})", $ids);
            flash('success', count($ids) . ' page(s) published.');
            break;
        case 'draft':
            db_query("UPDATE pages SET status = 'draft' WHERE id IN ({$in})", $ids);
            flash('success', count($ids) . ' page(s) set to draft.');
            break;
        case 'trash':
            $n = content_trash('pages', $ids);
            flash($n ? 'success' : 'error', $n ? "{$n} page(s) moved to the Trash." : 'System pages cannot be trashed. Set them to Draft instead.');
            break;
        case 'restore':
            flash('success', content_restore('pages', $ids) . ' page(s) restored as drafts.');
            break;
        case 'destroy':
            require_stepup('destroy');
            flash('success', content_destroy('pages', $ids) . ' page(s) permanently deleted.');
            break;
        case 'index':
        case 'noindex':
            db_query('UPDATE pages SET noindex = ' . ($action === 'noindex' ? 1 : 0) . " WHERE id IN ({$in})", $ids);
            flash('success', 'Search engine visibility updated.');
            break;
        default:
            flash('error', 'Choose a bulk action.');
    }
    back('admin/pages');
}

function admin_page_edit(?string $id = null): void
{
    require_login('content');
    $page = $id ? db_one('SELECT * FROM pages WHERE id = ?', [(int) $id]) : null;
    if ($id && !$page) abort(404);
    $template = $page['template'] ?? (string) (is_post() ? input('template', 'default') : query('template', 'default'));
    if (!$page && !in_array($template, ['default', 'legal'], true)) $template = 'default';
    $tdef = template_def($template);
    $base = page_base_schema($page);
    $values = $page ? array_merge($page, ['data' => page_data($page)]) : ['template' => $template, 'status' => 'draft', 'data' => template_defaults($template)];
    $errors = [];

    if (is_post()) {
        csrf_check();
        [$v, $errors] = parse_fields($base, $_POST);
        [$data, $dataErrors] = parse_fields($tdef['fields'], (array) ($_POST['data'] ?? []));
        $errors += array_combine(array_map(fn($k) => "data.{$k}", array_keys($dataErrors)), $dataErrors) ?: [];
        if ($page && $page['is_system']) { $v['slug'] = $page['slug']; $v['template'] = $page['template']; }
        else {
            $v['slug'] = unique_slug('pages', $v['slug'] ?: slugify($v['title'] ?? 'page'), $page['id'] ?? null);
            $reserved = ['admin', 'install', 'lead', 'track', 'blog', 'products', 'projects', 'catalogue', 'download', 'cron', 'assets', 'uploads', 'sitemap.xml', 'robots.txt', 'feed.xml', 'home'];
            if (in_array($v['slug'], $reserved, true)) $errors['slug'] = 'This URL is reserved. Choose another.';
            $v['template'] = in_array($v['template'], ['default', 'legal'], true) ? $v['template'] : 'default';
        }
        $v['category_id'] = $v['category_id'] !== null ? (int) $v['category_id'] : null;
        $v['noindex'] = (int) $v['noindex'];
        if (template_def($v['template'])['content']) $v['content'] = sanitize_html((string) ($_POST['content'] ?? ''));
        $v['data'] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$errors) {
            if ($page) {
                revision_save('page', $page);
                db_update('pages', $v, (int) $page['id']);
                $pid = (int) $page['id'];
                if ($v['slug'] !== $page['slug'] && $page['status'] === 'published' && !$page['is_system']) {
                    redirect_auto('/' . $page['slug'], '/' . $v['slug'], 'Automatic: page URL changed');
                    flash('info', 'The URL changed, so a 301 redirect from the old address was added automatically.');
                }
            }
            else $pid = db_insert('pages', $v);
            if (!$page && !empty($_POST['add_to_menu'])) {
                $menu = header_menu();
                $menu[] = ['label' => $v['title'], 'url' => $v['slug'], 'mega' => false];
                setting_set('menu_header', $menu);
            }
            flash('success', 'Page saved.');
            redirect('admin/pages/' . $pid . '/edit');
        }
        $values = array_merge($values, $v, ['data' => $data]);
        flash('error', 'Please fix the errors below.');
    }

    $actions = $page ? '<a class="adm-btn adm-btn-light" target="_blank" href="' . e(page_url($page) . ($page['status'] === 'draft' ? '?preview=1' : '')) . '">' . icon('eye', 'h-4 w-4') . ' View page</a>' : '';
    $revisions = $page ? revisions_for('page', $page) : [];
    admin_render($page ? 'Edit page: ' . $page['title'] : 'New page', 'pages/edit', compact('page', 'base', 'tdef', 'values', 'errors', 'template', 'revisions'), ['actions' => $actions, 'crumbs' => [['Pages', 'admin/pages']], 'wide' => true]);
}

function admin_page_delete(string $id): void
{
    require_login('content');
    csrf_check();
    $page = db_one('SELECT * FROM pages WHERE id = ?', [(int) $id]);
    if (!$page) abort(404);
    if ($page['is_system']) { flash('error', 'System pages cannot be deleted. Set them to Draft instead.'); redirect('admin/pages'); }
    content_trash('pages', [(int) $id]);
    flash('success', 'Page moved to the Trash and unpublished. You can restore it for ' . TRASH_DAYS . ' days.');
    redirect('admin/pages');
}

/* ================================================================== */
/* GENERIC RESOURCES                                                   */
/* ================================================================== */

function resources(): array
{
    $prodCats = fn() => array_column(product_categories(), 'name', 'id');
    $prods = fn() => array_column(db_all('SELECT id, name FROM products ORDER BY sort_order, name'), 'name', 'id');
    return [
        'products' => [
            'title' => 'Products', 'singular' => 'Product', 'table' => 'products', 'perm' => 'content', 'order' => 'sort_order, name',
            'columns' => ['name' => 'Name', 'category_id' => 'Category', 'status' => 'Status', 'sort_order' => 'Order'],
            'view' => fn($r) => url('products/' . $r['slug']),
            'json' => ['features', 'applications', 'specs'],
            'fields' => [
                'name' => ['type' => 'text', 'label' => 'Product name', 'required' => true, 'attrs' => 'data-slug-source'],
                'slug' => ['type' => 'slug', 'label' => 'URL slug'],
                'category_id' => ['type' => 'select', 'label' => 'Category', 'options' => $prodCats, 'empty' => '— None —'],
                'status' => ['type' => 'select', 'label' => 'Status', 'options' => ['published' => 'Published', 'draft' => 'Draft']],
                'icon' => ['type' => 'icon', 'label' => 'Menu icon'],
                'sort_order' => ['type' => 'number', 'label' => 'Display order', 'default' => 0],
                'image' => ['type' => 'image', 'label' => 'Main image'],
                'image_alt' => ['type' => 'text', 'label' => 'Image alt text', 'wide' => true],
                'summary' => ['type' => 'textarea', 'label' => 'Short summary', 'rows' => 2, 'required' => true],
                'overview' => ['type' => 'html', 'label' => 'Overview', 'toolbar' => 'basic'],
                'features' => ['type' => 'lines', 'label' => 'Key features (one per line)'],
                'applications' => ['type' => 'lines', 'label' => 'Applications (one per line)', 'rows' => 4],
                'specs' => ['type' => 'list', 'label' => 'Specifications', 'item' => 'Spec', 'fields' => ['label' => ['type' => 'text', 'label' => 'Specification'], 'value' => ['type' => 'text', 'label' => 'Value']]],
                'datasheet' => ['type' => 'document', 'label' => 'Datasheet (PDF)', 'help' => 'Shown as a “Download datasheet” button on the product page. Downloads are counted in Reports.'],
                'meta_title' => ['type' => 'text', 'label' => 'SEO title', 'counter' => 60],
                'meta_description' => ['type' => 'textarea', 'label' => 'Meta description', 'rows' => 2, 'counter' => 160],
            ],
        ],
        'projects' => [
            'title' => 'Projects', 'singular' => 'Project', 'table' => 'projects', 'perm' => 'content', 'order' => 'sort_order, completed_at DESC, id DESC',
            'columns' => ['title' => 'Project', 'client' => 'Client', 'industry_id' => 'Industry', 'status' => 'Status', 'completed_at' => 'Completed'],
            'view' => fn($r) => url('projects/' . $r['slug']) . ($r['status'] === 'draft' ? '?preview=1' : ''),
            'json' => ['product_ids', 'results'],
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Project title', 'required' => true, 'attrs' => 'data-slug-source', 'help' => 'e.g. “Replacement sheave set for a 50 t port crane”'],
                'slug' => ['type' => 'slug', 'label' => 'URL slug'],
                'status' => ['type' => 'select', 'label' => 'Status', 'options' => ['draft' => 'Draft', 'published' => 'Published'], 'help' => 'Only publish projects the client has approved for publication.'],
                'sort_order' => ['type' => 'number', 'label' => 'Display order', 'default' => 0],
                'client' => ['type' => 'text', 'label' => 'Client', 'help' => 'Or a description such as “European port operator” if the name is confidential.'],
                'location' => ['type' => 'text', 'label' => 'Location'],
                'industry_id' => ['type' => 'select', 'label' => 'Industry', 'options' => fn() => array_column(db_all('SELECT id, name FROM industries ORDER BY sort_order, name'), 'name', 'id'), 'empty' => '— None —'],
                'completed_at' => ['type' => 'date', 'label' => 'Completed'],
                'image' => ['type' => 'image', 'label' => 'Main photo'],
                'image_alt' => ['type' => 'text', 'label' => 'Photo alt text', 'wide' => true],
                'summary' => ['type' => 'textarea', 'label' => 'Summary (cards and search results)', 'rows' => 2, 'required' => true, 'counter' => 220],
                'challenge' => ['type' => 'html', 'label' => 'The challenge', 'toolbar' => 'basic'],
                'solution' => ['type' => 'html', 'label' => 'Our solution', 'toolbar' => 'basic'],
                'results' => ['type' => 'list', 'label' => 'Results (measurable outcomes)', 'item' => 'Result', 'fields' => ['value' => ['type' => 'text', 'label' => 'Figure', 'placeholder' => 'e.g. 9 days'], 'label' => ['type' => 'text', 'label' => 'Meaning', 'placeholder' => 'e.g. from drawing to delivery']]],
                'product_ids' => ['type' => 'multiselect', 'label' => 'Products supplied', 'options' => $prods],
                'testimonial' => ['type' => 'textarea', 'label' => 'Client quote (optional, with permission)', 'rows' => 3],
                'testimonial_by' => ['type' => 'text', 'label' => 'Quote attributed to', 'placeholder' => 'Name, role, company'],
                'meta_title' => ['type' => 'text', 'label' => 'SEO title', 'counter' => 60],
                'meta_description' => ['type' => 'textarea', 'label' => 'Meta description', 'rows' => 2, 'counter' => 160],
            ],
        ],
        'testimonials' => [
            'title' => 'Testimonials', 'singular' => 'Testimonial', 'table' => 'testimonials', 'perm' => 'content', 'order' => 'sort_order, id DESC',
            'columns' => ['author' => 'Client', 'company' => 'Company', 'rating' => 'Rating', 'status' => 'Status', 'permission' => 'Permission'],
            'fields' => [
                'quote' => ['type' => 'textarea', 'label' => 'What the client said', 'rows' => 4, 'required' => true, 'counter' => 400, 'help' => 'Use the client’s own words. Short, specific quotes (a problem solved, a deadline met) are the most convincing.'],
                'author' => ['type' => 'text', 'label' => 'Name', 'required' => true],
                'role' => ['type' => 'text', 'label' => 'Job title', 'placeholder' => 'e.g. Maintenance Manager'],
                'company' => ['type' => 'text', 'label' => 'Company', 'help' => 'Or a description such as “Port operator, Rotterdam” if the client prefers.'],
                'rating' => ['type' => 'select', 'label' => 'Star rating (optional)', 'options' => ['5' => '★★★★★ 5', '4' => '★★★★ 4', '3' => '★★★ 3'], 'empty' => '— No stars —'],
                'photo' => ['type' => 'image', 'label' => 'Photo or company logo (optional)'],
                'project_id' => ['type' => 'select', 'label' => 'Related project (optional)', 'options' => fn() => array_column(db_all("SELECT id, title FROM projects ORDER BY title"), 'title', 'id'), 'empty' => '— None —'],
                'permission' => ['type' => 'checkbox', 'label' => 'Permission', 'text' => 'The client has agreed that this quote, their name and company may be published', 'wide' => true],
                'status' => ['type' => 'select', 'label' => 'Status', 'options' => ['draft' => 'Draft', 'published' => 'Published']],
                'sort_order' => ['type' => 'number', 'label' => 'Display order', 'default' => 0],
            ],
        ],
        'certificates' => [
            'title' => 'Certificates', 'singular' => 'Certificate', 'table' => 'certificates', 'perm' => 'content', 'order' => 'sort_order, name',
            'columns' => ['name' => 'Certificate', 'standard' => 'Standard', 'issuer' => 'Issued by', 'expires_at' => 'Valid until', 'status' => 'Status'],
            'fields' => [
                'name' => ['type' => 'text', 'label' => 'Certificate name', 'required' => true, 'help' => 'e.g. “Quality management system certificate”'],
                'standard' => ['type' => 'text', 'label' => 'Standard', 'placeholder' => 'e.g. ISO 9001:2015'],
                'issuer' => ['type' => 'text', 'label' => 'Issued by (certification body)'],
                'number' => ['type' => 'text', 'label' => 'Certificate number'],
                'issued_at' => ['type' => 'date', 'label' => 'Issue date'],
                'expires_at' => ['type' => 'date', 'label' => 'Expiry date', 'help' => 'The certificate is hidden from the website automatically after this date, and administrators are emailed 30 and 7 days before.'],
                'status' => ['type' => 'select', 'label' => 'Status', 'options' => ['published' => 'Published', 'draft' => 'Draft']],
                'sort_order' => ['type' => 'number', 'label' => 'Display order', 'default' => 0],
                'scope' => ['type' => 'textarea', 'label' => 'Scope', 'rows' => 3],
                'file' => ['type' => 'document', 'label' => 'Certificate file (PDF)', 'help' => 'Upload only genuine, current certificates issued to your company.'],
            ],
        ],
        'industries' => [
            'title' => 'Industries', 'singular' => 'Industry', 'table' => 'industries', 'perm' => 'content', 'order' => 'sort_order, name',
            'columns' => ['name' => 'Name', 'status' => 'Status', 'sort_order' => 'Order'],
            'view' => fn($r) => url('industries#' . $r['slug']),
            'json' => ['product_ids'],
            'fields' => [
                'name' => ['type' => 'text', 'label' => 'Industry name', 'required' => true, 'attrs' => 'data-slug-source'],
                'slug' => ['type' => 'slug', 'label' => 'Anchor / slug'],
                'icon' => ['type' => 'icon', 'label' => 'Icon'],
                'status' => ['type' => 'select', 'label' => 'Status', 'options' => ['published' => 'Published', 'draft' => 'Draft']],
                'sort_order' => ['type' => 'number', 'label' => 'Display order', 'default' => 0],
                'image' => ['type' => 'image', 'label' => 'Image'],
                'image_alt' => ['type' => 'text', 'label' => 'Image alt text', 'wide' => true],
                'summary' => ['type' => 'textarea', 'label' => 'Short summary (tiles)', 'rows' => 2],
                'body' => ['type' => 'textarea', 'label' => 'Description (industries page)', 'rows' => 4],
                'product_ids' => ['type' => 'multiselect', 'label' => 'Relevant products', 'options' => $prods],
            ],
        ],
        'categories' => [
            'title' => 'Categories', 'singular' => 'Category', 'table' => 'categories', 'perm' => 'content', 'order' => "type, sort_order, name",
            'columns' => ['name' => 'Name', 'type' => 'Used for', 'slug' => 'Slug', '_count' => 'Items'],
            'count_sql' => "(CASE WHEN t.type = 'post' THEN (SELECT COUNT(*) FROM posts x WHERE x.category_id = t.id) ELSE (SELECT COUNT(*) FROM pages x WHERE x.category_id = t.id) END)",
            'view' => fn($r) => $r['type'] === 'post' ? url('blog/category/' . $r['slug']) : null,
            'unique_scope' => 'type',
            'fields' => [
                'name' => ['type' => 'text', 'label' => 'Name', 'required' => true, 'attrs' => 'data-slug-source'],
                'slug' => ['type' => 'slug', 'label' => 'Slug'],
                'type' => ['type' => 'select', 'label' => 'Used for', 'options' => ['post' => 'Blog posts', 'page' => 'Pages']],
                'sort_order' => ['type' => 'number', 'label' => 'Display order', 'default' => 0],
                'description' => ['type' => 'textarea', 'label' => 'Description', 'rows' => 3, 'help' => 'Shown at the top of the blog category page.'],
                'meta_title' => ['type' => 'text', 'label' => 'SEO title', 'counter' => 60],
                'meta_description' => ['type' => 'textarea', 'label' => 'Meta description', 'rows' => 2, 'counter' => 160],
            ],
        ],
        'product_categories' => [
            'title' => 'Product Categories', 'singular' => 'Product category', 'table' => 'product_categories', 'perm' => 'content', 'order' => 'sort_order, name',
            'columns' => ['name' => 'Name', 'slug' => 'Slug', 'sort_order' => 'Order', '_count' => 'Products'],
            'count_sql' => '(SELECT COUNT(*) FROM products x WHERE x.category_id = t.id)',
            'fields' => [
                'name' => ['type' => 'text', 'label' => 'Name', 'required' => true, 'attrs' => 'data-slug-source'],
                'slug' => ['type' => 'slug', 'label' => 'Slug'],
                'sort_order' => ['type' => 'number', 'label' => 'Display order', 'default' => 0],
            ],
        ],
        'users' => [
            'title' => 'Users', 'singular' => 'User', 'table' => 'users', 'perm' => 'users', 'order' => 'name',
            'columns' => ['name' => 'Name', 'username' => 'Username', 'email' => 'Email', 'role' => 'Role', 'active' => 'Active', 'last_login_at' => 'Last login'],
            'fields' => [
                'name' => ['type' => 'text', 'label' => 'Name', 'required' => true],
                'email' => ['type' => 'email', 'label' => 'Email', 'required' => true, 'help' => 'Sign-in email. Codes and security notices are sent here.'],
                'username' => ['type' => 'text', 'label' => 'Username', 'help' => 'Optional. Can be used instead of the email to sign in. Lowercase letters, numbers, dot, dash, underscore.'],
                'backup_email' => ['type' => 'email', 'label' => 'Backup email', 'help' => 'Optional. Stays unverified (and unused for codes) until the user confirms it with a code from My profile.'],
                'role' => ['type' => 'select', 'label' => 'Role', 'options' => ROLES],
                'active' => ['type' => 'checkbox', 'label' => 'Status', 'text' => 'Active (can sign in)', 'default' => true],
                'password' => ['type' => 'password', 'label' => 'Password', 'help' => 'At least 10 characters with letters and numbers. Leave blank to keep the current password.'],
            ],
        ],
    ];
}

function resource_def(string $res): array
{
    $r = resources()[$res] ?? null;
    if (!$r) abort(404);
    require_login($r['perm']);
    return $r;
}

function admin_resource_list(string $res): void
{
    $r = resource_def($res);
    $count = isset($r['count_sql']) ? ', ' . str_replace('t.', 't.', $r['count_sql']) . ' AS _count' : '';
    $where = '';
    $params = [];
    if ($res === 'categories' && in_array($t = (string) query('type', ''), ['post', 'page'], true)) { $where = 'WHERE t.type = ?'; $params[] = $t; }
    $rows = db_all("SELECT t.*{$count} FROM `{$r['table']}` t {$where} ORDER BY {$r['order']}", $params);
    $actions = '<a class="adm-btn adm-btn-primary" href="' . e(url("admin/r/{$res}/new")) . '">' . icon('plus', 'h-4 w-4') . ' Add ' . e(strtolower($r['singular'])) . '</a>';
    admin_render($r['title'], 'resource/index', compact('r', 'res', 'rows'), ['actions' => $actions]);
}

function admin_resource_edit(string $res, ?string $id = null): void
{
    $r = resource_def($res);
    if ($res === 'users') require_stepup('users');
    $row = $id ? db_one("SELECT * FROM `{$r['table']}` WHERE id = ?", [(int) $id]) : null;
    if ($id && !$row) abort(404);
    $schema = $r['fields'];
    if ($res === 'users' && !$row) $schema['password']['required'] = true;
    $values = $row ?? [];
    foreach ($r['json'] ?? [] as $j) if ($row) $values[$j] = json_decode_array($row[$j]);
    $errors = [];

    if (is_post()) {
        csrf_check();
        // multiselect is posted as an array
        $post = $_POST;
        foreach ($schema as $k => $def) if (($def['type'] ?? '') === 'multiselect') $post[$k] = array_values(array_map('intval', (array) ($_POST[$k] ?? [])));
        $plain = array_filter($schema, fn($d) => ($d['type'] ?? '') !== 'multiselect');
        [$v, $errors] = parse_fields($plain, $post);
        foreach ($schema as $k => $def) if (($def['type'] ?? '') === 'multiselect') $v[$k] = $post[$k];

        if (isset($schema['slug'])) {
            $scope = isset($r['unique_scope']) ? [$r['unique_scope'] . ' = ?', [$v[$r['unique_scope']]]] : ['', []];
            $v['slug'] = unique_slug($r['table'], $v['slug'] ?: slugify((string) ($v['name'] ?? $v['title'] ?? 'item')), $row['id'] ?? null, $scope[0], $scope[1]);
        }
        if ($res === 'users') resource_user_rules($v, $errors, $row);
        if ($res === 'testimonials') {
            $v['permission'] = (int) ($v['permission'] ?? 0);
            $v['rating'] = ($v['rating'] ?? '') !== '' && $v['rating'] !== null ? max(1, min(5, (int) $v['rating'])) : null;
            $v['project_id'] = ($v['project_id'] ?? null) !== null && $v['project_id'] !== '' ? (int) $v['project_id'] : null;
            if ($v['status'] === 'published' && !$v['permission']) $errors['permission'] = 'Confirm the client agreed to publication before publishing.';
        }
        foreach (['category_id', 'industry_id'] as $fk) if (array_key_exists($fk, $v)) $v[$fk] = $v[$fk] !== null ? (int) $v[$fk] : null;
        foreach (['sort_order'] as $n) if (array_key_exists($n, $v)) $v[$n] = (int) $v[$n];
        foreach (['active'] as $b) if (array_key_exists($b, $v)) $v[$b] = (int) $v[$b];
        foreach ($r['json'] ?? [] as $j) if (array_key_exists($j, $v)) $v[$j] = json_encode($v[$j], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!$errors) {
            if ($row) { db_update($r['table'], $v, (int) $row['id']); $rid = (int) $row['id']; }
            else $rid = db_insert($r['table'], $v);
            if ($res === 'users') resource_user_notify($row, $v, $rid);
            // Keep old links working when a published item's URL changes
            $urlBase = ['products' => 'products/', 'projects' => 'projects/'][$res] ?? null;
            if ($row && $urlBase && isset($v['slug']) && $v['slug'] !== $row['slug'] && ($row['status'] ?? '') === 'published') {
                redirect_auto('/' . $urlBase . $row['slug'], '/' . $urlBase . $v['slug'], 'Automatic: ' . strtolower($r['singular']) . ' URL changed');
                flash('info', 'The URL changed, so a 301 redirect from the old address was added automatically.');
            }
            flash('success', $r['singular'] . ' saved.');
            redirect("admin/r/{$res}/{$rid}/edit");
        }
        $values = array_merge($values, $v);
        foreach ($r['json'] ?? [] as $j) if (isset($values[$j]) && is_string($values[$j])) $values[$j] = json_decode_array($values[$j]);
        flash('error', 'Please fix the errors below.');
    }
    $view = isset($r['view']) && $row ? ($r['view'])($row) : null;
    $actions = $view ? '<a class="adm-btn adm-btn-light" target="_blank" href="' . e($view) . '">' . icon('eye', 'h-4 w-4') . ' View</a>' : '';
    admin_render(($row ? 'Edit ' : 'New ') . strtolower($r['singular']), 'resource/edit', compact('r', 'res', 'row', 'schema', 'values', 'errors'), ['actions' => $actions, 'crumbs' => [[$r['title'], "admin/r/{$res}"]]]);
}

/** Alert the affected user and all administrators about account changes made in Users. */
function resource_user_notify(?array $before, array $v, int $uid): void
{
    $u = db_one('SELECT * FROM users WHERE id = ?', [$uid]);
    if (!$u) return;
    $who = actor_name();
    if (!$before) {
        notify_admins('user_created', 'New user account created', "{$who} created a {$u['role']} account for {$u['name']} ({$u['email']}).", ['severity' => 'warning', 'link' => 'admin/r/users', 'include_actor' => true]);
        notify_user($u, 'account_created', 'An account was created for you', "{$who} created a {$u['role']} account for you on the " . company('name') . ' control panel. Sign in at ' . abs_url('admin/login') . ' and add a passkey or authenticator app in My profile.', ['severity' => 'info', 'category' => 'account']);
        return;
    }
    $changes = [];
    if ($before['role'] !== $u['role']) $changes[] = "role changed from {$before['role']} to {$u['role']}";
    if ((int) $before['active'] !== (int) $u['active']) $changes[] = $u['active'] ? 'account re-enabled' : 'account disabled';
    if (strtolower((string) $before['email']) !== strtolower((string) $u['email'])) $changes[] = "sign-in email changed from {$before['email']} to {$u['email']}";
    if (!empty($v['password_hash'])) $changes[] = 'password set by an administrator';
    if (!$changes) return;
    $text = "{$who} changed the account of {$u['name']}: " . implode('; ', $changes) . '.';
    notify_admins('user_changed', 'User account changed', $text, ['severity' => 'warning', 'link' => 'admin/r/users/' . $uid . '/edit', 'include_actor' => true]);
    security_notice($u, 'Your account was changed by an administrator', $text, strtolower((string) $before['email']) !== strtolower((string) $u['email']) ? $before['email'] : null, 'account_changed_by_admin');
}

function resource_user_rules(array &$v, array &$errors, ?array $row): void
{
    $v['email'] = strtolower(trim((string) $v['email']));
    if ($v['email'] && email_in_use($v['email'], isset($row['id']) ? (int) $row['id'] : null)) $errors['email'] = 'Another user already uses this email or username.';
    $v['username'] = strtolower(trim((string) ($v['username'] ?? '')));
    if ($p = username_problems($v['username'], isset($row['id']) ? (int) $row['id'] : null)) $errors['username'] = $p;
    $v['username'] = $v['username'] !== '' ? $v['username'] : null;
    $v['backup_email'] = strtolower(trim((string) ($v['backup_email'] ?? ''))) ?: null;
    if ($v['backup_email'] && $v['backup_email'] === $v['email']) $errors['backup_email'] = 'The backup email must differ from the sign-in email.';
    // A backup address set by an administrator must be re-verified by its owner
    if ($v['backup_email'] !== ($row['backup_email'] ?? null)) $v['backup_email_verified_at'] = null;
    if (!empty($v['password'])) {
        if ($p = password_problems($v['password'])) $errors['password'] = $p;
        $v['password_hash'] = password_hash($v['password'], PASSWORD_DEFAULT);
        $v['password_changed_at'] = now();
    } elseif (!$row) {
        $errors['password'] = 'Set a password for the new user.';
    }
    unset($v['password']);
    $me = current_user();
    if ($row && (int) $row['id'] === (int) $me['id'] && ($v['role'] !== 'admin' || !$v['active'])) {
        $errors['role'] = 'You cannot remove your own administrator access.';
    }
}

function admin_resource_delete(string $res, string $id): void
{
    $r = resource_def($res);
    csrf_check();
    if ($res === 'users') {
        require_stepup('users');
        if ((int) $id === (int) current_user()['id']) { flash('error', 'You cannot delete your own account.'); redirect('admin/r/users'); }
        $gone = db_one('SELECT name, email, role FROM users WHERE id = ?', [(int) $id]);
        if ($gone) notify_admins('user_deleted', 'User account deleted', actor_name() . ' deleted the ' . $gone['role'] . ' account of ' . $gone['name'] . ' (' . $gone['email'] . ').', ['severity' => 'warning', 'link' => 'admin/r/users', 'include_actor' => true]);
    }
    if ($res === 'product_categories' && db_val('SELECT COUNT(*) FROM products WHERE category_id = ?', [(int) $id])) {
        flash('info', 'Products in this category are now uncategorised.');
    }
    db_delete($r['table'], (int) $id);
    flash('success', $r['singular'] . ' deleted.');
    redirect("admin/r/{$res}");
}

/** Duplicate a product, industry or category as a new (draft where supported) item. */
function admin_resource_duplicate(string $res, string $id): void
{
    $r = resource_def($res);
    csrf_check();
    if (!in_array($res, ['products', 'industries', 'categories', 'product_categories'], true)) abort(404);
    $row = db_one("SELECT * FROM `{$r['table']}` WHERE id = ?", [(int) $id]);
    if (!$row) abort(404);
    $copy = array_diff_key($row, array_flip(['id', 'created_at', 'updated_at']));
    $copy['name'] = mb_substr($row['name'] . ' (copy)', 0, 190);
    $copy['slug'] = unique_slug($r['table'], $row['slug'] . '-copy');
    if (array_key_exists('status', $copy)) $copy['status'] = 'draft';
    $newId = db_insert($r['table'], $copy);
    flash('success', $r['singular'] . ' duplicated' . (array_key_exists('status', $copy) ? ' as a draft' : '') . '. Review the name and URL.');
    redirect("admin/r/{$res}/{$newId}/edit");
}

/* ================================================================== */
/* MEDIA                                                               */
/* ================================================================== */

const MEDIA_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'];
const MEDIA_MAX = 8 * 1024 * 1024;       // images
const MEDIA_PDF_MAX = 25 * 1024 * 1024;  // datasheets, catalogues, certificates

function admin_media(): void
{
    require_login('content');
    $q = (string) query('q', '');
    $type = (string) query('type', '');
    $where = ['1=1'];
    $params = [];
    if ($q !== '') { $where[] = '(original_name LIKE ? OR alt LIKE ?)'; array_push($params, "%{$q}%", "%{$q}%"); }
    if ($type === 'pdf') $where[] = "mime = 'application/pdf'";
    if ($type === 'image') $where[] = "mime LIKE 'image/%'";
    $rows = db_all('SELECT * FROM media WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 300', $params);
    $pending = images_supported() ? (int) db_val("SELECT COUNT(*) FROM media WHERE mime IN ('image/jpeg','image/png','image/webp') AND (variants IS NULL OR variants = '')") : 0;
    $actions = '<label class="adm-btn adm-btn-primary cursor-pointer">' . icon('upload', 'h-4 w-4') . ' Upload images or PDFs<input type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" class="sr-only" data-library-upload></label>';
    admin_render('Media Library', 'media', ['rows' => $rows, 'q' => $q, 'type' => $type, 'pending' => $pending, 'gd' => images_supported()], ['actions' => $actions, 'wide' => true]);
}

function admin_media_json(): void
{
    require_login('content');
    $type = (string) query('type', 'image');
    $rows = db_all('SELECT id, path, original_name, alt, width, height, mime FROM media WHERE ' . ($type === 'pdf' ? "mime = 'application/pdf'" : "mime LIKE 'image/%'") . ' ORDER BY id DESC LIMIT 200');
    json_response(array_map(fn($m) => $m + ['url' => media_url($m['path'])], $rows));
}

function admin_media_upload(): void
{
    $user = require_login('content');
    csrf_check();
    $f = $_FILES['file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) json_response(['error' => 'Upload failed. Images must be under 8 MB and PDFs under 25 MB.'], 422);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    if ($mime === 'application/pdf') {
        if ($f['size'] > MEDIA_PDF_MAX) json_response(['error' => 'PDF is larger than 25 MB.'], 422);
        $head = (string) file_get_contents($f['tmp_name'], false, null, 0, 5);
        if ($head !== '%PDF-') json_response(['error' => 'This file is not a valid PDF.'], 422);
        $info = [null, null];
    } else {
        if ($f['size'] > MEDIA_MAX) json_response(['error' => 'Image is larger than 8 MB.'], 422);
        $info = @getimagesize($f['tmp_name']);
        if (!isset(MEDIA_TYPES[$mime]) || !$info) json_response(['error' => 'Only JPG, PNG, WebP or GIF images and PDF documents are allowed.'], 422);
    }
    $dir = ($mime === 'application/pdf' ? 'uploads/docs/' : 'uploads/') . date('Y/m');
    @mkdir(PUBLIC_DIR . '/' . $dir, 0775, true);
    $base = slugify(pathinfo((string) $f['name'], PATHINFO_FILENAME)) ?: ($mime === 'application/pdf' ? 'document' : 'image');
    $name = substr($base, 0, 60) . '-' . bin2hex(random_bytes(3)) . '.' . MEDIA_TYPES[$mime];
    if (!move_uploaded_file($f['tmp_name'], PUBLIC_DIR . '/' . $dir . '/' . $name)) json_response(['error' => 'Could not save the file. Check folder permissions on public/uploads.'], 500);
    $path = $dir . '/' . $name;
    $row = ['path' => $path, 'original_name' => mb_substr((string) $f['name'], 0, 255), 'mime' => $mime, 'size' => (int) $f['size'],
        'width' => $info[0], 'height' => $info[1], 'alt' => mb_substr(ucfirst(str_replace('-', ' ', $base)), 0, 255), 'uploaded_by' => (int) $user['id']];
    if ($opt = image_optimize($path, $mime)) {
        $row = array_merge($row, ['width' => $opt['width'], 'height' => $opt['height'], 'size' => $opt['size'], 'variants' => json_encode($opt['variants'])]);
    }
    $id = db_insert('media', $row);
    json_response(['id' => $id, 'path' => $path, 'url' => media_url($path), 'mime' => $mime,
        'optimized' => $opt ? ['saved_kb' => max(0, (int) round(((int) $f['size'] - $opt['size']) / 1024)), 'sizes' => $opt['variants']] : null]);
}

/** Optimise images uploaded before optimisation existed (batch of 25 per click to avoid timeouts). */
function admin_media_optimize(): void
{
    require_login('content');
    csrf_check();
    if (!images_supported()) { flash('error', 'Image optimisation needs the PHP GD extension with WebP support.'); redirect('admin/media'); }
    @set_time_limit(300);
    $done = 0; $saved = 0;
    foreach (db_all("SELECT * FROM media WHERE mime IN ('image/jpeg','image/png','image/webp') AND (variants IS NULL OR variants = '') ORDER BY id LIMIT 25") as $m) {
        $before = (int) $m['size'];
        $opt = image_optimize($m['path'], $m['mime']);
        db_update('media', $opt ? ['width' => $opt['width'], 'height' => $opt['height'], 'size' => $opt['size'], 'variants' => json_encode($opt['variants'])] : ['variants' => '[]'], (int) $m['id']);
        if ($opt) { $done++; $saved += max(0, $before - $opt['size']); }
    }
    $left = (int) db_val("SELECT COUNT(*) FROM media WHERE mime IN ('image/jpeg','image/png','image/webp') AND (variants IS NULL OR variants = '')");
    flash('success', "Optimised {$done} image(s), saving " . round($saved / 1024) . ' KB' . ($left ? " — {$left} left, click again to continue." : '.'));
    redirect('admin/media');
}

function admin_media_update(string $id): void
{
    require_login('content');
    csrf_check();
    db_update('media', ['alt' => mb_substr((string) input('alt', ''), 0, 255)], (int) $id);
    if (wants_json()) json_response(['ok' => true]);
    flash('success', 'Alt text saved.');
    redirect('admin/media');
}

function admin_media_delete(string $id): void
{
    require_login('content');
    csrf_check();
    $m = db_one('SELECT * FROM media WHERE id = ?', [(int) $id]);
    if ($m) {
        $root = realpath(PUBLIC_DIR . '/uploads');
        $files = array_merge([PUBLIC_DIR . '/' . $m['path']], image_variant_files($m['path'], array_map('intval', json_decode((string) $m['variants'], true) ?: [])));
        foreach ($files as $f) {
            $real = realpath($f);
            if ($real && $root && str_starts_with($real, $root)) @unlink($real);
        }
        db_delete('media', (int) $id);
    }
    flash('success', 'File deleted. Pages that used it will show a placeholder until you choose a new one.');
    redirect('admin/media');
}

function admin_icon_svg(): void
{
    require_login('content');
    $name = (string) query('name', '');
    if ($name === '__list') json_response(icon_names());
    header('Content-Type: image/svg+xml');
    header('Cache-Control: private, max-age=86400');
    echo icon_exists($name) ? icon($name, 'h-5 w-5') : icon('circle-help', 'h-5 w-5');
}
