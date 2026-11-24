<?php
/** Reusable public-site UI components (return HTML strings). */
declare(strict_types=1);

function breadcrumbs_html(array $items, bool $light = true): string
{
    $all = array_merge([['label' => 'Home', 'url' => url('')]], $items);
    $h = '<nav aria-label="Breadcrumb"><ol class="breadcrumbs ' . ($light ? 'text-white/70' : 'text-charcoal/60') . '">';
    foreach ($all as $i => $b) {
        $h .= $i === count($all) - 1
            ? '<li aria-current="page" class="' . ($light ? 'text-white' : 'text-navy') . '">' . e($b['label']) . '</li>'
            : '<li><a href="' . e($b['url']) . '" class="hover:underline">' . e($b['label']) . '</a>' . icon('chevron-right', 'h-3.5 w-3.5 opacity-60') . '</li>';
    }
    return $h . '</ol></nav>';
}

function page_hero(array $o): string
{
    $image = $o['image'] ?? '';
    $h = '<section class="page-hero relative isolate overflow-hidden bg-navy text-white ' . ($image ? '' : 'blueprint') . '">';
    if ($image) {
        $h .= img_tag($image, $o['alt'] ?? '', ['class' => 'absolute inset-0 -z-20 h-full w-full object-cover', 'lazy' => false]);
        $h .= '<div class="hero-overlay absolute inset-0 -z-10" aria-hidden="true"></div>';
    }
    $h .= '<div class="mx-auto max-w-site px-4 pb-14 pt-12 sm:px-6 lg:px-8 lg:pb-20 lg:pt-16">' . breadcrumbs_html($o['crumbs'] ?? []);
    $h .= '<div class="mt-10 max-w-3xl">';
    if (!empty($o['eyebrow'])) $h .= '<p class="eyebrow eyebrow-light hero-in">' . e($o['eyebrow']) . '</p>';
    $h .= '<h1 class="hero-in mt-4 font-display text-5xl font-bold uppercase leading-[0.95] tracking-tight sm:text-6xl lg:text-7xl" style="--d:80ms">' . e($o['title']) . '</h1>';
    if (!empty($o['lead'])) $h .= '<p class="hero-in mt-6 max-w-2xl text-lg leading-relaxed text-white/80" style="--d:160ms">' . e($o['lead']) . '</p>';
    if (!empty($o['meta'])) $h .= '<div class="hero-in mt-6" style="--d:200ms">' . $o['meta'] . '</div>';
    return $h . '</div></div></section>';
}

function section_head(string $eyebrow, string $title, string $lead = '', bool $light = false, bool $center = false): string
{
    return '<div class="' . ($center ? 'mx-auto text-center ' : '') . 'max-w-2xl" data-reveal>'
        . '<p class="eyebrow ' . ($light ? 'eyebrow-light' : '') . ($center ? ' justify-center' : '') . '">' . e($eyebrow) . '</p>'
        . '<h2 class="section-title mt-4 ' . ($light ? 'text-white' : '') . '">' . e($title) . '</h2>'
        . ($lead ? '<p class="mt-5 text-lg leading-relaxed ' . ($light ? 'text-white/70' : 'text-charcoal/70') . '">' . e($lead) . '</p>' : '')
        . '</div>';
}

function reveal_delay(int $i, int $cols = 3): string
{
    $d = $i % $cols;
    return $d ? ' data-reveal-delay="' . min($d, 2) . '"' : '';
}

