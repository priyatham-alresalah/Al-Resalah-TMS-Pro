# Production Readiness Checklist

## Before Going Live

### 1. Environment & Secrets
- [ ] Create `.env` on production server with real values (never commit)
- [ ] Set `SUPABASE_URL`, `SUPABASE_ANON`, `SUPABASE_SERVICE` in .env
- [ ] Set `SMTP_*` for email (quotations, invoices, password reset)
- [ ] Ensure `.env` is not web-accessible (`.htaccess` blocks it)

### 2. Security
- [ ] Remove or never upload `api/auth/debug.php` (already removed in repo)
- [ ] Uncomment HTTPS redirect in `.htaccess` (lines 17–19) for cPanel
- [ ] Verify `display_errors = 0` on production (config does this for non-localhost)
- [ ] Run `sql/add_login_enabled.sql` in Supabase if using Clients/Candidates login control

### 3. Supabase
- [ ] Add production redirect URLs in Supabase: `https://reports.alresalahct.com/set_password.php`
- [ ] Confirm RLS policies and service role key permissions

### 4. File Permissions
- [ ] `logs/` and `cache/` writable by web server (755 or 775)
- [ ] `uploads/` writable for PDFs, QR codes

### 5. Post-Deploy
- [ ] Test login (staff, client portal, candidate portal)
- [ ] Test password reset flow
- [ ] Verify email sending (quote, invoice)

## Current Hardening (Already Done)
- Security headers (CSP, X-Frame-Options, X-Content-Type-Options)
- Session security (HttpOnly, SameSite, Secure)
- `.htaccess` blocks: `includes/`, `logs/`, `cache/`, `sql/`, `.env`
- CSRF on forms
- Custom 403/404 pages
