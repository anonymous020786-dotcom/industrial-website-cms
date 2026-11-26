<?php
/** Admin: site settings, integrations, email, profile. */
declare(strict_types=1);
require_once __DIR__ . '/admin.php';

function settings_tabs(): array
{
    $iconsSocial = ['linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'twitter' => 'X / Twitter', 'link' => 'Other'];
    return [
        'general' => ['Company & Branding', 'building-2', [
            'h1' => ['type' => 'heading', 'label' => 'Company details', 'help' => 'Used across the header, footer, contact page, emails and legal pages.'],
            'company_name' => ['type' => 'text', 'label' => 'Company / trading name', 'required' => true],
            'company_legal_name' => ['type' => 'text', 'label' => 'Registered legal name'],
            'company_short_name' => ['type' => 'text', 'label' => 'Logo main word'],
            'company_logo_sub' => ['type' => 'text', 'label' => 'Logo sub-line'],
            'company_tagline' => ['type' => 'text', 'label' => 'Announcement bar text', 'help' => 'Use “|” to split the bold and normal parts.', 'wide' => true],
            'company_description' => ['type' => 'textarea', 'label' => 'Short company description (footer)', 'rows' => 2],
            'company_registration' => ['type' => 'text', 'label' => 'Registration / tax number'],
            'company_jurisdiction' => ['type' => 'text', 'label' => 'Governing law (Terms of Use)', 'placeholder' => 'e.g. India, the State of Texas…'],
            'h2' => ['type' => 'heading', 'label' => 'Contact information'],
            'company_phone' => ['type' => 'text', 'label' => 'Phone (displayed)'],
            'company_phone_href' => ['type' => 'text', 'label' => 'Phone (digits for tel: link)', 'placeholder' => '+919876543210'],
            'company_email' => ['type' => 'email', 'label' => 'Sales email'],
            'company_privacy_email' => ['type' => 'email', 'label' => 'Privacy / data-protection email'],
            'company_address' => ['type' => 'textarea', 'label' => 'Address (one line per row)', 'rows' => 3],
            'company_hours' => ['type' => 'list', 'label' => 'Business hours', 'item' => 'Row', 'fields' => ['day' => ['type' => 'text', 'label' => 'Days'], 'time' => ['type' => 'text', 'label' => 'Hours']]],
            'social_links' => ['type' => 'list', 'label' => 'Social media', 'item' => 'Profile', 'fields' => ['label' => ['type' => 'text', 'label' => 'Name'], 'icon' => ['type' => 'select', 'label' => 'Network', 'options' => $iconsSocial], 'url' => ['type' => 'url', 'label' => 'Profile URL', 'wide' => true]]],
            'h3' => ['type' => 'heading', 'label' => 'Branding'],
            'logo_image' => ['type' => 'image', 'label' => 'Logo (light backgrounds)', 'help' => 'Optional — replaces the text logo. SVG/PNG with transparent background, ~40px tall.'],
            'logo_image_dark' => ['type' => 'image', 'label' => 'Logo (dark backgrounds / footer)'],
            'favicon' => ['type' => 'image', 'label' => 'Favicon'],
            'header_cta_label' => ['type' => 'text', 'label' => 'Header button label'],
            'heading_style' => ['type' => 'select', 'label' => 'Heading style', 'options' => ['industrial' => 'Industrial — condensed, uppercase (default)', 'editorial' => 'Editorial — elegant serif (Playfair Display)'], 'default' => 'industrial', 'help' => 'Changes the large headings across the website. Both fonts are self-hosted.'],
            'theme_default' => ['type' => 'select', 'label' => 'Default colour theme', 'options' => ['light' => 'Light', 'system' => 'Follow the visitor’s device', 'dark' => 'Dark'], 'help' => 'Visitors and the admin team can still switch with the sun/moon button; each person’s choice is remembered in their browser.'],
            'theme_toggle' => ['type' => 'checkbox', 'label' => 'Theme switch', 'text' => 'Show the light / dark toggle in the website header (the admin always has one)', 'default' => true],
            'admin_bar' => ['type' => 'checkbox', 'label' => 'Admin toolbar', 'text' => 'Show the admin toolbar (Edit this page, Leads, Sign out) on the website while you are signed in', 'default' => true, 'help' => 'Visitors never see it.'],
            'footer_login_link' => ['type' => 'checkbox', 'label' => 'Team login link', 'text' => 'Show a small “Team login” link in the footer', 'default' => true, 'help' => 'The sign-in page is always at /admin and /login, and stays protected by Turnstile, rate limits and two-step codes.'],
            'scroll_buttons' => ['type' => 'checkbox', 'label' => 'Scroll buttons', 'text' => 'Show floating scroll up / down buttons with reading progress (website and admin)', 'default' => true],
        ]],
        'seo' => ['SEO', 'search', [
            'site_url' => ['type' => 'url', 'label' => 'Production site URL', 'help' => 'e.g. https://www.yourcompany.com — used for canonical URLs, sitemap.xml and social sharing.', 'wide' => true],
            'search_indexing' => ['type' => 'checkbox', 'label' => 'Search engines', 'text' => 'Allow search engines to index this website', 'default' => true, 'help' => 'Turn off on staging/demo copies.'],
            'default_og_image' => ['type' => 'image', 'label' => 'Default social share image', 'help' => '1200×630px recommended.'],
            'site_language' => ['type' => 'text', 'label' => 'Site language code', 'placeholder' => 'en'],
            'google_site_verification' => ['type' => 'text', 'label' => 'Google Search Console verification code', 'help' => 'Only the <code>content</code> value of the HTML tag.'],
            'bing_site_verification' => ['type' => 'text', 'label' => 'Bing Webmaster verification code'],
        ]],
        'menus' => ['Menus', 'menu', [
            'menu_header' => ['type' => 'list', 'label' => 'Header menu', 'item' => 'Menu item', 'fields' => [
                'label' => ['type' => 'text', 'label' => 'Label'], 'url' => ['type' => 'text', 'label' => 'Link', 'placeholder' => 'about, products, https://…'],
                'mega' => ['type' => 'checkbox', 'label' => 'Mega menu', 'text' => 'Show the products mega menu'],
            ]],
            'menu_footer' => ['type' => 'list', 'label' => 'Footer “Company” links', 'item' => 'Link', 'fields' => ['label' => ['type' => 'text', 'label' => 'Label'], 'url' => ['type' => 'text', 'label' => 'Link']]],
        ]],
        'content' => ['Content Blocks', 'blocks', [
            'stats' => ['type' => 'list', 'label' => 'Statistics band', 'item' => 'Statistic', 'help' => 'Only publish figures you can verify.', 'fields' => ['value' => ['type' => 'number', 'label' => 'Number'], 'suffix' => ['type' => 'text', 'label' => 'Suffix', 'placeholder' => '+'], 'label' => ['type' => 'text', 'label' => 'Label', 'wide' => true]]],
            'stats_note' => ['type' => 'text', 'label' => 'Note under statistics', 'wide' => true, 'help' => 'Remove once figures are verified.'],
            'process' => ['type' => 'list', 'label' => 'Process steps (Home, About, Manufacturing)', 'item' => 'Step', 'fields' => ['icon' => ['type' => 'icon'], 'title' => ['type' => 'text'], 'text' => ['type' => 'textarea', 'rows' => 2]]],
            'enquiry_options' => ['type' => 'lines', 'label' => 'Extra enquiry options (forms)', 'help' => 'Added after the product list in the “Product / Service” dropdown.'],
            'cta_background' => ['type' => 'image', 'label' => 'CTA band background image'],
            'footer_cta_text' => ['type' => 'text', 'label' => 'Footer call-to-action text'],
            'footer_note' => ['type' => 'text', 'label' => 'Footer small print', 'wide' => true],
            'form_success_title' => ['type' => 'text', 'label' => 'Form success title'],
            'form_success_text' => ['type' => 'textarea', 'label' => 'Form success message', 'rows' => 2],
        ]],
        'integrations' => ['Integrations', 'plug', [
            'h_own' => ['type' => 'heading', 'label' => 'Built-in visitor analytics', 'help' => 'Page views, visitors, sources and devices in <strong>Analytics</strong> — no cookies, no third parties, no personal data (visitors are counted with a hash that changes every day), so no consent banner is needed for it.'],
            'analytics_enabled' => ['type' => 'checkbox', 'label' => 'Visitor analytics', 'text' => 'Count page views with the built-in analytics', 'default' => true, 'wide' => true],
            'analytics_respect_dnt' => ['type' => 'checkbox', 'label' => 'Do Not Track', 'text' => 'Skip visitors whose browser sends Do Not Track / Global Privacy Control', 'default' => true, 'wide' => true],
            'analytics_keep_days' => ['type' => 'number', 'label' => 'Keep page views for (days)', 'default' => 395],
            'h_an' => ['type' => 'heading', 'label' => 'Analytics & tracking', 'help' => 'These load only after visitors accept cookies (if the cookie banner is enabled).'],
            'ga4_id' => ['type' => 'text', 'label' => 'Google Analytics 4 — Measurement ID', 'placeholder' => 'G-XXXXXXXXXX'],
            'gtm_id' => ['type' => 'text', 'label' => 'Google Tag Manager — Container ID', 'placeholder' => 'GTM-XXXXXXX'],
            'meta_pixel_id' => ['type' => 'text', 'label' => 'Meta (Facebook) Pixel ID', 'placeholder' => '123456789012345'],
            'clarity_id' => ['type' => 'text', 'label' => 'Microsoft Clarity project ID'],
            'h_chat' => ['type' => 'heading', 'label' => 'Live chat — Tawk.to', 'help' => 'Tawk.to dashboard → Administration → Chat Widget. The direct chat link looks like <code>https://tawk.to/chat/<strong>PROPERTY_ID</strong>/<strong>WIDGET_ID</strong></code>.'],
            'tawk_property_id' => ['type' => 'text', 'label' => 'Tawk.to Property ID'],
            'tawk_widget_id' => ['type' => 'text', 'label' => 'Tawk.to Widget ID', 'placeholder' => 'default'],
            'tawk_require_consent' => ['type' => 'checkbox', 'label' => 'Consent', 'text' => 'Load chat only after cookie consent', 'default' => true],
            'h_wa' => ['type' => 'heading', 'label' => 'WhatsApp'],
            'whatsapp_enabled' => ['type' => 'checkbox', 'label' => 'Floating button', 'text' => 'Show the floating WhatsApp button'],
            'whatsapp_number' => ['type' => 'text', 'label' => 'WhatsApp number (with country code)', 'placeholder' => '+91 98765 43210', 'help' => 'Also enables WhatsApp links on product, contact and quote pages.'],
            'whatsapp_message' => ['type' => 'text', 'label' => 'Pre-filled message', 'wide' => true],
            'whatsapp_label' => ['type' => 'text', 'label' => 'Button label'],
            'whatsapp_position' => ['type' => 'select', 'label' => 'Position', 'options' => ['right' => 'Bottom right', 'left' => 'Bottom left']],
            'h_cf' => ['type' => 'heading', 'label' => 'Cloudflare', 'help' => 'Turnstile protects the contact and quote forms from spam bots (free). Create a widget at dash.cloudflare.com → Turnstile.'],
            'turnstile_site_key' => ['type' => 'text', 'label' => 'Turnstile site key'],
            'turnstile_secret' => ['type' => 'password', 'label' => 'Turnstile secret key', 'help' => 'Stored securely; leave blank to keep the saved key.'],
            'cf_trust_proxy' => ['type' => 'checkbox', 'label' => 'Proxy / Tunnel', 'text' => 'Site is behind Cloudflare proxy or Cloudflare Tunnel (use CF-Connecting-IP for visitor IPs)', 'wide' => true],
            'h_code' => ['type' => 'heading', 'label' => 'Custom code', 'help' => 'For other tools (LinkedIn Insight, HubSpot, Hotjar…). Only paste code from trusted providers.'],
            'custom_head_consent' => ['type' => 'code', 'label' => '<head> code — loads after cookie consent', 'rows' => 5],
            'custom_head_always' => ['type' => 'code', 'label' => '<head> code — always (essential only)', 'rows' => 4],
            'custom_body_always' => ['type' => 'code', 'label' => 'Before </body> — always', 'rows' => 4],
        ]],
        'email' => ['Email & Lead Alerts', 'mail', [
            'h_n' => ['type' => 'heading', 'label' => 'Lead notifications'],
            'lead_notify_enabled' => ['type' => 'checkbox', 'label' => 'Notifications', 'text' => 'Email the team when a new enquiry arrives', 'default' => true],
            'lead_notify_emails' => ['type' => 'text', 'label' => 'Send notifications to', 'placeholder' => 'sales@company.com, manager@company.com', 'help' => 'Separate multiple addresses with commas.', 'wide' => true],
            'lead_autoreply_enabled' => ['type' => 'checkbox', 'label' => 'Auto-reply', 'text' => 'Send an automatic confirmation email to the customer'],
            'lead_autoreply_subject' => ['type' => 'text', 'label' => 'Auto-reply subject'],
            'lead_autoreply_text' => ['type' => 'textarea', 'label' => 'Auto-reply message', 'rows' => 4],
            'h_s' => ['type' => 'heading', 'label' => 'SMTP server', 'help' => 'Use your hosting email (e.g. Hostinger: smtp.hostinger.com, port 465, SSL) or a service like Brevo, Mailgun or Google Workspace. Without SMTP, PHP mail() is used, which often lands in spam.'],
            'mail_from_name' => ['type' => 'text', 'label' => 'From name'],
            'mail_from_email' => ['type' => 'email', 'label' => 'From email', 'help' => 'Must be an address on your domain.'],
            'smtp_host' => ['type' => 'text', 'label' => 'SMTP host', 'placeholder' => 'smtp.hostinger.com'],
            'smtp_port' => ['type' => 'number', 'label' => 'Port', 'placeholder' => '465'],
            'smtp_encryption' => ['type' => 'select', 'label' => 'Encryption', 'options' => ['ssl' => 'SSL (port 465)', 'tls' => 'STARTTLS (port 587)', 'none' => 'None']],
            'smtp_user' => ['type' => 'text', 'label' => 'Username'],
            'smtp_pass' => ['type' => 'password', 'label' => 'Password', 'help' => 'Leave blank to keep the saved password.'],
        ]],
        'legal' => ['Legal & Cookies', 'scale', [
            'cookie_banner_enabled' => ['type' => 'checkbox', 'label' => 'Cookie banner', 'text' => 'Show the cookie consent banner (recommended — required for analytics in many regions)', 'default' => true, 'wide' => true],
            'cookie_banner_title' => ['type' => 'text', 'label' => 'Banner title'],
            'cookie_banner_text' => ['type' => 'textarea', 'label' => 'Banner text', 'rows' => 2],
            'legal_updated' => ['type' => 'date', 'label' => 'Policies last updated', 'help' => 'Shown on all legal pages via [last_updated].'],
            'legal_template_notice' => ['type' => 'checkbox', 'label' => 'Template notice', 'text' => 'Show “template — needs legal review” box on legal pages', 'default' => true, 'help' => 'Turn off only after a lawyer has reviewed your policies. Edit policy text in Pages.'],
        ]],
        'security' => ['Security & Login', 'shield-check', [
            'h_otp' => ['type' => 'heading', 'label' => 'Two-step login (email one-time passcode)', 'help' => 'After entering their password, users receive a 6-digit code by email. Codes expire in 10 minutes, allow 5 attempts and are stored hashed. Requires working email (Settings → Email).'],
            'admin_otp_enabled' => ['type' => 'checkbox', 'label' => 'Email OTP', 'text' => 'Require an emailed code for every admin sign-in', 'wide' => true],
            'otp_trust_days' => ['type' => 'number', 'label' => 'Trust a browser for (days)', 'help' => 'Users can tick “trust this browser” to skip the code on that device. 0 = always ask. Changing a password revokes trusted browsers.', 'default' => 30],
            'admin_idle_minutes' => ['type' => 'number', 'label' => 'Sign out after inactivity (minutes)', 'default' => 240],
            'stepup_trusted_minutes' => ['type' => 'select', 'label' => 'On trusted devices, a “confirm it’s you” lasts', 'options' => ['10' => '10 minutes (same as other devices)', '30' => '30 minutes', '60' => '1 hour', '120' => '2 hours', '240' => '4 hours'], 'default' => '10', 'help' => 'Fewer code prompts for sensitive actions on devices people marked as trusted. Other devices always ask again after 10 minutes.'],
            'h_remember' => ['type' => 'heading', 'label' => 'Keep me signed in', 'help' => 'Lets users stay signed in on their own devices. Uses a single-use token that rotates on every visit, is bound to the device and is revoked on sign-out, password change or “sign out everywhere”. Sensitive actions still ask for a one-time code.'],
            'remember_enabled' => ['type' => 'checkbox', 'label' => 'Keep me signed in', 'text' => 'Offer “Keep me signed in” on the sign-in page', 'default' => true, 'wide' => true],
            'remember_days' => ['type' => 'number', 'label' => 'Stay signed in for (days)', 'default' => 30, 'help' => '1–90 days.'],
            'h_ts' => ['type' => 'heading', 'label' => 'Bot protection', 'help' => 'Cloudflare Turnstile keys are set in Integrations. When configured, the contact and quote forms are always protected.'],
            'turnstile_login' => ['type' => 'checkbox', 'label' => 'Login', 'text' => 'Also require Turnstile on the admin sign-in page', 'default' => true, 'wide' => true],
            'h_strong' => ['type' => 'heading', 'label' => 'Passkeys & authenticator apps', 'help' => 'Each user can add passkeys (Windows Hello, Touch ID, Face ID, Android, security keys) and an authenticator app in My profile. Users with an authenticator app are always asked for its code after their password; a passkey sign-in already includes device verification.'],
            'require_strong_2fa' => ['type' => 'checkbox', 'label' => 'Enforce', 'text' => 'Remind administrators without a passkey or authenticator app to set one up (banner in the admin)', 'default' => true, 'wide' => true],
        ]],
        'catalogue' => ['Catalogue & Downloads', 'file-down', [
            'h_cat' => ['type' => 'heading', 'label' => 'Product catalogue', 'help' => 'A PDF catalogue offered on the Products page, product pages and in the footer. Product datasheets are set on each product.'],
            'catalogue_pdf' => ['type' => 'document', 'label' => 'Catalogue PDF'],
            'catalogue_title' => ['type' => 'text', 'label' => 'Catalogue name', 'default' => 'Product catalogue'],
            'catalogue_gated' => ['type' => 'checkbox', 'label' => 'Lead capture', 'text' => 'Ask for name, company and email before the catalogue download (creates a lead)', 'default' => true, 'wide' => true],
            'datasheets_gated' => ['type' => 'checkbox', 'label' => 'Datasheets', 'text' => 'Also ask for details before product datasheet downloads', 'default' => false, 'wide' => true, 'help' => 'Leave off for datasheets — engineers expect them to be instant. Downloads are counted either way.'],
        ]],
        'automation' => ['Automation', 'clock', [
            'h_digest' => ['type' => 'heading', 'label' => 'Daily lead follow-up email', 'help' => 'Every morning: overdue follow-ups, follow-ups due today and new leads nobody has contacted for 24 hours. Each salesperson also receives only their own leads. Nothing is sent on days with nothing to report.'],
            'digest_enabled' => ['type' => 'checkbox', 'label' => 'Digest', 'text' => 'Send the daily lead follow-up email', 'default' => true, 'wide' => true],
            'digest_hour' => ['type' => 'select', 'label' => 'Send at', 'options' => array_combine(range(0, 23), array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23))), 'default' => 8],
            'digest_emails' => ['type' => 'text', 'label' => 'Recipients', 'placeholder' => 'Defaults to the lead alert recipients', 'help' => 'Comma-separated.'],
            'h_backup' => ['type' => 'heading', 'label' => 'Automatic backups', 'help' => 'Database backups are created daily and stored privately in app/storage/backups. Manage and download them in Backups & automation.'],
            'backup_enabled' => ['type' => 'checkbox', 'label' => 'Backups', 'text' => 'Back up the database every day', 'default' => true, 'wide' => true],
            'backup_hour' => ['type' => 'select', 'label' => 'Run at', 'options' => array_combine(range(0, 23), array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23))), 'default' => 2],
            'backup_keep' => ['type' => 'number', 'label' => 'Keep the newest', 'default' => 14, 'help' => 'Number of daily backups to keep.'],
            'backup_uploads' => ['type' => 'checkbox', 'label' => 'Uploads', 'text' => 'Also zip uploaded images and documents (uses more disk space)', 'default' => false, 'wide' => true],
        ]],
        'quotes' => ['Quotes', 'file-text', [
            'h_q' => ['type' => 'heading', 'label' => 'Quotation defaults', 'help' => 'Used for every new quotation; each quote can still be changed. Your company name, address, phone, email and registration number come from Company & Branding.'],
            'quote_prefix' => ['type' => 'text', 'label' => 'Number prefix', 'default' => 'Q-', 'help' => 'Quotes are numbered Q-2026-0001, Q-2026-0002…'],
            'quote_currency' => ['type' => 'select', 'label' => 'Currency', 'options' => array_combine(array_keys(QUOTE_CURRENCIES), array_keys(QUOTE_CURRENCIES)), 'default' => 'INR'],
            'quote_tax_label' => ['type' => 'text', 'label' => 'Tax name', 'default' => 'GST'],
            'quote_tax_rate' => ['type' => 'number', 'label' => 'Tax rate (%)', 'default' => 18],
            'quote_validity_days' => ['type' => 'number', 'label' => 'Valid for (days)', 'default' => 30],
            'quote_delivery' => ['type' => 'text', 'label' => 'Default delivery', 'placeholder' => 'e.g. 3–4 weeks from purchase order, ex-works', 'wide' => true],
            'quote_payment_terms' => ['type' => 'text', 'label' => 'Default payment terms', 'placeholder' => 'e.g. 50% advance, balance before dispatch', 'wide' => true],
            'quote_terms' => ['type' => 'textarea', 'label' => 'Terms & conditions', 'rows' => 5, 'default' => "Prices are ex-works and exclusive of freight and insurance unless stated.\nDelivery time starts from receipt of the purchase order and advance payment.\nThis quotation is valid until the date shown."],
            'quote_bank' => ['type' => 'textarea', 'label' => 'Bank details (printed on the PDF)', 'rows' => 4, 'placeholder' => "Account name\nBank, branch\nAccount number · IFSC"],
        ]],
        'community' => ['Comments, Sharing & Newsletter', 'messages-square', [
            'h_share' => ['type' => 'heading', 'label' => 'Share buttons', 'help' => 'WhatsApp, LinkedIn, Facebook, X, Telegram, Reddit, email, copy link and the phone’s share sheet on articles, products, projects and jobs. Shared links carry utm tags, so visits show up in Reports by network.'],
            'share_enabled' => ['type' => 'checkbox', 'label' => 'Sharing', 'text' => 'Show share buttons', 'default' => true, 'wide' => true],
            'mobile_bar' => ['type' => 'checkbox', 'label' => 'Mobile contact bar', 'text' => 'On phones, show a bar with Call, WhatsApp, Service and Get a quote at the bottom of the screen', 'default' => true, 'wide' => true],
            'share_short_links' => ['type' => 'checkbox', 'label' => 'Short links', 'text' => 'Copy-link uses a short link (/s/abc123) with click counts', 'default' => true, 'wide' => true],
            'h_com' => ['type' => 'heading', 'label' => 'Blog comments', 'help' => 'Moderate in <strong>Comments</strong>. Each post can also switch comments off (post editor → Organise).'],
            'comments_enabled' => ['type' => 'checkbox', 'label' => 'Comments', 'text' => 'Allow comments on blog articles', 'default' => true, 'wide' => true],
            'comments_moderation' => ['type' => 'select', 'label' => 'Moderation', 'options' => ['all' => 'Approve every comment by hand', 'first' => 'Approve a person’s first comment; trust them afterwards', 'none' => 'Publish straight away (spam filter only)'], 'default' => 'first'],
            'comments_close_days' => ['type' => 'number', 'label' => 'Close comments after (days)', 'default' => 0, 'help' => '0 = never close.'],
            'comments_max_links' => ['type' => 'number', 'label' => 'Links allowed per comment', 'default' => 2, 'help' => 'More links than this = spam.'],
            'comments_blocked_words' => ['type' => 'textarea', 'label' => 'Blocked words', 'rows' => 3, 'help' => 'One per line or comma-separated. Applies to every public form: comments, reviews, contact/quote and other enquiry forms. Matches go to Spam (enquiries are kept, marked as spam, without alerts).'],
            'leads_max_links' => ['type' => 'number', 'label' => 'Links allowed in an enquiry', 'default' => 5, 'help' => 'Contact, quote and other enquiry forms with more links than this are filed as spam.'],
            'comments_notify' => ['type' => 'checkbox', 'label' => 'Alerts', 'text' => 'Notify editors and administrators about comments waiting for approval', 'default' => true, 'wide' => true],
            'comments_reply_email' => ['type' => 'checkbox', 'label' => 'Reply emails', 'text' => 'Email the commenter when the team replies from the admin', 'default' => true, 'wide' => true],
            'h_rev' => ['type' => 'heading', 'label' => 'Customer reviews', 'help' => 'Star ratings and reviews on product pages and at /reviews, with Google review stars (aggregateRating). Moderate in <strong>Reviews</strong>. Customers who bought from you (a won lead or an accepted quote with the same email) get a “Verified customer” badge.'],
            'reviews_enabled' => ['type' => 'checkbox', 'label' => 'Reviews', 'text' => 'Let customers write reviews', 'default' => true, 'wide' => true],
            'reviews_moderation' => ['type' => 'select', 'label' => 'Moderation', 'options' => ['all' => 'Approve every review by hand', 'verified' => 'Publish verified customers straight away; approve the rest'], 'default' => 'all'],
            'h_nl' => ['type' => 'heading', 'label' => 'Newsletter', 'help' => 'Sign-up form in the footer and on blog articles. Write and send newsletters in <strong>Newsletter</strong>; every email has an unsubscribe link and one-click unsubscribe for Gmail and Outlook.'],
            'newsletter_enabled' => ['type' => 'checkbox', 'label' => 'Newsletter', 'text' => 'Show the sign-up form', 'default' => true, 'wide' => true],
            'newsletter_double_optin' => ['type' => 'checkbox', 'label' => 'Double opt-in', 'text' => 'Ask new subscribers to confirm by email (recommended; required in the EU)', 'default' => true, 'wide' => true],
            'newsletter_title' => ['type' => 'text', 'label' => 'Newsletter name', 'default' => 'our engineering newsletter'],
            'newsletter_note' => ['type' => 'text', 'label' => 'Text under the form', 'default' => 'Monthly engineering notes. No spam — unsubscribe any time.', 'wide' => true],
            'newsletter_welcome' => ['type' => 'checkbox', 'label' => 'Welcome email', 'text' => 'Send a welcome email after subscribing', 'default' => true, 'wide' => true],
            'newsletter_welcome_text' => ['type' => 'textarea', 'label' => 'Welcome message', 'rows' => 3],
            'newsletter_reply_to' => ['type' => 'email', 'label' => 'Replies go to', 'help' => 'Optional. Default: the sender address in Email settings.'],
            'newsletter_batch' => ['type' => 'number', 'label' => 'Emails per batch', 'default' => 100, 'help' => 'Sent every time the scheduled job runs (every 10 minutes). Keep within your mail provider’s hourly limit.'],
        ]],
        'workflow' => ['Workflow & Reports', 'git-pull-request-arrow', [
            'h_ap' => ['type' => 'heading', 'label' => 'Content approval', 'help' => 'When on, changes editors make to live pages and blog posts — and anything they want to publish — wait in <strong>Approvals</strong> until an administrator approves them. Drafts can still be edited freely. Administrators publish directly.'],
            'content_approval' => ['type' => 'checkbox', 'label' => 'Approval', 'text' => 'Editors need an administrator’s approval to publish or change live content', 'wide' => true],
            'h_careers' => ['type' => 'heading', 'label' => 'Careers', 'help' => 'Job applications (with CVs) go to administrators only, separate from sales leads.'],
            'careers_email' => ['type' => 'email', 'label' => 'Also email applications to', 'placeholder' => 'hr@yourcompany.com', 'help' => 'Optional, e.g. your HR inbox. Also shown on the careers page when there are no openings.'],
            'h_rep' => ['type' => 'heading', 'label' => 'Weekly owner report', 'help' => 'Every week: new enquiries, quotations sent and won, service requests, top pages, part numbers people could not find, errors and backup status — by email and Telegram.'],
            'report_weekly' => ['type' => 'checkbox', 'label' => 'Weekly report', 'text' => 'Send the weekly report', 'default' => true, 'wide' => true],
            'report_day' => ['type' => 'select', 'label' => 'Send on', 'options' => [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'], 'default' => 1],
            'report_hour' => ['type' => 'select', 'label' => 'At', 'options' => array_combine(range(0, 23), array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23))), 'default' => 8],
            'report_recipients' => ['type' => 'text', 'label' => 'Send to', 'placeholder' => 'owner@company.com, manager@company.com', 'wide' => true, 'help' => 'Comma-separated. Leave empty to send to every active administrator.'],
            'report_telegram' => ['type' => 'checkbox', 'label' => 'Telegram', 'text' => 'Also send a short summary to administrators who connected Telegram', 'default' => true, 'wide' => true],
        ]],
        'telegram' => ['Telegram', 'send', [
            'h_tg' => ['type' => 'heading', 'label' => 'Telegram bot', 'help' => 'Send sign-in codes, one-tap sign-in approval, security alerts and new-lead alerts through your own Telegram bot. In Telegram, open <strong>@BotFather</strong>, send <code>/newbot</code>, choose a name, then paste the token it gives you here. Each team member then connects their own Telegram in My profile.'],
            'telegram_enabled' => ['type' => 'checkbox', 'label' => 'Telegram', 'text' => 'Use Telegram for codes and alerts', 'wide' => true],
            'telegram_bot_token' => ['type' => 'password', 'label' => 'Bot token', 'placeholder' => '123456789:AA…', 'help' => 'Stored securely; leave blank to keep the saved token.', 'wide' => true],
            'telegram_login_approval' => ['type' => 'checkbox', 'label' => 'One-tap approval', 'text' => 'Show Approve / Deny buttons with sign-in codes (people can still type the code)', 'default' => true, 'wide' => true],
            'telegram_webhook' => ['type' => 'checkbox', 'label' => 'Webhook', 'text' => 'Receive Telegram updates by webhook (needs the public HTTPS site address)', 'wide' => true, 'help' => 'Off = the site asks Telegram for updates only while someone is waiting (works anywhere, even on a laptop). On = instant, recommended for a live site.'],
        ]],
        'maintenance' => ['Maintenance mode', 'construction', [
            'h_m' => ['type' => 'heading', 'label' => 'Maintenance mode', 'help' => 'Visitors see a branded “we’ll be back” page with HTTP 503, which tells search engines to come back later without losing rankings. Signed-in team members, allowed IP addresses and anyone with the preview link still see the normal site. The admin keeps working.'],
            'maintenance_enabled' => ['type' => 'checkbox', 'label' => 'Status', 'text' => 'Show the maintenance page to visitors now', 'wide' => true],
            'maintenance_until' => ['type' => 'datetime', 'label' => 'Expected back (optional)', 'help' => 'Shown as a live countdown, and sent to search engines as the retry time.'],
            'h_sched' => ['type' => 'heading', 'label' => 'Schedule a maintenance window (optional)', 'help' => 'The site switches to maintenance by itself at the start time and back to normal at the end time. Leave empty to switch by hand.'],
            'maintenance_start' => ['type' => 'datetime', 'label' => 'Starts'],
            'maintenance_end' => ['type' => 'datetime', 'label' => 'Ends'],
            'h_page' => ['type' => 'heading', 'label' => 'Page content'],
            'maintenance_title' => ['type' => 'text', 'label' => 'Headline', 'default' => 'We’ll be back shortly', 'wide' => true],
            'maintenance_message' => ['type' => 'textarea', 'label' => 'Message', 'rows' => 3, 'default' => 'We’re carrying out planned improvements to our website. Thank you for your patience — everything will be back online very soon.'],
            'maintenance_show_contact' => ['type' => 'checkbox', 'label' => 'Contact box', 'text' => 'Show phone, email and WhatsApp so customers can still reach you', 'default' => true, 'wide' => true],
            'maintenance_contact_note' => ['type' => 'text', 'label' => 'Contact box text', 'default' => 'Urgent breakdown or spare-part request? Our team is still available.', 'wide' => true],
            'h_access' => ['type' => 'heading', 'label' => 'Who can still see the site', 'help' => 'Signed-in team members always can. Add office or developer IP addresses, or ranges such as 203.0.113.0/24.'],
            'maintenance_allow_ips' => ['type' => 'lines', 'label' => 'Allowed IP addresses (one per line)', 'wide' => true],
        ]],
        'system' => ['System', 'server', []],
    ];
}