function product_card(array $p, int $i = 0): string
{
    $cat = product_category_name((int) $p['category_id']);
    $num = pad2((int) $p['sort_order'] + 1);
    return '<article class="product-card group" data-category="' . (int) $p['category_id'] . '" data-search="' . e(mb_strtolower($p['name'] . ' ' . $p['summary'] . ' ' . $cat)) . '" data-reveal' . reveal_delay($i) . '>'
        . '<a href="' . e(product_url($p)) . '" class="flex h-full flex-col">'
        . '<div class="img-frame aspect-[4/3]" data-fallback-label="' . e($p['name']) . '">'
        . ($cat ? '<span class="product-tag">' . e($cat) . '</span>' : '')
        . img_tag($p['image'], (string) $p['image_alt'], ['widths' => [480, 800, 1200], 'sizes' => '(min-width:1024px) 33vw, (min-width:640px) 50vw, 100vw'])
        . '</div><div class="flex flex-1 flex-col p-7">'
        . '<span class="font-display text-sm font-semibold tracking-[0.2em] text-safety">' . $num . '</span>'
        . '<h3 class="mt-2 font-display text-[26px] font-semibold uppercase leading-tight tracking-wide text-navy">' . e($p['name']) . '</h3>'
        . '<p class="mt-3 flex-1 leading-relaxed text-charcoal/70">' . e($p['summary']) . '</p>'
        . '<span class="link-arrow mt-6 text-steel group-hover:text-navy">View Details ' . icon('arrow-right', 'h-4 w-4') . '</span>'
        . '</div></a></article>';
}

function industry_tile(array $ind, int $i = 0): string
{
    return '<a href="' . e(url('industries#' . $ind['slug'])) . '" class="industry-tile" data-reveal' . reveal_delay($i) . ' data-fallback-label="' . e($ind['name']) . '">'
        . img_tag($ind['image'], (string) $ind['image_alt'], ['widths' => [480, 800, 1200], 'sizes' => '(min-width:1024px) 33vw, (min-width:640px) 50vw, 100vw'])
        . '<div class="industry-body"><span class="industry-bar" aria-hidden="true"></span>'
        . '<h3 class="flex items-center gap-3 font-display text-2xl font-semibold uppercase leading-tight tracking-wide">' . icon($ind['icon'], 'h-5 w-5 shrink-0 text-safety') . e($ind['name']) . '</h3>'
        . '<p class="industry-desc text-sm leading-relaxed text-white/80">' . e($ind['summary']) . '</p></div></a>';
}

function post_card(array $p, int $i = 0, bool $large = false): string
{
    $meta = '<span>' . e(format_date($p['published_at'])) . '</span><span aria-hidden="true">·</span><span>' . (int) $p['reading_minutes'] . ' min read</span>';
    return '<article class="post-card group' . ($large ? ' post-card-lg' : '') . '" data-reveal' . reveal_delay($i) . '>'
        . '<a href="' . e(post_url($p)) . '" class="flex h-full flex-col">'
        . '<div class="img-frame ' . ($large ? 'aspect-[16/9] lg:aspect-auto lg:h-full' : 'aspect-[16/10]') . '" data-fallback-label="' . e($p['category_name'] ?? 'Article') . '">'
        . img_tag($p['featured_image'], (string) ($p['featured_alt'] ?: $p['title']), ['widths' => [480, 800, 1200], 'sizes' => $large ? '(min-width:1024px) 60vw, 100vw' : '(min-width:1024px) 33vw, (min-width:640px) 50vw, 100vw'])
        . '</div><div class="flex flex-1 flex-col p-6' . ($large ? ' lg:p-10' : '') . '">'
        . (!empty($p['category_name']) ? '<span class="text-xs font-bold uppercase tracking-[0.18em] text-steel">' . e($p['category_name']) . '</span>' : '')
        . '<h3 class="mt-3 font-display ' . ($large ? 'text-3xl lg:text-4xl' : 'text-2xl') . ' font-semibold uppercase leading-tight tracking-wide text-navy group-hover:text-steel">' . e($p['title']) . '</h3>'
        . '<p class="mt-3 flex-1 text-[15px] leading-relaxed text-charcoal/70">' . e(str_limit($p['excerpt'], $large ? 240 : 150)) . '</p>'
        . '<p class="mt-5 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-charcoal/50">' . $meta . '</p>'
        . '</div></a></article>';
}

/** Link for editor-entered URLs: site paths, https://, tel: and mailto: only (no javascript: etc.). */
function link_href(string $u): string
{
    $u = trim($u);
    if (preg_match('~^(https?://|tel:|mailto:)~i', $u)) return $u;
    if (str_starts_with($u, '#')) return $u;
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $u)) return url('');
    return menu_href($u);
}

