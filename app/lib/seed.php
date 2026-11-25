<?php
/** Database installation + demo content seeding (used by web installer and CLI). */
declare(strict_types=1);

function install_schema(): void
{
    $sql = (string) file_get_contents(APP_ROOT . '/database/schema.sql');
    foreach (array_filter(array_map('trim', preg_split('~;\s*\n~', $sql) ?: [])) as $stmt) {
        $lines = array_filter(explode("\n", $stmt), fn($l) => !str_starts_with(trim($l), '--'));
        $stmt = trim(implode("\n", $lines));
        if ($stmt !== '') db()->exec($stmt);
    }
}

function seed_all(string $siteUrl = ''): array
{
    $s = json_decode((string) file_get_contents(APP_ROOT . '/database/seed/site.json'), true);
    $c = $s['company'];
    $report = [];

    /* ---------------- Settings ---------------- */
    $settings = [
        'company_name' => $c['name'], 'company_legal_name' => $c['legalName'], 'company_short_name' => $c['shortName'], 'company_logo_sub' => $c['logoSub'],
        'company_tagline' => $c['tagline'], 'company_description' => $c['description'], 'company_registration' => $c['registration'],
        'company_jurisdiction' => $c['jurisdiction'], 'company_phone' => $c['phone'], 'company_phone_href' => $c['phoneHref'], 'company_email' => $c['email'],
        'company_privacy_email' => $c['privacyEmail'], 'company_address' => implode("\n", $c['address']),
        'company_hours' => array_map(fn($h) => ['day' => $h[0], 'time' => $h[1]], $c['hours']),
        'social_links' => array_map(fn($x) => ['label' => $x['label'], 'icon' => $x['icon'], 'url' => $x['url'] === '#' ? '' : $x['url']], $c['social']),
        'site_url' => $siteUrl, 'search_indexing' => true, 'site_language' => 'en', 'header_cta_label' => 'Get a Quote',
        'default_og_image' => 'assets/images/og-default.png',
        'menu_header' => [
            ['label' => 'Home', 'url' => '', 'mega' => false], ['label' => 'About Us', 'url' => 'about', 'mega' => false],
            ['label' => 'Products', 'url' => 'products', 'mega' => true], ['label' => 'Industries', 'url' => 'industries', 'mega' => false],
            ['label' => 'Manufacturing', 'url' => 'manufacturing', 'mega' => false], ['label' => 'Quality', 'url' => 'quality', 'mega' => false],
            ['label' => 'Blog', 'url' => 'blog', 'mega' => false], ['label' => 'Contact', 'url' => 'contact', 'mega' => false],
        ],
        'menu_footer' => [
            ['label' => 'About Us', 'url' => 'about'], ['label' => 'Industries', 'url' => 'industries'], ['label' => 'Manufacturing', 'url' => 'manufacturing'],
            ['label' => 'Quality', 'url' => 'quality'], ['label' => 'Blog', 'url' => 'blog'], ['label' => 'Contact', 'url' => 'contact'], ['label' => 'Request a Quote', 'url' => 'quote'],
        ],
        'stats' => $s['stats'],
        'stats_note' => 'Figures shown are illustrative placeholders for this demo website.',
        'process' => array_map(fn($p) => ['icon' => $p['icon'], 'title' => $p['name'], 'text' => $p['text']], $s['process']),
        'enquiry_options' => $s['extraEnquiryOptions'],
        'footer_cta_text' => 'Have a drawing or part number?',
        'footer_note' => 'Demo website — company details, figures, specifications and images are placeholders.',
        'form_success_title' => 'Thank you — message received',
        'form_success_text' => 'Our team will review your enquiry and get back to you within one business day.',
        'cookie_banner_enabled' => true, 'cookie_banner_title' => 'Your privacy',
        'cookie_banner_text' => 'We use essential storage to run this website and, with your consent, cookies for analytics and live chat.',
        'legal_updated' => $s['legalUpdated'], 'legal_template_notice' => true,
        'lead_notify_enabled' => true, 'lead_notify_emails' => '', 'lead_autoreply_enabled' => false,
        'lead_autoreply_subject' => 'We received your enquiry',
        'lead_autoreply_text' => "Thank you for contacting us. Our team has received your enquiry and will respond within one business day.\n\nIf your request is urgent, please call us directly.",
        'mail_from_name' => $c['name'], 'mail_from_email' => $c['email'], 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
        'whatsapp_enabled' => false, 'whatsapp_message' => 'Hello, I would like to enquire about your products.', 'whatsapp_label' => 'Chat on WhatsApp', 'whatsapp_position' => 'right',
        'tawk_require_consent' => true,
    ];
    foreach ($settings as $k => $v) {
        if (db_val('SELECT 1 FROM settings WHERE `key` = ?', [$k])) continue; // never overwrite existing settings
        setting_set($k, $v);
    }
    $report[] = count($settings) . ' settings';

    /* ---------------- Products & industries ---------------- */
    $catIds = [];
    foreach ($s['productCategories'] as $pc) {
        $id = db_val('SELECT id FROM product_categories WHERE slug = ?', [$pc['id']]);
        $catIds[$pc['id']] = $id ?: db_insert('product_categories', ['name' => $pc['label'], 'slug' => $pc['id'], 'sort_order' => $pc['sort']]);
    }
    $prodIds = [];
    foreach ($s['products'] as $p) {
        $id = db_val('SELECT id FROM products WHERE slug = ?', [$p['slug']]);
        $prodIds[$p['slug']] = $id ?: db_insert('products', [
            'category_id' => $catIds[$p['category']] ?? null, 'name' => $p['name'], 'slug' => $p['slug'], 'icon' => $p['icon'], 'image' => $p['image'], 'image_alt' => $p['alt'],
            'summary' => $p['summary'], 'overview' => $p['overview'], 'features' => json_encode($p['features']), 'applications' => json_encode($p['applications']),
            'specs' => json_encode(array_map(fn($x) => ['label' => $x[0], 'value' => $x[1]], $p['specs'])), 'sort_order' => $p['sort'],
        ]);
    }
    foreach ($s['industries'] as $i) {
        if (db_val('SELECT id FROM industries WHERE slug = ?', [$i['slug']])) continue;
        db_insert('industries', ['name' => $i['name'], 'slug' => $i['slug'], 'icon' => $i['icon'], 'image' => $i['image'], 'image_alt' => $i['alt'],
            'summary' => $i['summary'], 'body' => $i['body'], 'product_ids' => json_encode(array_values(array_filter(array_map(fn($sl) => (int) ($prodIds[$sl] ?? 0), $i['products'])))), 'sort_order' => $i['sort']]);
    }
    $report[] = count($s['products']) . ' products, ' . count($s['industries']) . ' industries';

    /* ---------------- Pages ---------------- */
    $img = fn($id) => "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=1600&q=80";
    $pages = [
        ['home', 'Home', 'home', ['Crane Components & Hydraulic Systems', 'Engineering Strength. Powering Performance.', 'Delivering precision-engineered crane components and advanced hydraulic solutions designed for demanding industrial applications.', $img('1578575437130-527eed3abbec')],
            ['meta_title' => $c['name'] . ' | Crane Components & Hydraulic Systems Manufacturer', 'meta_description' => 'Precision-engineered crane spare parts, hydraulic cylinders, power packs, pumps and custom hydraulic systems for construction, mining, ports and manufacturing.']],
        ['about', 'About Us', 'about', ['About Us', 'Built on Engineering Excellence', 'An industrial engineering and manufacturing partner for crane components and hydraulic systems.', $img('1504328345606-18bbc8c9d7d1')],
            ['meta_title' => 'About Us — Crane & Hydraulic Engineering', 'meta_description' => 'Meet the engineering and manufacturing team behind our crane components, hydraulic cylinders and custom hydraulic systems — machined, assembled and tested in-house.', 'content' => '<p>[company_name] designs and manufactures crane components, hydraulic cylinders, power packs and custom hydraulic systems for customers across construction, mining, ports, logistics and manufacturing.</p><p>We combine engineering, machining, fabrication, assembly and testing under one roof. That control lets us respond quickly to urgent breakdowns, reverse-engineer obsolete parts and deliver engineered-to-order systems with full documentation.</p><p>Our customers range from plant maintenance teams who need a critical spare part tomorrow, to OEMs who need a dependable manufacturing partner for repeat production.</p>']],
        ['products', 'Products', 'products', ['Products & Solutions', 'Components Engineered for Heavy Duty', 'Crane components and hydraulic products manufactured to specification, inspected and tested before dispatch.', $img('1537462715879-360eeb61a0ad')],
            ['meta_title' => 'Products & Solutions', 'meta_description' => 'Crane spare parts, hydraulic cylinders, power packs, pumps & motors, crane wheels and custom hydraulic systems.']],
        ['industries', 'Industries', 'industries', ['Industries We Serve', 'Trusted Where Equipment Works Hardest', 'Components and hydraulic systems engineered for the conditions of each industry.', $img('1494412574643-ff11b0a5c1c3')],
            ['meta_title' => 'Industries We Serve', 'meta_description' => 'Crane parts and hydraulic systems for construction, mining, material handling, ports and logistics, manufacturing plants and industrial OEMs — built for each duty cycle.']],
        ['manufacturing', 'Manufacturing', 'manufacturing', ['Manufacturing', 'From Raw Steel to Tested Assembly', 'Machining, fabrication, assembly and testing under one roof — so quality and lead times stay in our control.', $img('1504917595217-d4dc5ebe6122')],
            ['meta_title' => 'Manufacturing Capabilities', 'meta_description' => 'CNC machining, fabrication, welding, assembly and pressure testing under one roof — how we control quality and lead times for crane and hydraulic components.', 'data' => [
                'capabilities' => array_map(fn($x) => ['icon' => $x['icon'], 'name' => $x['name'], 'text' => $x['text'], 'points' => $x['points']], $s['capabilities']),
                'equipment' => array_map(fn($x) => ['label' => $x[0], 'value' => $x[1]], $s['equipment']),
            ]]],
        ['quality', 'Quality', 'quality', ['Quality & Reliability', 'Tested Before It Ever Carries a Load', 'Quality is designed in from the first drawing and verified at every stage of production.', $img('1581092580497-e0d23cbdf1dc')],
            ['meta_title' => 'Quality & Reliability', 'meta_description' => 'Dimensional inspection, material checks, pressure and leak testing and full documentation: how every crane component and hydraulic product is verified before dispatch.']],
        ['blog', 'Blog', 'blog', ['Insights & Technical Guides', 'Engineering Knowledge Hub', 'Practical guides on hydraulic cylinders, crane components, maintenance and manufacturing from our engineering team.', $img('1581092160562-40aa08e78837')],
            ['meta_title' => 'Blog — Hydraulic & Crane Engineering Guides', 'meta_description' => 'Practical articles on hydraulic cylinders, power packs, pumps, crane parts and maintenance from experienced industrial engineers.']],
        ['contact', 'Contact Us', 'contact', ['Contact', 'Let’s Talk Engineering', 'Questions about a part, a repair or a project? Our team is ready to help.', ''],
            ['meta_title' => 'Contact Our Engineering Team', 'meta_description' => 'Talk to our engineers about crane spare parts, hydraulic cylinder repair or a custom hydraulic system. Call, email or WhatsApp — we reply within one business day.', 'data' => ['faq' => array_map(fn($f) => ['q' => $f[0], 'a' => $f[1]], $s['faq'])]]],
        ['quote', 'Request a Quote', 'quote', ['Request a Quote', 'Tell Us What You Need', 'The more detail you share, the faster and more accurate our quotation. Drawings, part numbers and photos all help.', ''], ['meta_title' => 'Request a Quote', 'meta_description' => 'Request a quotation for crane components, hydraulic cylinders, power packs or custom hydraulic systems. Upload drawings or photos for a faster, more accurate price.']],
        ['sitemap', 'Sitemap', 'sitemap', ['', 'Sitemap', '', ''], ['noindex' => 0, 'meta_title' => 'Sitemap', 'meta_description' => 'Every page on our website in one place: products, industries, manufacturing and quality information, technical blog articles and company policies.']],
    ];
    foreach ($s['legalPages'] as $lp) {
        $pages[] = [$lp['slug'], $lp['title'], 'legal', ['', $lp['title'], '', ''], ['content' => $lp['content'], 'meta_description' => str_replace(['[company_name]'], [$c['name']], $lp['description'])]];
    }
    foreach ($pages as [$slug, $title, $tpl, [$eb, $ht, $lead, $hero], $extra]) {
        if (db_val('SELECT id FROM pages WHERE slug = ?', [$slug])) continue;
        $data = array_merge(template_defaults($tpl), $extra['data'] ?? []);
        unset($extra['data']);
        db_insert('pages', array_merge([
            'slug' => $slug, 'title' => $title, 'template' => $tpl, 'status' => 'published', 'is_system' => 1,
            'hero_eyebrow' => $eb ?: null, 'hero_title' => $ht ?: null, 'hero_lead' => $lead ?: null, 'hero_image' => $hero ?: null,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], $extra));
    }
    $report[] = count($pages) . ' pages';

    /* ---------------- Blog ---------------- */
    $report[] = seed_posts();
    return $report;
}

