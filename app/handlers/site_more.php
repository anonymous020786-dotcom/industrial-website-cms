<?php
/**
 * Public: part-number finder, downloads centre, distributor network and careers.
 */
declare(strict_types=1);
require_once __DIR__ . '/site_extra.php'; // site_render(), download gate

/* ------------------------------------------------------------------ */
/* Part-number cross-reference                                         */
/* ------------------------------------------------------------------ */

/** Published cross-references matching a part number (exact first, then "starts with"). */
function part_lookup(string $q, int $limit = 20): array
{
    $norm = part_norm($q);
    if (strlen($norm) < 3) return [];
    $rows = db_all("SELECT * FROM part_refs WHERE status = 'published' AND (ref_norm LIKE ? OR UPPER(REPLACE(REPLACE(REPLACE(our_part, '-', ''), ' ', ''), '.', '')) = ?)
        ORDER BY ref_norm = ? DESC, ref_number LIMIT {$limit}", [$norm . '%', $norm, $norm]);
    $products = array_column(products_all(), null, 'id');
    foreach ($rows as &$r) {
        $r['product'] = $products[(int) $r['product_id']] ?? null;
        $r['exact'] = $r['ref_norm'] === $norm || part_norm((string) $r['our_part']) === $norm;
    }
    return $rows;
}

function site_parts(): void
{
    $q = mb_substr(trim((string) query('q', '')), 0, 80);
    $matches = $q !== '' ? part_lookup($q) : [];
    if ($q !== '' && rate_limit(ip_bucket('part-search'), 120, 3600)) db_insert('events', ['type' => $matches ? 'part_found' : 'part_missing', 'page' => mb_substr($q, 0, 500)]);
    $meta = ['title' => $q !== '' ? 'Part number ' . $q : 'Find a part by part number', 'description' => 'Enter a part number from any brand — original equipment, competitor or old drawing — and find our equivalent crane or hydraulic part.',
        'canonical' => abs_url('parts'), 'noindex' => $q !== '', 'crumbs' => [['label' => 'Part finder', 'url' => url('parts')]]];
    site_render($meta, view('site/parts', compact('meta', 'q', 'matches')));
}

/* ------------------------------------------------------------------ */
/* Downloads centre                                                    */
/* ------------------------------------------------------------------ */

function download_url(array $d): string
{
    return url('download/file/' . (int) $d['id']);
}

function site_downloads(): void
{
    $rows = db_all("SELECT * FROM downloads WHERE status = 'published' ORDER BY sort_order, title");
    $groups = [];
    foreach ($rows as $r) $groups[$r['category']][] = $r;
    $order = ['Brochures', 'Datasheets', 'Manuals', 'CAD drawings', 'Certificates', 'Other'];
    uksort($groups, fn($a, $b) => (array_search($a, $order, true) === false ? 99 : array_search($a, $order, true)) <=> (array_search($b, $order, true) === false ? 99 : array_search($b, $order, true)));
    $sheets = array_values(array_filter(products_all(), fn($p) => !empty($p['datasheet'])));
    $meta = ['title' => 'Downloads — brochures, manuals, CAD and certificates', 'description' => 'Download product brochures, datasheets, installation and maintenance manuals, CAD drawings and quality certificates.',
        'canonical' => abs_url('downloads'), 'crumbs' => [['label' => 'Downloads', 'url' => url('downloads')]]];
    site_render($meta, view('site/downloads', compact('meta', 'groups', 'sheets')));
}

function site_download_file(string $id): void
{
    $d = ctype_digit($id) ? db_one("SELECT * FROM downloads WHERE id = ? AND status = 'published'", [(int) $id]) : null;
    if (!$d) abort(404);
    if ($d['gated'] && !download_access_ok()) redirect('catalogue?next=' . rawurlencode('download/file/' . $d['id']));
    if (rate_limit(ip_bucket('dl-count-' . $d['id']), 5, 3600)) db_query('UPDATE downloads SET downloads = downloads + 1 WHERE id = ?', [(int) $d['id']]);
    download_send((string) $d['file'], (string) $d['title'], 'download', (string) $d['title']);
}

/* ------------------------------------------------------------------ */
/* Distributor network                                                 */
/* ------------------------------------------------------------------ */

function site_distributors(): void
{
    $all = db_all("SELECT * FROM distributors WHERE status = 'published' ORDER BY region, sort_order, name");
    $regions = array_values(array_unique(array_column($all, 'region')));
    $region = in_array($r = (string) query('region', ''), $regions, true) ? $r : '';
    $groups = [];
    foreach ($all as $d) if ($region === '' || $d['region'] === $region) $groups[$d['region']][] = $d;
    $meta = ['title' => 'Distributors & dealers', 'description' => 'Find an authorised distributor near you for crane components, hydraulic cylinders and spare parts — or apply to become a distributor.',
        'canonical' => abs_url('distributors'), 'noindex' => $region !== '', 'crumbs' => [['label' => 'Distributors', 'url' => url('distributors')]]];
    site_render($meta, view('site/distributors', compact('meta', 'groups', 'regions', 'region', 'all')));
}

/* ------------------------------------------------------------------ */
/* Careers                                                             */
/* ------------------------------------------------------------------ */

function site_careers(): void
{
    $jobs = jobs_open();
    $meta = ['title' => 'Careers — jobs in manufacturing, service and engineering', 'description' => 'Join our team. Current openings in production, field service, engineering and sales.',
        'canonical' => abs_url('careers'), 'crumbs' => [['label' => 'Careers', 'url' => url('careers')]]];
    site_render($meta, view('site/careers', compact('meta', 'jobs')));
}

function site_career(string $slug): void
{
    $job = db_one('SELECT * FROM jobs WHERE slug = ?', [$slug]);
    $open = $job && $job['status'] === 'published' && (!$job['closes_at'] || $job['closes_at'] >= date('Y-m-d'));
    // Drafts: preview for the team only. A closed published opening stays reachable (410) so old links explain themselves.
    if (!$job || ($job['status'] === 'draft' && !can_preview())) abort(404);
    $types = ['Full-time' => 'FULL_TIME', 'Part-time' => 'PART_TIME', 'Contract' => 'CONTRACTOR', 'Apprenticeship' => 'OTHER', 'Internship' => 'INTERN'];
    $ld = ['@context' => 'https://schema.org', '@type' => 'JobPosting', 'title' => $job['title'], 'description' => $job['description'] ?: e((string) $job['summary']),
        'datePosted' => substr((string) $job['created_at'], 0, 10), 'employmentType' => $types[$job['job_type']] ?? 'FULL_TIME',
        'hiringOrganization' => ['@type' => 'Organization', 'name' => company('name'), 'sameAs' => abs_url('')],
        'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $job['location'] ?: trim((string) strtok(company('address'), "
"))]]];
    if ($job['closes_at']) $ld['validThrough'] = $job['closes_at'] . 'T23:59:59';
    $meta = ['title' => $job['title'] . ' — careers', 'description' => (string) ($job['summary'] ?: 'Apply for ' . $job['title'] . ' at ' . company('name') . '.'),
        'canonical' => abs_url('careers/' . $job['slug']), 'noindex' => !$open, 'jsonld' => $open ? [$ld] : [],
        'crumbs' => [['label' => 'Careers', 'url' => url('careers')], ['label' => $job['title'], 'url' => url('careers/' . $job['slug'])]]];
    site_render($meta, view('site/career', compact('meta', 'job', 'open')), $open || $job['status'] === 'draft' ? 200 : 410);
}

/* ------------------------------------------------------------------ */
/* Site search                                                         */
/* ------------------------------------------------------------------ */

function site_search_page(): void
{
    $q = mb_substr(trim((string) query('q', '')), 0, 100);
    $found = $q !== '' ? site_search($q) : ['results' => [], 'parts' => [], 'suggestion' => null];
    if ($q !== '' && rate_limit(ip_bucket('search-log'), 120, 3600)) db_insert('events', ['type' => $found['results'] || $found['parts'] ? 'search' : 'search_empty', 'page' => mb_substr($q, 0, 500)]);
    $meta = ['title' => $q !== '' ? 'Search: ' . $q : 'Search', 'description' => 'Search products, part numbers, articles, projects, downloads and jobs.',
        'canonical' => abs_url('search'), 'noindex' => true, 'crumbs' => [['label' => 'Search', 'url' => url('search')]]];
    site_render($meta, view('site/search', compact('meta', 'q') + $found));
}
