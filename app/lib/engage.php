<?php
/**
 * Engagement: short links, social share buttons and blog comments.
 */
declare(strict_types=1);

/* ------------------------------------------------------------------ */
/* Short links: /s/{code}                                              */
/* ------------------------------------------------------------------ */

const SHORT_CODE_CHARS = 'abcdefghijkmnpqrstuvwxyz23456789'; // no 0/o/1/l: easy to read and type

function short_code_valid(string $code): bool
{
    return (bool) preg_match('~^[A-Za-z0-9][A-Za-z0-9_-]{1,31}$~', $code);
}

function short_code_random(int $len = 6): string
{
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= SHORT_CODE_CHARS[random_int(0, strlen(SHORT_CODE_CHARS) - 1)];
    return $s;
}

/** Where a short link may point: this site (path) or an http(s) URL. */
function short_target_valid(string $target): bool
{
    return (bool) (preg_match('~^/[^/\\\\]~', $target) || $target === '/' || filter_var($target, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $target));
}

function short_url(array $link): string
{
    return abs_url('s/' . $link['code']);
}

/** The short link for a page on this site (created on first use). Returns null if the table is missing. */
function short_link_for(string $path, string $title = ''): ?array
{
    $path = '/' . ltrim($path, '/');
    try {
        $row = db_one('SELECT * FROM short_links WHERE target = ? AND auto = 1 LIMIT 1', [$path]);
        if ($row) return $row;
        for ($try = 0; $try < 5; $try++) {
            $code = short_code_random();
            if (db_val('SELECT 1 FROM short_links WHERE code = ?', [$code])) continue;
            db_insert('short_links', ['code' => $code, 'target' => $path, 'title' => mb_substr($title, 0, 255) ?: null, 'auto' => 1]);
            return db_one('SELECT * FROM short_links WHERE code = ?', [$code]);
        }
    } catch (Throwable $e) {
        error_log('short_link_for: ' . $e->getMessage());
    }
    return null;
}

/** Share networks: [label, icon, url template with {u} (url) and {t} (title)] */
function share_networks(): array
{
    return [
        'whatsapp' => ['WhatsApp', 'message-circle', 'https://wa.me/?text={t}%20{u}'],
        'linkedin' => ['LinkedIn', 'linkedin', 'https://www.linkedin.com/sharing/share-offsite/?url={u}'],
        'facebook' => ['Facebook', 'facebook', 'https://www.facebook.com/sharer/sharer.php?u={u}'],
        'x' => ['X', 'twitter', 'https://twitter.com/intent/tweet?url={u}&text={t}'],
        'telegram' => ['Telegram', 'send', 'https://t.me/share/url?url={u}&text={t}'],
        'reddit' => ['Reddit', 'message-square-share', 'https://www.reddit.com/submit?url={u}&title={t}'],
        'email' => ['Email', 'mail', 'mailto:?subject={t}&body={u}'],
    ];
}

/**
 * Share bar: network buttons, copy link (the short link) and the phone's own share sheet.
 * Links carry utm tags so shared visits show up in Reports by network.
 */
function share_bar(string $path, string $title, string $label = 'Share'): string
{
    if (!setting('share_enabled', true)) return '';
    $enabled = (array) (setting('share_networks', []) ?: array_keys(share_networks()));
    $link = setting('share_short_links', true) ? short_link_for($path, $title) : null;
    $canonical = abs_url(ltrim($path, '/'));
    $copy = $link ? short_url($link) : $canonical;
    $h = '<div class="share-bar" data-share data-share-title="' . e($title) . '" data-share-url="' . e($copy) . '">'
        . '<span class="share-label">' . e($label) . '</span><div class="share-buttons">';
    foreach (share_networks() as $key => [$name, $icon, $tpl]) {
        if (!in_array($key, $enabled, true)) continue;
        $u = $canonical . (str_contains($canonical, '?') ? '&' : '?') . 'utm_source=' . $key . '&utm_medium=social&utm_campaign=share';
        $href = str_replace(['{u}', '{t}'], [rawurlencode($u), rawurlencode($title)], $tpl);
        $h .= '<a class="share-btn share-' . $key . '" href="' . e($href) . '"' . ($key === 'email' ? '' : ' target="_blank" rel="noopener nofollow"')
            . ' data-share-net="' . $key . '" aria-label="Share on ' . e($name) . '" title="' . e($name) . '">' . icon($icon, 'h-4 w-4') . '</a>';
    }
    $h .= '<button type="button" class="share-btn" data-copy="' . e($copy) . '" aria-label="Copy link" title="Copy link">' . icon('link', 'h-4 w-4') . '</button>'
        . '<button type="button" class="share-btn share-native" data-share-native hidden aria-label="More sharing options" title="More…">' . icon('share-2', 'h-4 w-4') . '</button>'
        . '</div>';
    if ($link) $h .= '<span class="share-short" title="Short link"><span class="sr-only">Short link: </span>' . e(preg_replace('~^https?://~', '', $copy)) . '</span>';
    return $h . '</div>';
}

