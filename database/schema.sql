-- Industrial Website CMS — MySQL / MariaDB schema (utf8mb4)
-- Imported automatically by the installer (/install or `php app/cli.php install`).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  username      VARCHAR(60) NULL UNIQUE,
  backup_email  VARCHAR(190) NULL,
  backup_email_verified_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  totp_secret   TEXT NULL,
  totp_enabled_at DATETIME NULL,
  avatar        VARCHAR(500) NULL,
  notify_prefs  TEXT NULL,
  session_salt  VARCHAR(32) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','editor','sales') NOT NULL DEFAULT 'editor',
  active        TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key`      VARCHAR(100) NOT NULL PRIMARY KEY,
  `value`    MEDIUMTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type             ENUM('post','page') NOT NULL DEFAULT 'post',
  name             VARCHAR(150) NOT NULL,
  slug             VARCHAR(160) NOT NULL,
  description      TEXT NULL,
  meta_title       VARCHAR(190) NULL,
  meta_description VARCHAR(300) NULL,
  sort_order       INT NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cat_type_slug (type, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug             VARCHAR(160) NOT NULL UNIQUE,
  title            VARCHAR(190) NOT NULL,
  template         VARCHAR(40) NOT NULL DEFAULT 'default',
  status           ENUM('draft','published') NOT NULL DEFAULT 'published',
  category_id      INT UNSIGNED NULL,
  hero_eyebrow     VARCHAR(190) NULL,
  hero_title       VARCHAR(255) NULL,
  hero_lead        TEXT NULL,
  hero_image       VARCHAR(500) NULL,
  content          MEDIUMTEXT NULL,
  data             MEDIUMTEXT NULL,
  meta_title       VARCHAR(190) NULL,
  meta_description VARCHAR(300) NULL,
  og_image         VARCHAR(500) NULL,
  noindex          TINYINT(1) NOT NULL DEFAULT 0,
  is_system        TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at       DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pages_category (category_id),
  CONSTRAINT fk_pages_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_categories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(150) NOT NULL,
  slug       VARCHAR(160) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id      INT UNSIGNED NULL,
  name             VARCHAR(190) NOT NULL,
  slug             VARCHAR(160) NOT NULL UNIQUE,
  icon             VARCHAR(60) NULL,
  image            VARCHAR(500) NULL,
  image_alt        VARCHAR(255) NULL,
  summary          TEXT NULL,
  overview         MEDIUMTEXT NULL,
  features         TEXT NULL,
  applications     TEXT NULL,
  specs            TEXT NULL,
  datasheet        VARCHAR(500) NULL,
  meta_title       VARCHAR(190) NULL,
  meta_description VARCHAR(300) NULL,
  status           ENUM('draft','published') NOT NULL DEFAULT 'published',
  sort_order       INT NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS industries (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(190) NOT NULL,
  slug        VARCHAR(160) NOT NULL UNIQUE,
  icon        VARCHAR(60) NULL,
  image       VARCHAR(500) NULL,
  image_alt   VARCHAR(255) NULL,
  summary     TEXT NULL,
  body        TEXT NULL,
  product_ids TEXT NULL,
  status      ENUM('draft','published') NOT NULL DEFAULT 'published',
  sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title            VARCHAR(255) NOT NULL,
  slug             VARCHAR(190) NOT NULL UNIQUE,
  excerpt          TEXT NULL,
  content          MEDIUMTEXT NULL,
  featured_image   VARCHAR(500) NULL,
  featured_alt     VARCHAR(255) NULL,
  category_id      INT UNSIGNED NULL,
  tags             VARCHAR(500) NULL,
  author_id        INT UNSIGNED NULL,
  status           ENUM('draft','published','scheduled') NOT NULL DEFAULT 'draft',
  published_at     DATETIME NULL,
  meta_title       VARCHAR(190) NULL,
  meta_description VARCHAR(300) NULL,
  focus_keyword    VARCHAR(190) NULL,
  canonical_url    VARCHAR(500) NULL,
  noindex          TINYINT(1) NOT NULL DEFAULT 0,
  reading_minutes  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  views            INT UNSIGNED NOT NULL DEFAULT 0,
  deleted_at       DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_posts_public (status, published_at),
  KEY idx_posts_category (category_id),
  FULLTEXT KEY ft_posts (title, excerpt, content),
  CONSTRAINT fk_posts_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_posts_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  path          VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime          VARCHAR(100) NOT NULL,
  size          INT UNSIGNED NOT NULL,
  width         INT UNSIGNED NULL,
  height        INT UNSIGNED NULL,
  alt           VARCHAR(255) NULL,
  variants      TEXT NULL,
  uploaded_by   INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type         VARCHAR(20) NOT NULL DEFAULT 'contact',
  status       VARCHAR(20) NOT NULL DEFAULT 'new',
  priority     ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  name         VARCHAR(150) NOT NULL,
  company      VARCHAR(190) NULL,
  email        VARCHAR(190) NULL,
  phone        VARCHAR(50) NULL,
  product      VARCHAR(190) NULL,
  quantity     INT UNSIGNED NULL,
  required_by  DATE NULL,
  part_number  VARCHAR(190) NULL,
  message      TEXT NULL,
  items        MEDIUMTEXT NULL,
  value        DECIMAL(14,2) NULL,
  tags         VARCHAR(255) NULL,
  assigned_to  INT UNSIGNED NULL,
  follow_up_at DATE NULL,
  source_page  VARCHAR(500) NULL,
  referrer     VARCHAR(500) NULL,
  utm_source   VARCHAR(120) NULL,
  utm_medium   VARCHAR(120) NULL,
  utm_campaign VARCHAR(120) NULL,
  ip           VARCHAR(45) NULL,
  user_agent   VARCHAR(500) NULL,
  consent      TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_leads_status (status),
  KEY idx_leads_created (created_at),
  KEY idx_leads_followup (follow_up_at),
  CONSTRAINT fk_leads_user FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_files (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id       INT UNSIGNED NOT NULL,
  stored_name   VARCHAR(100) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime          VARCHAR(100) NOT NULL,
  size          INT UNSIGNED NOT NULL,
  CONSTRAINT fk_lead_files_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_notes (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  kind       VARCHAR(20) NOT NULL DEFAULT 'note',
  body       TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notes_lead (lead_id),
  CONSTRAINT fk_notes_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anonymous click events (WhatsApp / phone / email buttons) — no personal data stored
CREATE TABLE IF NOT EXISTS events (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type       VARCHAR(40) NOT NULL,
  page       VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_events_type_date (type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting for login attempts and public form submissions (IP stored as hash)
CREATE TABLE IF NOT EXISTS rate_limits (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bucket     VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rl_bucket (bucket, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin email one-time passcodes (2-step login). Codes are stored hashed.
CREATE TABLE IF NOT EXISTS login_otps (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  purpose    VARCHAR(30) NOT NULL DEFAULT 'login',
  target     VARCHAR(190) NULL,
  code_hash  VARCHAR(255) NOT NULL,
  attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_otp_user (user_id, created_at),
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security audit log for admin authentication
CREATE TABLE IF NOT EXISTS auth_events (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  email      VARCHAR(190) NULL,
  event      VARCHAR(40) NOT NULL,
  ip         VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_auth_events_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Content revision history (posts and pages). Snapshot of the previous version on every save.
CREATE TABLE IF NOT EXISTS revisions (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity     VARCHAR(20) NOT NULL,
  entity_id  INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  title      VARCHAR(255) NULL,
  snapshot   MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_revisions_entity (entity, entity_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 301/302 redirects managed in the admin (exact path match, query string ignored)
CREATE TABLE IF NOT EXISTS redirects (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source      VARCHAR(500) NOT NULL,
  source_hash CHAR(40) NOT NULL,
  target      VARCHAR(500) NOT NULL,
  code        SMALLINT UNSIGNED NOT NULL DEFAULT 301,
  note        VARCHAR(190) NULL,
  hits        INT UNSIGNED NOT NULL DEFAULT 0,
  last_hit_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_redirects_source (source_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paths that returned 404 (to find broken links and missing redirects)
CREATE TABLE IF NOT EXISTS not_found_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  path       VARCHAR(500) NOT NULL,
  path_hash  CHAR(40) NOT NULL,
  referrer   VARCHAR(500) NULL,
  hits       INT UNSIGNED NOT NULL DEFAULT 1,
  ignored    TINYINT(1) NOT NULL DEFAULT 0,
  first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_404_path (path_hash),
  KEY idx_404_last (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Case studies / projects
CREATE TABLE IF NOT EXISTS projects (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title            VARCHAR(190) NOT NULL,
  slug             VARCHAR(160) NOT NULL UNIQUE,
  client           VARCHAR(190) NULL,
  location         VARCHAR(190) NULL,
  industry_id      INT UNSIGNED NULL,
  product_ids      TEXT NULL,
  summary          TEXT NULL,
  challenge        MEDIUMTEXT NULL,
  solution         MEDIUMTEXT NULL,
  results          TEXT NULL,
  testimonial      TEXT NULL,
  testimonial_by   VARCHAR(190) NULL,
  image            VARCHAR(500) NULL,
  image_alt        VARCHAR(255) NULL,
  completed_at     DATE NULL,
  meta_title       VARCHAR(190) NULL,
  meta_description VARCHAR(300) NULL,
  status           ENUM('draft','published') NOT NULL DEFAULT 'draft',
  sort_order       INT NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quality / compliance certificates (hidden automatically after expiry)
CREATE TABLE IF NOT EXISTS certificates (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(190) NOT NULL,
  standard   VARCHAR(120) NULL,
  issuer     VARCHAR(190) NULL,
  number     VARCHAR(120) NULL,
  scope      TEXT NULL,
  issued_at  DATE NULL,
  expires_at DATE NULL,
  file       VARCHAR(500) NULL,
  status     ENUM('draft','published') NOT NULL DEFAULT 'published',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WebAuthn passkeys (public keys only — private keys never leave the user's device)
CREATE TABLE IF NOT EXISTS passkeys (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  credential_id VARCHAR(512) NOT NULL,
  public_key    TEXT NOT NULL,
  alg           INT NOT NULL,
  sign_count    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  transports    VARCHAR(190) NULL,
  aaguid        CHAR(36) NULL,
  backed_up     TINYINT(1) NOT NULL DEFAULT 0,
  name          VARCHAR(120) NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at  DATETIME NULL,
  UNIQUE KEY uq_passkeys_cred (credential_id(255)),
  KEY idx_passkeys_user (user_id),
  CONSTRAINT fk_passkeys_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-time recovery codes for authenticator-app (TOTP) two-factor sign-in. Stored hashed.
CREATE TABLE IF NOT EXISTS recovery_codes (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  code_hash  VARCHAR(255) NOT NULL,
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_recovery_user (user_id),
  CONSTRAINT fk_recovery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Client testimonials (shown on the home page once published; publish only with the client's permission)
CREATE TABLE IF NOT EXISTS testimonials (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quote        TEXT NOT NULL,
  author       VARCHAR(150) NOT NULL,
  role         VARCHAR(150) NULL,
  company      VARCHAR(190) NULL,
  rating       TINYINT UNSIGNED NULL,
  photo        VARCHAR(500) NULL,
  project_id   INT UNSIGNED NULL,
  permission   TINYINT(1) NOT NULL DEFAULT 0,
  status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  sort_order   INT NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security & activity notifications (in-app bell + email log)
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  event      VARCHAR(50) NOT NULL,
  severity   ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
  title      VARCHAR(190) NOT NULL,
  body       TEXT NULL,
  link       VARCHAR(300) NULL,
  ip         VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  emailed    TINYINT(1) NOT NULL DEFAULT 0,
  read_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user (user_id, read_at, id),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Browsers a user has signed in from (new-device sign-in alerts). Identified by a random cookie, stored hashed.
CREATE TABLE IF NOT EXISTS known_devices (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  device_hash CHAR(64) NOT NULL,
  label       VARCHAR(120) NULL,
  ip          VARCHAR(45) NULL,
  trusted_until  DATETIME NULL,
  remember_hash  CHAR(64) NULL,
  remember_until DATETIME NULL,
  first_seen  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_known_device (user_id, device_hash),
  CONSTRAINT fk_known_devices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unexpected errors (500s) with the reference shown to the visitor — Admin → Error log
CREATE TABLE IF NOT EXISTS app_errors (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref         VARCHAR(16) NOT NULL,
  fingerprint CHAR(40) NOT NULL,
  status      SMALLINT UNSIGNED NOT NULL DEFAULT 500,
  method      VARCHAR(10) NULL,
  path        VARCHAR(500) NULL,
  class       VARCHAR(120) NULL,
  message     TEXT NULL,
  location    VARCHAR(300) NULL,
  trace       MEDIUMTEXT NULL,
  user_id     INT UNSIGNED NULL,
  ip          VARCHAR(45) NULL,
  user_agent  VARCHAR(255) NULL,
  resolved_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_app_errors_ref (ref),
  KEY idx_app_errors_fp (fingerprint, created_at),
  KEY idx_app_errors_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quotations (Admin → Quotes); customers view, download and accept them at /q/{token}
CREATE TABLE IF NOT EXISTS quotes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number         VARCHAR(30) NOT NULL,
  lead_id        INT UNSIGNED NULL,
  status         VARCHAR(20) NOT NULL DEFAULT 'draft',
  customer_name  VARCHAR(150) NOT NULL,
  company        VARCHAR(190) NULL,
  email          VARCHAR(190) NULL,
  phone          VARCHAR(50) NULL,
  address        TEXT NULL,
  customer_tax_id VARCHAR(60) NULL,
  currency       VARCHAR(3) NOT NULL DEFAULT 'INR',
  items          MEDIUMTEXT NOT NULL,
  tax_label      VARCHAR(30) NOT NULL DEFAULT 'GST',
  tax_rate       DECIMAL(5,2) NOT NULL DEFAULT 0,
  subtotal       DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax            DECIMAL(14,2) NOT NULL DEFAULT 0,
  total          DECIMAL(14,2) NOT NULL DEFAULT 0,
  valid_until    DATE NULL,
  delivery       VARCHAR(255) NULL,
  payment_terms  VARCHAR(255) NULL,
  notes          TEXT NULL,
  terms          TEXT NULL,
  token          CHAR(40) NOT NULL,
  sent_at        DATETIME NULL,
  viewed_at      DATETIME NULL,
  decided_at     DATETIME NULL,
  decided_name   VARCHAR(150) NULL,
  decided_ip     VARCHAR(45) NULL,
  decline_reason TEXT NULL,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_quotes_number (number),
  UNIQUE KEY uq_quotes_token (token),
  KEY idx_quotes_lead (lead_id),
  KEY idx_quotes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Part-number cross-reference (old / competitor numbers → our product)
CREATE TABLE IF NOT EXISTS part_refs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref_number  VARCHAR(120) NOT NULL,
  ref_norm    VARCHAR(120) NOT NULL,
  brand       VARCHAR(120) NULL,
  product_id  INT UNSIGNED NULL,
  our_part    VARCHAR(120) NULL,
  notes       VARCHAR(255) NULL,
  status      ENUM('draft','published') NOT NULL DEFAULT 'published',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_part_refs_norm (ref_norm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Downloads centre (brochures, manuals, CAD, certificates)
CREATE TABLE IF NOT EXISTS downloads (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(190) NOT NULL,
  category    VARCHAR(80) NOT NULL DEFAULT 'Brochures',
  description TEXT NULL,
  file        VARCHAR(500) NOT NULL,
  gated       TINYINT(1) NOT NULL DEFAULT 0,
  status      ENUM('draft','published') NOT NULL DEFAULT 'published',
  sort_order  INT NOT NULL DEFAULT 0,
  downloads   INT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Distributor / dealer network
CREATE TABLE IF NOT EXISTS distributors (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(190) NOT NULL,
  region      VARCHAR(120) NOT NULL,
  city        VARCHAR(120) NULL,
  country     VARCHAR(120) NULL,
  address     TEXT NULL,
  phone       VARCHAR(50) NULL,
  email       VARCHAR(190) NULL,
  website     VARCHAR(255) NULL,
  products    VARCHAR(255) NULL,
  status      ENUM('draft','published') NOT NULL DEFAULT 'published',
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Careers: job openings (applications are leads of type 'job')
CREATE TABLE IF NOT EXISTS jobs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(190) NOT NULL,
  slug        VARCHAR(160) NOT NULL UNIQUE,
  department  VARCHAR(120) NULL,
  location    VARCHAR(120) NULL,
  job_type    VARCHAR(60) NULL,
  summary     VARCHAR(300) NULL,
  description MEDIUMTEXT NULL,
  status      ENUM('draft','published') NOT NULL DEFAULT 'draft',
  closes_at   DATE NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who changed what (content history across pages, posts, products and other content)
CREATE TABLE IF NOT EXISTS content_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  entity      VARCHAR(40) NOT NULL,
  entity_id   INT UNSIGNED NULL,
  action      VARCHAR(20) NOT NULL,
  title       VARCHAR(255) NULL,
  detail      VARCHAR(500) NULL,
  ip          VARCHAR(45) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_content_log_entity (entity, entity_id),
  KEY idx_content_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Changes editors submit for approval (applied only when an administrator approves)
CREATE TABLE IF NOT EXISTS content_reviews (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity      VARCHAR(20) NOT NULL,
  entity_id   INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NULL,
  title       VARCHAR(255) NULL,
  snapshot    MEDIUMTEXT NOT NULL,
  status      VARCHAR(20) NOT NULL DEFAULT 'pending',
  note        TEXT NULL,
  decided_by  INT UNSIGNED NULL,
  decided_at  DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_content_reviews_status (status, created_at),
  KEY idx_content_reviews_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Short links (/s/{code}) used by share buttons and created by hand in the admin
CREATE TABLE IF NOT EXISTS short_links (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(32) NOT NULL,
  target        VARCHAR(1000) NOT NULL,
  title         VARCHAR(255) NULL,
  auto          TINYINT(1) NOT NULL DEFAULT 0,
  clicks        INT UNSIGNED NOT NULL DEFAULT 0,
  last_click_at DATETIME NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_short_links_code (code),
  KEY idx_short_links_target (target(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blog comments (moderated)
CREATE TABLE IF NOT EXISTS comments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id     INT UNSIGNED NOT NULL,
  parent_id   INT UNSIGNED NULL,
  user_id     INT UNSIGNED NULL,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(190) NULL,
  body        TEXT NOT NULL,
  status      VARCHAR(20) NOT NULL DEFAULT 'pending',
  spam_reason VARCHAR(190) NULL,
  ip          VARCHAR(45) NULL,
  user_agent  VARCHAR(255) NULL,
  approved_at DATETIME NULL,
  approved_by INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_comments_post (post_id, status, created_at),
  KEY idx_comments_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Newsletter: subscribers (double opt-in), campaigns and per-recipient delivery/open/click
CREATE TABLE IF NOT EXISTS subscribers (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(190) NOT NULL,
  name            VARCHAR(150) NULL,
  status          VARCHAR(20) NOT NULL DEFAULT 'pending',
  token           CHAR(40) NOT NULL,
  source          VARCHAR(60) NULL,
  ip              VARCHAR(45) NULL,
  confirmed_at    DATETIME NULL,
  unsubscribed_at DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_subscribers_email (email),
  UNIQUE KEY uq_subscribers_token (token),
  KEY idx_subscribers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject      VARCHAR(190) NOT NULL,
  preheader    VARCHAR(190) NULL,
  body         MEDIUMTEXT NULL,
  latest_posts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status       VARCHAR(20) NOT NULL DEFAULT 'draft',
  scheduled_at DATETIME NULL,
  started_at   DATETIME NULL,
  sent_at      DATETIME NULL,
  recipients   INT UNSIGNED NOT NULL DEFAULT 0,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_campaigns_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_sends (
  campaign_id   INT UNSIGNED NOT NULL,
  subscriber_id INT UNSIGNED NOT NULL,
  sent_at       DATETIME NULL,
  error         VARCHAR(255) NULL,
  opened_at     DATETIME NULL,
  clicked_at    DATETIME NULL,
  PRIMARY KEY (campaign_id, subscriber_id),
  KEY idx_campaign_sends_pending (campaign_id, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cookie-free visitor analytics (one row per page view; visitor = daily-salted hash, not personal data)
CREATE TABLE IF NOT EXISTS page_hits (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  day        DATE NOT NULL,
  path       VARCHAR(255) NOT NULL,
  source     VARCHAR(120) NULL,
  device     VARCHAR(10) NOT NULL DEFAULT 'desktop',
  visitor    CHAR(16) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_page_hits_day (day, path),
  KEY idx_page_hits_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer reviews (moderated; product_id NULL = review of the company)
CREATE TABLE IF NOT EXISTS reviews (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NULL,
  rating      TINYINT UNSIGNED NOT NULL,
  title       VARCHAR(150) NULL,
  body        TEXT NOT NULL,
  name        VARCHAR(120) NOT NULL,
  company     VARCHAR(150) NULL,
  email       VARCHAR(190) NULL,
  verified    TINYINT(1) NOT NULL DEFAULT 0,
  status      VARCHAR(20) NOT NULL DEFAULT 'pending',
  reply       TEXT NULL,
  replied_at  DATETIME NULL,
  ip          VARCHAR(45) NULL,
  approved_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_reviews_product (product_id, status),
  KEY idx_reviews_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form tokens that were already used (single-use public forms; rows expire after 2 hours)
CREATE TABLE IF NOT EXISTS form_nonces (
  nonce      CHAR(40) NOT NULL PRIMARY KEY,
  expires_at DATETIME NOT NULL,
  KEY idx_form_nonces_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