/** Import blog drafts from database/seed/posts.json (generated by `npm run posts`). Existing slugs are skipped. */
function seed_posts(?int $authorId = null, bool $refreshDrafts = false): string
{
    $file = APP_ROOT . '/database/seed/posts.json';
    if (!is_file($file)) return '0 posts (posts.json not found)';
    $data = json_decode((string) file_get_contents($file), true);
    $catIds = [];
    foreach ($data['categories'] as $i => $cat) {
        $id = db_val("SELECT id FROM categories WHERE type = 'post' AND slug = ?", [$cat['slug']]);
        $catIds[$cat['slug']] = $id ?: db_insert('categories', ['type' => 'post', 'name' => $cat['name'], 'slug' => $cat['slug'], 'description' => $cat['description'], 'sort_order' => $i,
            'meta_title' => $cat['name'] . ' — Guides & Articles', 'meta_description' => mb_substr($cat['description'], 0, 300)]);
    }
    $n = 0;
    $refreshed = 0;
    foreach ($data['posts'] as $p) {
        $existing = db_one('SELECT id, status FROM posts WHERE slug = ?', [$p['slug']]);
        if ($existing) {
            // --refresh: update content of posts that are still drafts (never touches published or scheduled posts)
            if ($refreshDrafts && $existing['status'] === 'draft') {
                db_update('posts', ['title' => $p['title'], 'excerpt' => $p['excerpt'], 'content' => sanitize_html($p['html']),
                    'meta_title' => $p['metaTitle'], 'meta_description' => $p['metaDescription'], 'focus_keyword' => $p['keyword'],
                    'tags' => implode(', ', $p['tags']), 'reading_minutes' => reading_minutes($p['html'])], (int) $existing['id']);
                $refreshed++;
            }
            continue;
        }
        db_insert('posts', [
            'title' => $p['title'], 'slug' => $p['slug'], 'excerpt' => $p['excerpt'], 'content' => sanitize_html($p['html']),
            'featured_image' => $p['image'], 'featured_alt' => $p['imageAlt'], 'category_id' => $catIds[$p['category']] ?? null, 'tags' => implode(', ', $p['tags']),
            'author_id' => $authorId, 'status' => 'draft', 'published_at' => null,
            'meta_title' => $p['metaTitle'], 'meta_description' => $p['metaDescription'], 'focus_keyword' => $p['keyword'],
            'reading_minutes' => reading_minutes($p['html']),
        ]);
        $n++;
    }
    return "{$n} blog drafts in " . count($data['categories']) . ' categories' . ($refreshDrafts ? ", {$refreshed} existing drafts refreshed" : '');
}

