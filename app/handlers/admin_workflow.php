<?php
/**
 * Admin: content approvals (review queue) and the "who changed what" activity log.
 */
declare(strict_types=1);
require_once __DIR__ . '/admin_content.php'; // revision_save(), unique_slug() helpers used by review_apply()

const REVIEW_LABELS = ['title' => 'Title', 'slug' => 'URL', 'status' => 'Status', 'published_at' => 'Publish date', 'excerpt' => 'Excerpt', 'content' => 'Content',
    'data' => 'Page sections', 'meta_title' => 'SEO title', 'meta_description' => 'Meta description', 'featured_image' => 'Image', 'featured_alt' => 'Image alt text',
    'hero_eyebrow' => 'Banner label', 'hero_title' => 'Banner title', 'hero_lead' => 'Banner text', 'hero_image' => 'Banner image', 'category_id' => 'Category', 'tags' => 'Tags',
    'focus_keyword' => 'Focus keyword', 'canonical_url' => 'Canonical URL', 'noindex' => 'Hide from search engines', 'og_image' => 'Social image', 'author_id' => 'Author', 'template' => 'Template'];

/** Readable text for a field value (HTML stripped, section JSON flattened into "label: text" lines). */
function review_text(string $field, mixed $value): string
{
    $s = (string) $value;
    if ($field === 'data') {
        $lines = [];
        $walk = function ($v, string $path) use (&$walk, &$lines) {
            if (is_array($v)) { foreach ($v as $k => $x) $walk($x, $path === '' ? (string) $k : $path . ' › ' . $k); return; }
            $t = trim(html_entity_decode(strip_tags((string) $v), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($t !== '') $lines[] = $path . ': ' . $t;
        };
        $walk(json_decode($s, true) ?: [], '');
        return implode("\n", $lines);
    }
    if (in_array($field, ['content', 'excerpt'], true)) {
        $s = preg_replace('~<(?:/p|br\s*/?|/h[1-6]|/li)>~i', "\n", $s) ?? $s;
        return trim(preg_replace("~\n{2,}~", "\n", html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
    return $s;
}

/** Word-level diff as HTML (<del>/<ins>), falls back to before/after blocks for very long texts. */
function review_diff_html(string $old, string $new): string
{
    $a = preg_split('~(\s+)~u', $old, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    $b = preg_split('~(\s+)~u', $new, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($a) * count($b) > 4_000_000) {
        return '<div class="review-del">' . nl2br(e($old)) . '</div><div class="review-ins mt-2">' . nl2br(e($new)) . '</div>';
    }
    // Trim the common start and end, then LCS on the middle
    $pre = 0;
    while ($pre < count($a) && $pre < count($b) && $a[$pre] === $b[$pre]) $pre++;
    $suf = 0;
    while ($suf < count($a) - $pre && $suf < count($b) - $pre && $a[count($a) - 1 - $suf] === $b[count($b) - 1 - $suf]) $suf++;
    $ma = array_slice($a, $pre, count($a) - $pre - $suf);
    $mb = array_slice($b, $pre, count($b) - $pre - $suf);
    $n = count($ma); $m = count($mb);
    $L = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
    for ($i = $n - 1; $i >= 0; $i--) for ($j = $m - 1; $j >= 0; $j--) $L[$i][$j] = $ma[$i] === $mb[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
    $out = e(implode('', array_slice($a, 0, $pre)));
    $i = $j = 0;
    $del = $ins = '';
    $flush = function () use (&$out, &$del, &$ins) {
        if ($del !== '') $out .= '<del>' . e($del) . '</del>';
        if ($ins !== '') $out .= '<ins>' . e($ins) . '</ins>';
        $del = $ins = '';
    };
    while ($i < $n || $j < $m) {
        if ($i < $n && $j < $m && $ma[$i] === $mb[$j]) { $flush(); $out .= e($ma[$i]); $i++; $j++; }
        elseif ($j < $m && ($i >= $n || $L[$i][$j + 1] >= $L[$i + 1][$j])) $ins .= $mb[$j++];
        else $del .= $ma[$i++];
    }
    $flush();
    $out .= e(implode('', array_slice($a, count($a) - $suf)));
    return nl2br($out);
}

function review_or_404(string $id): array
{
    $r = ctype_digit($id) ? db_one('SELECT r.*, u.name AS user_name, d.name AS decided_name FROM content_reviews r LEFT JOIN users u ON u.id = r.user_id LEFT JOIN users d ON d.id = r.decided_by WHERE r.id = ?', [(int) $id]) : null;
    if (!$r || !isset(REVIEW_ENTITIES[$r['entity']])) abort(404);
    return $r;
}

function admin_reviews(): void
{
    require_login('settings');
    $pending = db_all("SELECT r.*, u.name AS user_name FROM content_reviews r LEFT JOIN users u ON u.id = r.user_id WHERE r.status = 'pending' ORDER BY r.created_at");
    $recent = db_all("SELECT r.*, u.name AS user_name, d.name AS decided_name FROM content_reviews r LEFT JOIN users u ON u.id = r.user_id LEFT JOIN users d ON d.id = r.decided_by
        WHERE r.status <> 'pending' ORDER BY r.decided_at DESC LIMIT 30");
    $actions = '<a class="adm-btn adm-btn-light" href="' . e(url('admin/settings/workflow')) . '">' . icon('settings', 'h-4 w-4') . ' Approval settings</a>';
    admin_render('Content approvals', 'workflow/reviews', compact('pending', 'recent'), ['actions' => $actions]);
}

function admin_review_show(string $id): void
{
    require_login('settings');
    $review = review_or_404($id);
    [$table, $label, $editBase] = REVIEW_ENTITIES[$review['entity']];
    $row = db_one("SELECT * FROM `{$table}` WHERE id = ?", [(int) $review['entity_id']]);
    $snap = json_decode((string) $review['snapshot'], true) ?: [];
    $changes = [];
    if ($row) foreach ($snap as $k => $v) {
        if (!array_key_exists($k, $row) || in_array($k, ['reading_minutes', 'id', 'updated_at', 'created_at'], true)) continue;
        $before = review_text($k, $row[$k]);
        $after = review_text($k, $v);
        if ($k === 'data' && json_decode((string) $row[$k], true) == json_decode((string) $v, true)) continue;
        if ($before !== $after) $changes[] = ['label' => REVIEW_LABELS[$k] ?? ucfirst(str_replace('_', ' ', $k)), 'diff' => review_diff_html($before, $after), 'before' => $before, 'after' => $after];
    }
    $stale = $row && $row['updated_at'] > $review['created_at'] && $review['status'] === 'pending';
    $preview = $row ? ($review['entity'] === 'post' ? url('blog/' . $row['slug']) : page_url($row)) : null;
    admin_render('Review: ' . $review['title'], 'workflow/review', compact('review', 'row', 'changes', 'stale', 'label', 'editBase', 'preview'), ['crumbs' => [['Content approvals', 'admin/reviews']]]);
}

function admin_review_decide(string $id): void
{
    $me = require_login('settings');
    csrf_check();
    $review = review_or_404($id);
    if ($review['status'] !== 'pending') { flash('error', 'This change was already ' . $review['status'] . '.'); redirect('admin/reviews/' . $review['id']); }
    $approve = input('decision') === 'approve';
    $note = mb_substr(trim((string) input('note', '')), 0, 2000);
    if (!$approve && $note === '') { flash('error', 'Add a short note so the editor knows what to change.'); redirect('admin/reviews/' . $review['id']); }
    [, $label, $editBase] = REVIEW_ENTITIES[$review['entity']];
    try {
        $itemId = $approve ? review_apply($review) : (int) $review['entity_id'];
    } catch (Throwable $e) {
        flash('error', 'Could not apply the change: ' . $e->getMessage());
        redirect('admin/reviews/' . $review['id']);
    }
    db_update('content_reviews', ['status' => $approve ? 'approved' : 'rejected', 'note' => $note ?: null, 'decided_by' => (int) $me['id'], 'decided_at' => now()], (int) $review['id']);
    content_log($review['entity'], $itemId, $approve ? 'approved' : 'rejected', (string) $review['title'], ($review['user_name'] ? 'Sent by ' . $review['user_name'] : '') . ($note ? ' · ' . $note : ''));
    $author = $review['user_id'] ? db_one('SELECT * FROM users WHERE id = ? AND active = 1', [(int) $review['user_id']]) : null;
    if ($author) notify_user($author, 'content_review_' . ($approve ? 'approved' : 'rejected'), ($approve ? 'Approved: ' : 'Changes requested: ') . $review['title'],
        $me['name'] . ($approve ? ' approved your changes to “' . $review['title'] . '”. They are now live.' : ' did not approve your changes to “' . $review['title'] . '”: ' . $note),
        ['link' => $editBase . $itemId . '/edit', 'category' => 'reviews', 'severity' => $approve ? 'info' : 'warning']);
    flash('success', $approve ? 'Approved — the change is live.' : 'Sent back to ' . ($review['user_name'] ?: 'the editor') . ' with your note.');
    redirect('admin/reviews');
}

/* ------------------------------------------------------------------ */
/* Activity: who changed what                                          */
/* ------------------------------------------------------------------ */

const ACTIVITY_ENTITIES = ['page' => 'Pages', 'post' => 'Blog posts', 'products' => 'Products', 'projects' => 'Projects', 'categories' => 'Categories', 'product_categories' => 'Product categories',
    'industries' => 'Industries', 'certificates' => 'Certificates', 'testimonials' => 'Testimonials', 'downloads' => 'Downloads', 'part_refs' => 'Part numbers', 'distributors' => 'Distributors',
    'jobs' => 'Job openings', 'users' => 'Users', 'quote' => 'Quotations', 'report' => 'Reports'];

function activity_link(array $r): ?string
{
    if (!$r['entity_id'] || $r['action'] === 'deleted' && !in_array($r['entity'], ['page', 'post'], true)) return null;
    return match ($r['entity']) {
        'page' => url('admin/pages/' . $r['entity_id'] . '/edit'),
        'post' => url('admin/posts/' . $r['entity_id'] . '/edit'),
        'quote' => url('admin/quotes/' . $r['entity_id'] . '/edit'),
        default => isset(resources()[$r['entity']]) ? url('admin/r/' . $r['entity'] . '/' . $r['entity_id'] . '/edit') : null,
    };
}

function admin_activity(): void
{
    require_login('settings');
    $where = ['1=1'];
    $params = [];
    if (isset(ACTIVITY_ENTITIES[$ent = (string) query('entity', '')])) { $where[] = 'l.entity = ?'; $params[] = $ent; }
    if (ctype_digit((string) query('user', ''))) { $where[] = 'l.user_id = ?'; $params[] = (int) query('user'); }
    if (isset(HISTORY_ACTIONS[$act = (string) query('action', '')])) { $where[] = 'l.action = ?'; $params[] = $act; }
    if (($q = trim((string) query('q', ''))) !== '') { $where[] = 'l.title LIKE ?'; $params[] = '%' . addcslashes($q, '%_\\') . '%'; }
    $w = implode(' AND ', $where);
    $total = (int) db_val("SELECT COUNT(*) FROM content_log l WHERE {$w}", $params);
    $pager = paginate($total, 50, (int) query('page', 1));
    $rows = db_all("SELECT l.*, u.name AS user_name FROM content_log l LEFT JOIN users u ON u.id = l.user_id WHERE {$w} ORDER BY l.id DESC LIMIT {$pager['per']} OFFSET {$pager['offset']}", $params);
    $users = array_column(db_all('SELECT id, name FROM users ORDER BY name'), 'name', 'id');
    admin_render('Activity', 'workflow/activity', compact('rows', 'pager', 'users'), ['wide' => true]);
}

/* ------------------------------------------------------------------ */
/* Weekly owner report: preview and send now                          */
/* ------------------------------------------------------------------ */

function admin_report_weekly(): void
{
    require_login('settings');
    $d = weekly_report_data();
    $html = weekly_report_html($d);
    $next = null;
    if (setting('report_weekly', true)) {
        $day = (int) setting('report_day', 1);
        $hour = (int) setting('report_hour', 8);
        $t = strtotime('today ' . $hour . ':00');
        while ((int) date('N', $t) !== $day || $t <= time() || date('o-W', $t) === date('o-W', (int) setting('cron_last_weekly', 0))) $t += 86400;
        $next = $t;
    }
    $actions = '<a class="adm-btn adm-btn-light" href="' . e(url('admin/settings/workflow')) . '">' . icon('settings', 'h-4 w-4') . ' Schedule & recipients</a>';
    admin_render('Weekly report', 'workflow/weekly', compact('html', 'next'), ['actions' => $actions, 'crumbs' => [['Reports', 'admin/reports']]]);
}

function admin_report_weekly_send(): void
{
    require_login('settings');
    csrf_check();
    if (!rate_limit('weekly-report-now', 5, 3600)) { flash('error', 'The report was sent several times in the last hour. Please try later.'); redirect('admin/reports/weekly'); }
    $r = weekly_report_send();
    content_log('report', null, 'sent', 'Weekly report', $r['emails'] . ' email recipient(s), ' . $r['telegram'] . ' Telegram');
    flash($r['emails'] || $r['telegram'] ? 'success' : 'error', $r['emails'] || $r['telegram'] ? 'Weekly report sent to ' . $r['emails'] . ' email recipient(s)' . ($r['telegram'] ? ' and ' . $r['telegram'] . ' Telegram chat(s)' : '') . '.' : 'Nothing was sent — check the recipients and email settings.');
    redirect('admin/reports/weekly');
}
