<?php
/** Icons, HTML sanitising for editor content, shortcodes and table-of-contents helpers. */
declare(strict_types=1);

/** Inline Lucide SVG icon (icon data generated into app/data/lucide.php by `npm run assets`). */
function icon(?string $name, string $class = 'h-5 w-5'): string
{
    static $icons = null;
    $icons ??= is_file(APP_DIR . '/data/lucide.php') ? require APP_DIR . '/data/lucide.php' : [];
    $inner = $icons[(string) $name] ?? $icons['circle'] ?? '';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . e($class) . '" aria-hidden="true" focusable="false">' . $inner . '</svg>';
}

function icon_exists(string $name): bool
{
    static $icons = null;
    $icons ??= is_file(APP_DIR . '/data/lucide.php') ? require APP_DIR . '/data/lucide.php' : [];
    return isset($icons[$name]);
}

function icon_names(): array
{
    $icons = is_file(APP_DIR . '/data/lucide.php') ? require APP_DIR . '/data/lucide.php' : [];
    return array_keys($icons);
}

/**
 * Allow-list HTML sanitiser for rich-text content (posts, pages, products).
 * Removes scripts, event handlers, inline styles and unsafe URLs.
 */
function sanitize_html(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') return '';

    $allowed = [
        'p' => ['class'], 'br' => [], 'hr' => [], 'h2' => ['id', 'class'], 'h3' => ['id', 'class'], 'h4' => ['id', 'class'],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [], 'mark' => [],
        'ul' => ['class'], 'ol' => ['class', 'start'], 'li' => ['class', 'data-list'],
        'a' => ['href', 'title', 'target', 'rel'], 'img' => ['src', 'alt', 'width', 'height', 'loading', 'class'],
        'blockquote' => ['class'], 'pre' => ['class'], 'code' => [], 'span' => ['class'], 'div' => ['class'],
        'table' => ['class'], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'th' => ['scope', 'colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'figure' => ['class'], 'figcaption' => [], 'iframe' => ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'loading'],
        'address' => ['class'],
    ];
    $drop = ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'svg', 'math', 'noscript', 'template'];
    $iframeHosts = ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com', 'www.google.com', 'maps.google.com'];

    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('__root');
    if (!$root) return '';

    $safeUrl = function (string $url, bool $image = false): bool {
        $url = trim(html_entity_decode($url, ENT_QUOTES));
        if ($url === '') return false;
        if (preg_match('~^(https?:)?//~i', $url) || str_starts_with($url, '/') || str_starts_with($url, '#') || !preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) return true;
        return !$image && (bool) preg_match('~^(mailto|tel):~i', $url);
    };

    $walk = function (DOMNode $node) use (&$walk, $allowed, $drop, $iframeHosts, $safeUrl): void {
        for ($child = $node->firstChild; $child !== null; $child = $next) {
            $next = $child->nextSibling;
            if ($child instanceof DOMComment) { $node->removeChild($child); continue; }
            if (!$child instanceof DOMElement) continue;
            $tag = strtolower($child->tagName);

            if (in_array($tag, $drop, true)) { $node->removeChild($child); continue; }
            if (!isset($allowed[$tag])) {
                // Unknown tag: keep its contents, drop the wrapper.
                $walk($child);
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                $ok = in_array($name, $allowed[$tag], true);
                if ($ok && in_array($name, ['href', 'src'], true)) $ok = $safeUrl($attr->value, $tag !== 'a');
                if ($ok && $name === 'class') $attr->value = preg_replace('~[^a-z0-9 _-]~i', '', $attr->value) ?? '';
                if (!$ok) $child->removeAttribute($attr->name);
            }
            if ($tag === 'iframe') {
                $host = parse_url($child->getAttribute('src'), PHP_URL_HOST);
                if (!$host || !in_array(strtolower($host), $iframeHosts, true)) { $node->removeChild($child); continue; }
                $child->setAttribute('loading', 'lazy');
            }
            if ($tag === 'a' && $child->getAttribute('target') === '_blank') $child->setAttribute('rel', 'noopener noreferrer');
            if ($tag === 'img' && !$child->hasAttribute('loading')) $child->setAttribute('loading', 'lazy');
            $walk($child);
        }
    };
    $walk($root);

    $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return trim($out);
}

/** Add ids to <h2> headings and return [html, toc] for article/legal pages. */
function add_heading_ids(string $html): array
{
    $toc = [];
    $used = [];
    $html = preg_replace_callback('~<h2([^>]*)>(.*?)</h2>~is', function ($m) use (&$toc, &$used) {
        $attrs = $m[1];
        $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES));
        if (preg_match('~\sid="([^"]+)"~', $attrs, $id)) {
            $slug = $id[1];
        } else {
            $slug = slugify($text);
            $base = $slug; $i = 2;
            while (isset($used[$slug])) $slug = $base . '-' . $i++;
            $attrs .= ' id="' . e($slug) . '"';
        }
        $used[$slug] = true;
        $toc[] = ['id' => $slug, 'text' => $text];
        return "<h2{$attrs}>{$m[2]}</h2>";
    }, $html) ?? $html;
    return [$html, $toc];
}

/** Rewrite root-relative links in stored content (/products/x) for sub-folder installs. */
function fix_internal_links(string $html): string
{
    $base = base_path();
    if ($base === '') return $html;
    return preg_replace('~(href|src)="/(?!/)~', '$1="' . $base . '/', $html) ?? $html;
}

/** Replace [shortcodes] in CMS content with live company settings. */
function render_shortcodes(string $html): string
{
    $address = array_filter(array_map('trim', explode("\n", company('address'))));
    $map = [
        '[company_name]' => e(company('name')),
        '[legal_name]' => e(company('legal_name')),
        '[privacy_email]' => e(company('privacy_email', company('email'))),
        '[email]' => e(company('email')),
        '[phone]' => e(company('phone')),
        '[phone_href]' => e(company('phone_href')),
        '[address]' => implode('<br>', array_map('e', $address)),
        '[jurisdiction]' => e(company('jurisdiction', '[Jurisdiction]')),
        '[last_updated]' => e(format_date((string) setting('legal_updated', date('Y-m-d')), 'j F Y')),
        '[year]' => date('Y'),
    ];
    if (str_contains($html, '[contact_block]')) {
        $map['[contact_block]'] = '<address class="legal-address"><strong>' . $map['[legal_name]'] . '</strong><br>' . $map['[address]']
            . '<br>Email: <a href="mailto:' . $map['[privacy_email]'] . '">' . $map['[privacy_email]'] . '</a>'
            . '<br>Phone: <a href="tel:' . $map['[phone_href]'] . '">' . $map['[phone]'] . '</a></address>';
    }
    if (str_contains($html, '[cookie_table]')) $map['[cookie_table]'] = cookie_table_html();
    // Shortcodes sit inside <p> after editing in the rich-text editor — unwrap block ones.
    $html = preg_replace('~<p>\s*(\[(?:contact_block|cookie_table)\])\s*</p>~', '$1', $html) ?? $html;
    return strtr($html, $map);
}