function create_admin(string $name, string $email, string $password): int
{
    $email = strtolower(trim($email));
    $id = db_val('SELECT id FROM users WHERE email = ?', [$email]);
    if ($id) {
        db_update('users', ['name' => $name, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'admin', 'active' => 1], (int) $id);
        return (int) $id;
    }
    return db_insert('users', ['name' => $name, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'admin']);
}

function write_config(array $db, string $baseUrl = 'auto', bool $debug = false): void
{
    $cfg = [
        'db' => $db,
        'app_key' => bin2hex(random_bytes(32)),
        'debug' => $debug,
        'base_path' => $baseUrl,
        'timezone' => date_default_timezone_get(),
    ];
    $php = "<?php\n// Generated by the installer on " . date('Y-m-d H:i') . ". Keep this file private — never commit it.\nreturn " . var_export($cfg, true) . ";\n";
    if (file_put_contents(APP_DIR . '/config.php', $php) === false) throw new RuntimeException('Cannot write app/config.php — check folder permissions.');
    $GLOBALS['__config'] = $cfg;
}

function finish_install(): void
{
    @mkdir(STORAGE_DIR, 0775, true);
    file_put_contents(STORAGE_DIR . '/installed.lock', date('c'));
}

/* ------------------------------------------------------------------ */
/* Migrations for sites installed before newer columns existed.        */
/* Portable across MySQL and MariaDB (checks information_schema).      */
/* ------------------------------------------------------------------ */

