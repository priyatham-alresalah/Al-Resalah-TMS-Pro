# Production Deployment – cPanel / Hostinger

Use this checklist when deploying the Training Management System to cPanel (e.g. Hostinger).

---

## 1. Before Upload

- [ ] Remove or do not upload: `users_debug.php`, `users_test.php`, `database_fixes_*.sql`, `fpdf_temp.zip`, and internal fix/summary `.md` files (already removed in repo).
- [ ] Keep: `MODULES_EXPLANATION.md`, `CERTIFICATE_FLOW.md` (optional reference).

---

## 2. Environment (cPanel)

- [ ] **Subdomain or domain:** If the app is at the document root (e.g. `reports.yourdomain.com`), `BASE_PATH` is auto-detected as `''` (no subfolder).
- [ ] **PHP:** Use PHP 7.4+ (8.0+ recommended). Enable extensions: `gd` or `imagick` (for certificate PDF logo), `curl`, `json`, `mbstring`.
- [ ] **Environment variables:** In cPanel → **MultiPHP INI Editor** or **.env** (if supported), set:
  - `SUPABASE_URL` – Supabase project URL
  - `SUPABASE_ANON` – Supabase anon key (login/reset)
  - `SUPABASE_SERVICE` – Supabase service role key (server-side CRUD)
- [ ] **Alternative:** Create a `.env` file in the app root (same folder as `index.php`) with:
  ```ini
  SUPABASE_URL=https://your-project.supabase.co
  SUPABASE_ANON=your-anon-key
  SUPABASE_SERVICE=your-service-role-key
  ```
  Ensure `.env` is not publicly accessible (`.htaccess` already denies `\.env`).

---

## 3. File Permissions

- [ ] **Directories:** `cache/`, `logs/`, `uploads/` (and subdirs like `uploads/quotes/`, `uploads/certificates/`) → writable by PHP (e.g. `755` or `775` depending on server).
- [ ] **Files:** PHP files typically `644`. No execution needed for `includes/`, `logs/`, `cache/` as they are blocked by `.htaccess`.

---

## 4. Security (Already in App)

- [ ] **.htaccess:** Blocks direct access to `includes/`, `logs/`, `cache/`, and files like `.env`, `.log`, `.sql`, `.md`.
- [ ] **config.php:** `display_errors = 0` and `expose_php = 0` when not on localhost; errors only logged.
- [ ] **403/404:** Custom `403.php` and `404.php`; `ErrorDocument` set in `.htaccess`.
- [ ] **HTTPS:** Uncomment the HTTPS redirect in `.htaccess` for production.

---

## 5. App Logic Summary (Final Check)

| Flow | Steps |
|------|--------|
| **Auth** | Login → `index.php`; session 30 min; RBAC on each page. |
| **Inquiry → Quote** | Inquiries → Create Quotation (status `pending_approval`) → Approve → `accepted`. |
| **Quote → Training** | Accepted quotation + verified LPO (Client Orders) → Schedule Training. |
| **Training** | Assign Trainer → Assign Candidates → Start → Verify Attendance → Complete. |
| **Certificate** | After training completed → Issue Certificates (bulk). |
| **Invoice** | Auto-created when certificates are issued (from approved quotation amounts). |
| **Paths** | All links use `BASE_PATH`; root PHP files (e.g. `dashboard.php`) redirect to `pages/`. |

---

## 6. Post-Deploy Checks

- [ ] Open app URL → redirect to login or dashboard.
- [ ] Login with a real user → Dashboard and sidebar load.
- [ ] Create inquiry → Create quotation → Approve → Upload/verify LPO → Schedule training.
- [ ] Complete a training (assign trainer, candidates, start, verify attendance, complete) → Issue certificates → Check Invoices list for auto-created invoice.
- [ ] Visit a non-existent path → custom 404 page.
- [ ] Check `logs/` (or server error log) for PHP errors; fix any missing config (e.g. Supabase keys, GD/Imagick).

---

## 7. Optional (Hostinger / cPanel)

- [ ] **PHP version:** Select PHP 8.0 or 8.1 in **MultiPHP Manager**.
- [ ] **SSL:** Enable SSL for the (sub)domain; then uncomment HTTPS redirect in `.htaccess`.
- [ ] **Cron:** Not required for current app; add later if you need scheduled tasks.

---

## 8. Rollback

Keep a backup of the previous production files and database (Supabase) before going live. If using Git, tag the release (e.g. `v1.0-production`) before deploy.