function admin_settings(string $tab): void
{
    require_login('settings');
    $tabs = settings_tabs();
    if (!isset($tabs[$tab])) abort(404);
    [$label, , $schema] = $tabs[$tab];
    $errors = [];
    // Security, email (SMTP password) and integration keys: confirm identity before even showing them
    $sensitiveTab = in_array($tab, ['security', 'email', 'integrations', 'telegram'], true);
    if ($sensitiveTab) require_stepup('settings');
    // Taking the site offline (or back online) is confirmed with a one-time code
    if ($tab === 'maintenance' && is_post()) require_stepup('maintenance');

    if (is_post() && $schema) {
        csrf_check();
        [$values, $errors] = parse_fields($schema, $_POST);
        if ($tab === 'general' && !empty($values['company_phone']) && empty($values['company_phone_href'])) {
            $values['company_phone_href'] = '+' . ltrim(preg_replace('~[^\d]~', '', $values['company_phone']), '0');
        }
        if ($tab === 'integrations') {
            foreach (['ga4_id' => '~^G-[A-Z0-9]{4,20}$~', 'gtm_id' => '~^GTM-[A-Z0-9]{4,12}$~', 'meta_pixel_id' => '~^\d{6,20}$~'] as $k => $re) {
                $values[$k] = strtoupper(trim((string) $values[$k]));
                if ($k === 'meta_pixel_id') $values[$k] = preg_replace('~\D~', '', $values[$k]);
                if ($values[$k] !== '' && !preg_match($re, $values[$k])) $errors[$k] = 'This ID format looks wrong.';
            }
        }
        if ($tab === 'telegram' && !$errors) {
            $token = (string) ($values['telegram_bot_token'] ?? '') !== '' ? (string) $values['telegram_bot_token'] : (string) setting('telegram_bot_token', '');
            if (!empty($values['telegram_enabled']) || (string) ($values['telegram_bot_token'] ?? '') !== '') {
                $me = $token === '' ? null : telegram_api('getMe', [], $token);
                if ($me === null) {
                    $errors['telegram_bot_token'] = $token === '' ? 'Paste the bot token from @BotFather.' : (string) $GLOBALS['telegram_error'];
                } else {
                    setting_set('telegram_bot_username', (string) ($me['username'] ?? ''));
                    setting_set('telegram_bot_name', (string) ($me['first_name'] ?? ''));
                }
            }
            if (!$errors && (bool) $values['telegram_webhook'] !== (bool) setting('telegram_webhook', false)) {
                if ($token !== '' && ($err = telegram_configure_webhook((bool) $values['telegram_webhook'], $token))) $errors['telegram_webhook'] = $err;
            }
        }
        if ($tab === 'maintenance') {
            $start = $values['maintenance_start'] ? strtotime($values['maintenance_start']) : null;
            $end = $values['maintenance_end'] ? strtotime($values['maintenance_end']) : null;
            if ($end && !$start) $errors['maintenance_start'] = 'Set a start time for the window, or clear the end time.';
            if ($start && $end && $end <= $start) $errors['maintenance_end'] = 'The end must be after the start.';
            if ($end && $end <= time() && !isset($errors['maintenance_end'])) $errors['maintenance_end'] = 'This time has already passed.';
            foreach ($values['maintenance_allow_ips'] as $rule) {
                if (!ip_rule_valid($rule)) { $errors['maintenance_allow_ips'] = '“' . $rule . '” is not a valid IP address or range.'; break; }
            }
            $wasActive = maintenance_active();
        }
        if ($tab === 'seo' && !empty($values['site_url'])) $values['site_url'] = rtrim($values['site_url'], '/');
        if ($tab === 'security') {
            $values['otp_trust_days'] = max(0, min(90, (int) $values['otp_trust_days']));
            $values['admin_idle_minutes'] = max(15, min(1440, (int) ($values['admin_idle_minutes'] ?: 240)));
            $values['remember_days'] = max(1, min(90, (int) ($values['remember_days'] ?: 30)));
            // Lock-out protection: OTP needs working email delivery.
            if (!empty($values['admin_otp_enabled']) && !setting('admin_otp_enabled')) {
                if (!setting('smtp_host') && !config('debug')) {
                    $errors['admin_otp_enabled'] = 'Configure SMTP in Settings → Email and send a test email first — otherwise nobody will receive their sign-in code.';
                } elseif (!setting('mail_test_ok_at')) {
                    $errors['admin_otp_enabled'] = 'Send a successful test email from Settings → Email before enabling two-step login.';
                }
            }
            if (!empty($values['turnstile_login']) && !turnstile_enabled()) {
                flash('info', 'Turnstile on login will activate once site and secret keys are saved in Integrations.');
            }
        }
        if (!$errors) {
            $changed = [];
            foreach ($values as $k => $v) {
                if (($schema[$k]['type'] ?? '') === 'heading') continue;
                if (json_encode(setting($k)) !== json_encode($v)) $changed[] = (string) ($schema[$k]['label'] ?? $k);
            }
            foreach ($values as $k => $v) setting_set($k, $v);
            if ($tab === 'maintenance') {
                setting_set('maintenance_changed_at', now());
                $isActive = maintenance_state(true)['active'];
                if ($isActive !== $wasActive) maintenance_notify($isActive);
                $changed = $isActive !== $wasActive ? [] : $changed; // the on/off alert already covers it
            }
            if ($changed) {
                notify_admins('settings_changed', 'Settings changed: ' . $label,
                    actor_name() . ' changed ' . count($changed) . ' setting(s) in ' . $label . ': ' . implode(', ', array_slice($changed, 0, 12)) . (count($changed) > 12 ? '…' : '') . '. Secret values are never included in alerts.',
                    ['severity' => $sensitiveTab ? 'warning' : 'info', 'link' => 'admin/settings/' . $tab, 'email' => $sensitiveTab ? null : false, 'include_actor' => true]);
            }
            flash('success', $label . ' saved.');
            redirect('admin/settings/' . $tab);
        }
        flash('error', 'Please fix the errors below.');
        $current = array_merge(settings_all(), $values);
    } else {
        $current = settings_all();
    }
    // Never echo secrets back into the form
    foreach (['turnstile_secret', 'smtp_pass', 'telegram_bot_token'] as $s) $current[$s] = '';

    admin_render('Settings', 'settings', ['tabs' => $tabs, 'tab' => $tab, 'schema' => $schema, 'values' => $current, 'errors' => $errors,
        'secrets' => ['turnstile_secret' => (bool) setting('turnstile_secret'), 'smtp_pass' => (bool) setting('smtp_pass'), 'telegram_bot_token' => (bool) setting('telegram_bot_token')]]);
}

