<?php
/**
 * Customer reviews: star ratings on product pages (moderated), team replies,
 * verified-customer badge, and aggregateRating structured data.
 */
declare(strict_types=1);

const REVIEW_STATUSES = ['pending' => ['Pending', 'bg-amber-100 text-amber-800'], 'approved' => ['Approved', 'bg-emerald-100 text-emerald-800'], 'rejected' => ['Rejected', 'bg-gray-200 text-gray-700']];

function reviews_enabled(): bool
{
    return (bool) setting('reviews_enabled', true);
}

/** Approved reviews (product id, or null for the whole site). */
function reviews_for(?int $productId, int $limit = 50): array
{
    $sql = "SELECT r.*, p.name AS product_name, p.slug AS product_slug FROM reviews r LEFT JOIN products p ON p.id = r.product_id WHERE r.status = 'approved'";
    $params = [];
    if ($productId !== null) { $sql .= ' AND r.product_id = ?'; $params[] = $productId; }
    return db_all($sql . ' ORDER BY r.created_at DESC LIMIT ' . max(1, $limit), $params);
}

/** ['count' => n, 'avg' => 4.6, 'dist' => [5 => n, 4 => n, ...]] */
function reviews_stats(?int $productId): array
{
    $sql = "SELECT rating, COUNT(*) n FROM reviews WHERE status = 'approved'" . ($productId !== null ? ' AND product_id = ?' : '') . ' GROUP BY rating';
    $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach (db_all($sql, $productId !== null ? [$productId] : []) as $r) $dist[(int) $r['rating']] = (int) $r['n'];
    $count = array_sum($dist);
    $avg = $count ? array_sum(array_map(fn($k, $v) => $k * $v, array_keys($dist), $dist)) / $count : 0;
    return ['count' => $count, 'avg' => round($avg, 1), 'dist' => $dist];
}

function stars_html(float $rating, string $size = 'h-4 w-4'): string
{
    static $set = 0;
    $set++;
    $h = '<span class="stars" role="img" aria-label="' . e(number_format($rating, 1)) . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        $fill = $rating >= $i ? 'full' : ($rating >= $i - 0.5 ? 'half' : 'empty');
        $h .= '<svg class="' . $size . ' star-' . $fill . '" viewBox="0 0 24 24" aria-hidden="true"><defs><linearGradient id="sh' . $set . '-' . $i . '"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="transparent"/></linearGradient></defs>'
            . '<path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.5 1.3 6.6L12 17.3l-5.9 3.2 1.3-6.6-4.9-4.5 6.6-.8z" fill="' . ($fill === 'full' ? 'currentColor' : ($fill === 'half' ? 'url(#sh' . $set . '-' . $i . ')' : 'none')) . '" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>';
    }
    return $h . '</span>';
}

/** Has this email bought from us? (a won lead or an accepted quotation) */
function review_is_verified(string $email): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    return (bool) (db_val("SELECT 1 FROM leads WHERE email = ? AND status = 'won' LIMIT 1", [$email]) ?: db_val("SELECT 1 FROM quotes WHERE email = ? AND status = 'accepted' LIMIT 1", [$email]));
}

/** Product JSON-LD additions: aggregateRating and up to 5 reviews. */
function reviews_jsonld(int $productId): array
{
    $s = reviews_stats($productId);
    if (!$s['count']) return [];
    $ld = ['aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => $s['avg'], 'reviewCount' => $s['count'], 'bestRating' => 5, 'worstRating' => 1]];
    foreach (reviews_for($productId, 5) as $r) {
        $ld['review'][] = ['@type' => 'Review', 'author' => ['@type' => 'Person', 'name' => $r['name']], 'datePublished' => substr((string) $r['created_at'], 0, 10),
            'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int) $r['rating'], 'bestRating' => 5], 'name' => $r['title'] ?: null, 'reviewBody' => $r['body']];
    }
    return $ld;
}