function testimonials_published(int $limit = 6): array
{
    try {
        return db_all("SELECT t.*, p.slug AS project_slug FROM testimonials t LEFT JOIN projects p ON p.id = t.project_id AND p.status = 'published'
            WHERE t.status = 'published' AND t.permission = 1 ORDER BY t.sort_order, t.id DESC LIMIT " . max(1, $limit));
    } catch (Throwable) { return []; }
}

function testimonial_card(array $t, int $i = 0): string
{
    $who = array_filter([$t['role'] ?? null, $t['company'] ?? null]);
    $stars = (int) ($t['rating'] ?? 0);
    return '<figure class="quote-card" data-reveal' . reveal_delay($i) . '>'
        . ($stars ? '<p class="quote-stars" aria-label="Rated ' . $stars . ' out of 5">' . str_repeat(icon('star', 'h-4 w-4'), $stars) . '</p>' : '')
        . '<blockquote class="quote-text">' . e($t['quote']) . '</blockquote>'
        . '<figcaption class="quote-by">'
        . (!empty($t['photo']) ? '<span class="quote-avatar">' . img_tag($t['photo'], '', ['class' => 'h-full w-full object-cover', 'widths' => [480], 'sizes' => '48px']) . '</span>'
            : '<span class="quote-avatar quote-initial" aria-hidden="true">' . e(mb_strtoupper(mb_substr((string) $t['author'], 0, 1))) . '</span>')
        . '<span><span class="block font-semibold text-white">' . e($t['author']) . '</span>' . ($who ? '<span class="block text-sm text-white/70">' . e(implode(', ', $who)) . '</span>' : '')
        . (!empty($t['project_slug']) ? '<a class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-safety hover:underline" href="' . e(url('projects/' . $t['project_slug'])) . '">Read the case study ' . icon('arrow-right', 'h-3.5 w-3.5') . '</a>' : '')
        . '</span></figcaption></figure>';
}

function project_card(array $p, int $i = 0): string
{
    $meta = array_filter([$p['industry'] ?? null, $p['location'] ?? null, !empty($p['completed_at']) ? date('Y', strtotime((string) $p['completed_at'])) : null]);
    return '<article class="post-card group" data-reveal' . reveal_delay($i) . '>'
        . '<a href="' . e(url('projects/' . $p['slug'])) . '" class="flex h-full flex-col">'
        . '<div class="img-frame aspect-[16/10]" data-fallback-label="' . e($p['industry'] ?? 'Project') . '">'
        . img_tag($p['image'], (string) ($p['image_alt'] ?: $p['title']), ['widths' => [480, 800, 1200], 'sizes' => '(min-width:1024px) 33vw, (min-width:640px) 50vw, 100vw'])
        . '</div><div class="flex flex-1 flex-col p-6">'
        . ($meta ? '<span class="text-xs font-bold uppercase tracking-[0.18em] text-steel">' . e(implode(' · ', $meta)) . '</span>' : '')
        . '<h3 class="mt-3 font-display text-2xl font-semibold uppercase leading-tight tracking-wide text-navy group-hover:text-steel">' . e($p['title']) . '</h3>'
        . '<p class="mt-3 flex-1 text-[15px] leading-relaxed text-charcoal/70">' . e(str_limit((string) $p['summary'], 170)) . '</p>'
        . '<span class="link-arrow mt-5 text-steel group-hover:text-navy">Read the case study ' . icon('arrow-right', 'h-4 w-4') . '</span>'
        . '</div></a></article>';
}

/** Published projects, newest first. */
function projects_published(?int $industryId = null, int $limit = 0): array
{
    $sql = "SELECT p.*, i.name AS industry, i.slug AS industry_slug FROM projects p LEFT JOIN industries i ON i.id = p.industry_id WHERE p.status = 'published'"
        . ($industryId ? ' AND p.industry_id = ' . (int) $industryId : '') . ' ORDER BY p.sort_order, p.completed_at DESC, p.id DESC' . ($limit ? ' LIMIT ' . $limit : '');
    try { return db_all($sql); } catch (Throwable) { return []; }
}

/** Certificates that are published and not expired (shown on the Quality page). */
function certificates_valid(): array
{
    try {
        return db_all("SELECT * FROM certificates WHERE status = 'published' AND (expires_at IS NULL OR expires_at >= CURDATE()) ORDER BY sort_order, name");
    } catch (Throwable) { return []; }
}

function stats_band(): string
{
    $stats = setting('stats', []);
    if (!$stats) return '';
    $h = '<section class="blueprint bg-steel py-16 text-white lg:py-20" aria-label="Company figures"><div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8"><dl class="grid grid-cols-2 gap-y-10 lg:grid-cols-4">';
    foreach ($stats as $s) {
        $h .= '<div class="stat" data-reveal><dt class="sr-only">' . e($s['label'] ?? '') . '</dt><dd>'
            . '<span class="stat-value"><span data-count="' . (int) ($s['value'] ?? 0) . '">' . (int) ($s['value'] ?? 0) . '</span><span class="suffix">' . e($s['suffix'] ?? '') . '</span></span>'
            . '<span class="stat-label block">' . e($s['label'] ?? '') . '</span></dd></div>';
    }
    $h .= '</dl>';
    if ($note = (string) setting('stats_note', '')) $h .= '<p class="mt-10 text-xs uppercase tracking-widest text-white/50">' . e($note) . '</p>';
    return $h . '</div></section>';
}

function process_steps(bool $light = false): string
{
    $steps = setting('process', []);
    $h = '<ol class="process grid gap-px sm:grid-cols-2 lg:grid-cols-' . max(1, min(5, count($steps))) . ($light ? ' process-light' : '') . '">';
    foreach ($steps as $i => $s) {
        $h .= '<li class="process-step" data-reveal' . reveal_delay($i, 5) . '><div class="flex items-center justify-between">'
            . '<span class="process-icon">' . icon($s['icon'] ?? 'circle', 'h-6 w-6') . '</span>'
            . '<span class="font-display text-sm font-semibold tracking-[0.2em] ' . ($light ? 'text-white/40' : 'text-charcoal/35') . '">STEP ' . pad2($i + 1) . '</span></div>'
            . '<h3 class="mt-6 font-display text-xl font-semibold uppercase tracking-wide ' . ($light ? 'text-white' : 'text-navy') . '">' . e($s['title'] ?? '') . '</h3>'
            . '<p class="mt-2 text-sm leading-relaxed ' . ($light ? 'text-white/65' : 'text-charcoal/65') . '">' . e($s['text'] ?? '') . '</p></li>';
    }
    return $h . '</ol>';
}

function cta_band(?string $title = null, ?string $text = null): string
{
    $title = $title ?: 'Looking for Reliable Industrial Components?';
    $text = $text ?: "Let's discuss your engineering requirements and find the right manufacturing solution for your business.";
    $bg = (string) setting('cta_background', 'https://images.unsplash.com/photo-1590496793929-36417d3117de?auto=format&fit=crop&w=1600&q=70');
    return '<section class="relative isolate overflow-hidden bg-navy py-20 text-white lg:py-24">'
        . ($bg ? img_tag($bg, '', ['class' => 'absolute inset-0 -z-20 h-full w-full object-cover']) : '')
        . '<div class="absolute inset-0 -z-10 bg-navy/85" aria-hidden="true"></div>'
        . '<div class="mx-auto max-w-site px-4 sm:px-6 lg:px-8"><div class="grid items-end gap-10 lg:grid-cols-12" data-reveal>'
        . '<div class="lg:col-span-8"><h2 class="font-display text-4xl font-bold uppercase leading-none tracking-tight sm:text-5xl lg:text-6xl">' . e($title) . '</h2>'
        . '<p class="mt-6 max-w-2xl text-lg leading-relaxed text-white/75">' . e($text) . '</p></div>'
        . '<div class="flex flex-col gap-3 sm:flex-row lg:col-span-4 lg:flex-col">'
        . '<a href="' . e(url('quote')) . '" class="btn btn-primary btn-lg justify-center">Request a Quote ' . icon('arrow-right', 'btn-arrow h-5 w-5') . '</a>'
        . '<a href="' . e(url('contact')) . '" class="btn btn-ghost-light btn-lg justify-center">Contact Our Team</a>'
        . '</div></div></div></section>';
}

/* ------------------------------ Forms ------------------------------ */

function enquiry_options(string $selected = ''): string
{
    $opts = array_merge(array_column(products_all(), 'name'), setting('enquiry_options', []) ?: []);
    $h = '';
    foreach ($opts as $o) $h .= '<option value="' . e($o) . '"' . ($o === $selected ? ' selected' : '') . '>' . e($o) . '</option>';
    return $h;
}

function form_field(array $o): string
{
    $id = $o['id'];
    $type = $o['type'] ?? 'text';
    $req = !empty($o['required']) ? ' required' : '';
    $ac = !empty($o['autocomplete']) ? ' autocomplete="' . e($o['autocomplete']) . '"' : '';
    $ph = !empty($o['placeholder']) ? ' placeholder="' . e($o['placeholder']) . '"' : '';
    $desc = ' aria-describedby="err-' . $id . '"';
    $control = match ($type) {
        'select' => '<select id="' . $id . '" name="' . $id . '"' . $req . $desc . '><option value="">Select an option</option>' . ($o['options'] ?? '') . '</select>',
        'textarea' => '<textarea id="' . $id . '" name="' . $id . '" rows="' . (int) ($o['rows'] ?? 5) . '"' . $req . $ph . $desc . '></textarea>',
        default => '<input id="' . $id . '" name="' . $id . '" type="' . e($type) . '"' . $req . $ac . $ph . $desc . (($type === 'number') ? ' min="1"' : '') . (isset($o['value']) && $o['value'] !== '' ? ' value="' . e((string) $o['value']) . '"' : '') . '>',
    };
    if (!empty($o['error'])) $o['error_html'] = e((string) $o['error']);
    return '<div class="field' . (!empty($o['full']) ? ' sm:col-span-2' : '') . (!empty($o['error']) ? ' has-error' : '') . '"><label for="' . $id . '">' . e($o['label']) . (!empty($o['required']) ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>'
        . $control . '<p class="field-error" id="err-' . $id . '">' . ($o['error_html'] ?? '') . '</p></div>';
}

function consent_field(): string
{
    return '<div class="field sm:col-span-2"><label class="consent"><input type="checkbox" id="consent" name="consent" value="1" required aria-describedby="err-consent">'
        . '<span>I agree that ' . e(company('name')) . ' may use the details I submit to respond to my enquiry, as described in the <a href="' . e(url('privacy-policy')) . '" class="font-semibold text-steel underline underline-offset-2">Privacy Policy</a>. <span class="req" aria-hidden="true">*</span></span></label>'
        . '<p class="field-error" id="err-consent"></p></div>';
}

/** Hidden anti-spam + attribution fields shared by both public forms */
function form_hidden(string $form): string
{
    $h = '<input type="hidden" name="form" value="' . e($form) . '"><input type="hidden" name="_ft" value="' . e(form_token()) . '">'
        . '<input type="hidden" name="_page" value=""><input type="hidden" name="_landing" value=""><input type="hidden" name="_referrer" value="">'
        . '<div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
    if (turnstile_enabled()) {
        $h .= '<div class="cf-turnstile sm:col-span-2" data-sitekey="' . e(setting('turnstile_site_key')) . '" data-theme="light"></div>';
    }
    return $h;
}

function form_success(string $id): string
{
    return '<div id="' . $id . '" class="form-success" hidden tabindex="-1" role="status">'
        . '<span class="text-safety">' . icon('circle-check', 'h-10 w-10') . '</span>'
        . '<h3 class="mt-5 font-display text-3xl font-semibold uppercase tracking-wide text-navy">' . e(setting('form_success_title', 'Thank you — message received')) . '</h3>'
        . '<p class="mt-3 leading-relaxed text-charcoal/75">Thank you, <span data-success-name></span>. ' . e(setting('form_success_text', 'Our team will review your enquiry and get back to you within one business day.')) . '</p>'
        . '<button type="button" class="btn btn-outline mt-8" data-form-reset>Send another enquiry</button></div>';
}

function form_alert(): string
{
    return '<div class="form-alert" role="alert" hidden data-form-alert></div>';
}
