<?php
/**
 * Third-party integrations configured in Admin → Settings → Integrations.
 * Non-essential scripts are emitted as type="text/plain" data-consent and only
 * activated by main.js after the visitor accepts cookies (when the banner is enabled).
 */
declare(strict_types=1);

function consent_required(): bool
{
    return (bool) setting('cookie_banner_enabled', true);
}

/** Wrap a script so it waits for consent (or runs immediately if consent is not required). */
function consent_script(string $code = '', string $src = '', bool $async = true): string
{
    $gated = consent_required();
    $type = $gated ? ' type="text/plain" data-consent' : '';
    if ($src !== '') {
        return $gated
            ? '<script' . $type . ' data-src="' . e($src) . '"></script>'
            : '<script' . ($async ? ' async' : '') . ' src="' . e($src) . '"></script>';
    }
    return '<script' . $type . '>' . $code . '</script>';
}

function clean_id(string $key, string $pattern): string
{
    $v = trim((string) setting($key, ''));
    return preg_match($pattern, $v) ? $v : '';
}

function integrations_head(): string
{
    $out = [];
    if ($v = trim((string) setting('google_site_verification', ''))) $out[] = '<meta name="google-site-verification" content="' . e($v) . '">';
    if ($v = trim((string) setting('bing_site_verification', ''))) $out[] = '<meta name="msvalidate.01" content="' . e($v) . '">';

    if ($ga = clean_id('ga4_id', '~^G-[A-Z0-9]{4,20}$~')) {
        $out[] = consent_script('', 'https://www.googletagmanager.com/gtag/js?id=' . $ga);
        $out[] = consent_script("window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$ga}',{anonymize_ip:true});");
    }
    if ($gtm = clean_id('gtm_id', '~^GTM-[A-Z0-9]{4,12}$~')) {
        $out[] = consent_script("(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$gtm}');");
    }
    if ($px = clean_id('meta_pixel_id', '~^\d{6,20}$~')) {
        $out[] = consent_script("!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{$px}');fbq('track','PageView');");
    }
    if ($cl = clean_id('clarity_id', '~^[a-z0-9]{6,20}$~i')) {
        $out[] = consent_script("(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script','{$cl}');");
    }
    if ($code = (string) setting('custom_head_always', '')) $out[] = $code;
    if ($code = (string) setting('custom_head_consent', '')) {
        // Custom code needing consent is stored in a template and injected by main.js after acceptance.
        $out[] = consent_required() ? '<template data-consent-html>' . $code . '</template>' : $code;
    }
    return implode("\n  ", $out);
}