function admin_settings_test_email(): void
{
    $user = require_login('settings');
    csrf_check();
    $to = (string) (setting('lead_notify_emails') ?: $user['email']);
    $res = send_mail($to, 'Test email from ' . company('name'), mail_layout('Email delivery works', '<p style="font-size:14px">This test was sent from the website admin panel at ' . e(date('j M Y H:i')) . '. Lead notifications will arrive like this.</p>'));
    if ($res === true) setting_set('mail_test_ok_at', now());
    flash($res === true ? 'success' : 'error', $res === true ? 'Test email sent to ' . $to . '.' : (string) $res);
    redirect('admin/settings/email');
}

/** Development mailbox (debug mode only): shows emails saved by the mailer. */
function admin_dev_mail(): void
{
    require_login('settings');
    if (!config('debug') && !config('mail_log')) abort(404);
    $files = array_reverse(glob(STORAGE_DIR . '/mail/*.html') ?: []);
    $show = (string) query('f', '');
    $current = null;
    if ($show !== '' && preg_match('~^[\w-]+\.html$~', $show) && is_file(STORAGE_DIR . '/mail/' . $show)) {
        $current = (string) file_get_contents(STORAGE_DIR . '/mail/' . $show);
    }
    $list = array_map(function ($f) {
        $head = (string) fgets(fopen($f, 'r'));
        preg_match('~to: (.*?) \| subject: (.*?) \| (\S+)~', html_entity_decode($head, ENT_QUOTES), $m);
        return ['file' => basename($f), 'to' => $m[1] ?? '', 'subject' => $m[2] ?? basename($f), 'date' => $m[3] ?? ''];
    }, array_slice($files, 0, 100));
    admin_render('Dev mailbox', 'dev_mail', ['list' => $list, 'current' => $current, 'show' => $show], ['crumbs' => [['Settings', 'admin/settings/system']], 'wide' => true]);
}

