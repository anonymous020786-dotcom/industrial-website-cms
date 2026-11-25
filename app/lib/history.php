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
