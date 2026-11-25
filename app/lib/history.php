<?php
/**
 * "Who changed what": a single history of content and sales changes (Admin → Insights → Activity),
 * plus the approval workflow where editors' changes wait for an administrator.
 */
declare(strict_types=1);

const HISTORY_ACTIONS = [
    'created' => ['Created', 'bg-emerald-100 text-emerald-800'], 'updated' => ['Updated', 'bg-blue-100 text-blue-800'],
    'deleted' => ['Deleted', 'bg-red-100 text-red-700'], 'restored' => ['Restored', 'bg-violet-100 text-violet-800'],
    'published' => ['Published', 'bg-emerald-100 text-emerald-800'], 'submitted' => ['Sent for approval', 'bg-amber-100 text-amber-800'],
    'approved' => ['Approved', 'bg-emerald-100 text-emerald-800'], 'rejected' => ['Changes rejected', 'bg-red-100 text-red-700'],
    'sent' => ['Sent', 'bg-blue-100 text-blue-800'], 'accepted' => ['Accepted', 'bg-emerald-100 text-emerald-800'], 'declined' => ['Declined', 'bg-red-100 text-red-700'],
];

/** Record a change. Never throws (history must not break the action itself). */
function content_log(string $entity, ?int $id, string $action, ?string $title = null, ?string $detail = null): void
{
    try {
        $u = function_exists('current_user') ? current_user() : null;
        db_insert('content_log', ['user_id' => $u['id'] ?? null, 'entity' => mb_substr($entity, 0, 40), 'entity_id' => $id, 'action' => mb_substr($action, 0, 20),
            'title' => $title !== null ? mb_substr($title, 0, 255) : null, 'detail' => $detail !== null ? mb_substr($detail, 0, 500) : null, 'ip' => client_ip()]);
    } catch (Throwable $e) {
        error_log('content_log: ' . $e->getMessage());
    }
}

/** Fields that changed between two rows, as readable labels (for "Updated: title, price, image"). */
function history_changed_fields(?array $before, array $after, array $labels = []): string
{
    if (!$before) return '';
    $skip = ['updated_at', 'created_at', 'id', 'views', 'downloads'];
    $out = [];
    foreach ($after as $k => $v) {
        if (in_array($k, $skip, true) || !array_key_exists($k, $before)) continue;
        if ((string) $before[$k] !== (string) (is_array($v) ? json_encode($v) : $v)) $out[] = $labels[$k] ?? str_replace('_', ' ', $k);
    }
    return $out ? 'Changed: ' . implode(', ', array_slice($out, 0, 12)) . (count($out) > 12 ? '…' : '') : 'No visible changes';
}


/* ------------------------------------------------------------------ */
/* Content approval: editors' changes to live pages/posts wait for an administrator */
/* ------------------------------------------------------------------ */

const REVIEW_ENTITIES = ['post' => ['posts', 'Blog post', 'admin/posts/'], 'page' => ['pages', 'Page', 'admin/pages/']];

function approval_required(): bool
{
    $u = current_user();
    return $u && $u['role'] !== 'admin' && (bool) setting('content_approval', false);
}

function content_is_live(?array $row): bool
{
    return $row && in_array($row['status'] ?? '', ['published', 'scheduled'], true) && empty($row['deleted_at']);
}

/** This user's pending change for an item (to keep editing it), or null. */
function review_pending_for(string $entity, int $id, ?int $userId = null): ?array
{
    $sql = "SELECT r.*, u.name AS user_name FROM content_reviews r LEFT JOIN users u ON u.id = r.user_id WHERE r.entity = ? AND r.entity_id = ? AND r.status = 'pending'";
    $params = [$entity, $id];
    if ($userId !== null) { $sql .= ' AND r.user_id = ?'; $params[] = $userId; }
    return db_one($sql . ' ORDER BY r.id DESC LIMIT 1', $params);
}

/** Store (or replace) the editor's pending change and tell the administrators. */
function review_submit(string $entity, int $id, array $values, string $title): int
{
    $u = current_user();
    $snap = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $existing = review_pending_for($entity, $id, (int) $u['id']);
    if ($existing) {
        db_update('content_reviews', ['snapshot' => $snap, 'title' => mb_substr($title, 0, 255), 'created_at' => now()], (int) $existing['id']);
        $rid = (int) $existing['id'];
    } else {
        $rid = db_insert('content_reviews', ['entity' => $entity, 'entity_id' => $id, 'user_id' => (int) $u['id'], 'title' => mb_substr($title, 0, 255), 'snapshot' => $snap]);
    }
    content_log($entity, $id, 'submitted', $title);
    notify_admins('content_review', 'Approval needed: ' . $title, $u['name'] . ' sent changes to the ' . strtolower(REVIEW_ENTITIES[$entity][1]) . ' “' . $title . '” for approval.',
        ['link' => 'admin/reviews/' . $rid, 'category' => 'reviews']);
    return $rid;
}

/** Apply an approved change. Returns the item id. */
function review_apply(array $review): int
{
    [$table, , $editBase] = REVIEW_ENTITIES[$review['entity']];
    $row = db_one("SELECT * FROM `{$table}` WHERE id = ?", [(int) $review['entity_id']]);
    if (!$row) throw new RuntimeException('The item no longer exists.');
    $v = array_intersect_key(json_decode((string) $review['snapshot'], true) ?: [], $row);
    unset($v['id'], $v['created_at'], $v['updated_at'], $v['views'], $v['deleted_at'], $v['is_system']);
    if ($row['is_system'] ?? false) unset($v['slug'], $v['template']);
    if (isset($v['slug'])) $v['slug'] = unique_slug($table, (string) $v['slug'], (int) $row['id']);
    if ($review['entity'] === 'post' && ($v['status'] ?? '') === 'published' && empty($v['published_at'])) $v['published_at'] = now();
    revision_save($review['entity'], $row);
    db_update($table, $v, (int) $row['id']);
    $base = $review['entity'] === 'post' ? '/blog/' : '/';
    if (isset($v['slug']) && $v['slug'] !== $row['slug'] && content_is_live($row)) redirect_auto($base . $row['slug'], $base . $v['slug'], 'Automatic: URL changed (approved change)');
    return (int) $row['id'];
}