/** Re-check the current password for sensitive profile changes (rate limited per user). */
function profile_password_ok(array $row, string $password): bool|string
{
    $bucket = 'reauth:' . (int) $row['id'];
    $fails = (int) db_val('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)', [$bucket]);
    if ($fails >= 5) return 'Too many incorrect password attempts. Please wait 15 minutes.';
    if (!password_verify($password, (string) $row['password_hash'])) {
        db_insert('rate_limits', ['bucket' => $bucket]);
        auth_log('reauth_failed', (int) $row['id'], $row['email']);
        return 'Current password is incorrect.';
    }
    db_query('DELETE FROM rate_limits WHERE bucket = ?', [$bucket]);
    return true;
}

/**
 * My profile: name + username, password change, primary email change (OTP to the new
 * address), backup email add / change / remove (OTP to the backup address).
 * Pending email changes live in the session for 15 minutes.
 */
function admin_profile(): void
{
    $user = require_login();
    $uid = (int) $user['id'];
    $row = db_one('SELECT * FROM users WHERE id = ?', [$uid]);
    $errors = [];
    $old = [];
    foreach (['pending_email', 'pending_backup'] as $k) {
        if (isset($_SESSION[$k]) && time() - (int) ($_SESSION[$k]['at'] ?? 0) > 900) unset($_SESSION[$k]);
    }

    if (is_post()) {
        csrf_check();
        $action = (string) input('action');
        $old = $_POST;
        // Critical account changes need a fresh one-time code / passkey, not just the password
        $stepFor = ['password' => 'password', 'email_start' => 'email', 'backup_start' => 'backup_email', 'backup_remove' => 'backup_email'];
        if (isset($stepFor[$action])) require_stepup($stepFor[$action]);
        switch ($action) {
            case 'profile':
                $name = trim((string) input('name'));
                $username = strtolower(trim((string) input('username')));
                if (mb_strlen($name) < 2) $errors['name'] = 'Enter your name.';
                if ($p = username_problems($username, $uid)) $errors['username'] = $p;
                if (!$errors) {
                    db_update('users', ['name' => mb_substr($name, 0, 120), 'username' => $username !== '' ? $username : null], $uid);
                    if ($username !== (string) $row['username']) auth_log($username === '' ? 'username_removed' : 'username_changed', $uid, $row['email']);
                    flash('success', 'Profile updated.');
                    redirect('admin/profile');
                }
                break;

            case 'password':
                $new = (string) ($_POST['new_password'] ?? '');
                $ok = profile_password_ok($row, (string) ($_POST['current_password'] ?? ''));
                if ($ok !== true) $errors['current_password'] = $ok;
                elseif ($p = password_problems($new)) $errors['new_password'] = $p;
                elseif ($new !== (string) ($_POST['confirm_password'] ?? '')) $errors['confirm_password'] = 'Passwords do not match.';
                elseif (password_verify($new, (string) $row['password_hash'])) $errors['new_password'] = 'Choose a password different from the current one.';
                if (!$errors) {
                    set_password($row, $new);
                    $_SESSION['ver'] = session_fingerprint($uid); // keep this session signed in
                    if (!empty($_POST['keep_device'])) trust_device($uid);
                    flash('success', 'Password changed. All other sessions and trusted browsers were signed out, and a security notice was emailed to you.');
                    redirect('admin/profile');
                }
                break;

            case 'email_start':
                $new = strtolower(trim((string) input('new_email')));
                $ok = profile_password_ok($row, (string) ($_POST['email_password'] ?? ''));
                if (!filter_var($new, FILTER_VALIDATE_EMAIL)) $errors['new_email'] = 'Enter a valid email address.';
                elseif ($new === strtolower((string) $row['email'])) $errors['new_email'] = 'This is already your sign-in email.';
                elseif (email_in_use($new, $uid)) $errors['new_email'] = 'This email is already used by another account.';
                if (!$errors && $ok !== true) $errors['email_password'] = $ok;
                if (!$errors) {
                    $res = otp_send($row, 'email_change', $new, $new);
                    if ($res === true) {
                        $_SESSION['pending_email'] = ['to' => $new, 'at' => time()];
                        flash('info', 'We sent a 6-digit code to ' . $new . '. Enter it below to confirm the change.');
                        redirect('admin/profile#email');
                    }
                    $errors['new_email'] = $res;
                }
                break;

            case 'email_verify':
                $pending = $_SESSION['pending_email'] ?? null;
                if (!$pending) { flash('error', 'That email change expired. Please start again.'); redirect('admin/profile#email'); }
                $r = otp_check($uid, (string) input('code'), 'email_change');
                if (!$r['ok']) { $errors['email_code'] = $r['error']; break; }
                if ($r['target'] !== $pending['to'] || email_in_use($pending['to'], $uid)) { $errors['email_code'] = 'This email can no longer be used. Please start again.'; unset($_SESSION['pending_email']); break; }
                $previous = (string) $row['email'];
                $update = ['email' => $pending['to']];
                // If the new primary was the backup, clear the backup so they stay distinct
                if (strtolower((string) $row['backup_email']) === $pending['to']) $update += ['backup_email' => null, 'backup_email_verified_at' => null];
                db_update('users', $update, $uid);
                unset($_SESSION['pending_email'], $_SESSION['otp_dev_code']);
                auth_log('email_changed', $uid, $pending['to']);
                security_notice(array_merge($row, $update), 'Your sign-in email was changed',
                    'The sign-in email for your ' . company('name') . ' control panel account was changed from ' . $previous . ' to ' . $pending['to'] . ' on ' . date('j M Y \a\t H:i') . ' from IP ' . client_ip() . '.', $previous);
                flash('success', 'Your sign-in email is now ' . $pending['to'] . '. A notice was sent to the old address.');
                redirect('admin/profile');

            case 'email_cancel':
                unset($_SESSION['pending_email'], $_SESSION['otp_dev_code']);
                db_query('UPDATE login_otps SET used_at = NOW() WHERE user_id = ? AND purpose = ? AND used_at IS NULL', [$uid, 'email_change']);
                flash('info', 'Email change cancelled.');
                redirect('admin/profile#email');

            case 'backup_start':
                $new = strtolower(trim((string) input('backup_email')));
                $ok = profile_password_ok($row, (string) ($_POST['backup_password'] ?? ''));
                if (!filter_var($new, FILTER_VALIDATE_EMAIL)) $errors['backup_email'] = 'Enter a valid email address.';
                elseif ($new === strtolower((string) $row['email'])) $errors['backup_email'] = 'Your backup email must be different from your sign-in email.';
                elseif ($new === strtolower((string) $row['backup_email']) && $row['backup_email_verified_at']) $errors['backup_email'] = 'This is already your verified backup email.';
                if (!$errors && $ok !== true) $errors['backup_password'] = $ok;
                if (!$errors) {
                    $res = otp_send($row, 'backup_email', $new, $new);
                    if ($res === true) {
                        $_SESSION['pending_backup'] = ['to' => $new, 'at' => time()];
                        flash('info', 'We sent a 6-digit code to ' . $new . '. Enter it below to confirm your backup email.');
                        redirect('admin/profile#backup');
                    }
                    $errors['backup_email'] = $res;
                }
                break;

            case 'backup_verify':
                $pending = $_SESSION['pending_backup'] ?? null;
                if (!$pending) { flash('error', 'That backup email request expired. Please start again.'); redirect('admin/profile#backup'); }
                $r = otp_check($uid, (string) input('code'), 'backup_email');
                if (!$r['ok']) { $errors['backup_code'] = $r['error']; break; }
                if ($r['target'] !== $pending['to']) { $errors['backup_code'] = 'Code does not match this request. Please start again.'; unset($_SESSION['pending_backup']); break; }
                $previous = $row['backup_email_verified_at'] ? (string) $row['backup_email'] : null;
                db_update('users', ['backup_email' => $pending['to'], 'backup_email_verified_at' => now()], $uid);
                unset($_SESSION['pending_backup'], $_SESSION['otp_dev_code']);
                auth_log($previous ? 'backup_email_changed' : 'backup_email_added', $uid, $pending['to']);
                security_notice(array_merge($row, ['backup_email' => $pending['to'], 'backup_email_verified_at' => now()]),
                    $previous ? 'Your backup email was changed' : 'A backup email was added',
                    ($previous ? 'The backup email for your account was changed from ' . $previous . ' to ' : 'A backup email was added to your account: ') . $pending['to'] . '. It can receive sign-in and password-reset codes.', $previous);
                flash('success', 'Backup email ' . $pending['to'] . ' is verified. It can now receive sign-in and reset codes.');
                redirect('admin/profile#backup');

            case 'backup_cancel':
                unset($_SESSION['pending_backup'], $_SESSION['otp_dev_code']);
                db_query('UPDATE login_otps SET used_at = NOW() WHERE user_id = ? AND purpose = ? AND used_at IS NULL', [$uid, 'backup_email']);
                flash('info', 'Backup email request cancelled.');
                redirect('admin/profile#backup');

            case 'backup_remove':
                if (!$row['backup_email']) redirect('admin/profile#backup');
                $ok = profile_password_ok($row, (string) ($_POST['remove_password'] ?? ''));
                if ($ok !== true) { $errors['remove_password'] = $ok; break; }
                $removed = (string) $row['backup_email'];
                $wasVerified = (bool) $row['backup_email_verified_at'];
                db_update('users', ['backup_email' => null, 'backup_email_verified_at' => null], $uid);
                auth_log('backup_email_removed', $uid, $removed);
                security_notice(array_merge($row, ['backup_email' => null, 'backup_email_verified_at' => null]), 'Your backup email was removed',
                    'The backup email ' . $removed . ' was removed from your account on ' . date('j M Y \a\t H:i') . ' from IP ' . client_ip() . '.', $wasVerified ? $removed : null);
                flash('success', 'Backup email removed.');
                redirect('admin/profile#backup');

            case 'resend':
                $which = input('which') === 'backup' ? 'pending_backup' : 'pending_email';
                $pending = $_SESSION[$which] ?? null;
                if (!$pending) redirect('admin/profile');
                $purpose = $which === 'pending_backup' ? 'backup_email' : 'email_change';
                $res = otp_send($row, $purpose, $pending['to'], $pending['to']);
                if ($res === true) $_SESSION[$which]['at'] = time();
                flash($res === true ? 'success' : 'error', $res === true ? 'A new code was sent to ' . $pending['to'] . '.' : $res);
                redirect('admin/profile#' . ($which === 'pending_backup' ? 'backup' : 'email'));

            default:
                abort(400, 'Unknown action.');
        }
    }

    $events = db_all("SELECT event, ip, created_at FROM auth_events WHERE user_id = ? ORDER BY id DESC LIMIT 12", [$uid]);
    admin_render('My profile', 'profile', [
        'user' => $row, 'errors' => $errors, 'old' => $old, 'events' => $events,
        'pendingEmail' => $_SESSION['pending_email']['to'] ?? null,
        'pendingBackup' => $_SESSION['pending_backup']['to'] ?? null,
        'devCode' => config('debug') ? ($_SESSION['otp_dev_code'] ?? null) : null,
        'passkeys' => db_all('SELECT id, name, transports, backed_up, created_at, last_used_at FROM passkeys WHERE user_id = ? ORDER BY id', [$uid]),
        'totpOn' => totp_enabled_for($row),
        'totpPending' => isset($_SESSION['totp_pending']) && time() - (int) $_SESSION['totp_pending']['at'] <= 900 ? $_SESSION['totp_pending']['secret'] : null,
        'recoveryOnce' => (function () { $c = $_SESSION['recovery_codes_once'] ?? null; unset($_SESSION['recovery_codes_once']); return $c; })(),
        'recoveryExportLeft' => (function () { $x = $_SESSION['recovery_export'] ?? null; return is_array($x) ? max(0, (int) $x['until'] - time()) : 0; })(),
        'recoveryLeft' => recovery_codes_left($uid),
        'devices' => db_all('SELECT * FROM known_devices WHERE user_id = ? ORDER BY last_seen DESC LIMIT 30', [$uid]),
        'tgLinkUrl' => (int) ($_SESSION['telegram_link_until'] ?? 0) > time() ? (string) ($_SESSION['telegram_link_url'] ?? '') : '',
        'currentDevice' => !empty($_COOKIE[DEVICE_COOKIE]) ? device_hash() : '',
    ]);
}
