<?php /** @var array $tabs @var string $tab @var array $schema @var array $values @var array $errors @var array $secrets */ ?>
<div class="grid gap-6 lg:grid-cols-[230px_1fr]">
  <nav class="adm-card h-fit p-2" aria-label="Settings sections">
    <?php foreach ($tabs as $k => [$l, $i]): ?>
    <a href="<?= e(url('admin/settings/' . $k)) ?>" class="adm-subnav<?= $k === $tab ? ' is-active' : '' ?>"><?= icon($i, 'h-4 w-4') ?><?= e($l) ?></a>
    <?php endforeach ?>
  </nav>

  <div>
    <h2 class="mb-4 text-xl font-bold text-navy"><?= e($tabs[$tab][0]) ?></h2>
    <?php if ($tab === 'integrations' || $tab === 'email'):
        $tsKey = (string) setting('turnstile_site_key', '');
        $tsTest = (bool) preg_match('~^[123]x0{10,}~', $tsKey);
        $smtpOk = setting('smtp_host') && setting('mail_test_ok_at');
        $status = [
            ['Google Analytics 4', 'bar-chart-3', (bool) setting('ga4_id'), setting('ga4_id') ?: 'Not set'],
            ['Google Tag Manager', 'tags', (bool) setting('gtm_id'), setting('gtm_id') ?: 'Not set'],
            ['Meta Pixel', 'target', (bool) setting('meta_pixel_id'), setting('meta_pixel_id') ? 'Connected' : 'Not set'],
            ['Microsoft Clarity', 'mouse-pointer-click', (bool) setting('clarity_id'), setting('clarity_id') ?: 'Not set'],
            ['Tawk.to live chat', 'message-circle', setting('tawk_property_id') && setting('tawk_widget_id'), setting('tawk_property_id') ? 'Connected' : 'Not set'],
            ['WhatsApp button', 'phone', setting('whatsapp_enabled') && setting('whatsapp_number'), setting('whatsapp_enabled') ? (setting('whatsapp_number') ? 'On' : 'Number missing') : 'Off'],
            ['Cloudflare Turnstile', 'shield-check', turnstile_enabled() && !$tsTest, turnstile_enabled() ? ($tsTest ? 'TEST keys — replace before launch' : 'Protecting forms') : 'Not set'],
            ['Cloudflare proxy / Tunnel', 'cloud', (bool) setting('cf_trust_proxy'), setting('cf_trust_proxy') ? 'Real visitor IPs on' : 'Off'],
            ['Search Console', 'search', (bool) setting('google_site_verification'), setting('google_site_verification') ? 'Verified tag set' : 'Not set'],
            ['Bing Webmaster', 'search', (bool) setting('bing_site_verification'), setting('bing_site_verification') ? 'Verified tag set' : 'Not set'],
            ['SMTP email', 'mail', $smtpOk, setting('smtp_host') ? ($smtpOk ? 'Tested ' . time_ago((string) setting('mail_test_ok_at')) : 'Configured, not tested') : (config('debug') ? 'Dev mailbox (debug)' : 'Not set')],
            ['Cookie consent', 'cookie', (bool) setting('cookie_banner_enabled', true), setting('cookie_banner_enabled', true) ? 'Banner on' : 'Off'],
        ];
        $on = count(array_filter($status, fn($s) => $s[2]));
    ?>
    <div class="adm-card mb-6 p-5">
      <div class="mb-4 flex items-center justify-between gap-3"><h3 class="font-semibold text-navy">Integration status</h3><span class="text-sm text-charcoal/60"><?= $on ?> of <?= count($status) ?> active</span></div>
      <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($status as [$name, $ic, $ok, $note]): $warn = !$ok && (str_contains((string) $note, 'TEST') || str_contains((string) $note, 'missing') || str_contains((string) $note, 'not tested')); ?>
        <li class="flex items-center gap-3 rounded-lg border border-black/5 p-3">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full <?= $ok ? 'bg-emerald-50 text-emerald-700' : ($warn ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-500') ?>"><?= icon($ic, 'h-4 w-4') ?></span>
          <span class="min-w-0"><span class="block text-sm font-semibold text-navy"><?= e($name) ?></span><span class="block truncate text-xs <?= $warn ? 'text-amber-700' : 'text-charcoal/60' ?>" title="<?= e((string) $note) ?>"><?= e((string) $note) ?></span></span>
        </li>
        <?php endforeach ?>
      </ul>
    </div>
    <?php endif ?>
    <?php if ($tab === 'system'): ?>
      <div class="adm-card divide-y divide-black/5">
        <?php
        $uploadsWritable = is_writable(PUBLIC_DIR . '/uploads');
        $storageWritable = is_writable(STORAGE_DIR);
        $checks = [
            ['PHP version', PHP_VERSION, version_compare(PHP_VERSION, '8.1', '>=')],
            ['Database', 'MySQL/MariaDB ' . db_val('SELECT VERSION()'), true],
            ['public/uploads writable', $uploadsWritable ? 'Yes' : 'No — media uploads will fail', $uploadsWritable],
            ['app/storage writable', $storageWritable ? 'Yes' : 'No — lead attachments & logs will fail', $storageWritable],
            ['HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'Enabled' : 'Not detected — enable SSL on your host or Cloudflare', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'],
            ['Debug mode', config('debug') ? 'ON — disable in app/config.php for production' : 'Off', !config('debug')],
            ['Upload limit', ini_get('upload_max_filesize') . ' per file / ' . ini_get('post_max_size') . ' per request', true],
            ['Cloudflare', !empty($_SERVER['HTTP_CF_RAY']) ? 'Requests are coming through Cloudflare' : 'Not detected', true],
            ['App version', APP_VERSION, true],
        ];
        foreach ($checks as [$l, $v, $ok]): ?>
        <div class="flex items-center justify-between gap-4 px-6 py-3.5"><span class="text-sm font-medium text-navy"><?= e($l) ?></span><span class="flex items-center gap-2 text-right text-sm <?= $ok ? 'text-charcoal/70' : 'font-semibold text-red-600' ?>"><?= icon($ok ? 'circle-check' : 'circle-alert', 'h-4 w-4 ' . ($ok ? 'text-emerald-600' : 'text-red-600')) ?><?= e($v) ?></span></div>
        <?php endforeach ?>
      </div>
      <?php if (config('debug')): ?>
      <a href="<?= e(url('admin/dev-mail')) ?>" class="adm-card mt-6 flex items-center gap-4 p-6 hover:border-steel"><?= icon('inbox', 'h-6 w-6 text-steel') ?><span><strong class="block text-navy">Dev mailbox</strong><span class="text-sm text-charcoal/60">Debug mode is on — view every email the site has sent (OTP codes, lead alerts, auto-replies).</span></span><?= icon('arrow-right', 'ml-auto h-5 w-5 text-charcoal/40') ?></a>
      <?php endif ?>
      <div class="adm-card mt-6 p-6">
        <h3 class="adm-card-title"><?= icon('cloud', 'h-5 w-5 text-steel') ?> Cloudflare Tunnel (expose a local or private server)</h3>
        <p class="mt-2 text-sm leading-relaxed text-charcoal/70">To share this site from your computer or a private server without opening ports, install <code>cloudflared</code> and run:</p>
        <pre class="adm-code mt-3">cloudflared tunnel --url http://localhost:8000</pre>
        <p class="mt-3 text-sm leading-relaxed text-charcoal/70">For a permanent tunnel on your own domain: <code>cloudflared tunnel login</code> → <code>cloudflared tunnel create industrial</code> → route DNS → <code>cloudflared tunnel run industrial</code>. Then enable <strong>“behind Cloudflare proxy or Tunnel”</strong> in <a class="adm-link" href="<?= e(url('admin/settings/integrations')) ?>">Integrations</a> so visitor IPs are recorded correctly, and set the public URL in <a class="adm-link" href="<?= e(url('admin/settings/seo')) ?>">SEO</a>.</p>
      </div>
    <?php else: ?>
    <form method="post" class="adm-card p-6">
      <?= csrf_field() ?>
      <div class="grid gap-4 sm:grid-cols-2"><?= render_fields($schema, $values, $errors) ?></div>
      <?php foreach ($secrets as $k => $has) if ($has && isset($schema[$k])): ?><p class="mt-3 text-xs text-emerald-700"><?= icon('lock', 'inline h-3.5 w-3.5') ?> <?= e($schema[$k]['label']) ?> is saved.</p><?php endif ?>
      <div class="mt-6 flex flex-wrap items-center justify-end gap-2 border-t border-black/10 pt-5">
        <button class="adm-btn adm-btn-primary"><?= icon('save', 'h-4 w-4') ?> Save settings</button>
      </div>
    </form>
    <?php if ($tab === 'security'): $events = db_all('SELECT e.*, u.name FROM auth_events e LEFT JOIN users u ON u.id = e.user_id ORDER BY e.id DESC LIMIT 40');
      $labels = ['login_success' => ['Signed in', 'bg-emerald-100 text-emerald-800'], 'login_failed' => ['Wrong password', 'bg-red-100 text-red-700'], 'login_locked' => ['Locked out', 'bg-red-100 text-red-700'],
        'otp_sent' => ['Code sent', 'bg-blue-100 text-blue-800'], 'otp_failed' => ['Wrong code', 'bg-amber-100 text-amber-800'], 'otp_send_failed' => ['Code email failed', 'bg-red-100 text-red-700'],
        'login_captcha_failed' => ['Bot check failed', 'bg-red-100 text-red-700'], 'session_timeout' => ['Timed out', 'bg-gray-200 text-gray-700']]; ?>
    <div class="adm-card mt-6">
      <div class="flex items-center justify-between p-6 pb-3"><h3 class="adm-card-title"><?= icon('scroll-text', 'h-5 w-5 text-steel') ?> Recent sign-in activity</h3><span class="text-xs text-charcoal/50">Kept for 180 days</span></div>
      <?php if ($events): ?><div class="overflow-x-auto"><table class="adm-table"><thead><tr><th>Event</th><th>User</th><th>IP address</th><th>When</th></tr></thead><tbody>
      <?php foreach ($events as $ev): [$l, $c] = $labels[$ev['event']] ?? [$ev['event'], 'bg-gray-100 text-gray-700']; ?>
        <tr><td><span class="adm-badge <?= $c ?>"><?= e($l) ?></span></td><td class="text-sm"><?= e($ev['name'] ?: $ev['email']) ?></td><td class="text-sm text-charcoal/65"><?= e($ev['ip']) ?></td><td class="whitespace-nowrap text-sm text-charcoal/60" title="<?= e($ev['created_at']) ?>"><?= e(time_ago($ev['created_at'])) ?></td></tr>
      <?php endforeach ?></tbody></table></div>
      <?php else: ?><p class="px-6 pb-6 text-sm text-charcoal/55">No sign-in activity recorded yet.</p><?php endif ?>
    </div>
    <?php endif ?>
    <?php if ($tab === 'email'): ?>
    <form method="post" action="<?= e(url('admin/settings-test-email')) ?>" class="mt-4"><?= csrf_field() ?><button class="adm-btn adm-btn-light"><?= icon('send', 'h-4 w-4') ?> Send a test email</button> <span class="ml-2 text-sm text-charcoal/55">Save first, then test.</span></form>
    <?php endif ?>
    <?php endif ?>
  </div>
</div>