function integrations_body_end(): string
{
    $out = [];
    $prop = clean_id('tawk_property_id', '~^[a-f0-9]{12,40}$~i');
    $widget = clean_id('tawk_widget_id', '~^[a-z0-9]{4,20}$~i');
    if ($prop && $widget) {
        $code = "var Tawk_API=Tawk_API||{},Tawk_LoadStart=new Date();(function(){var s1=document.createElement('script'),s0=document.getElementsByTagName('script')[0];s1.async=true;s1.src='https://embed.tawk.to/{$prop}/{$widget}';s1.charset='UTF-8';s1.setAttribute('crossorigin','*');s0.parentNode.insertBefore(s1,s0);})();";
        $out[] = setting('tawk_require_consent', true) ? consent_script($code) : '<script>' . $code . '</script>';
    }
    if (turnstile_enabled()) {
        $out[] = '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }
    if ($code = (string) setting('custom_body_always', '')) $out[] = $code;
    return implode("\n", $out);
}

function whatsapp_link(?string $message = null): string
{
    $num = preg_replace('~\D~', '', (string) setting('whatsapp_number', ''));
    if (!$num) return '';
    $msg = $message ?? (string) setting('whatsapp_message', 'Hello, I would like to enquire about your products.');
    return 'https://wa.me/' . $num . ($msg !== '' ? '?text=' . rawurlencode($msg) : '');
}

function whatsapp_button(): string
{
    if (!setting('whatsapp_enabled') || !($href = whatsapp_link())) return '';
    $pos = setting('whatsapp_position', 'right') === 'left' ? 'wa-left' : 'wa-right';
    $label = (string) setting('whatsapp_label', 'Chat on WhatsApp');
    $svg = '<svg viewBox="0 0 32 32" class="h-7 w-7" aria-hidden="true" fill="currentColor"><path d="M16.04 3C9.4 3 4 8.36 4 14.97c0 2.11.56 4.17 1.62 5.98L4 29l8.27-1.58a12.1 12.1 0 0 0 3.77.6C22.7 28.02 28 22.66 28 16.05 28 9.44 22.7 3 16.04 3Zm0 22.83c-1.17 0-2.33-.2-3.43-.6l-.49-.18-4.9.94.98-4.74-.21-.5a9.66 9.66 0 0 1-1.12-4.75c0-5.4 4.42-9.79 9.86-9.79 5.43 0 9.86 4.47 9.86 9.86 0 5.4-4.43 9.76-9.86 9.76Zm5.4-7.33c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15s-.77.96-.94 1.16c-.17.2-.35.22-.64.07a8.06 8.06 0 0 1-2.38-1.47 8.9 8.9 0 0 1-1.65-2.04c-.17-.3 0-.46.13-.6.13-.14.3-.35.44-.52.15-.18.2-.3.3-.5.1-.2.05-.38-.02-.53-.08-.15-.67-1.6-.92-2.2-.24-.57-.48-.5-.67-.5h-.57c-.2 0-.52.08-.79.37-.27.3-1.04 1.01-1.04 2.47s1.06 2.87 1.21 3.07c.15.2 2.09 3.18 5.06 4.46.71.3 1.26.49 1.69.62.71.23 1.36.2 1.87.12.57-.09 1.75-.71 2-1.4.25-.69.25-1.28.17-1.4-.07-.13-.27-.2-.57-.35Z"/></svg>';
    return '<a href="' . e($href) . '" class="wa-float ' . $pos . '" target="_blank" rel="noopener" data-track="whatsapp" aria-label="' . e($label) . '">' . $svg . '<span class="wa-label">' . e($label) . '</span></a>';
}

/** Cookie policy table — reflects the integrations that are actually enabled. */
function cookie_table_html(): string
{
    $rows = [['cookie-consent', 'Local storage (first-party)', 'Strictly necessary', 'Remembers your cookie choice so the banner is not shown on every page.', '12 months']];
    if (clean_id('ga4_id', '~^G-~') || clean_id('gtm_id', '~^GTM-~')) $rows[] = ['_ga, _ga_*', 'Cookie (Google Analytics)', 'Analytics', 'Distinguishes visitors and sessions to produce anonymous usage statistics.', 'Up to 2 years'];
    if (clean_id('meta_pixel_id', '~^\d+$~')) $rows[] = ['_fbp', 'Cookie (Meta)', 'Marketing', 'Measures the effectiveness of advertising on Meta platforms.', '3 months'];
    if (clean_id('clarity_id', '~^[a-z0-9]+$~i')) $rows[] = ['_clck, _clsk, CLID', 'Cookie (Microsoft Clarity)', 'Analytics', 'Records anonymised interactions to help us improve the website.', 'Up to 1 year'];
    if (setting('tawk_property_id') && setting('tawk_widget_id')) $rows[] = ['twk_*, TawkConnectionTime', 'Cookie / storage (Tawk.to)', setting('tawk_require_consent', true) ? 'Functional (consent)' : 'Functional', 'Enables the live chat widget and remembers your conversation.', 'Up to 6 months'];
    if (turnstile_enabled()) $rows[] = ['cf_clearance / Turnstile', 'Cloudflare', 'Strictly necessary', 'Protects our forms from spam and automated abuse.', 'Session'];

    $html = '<div class="table-wrap"><table><thead><tr><th scope="col">Name</th><th scope="col">Provider / type</th><th scope="col">Category</th><th scope="col">Purpose</th><th scope="col">Duration</th></tr></thead><tbody>';
    foreach ($rows as $r) $html .= '<tr><td><code>' . e($r[0]) . '</code></td><td>' . e($r[1]) . '</td><td>' . e($r[2]) . '</td><td>' . e($r[3]) . '</td><td>' . e($r[4]) . '</td></tr>';
    return $html . '</tbody></table></div>';
}