/* ------------------------------------------------------------------ */
/* Comments                                                            */
/* ------------------------------------------------------------------ */

const COMMENT_STATUSES = ['pending' => ['Pending', 'bg-amber-100 text-amber-800'], 'approved' => ['Approved', 'bg-emerald-100 text-emerald-800'],
    'spam' => ['Spam', 'bg-red-100 text-red-700'], 'trash' => ['Trash', 'bg-gray-200 text-gray-700']];

/** Comments are open on this post (site setting, per-post switch, optional auto-close after N days). */
function comments_open(array $post): bool
{
    if (!setting('comments_enabled', true) || empty($post['allow_comments'])) return false;
    $days = (int) setting('comments_close_days', 0);
    return !$days || !$post['published_at'] || strtotime((string) $post['published_at']) > time() - $days * 86400;
}

/** Approved comments for a post as a thread: [top-level comment + 'replies' => [...]]. */
function comments_thread(int $postId): array
{
    $rows = db_all("SELECT c.*, u.name AS staff_name FROM comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.post_id = ? AND c.status = 'approved' ORDER BY c.created_at, c.id", [$postId]);
    $top = [];
    $children = [];
    foreach ($rows as $r) {
        if ($r['parent_id']) $children[(int) $r['parent_id']][] = $r;
        else $top[(int) $r['id']] = $r + ['replies' => []];
    }
    foreach ($children as $pid => $list) if (isset($top[$pid])) $top[$pid]['replies'] = $list;
    return array_values($top);
}

function comment_count(int $postId): int
{
    return (int) db_val("SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = 'approved'", [$postId]);
}

/** Plain text in, safe HTML out: paragraphs, line breaks and nofollow links. */
function comment_html(string $body): string
{
    $out = '';
    foreach (preg_split("~\n\s*\n~", trim($body)) ?: [] as $para) {
        $p = nl2br(e(trim($para)));
        $p = preg_replace_callback('~https?://[^\s<]+~i', fn($m) => '<a href="' . $m[0] . '" rel="nofollow ugc noopener" target="_blank">' . $m[0] . '</a>', $p) ?? $p;
        $out .= '<p>' . $p . '</p>';
    }
    return $out;
}

/** Why a comment looks like spam (null = it doesn't). */
function comment_spam_reason(string $body, string $name, string $email): ?string
{
    if ($r = content_spam_reason($body . ' ' . $name, (int) setting('comments_max_links', 2))) return $r;
    $text = mb_strtolower($body . ' ' . $name);
    foreach (array_filter(array_map('trim', preg_split('~[\n,]~', (string) setting('comments_blocked_words', '')) ?: [])) as $w) {
        if ($w !== '' && str_contains($text, mb_strtolower($w))) return 'Blocked word: ' . $w;
    }
    if (preg_match_all('~https?://~i', $body) > (int) setting('comments_max_links', 2)) return 'Too many links';
    if (preg_match('~https?://~i', $name)) return 'Link in name';
    if ($email !== '' && db_val("SELECT COUNT(*) FROM comments WHERE email = ? AND status = 'spam'", [$email]) >= 2) return 'Sender marked as spam before';
    return null;
}

/** Status for a new visitor comment, following the moderation setting. */
function comment_initial_status(string $email): string
{
    return match ((string) setting('comments_moderation', 'first')) {
        'none' => 'approved',
        'first' => $email !== '' && db_val("SELECT 1 FROM comments WHERE email = ? AND status = 'approved' AND user_id IS NULL LIMIT 1", [$email]) ? 'approved' : 'pending',
        default => 'pending',
    };
}

function avatar_initials(string $name): string
{
    $parts = preg_split('~\s+~', trim($name)) ?: [];
    $i = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : ''));
    return $i ?: '?';
}