function column_exists(string $table, string $column): bool
{
    return (bool) db_val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);
}

function index_exists(string $table, string $index): bool
{
    return (bool) db_val('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?', [$table, $index]);
}

/** @return string[] list of changes applied */
function run_migrations(): array
{
    install_schema(); // new tables (CREATE TABLE IF NOT EXISTS)
    $changes = [];
    $columns = [
        'users' => [
            'username' => 'VARCHAR(60) NULL AFTER email',
            'backup_email' => 'VARCHAR(190) NULL AFTER username',
            'backup_email_verified_at' => 'DATETIME NULL AFTER backup_email',
            'password_changed_at' => 'DATETIME NULL AFTER backup_email_verified_at',
            'totp_secret' => 'TEXT NULL AFTER password_changed_at',
            'totp_enabled_at' => 'DATETIME NULL AFTER totp_secret',
            'avatar' => 'VARCHAR(500) NULL AFTER totp_enabled_at',
            'notify_prefs' => 'TEXT NULL AFTER avatar',
            'session_salt' => 'VARCHAR(32) NULL AFTER notify_prefs',
            'telegram_chat_id' => 'VARCHAR(32) NULL AFTER session_salt',
            'telegram_username' => 'VARCHAR(64) NULL AFTER telegram_chat_id',
            'telegram_linked_at' => 'DATETIME NULL AFTER telegram_username',
            'telegram_prefs' => 'TEXT NULL AFTER telegram_linked_at',
            'telegram_link_hash' => 'CHAR(64) NULL AFTER telegram_prefs',
            'telegram_link_expires' => 'DATETIME NULL AFTER telegram_link_hash',
        ],
        'known_devices' => [
            'trusted_until' => 'DATETIME NULL AFTER ip',
            'remember_hash' => 'CHAR(64) NULL AFTER trusted_until',
            'remember_until' => 'DATETIME NULL AFTER remember_hash',
        ],
        'leads' => [
            'items' => 'MEDIUMTEXT NULL AFTER message',
            'track_token' => 'CHAR(32) NULL AFTER consent',        // service requests: public status link
            'service_status' => 'VARCHAR(20) NULL AFTER track_token',
            'location' => 'VARCHAR(190) NULL AFTER service_status',
            'job_id' => 'INT UNSIGNED NULL AFTER location',          // careers applications
            'stage' => 'VARCHAR(20) NULL AFTER job_id',               // hiring pipeline for job applications
        ],
        'products' => ['datasheet' => 'VARCHAR(500) NULL AFTER specs'],
        'media' => ['variants' => 'TEXT NULL AFTER alt'],
        'posts' => ['deleted_at' => 'DATETIME NULL AFTER views', 'allow_comments' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER noindex'],
        'pages' => ['deleted_at' => 'DATETIME NULL AFTER is_system'],
        'login_otps' => [
            'purpose' => "VARCHAR(30) NOT NULL DEFAULT 'login' AFTER user_id",
            'target' => 'VARCHAR(190) NULL AFTER purpose',
            'channel' => "VARCHAR(10) NOT NULL DEFAULT 'email' AFTER target",
            'approval' => 'VARCHAR(10) NULL AFTER channel',
            'approval_nonce' => 'CHAR(24) NULL AFTER approval',
            'tg_message_id' => 'BIGINT NULL AFTER approval_nonce',
        ],
    ];
    foreach ($columns as $table => $cols) {
        foreach ($cols as $col => $def) {
            if (!column_exists($table, $col)) {
                db()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
                $changes[] = "{$table}.{$col}";
            }
        }
    }
    // lead_notes.kind: ENUM -> VARCHAR (service updates and future note kinds)
    if (db_val("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lead_notes' AND COLUMN_NAME = 'kind'") === 'enum') {
        db()->exec("ALTER TABLE `lead_notes` MODIFY `kind` VARCHAR(20) NOT NULL DEFAULT 'note'");
        $changes[] = 'lead_notes.kind varchar';
    }
    // leads.type: ENUM -> VARCHAR so new lead kinds (e.g. 'download') need no further migrations
    if (db_val("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND COLUMN_NAME = 'type'") === 'enum') {
        db()->exec("ALTER TABLE `leads` MODIFY `type` VARCHAR(20) NOT NULL DEFAULT 'contact'");
        $changes[] = 'leads.type varchar';
    }
    if (!index_exists('users', 'username') && !index_exists('users', 'uq_users_username')) {
        db()->exec('ALTER TABLE `users` ADD UNIQUE KEY `uq_users_username` (`username`)');
        $changes[] = 'users.username unique index';
    }
    setting_set('schema_version', SCHEMA_VERSION);
    return $changes;
}
