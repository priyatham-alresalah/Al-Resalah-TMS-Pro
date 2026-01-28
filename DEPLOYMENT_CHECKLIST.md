# Deployment Checklist for Training Management System

## Pre-Deployment Configuration

### ✅ 1. Update `includes/config.php`

**BASE_PATH Configuration:**
- For subdomain `https://reports.alresalahct.com/`: Set `BASE_PATH` to `''` (empty string)
- For localhost development: Set `BASE_PATH` to `'/training-management-system'`

**Error Reporting:**
- ✅ Already configured for production (errors logged, not displayed)
- Current setting: `error_reporting(E_ALL); ini_set('display_errors', 0);`

### ✅ 2. File Paths Fixed
- ✅ Header logo paths use `BASE_PATH`
- ✅ API redirects use `BASE_PATH`
- ✅ Auth check redirects use `BASE_PATH`
- ⚠️ Favicon paths still hardcoded (non-critical, can be fixed later)

### ✅ 3. .htaccess File
- ✅ Created with security headers
- ✅ Cache control for static assets
- ✅ Directory listing disabled
- ✅ Sensitive file protection

## Deployment Steps

### Step 1: Upload Files
1. Upload entire `training-management-system` folder to your cPanel
2. For subdomain `reports.alresalahct.com`, upload to:
   - `/public_html/reports/` (if using subdomain folder)
   - OR `/public_html/` (if subdomain points to root)

### Step 2: Verify Configuration
1. Check `includes/config.php`:
   - `BASE_PATH` should be `''` for subdomain root
   - Error reporting should be OFF for production
   - Supabase credentials are correct

2. Verify `.htaccess` file is uploaded

### Step 3: Set File Permissions
```bash
# Set directory permissions
chmod 755 for all directories
chmod 644 for all PHP files
chmod 644 for .htaccess
```

### Step 4: Test Critical Paths
- [ ] Login page loads: `https://reports.alresalahct.com/`
- [ ] Dashboard accessible after login
- [ ] Assets load correctly (CSS, images, favicon)
- [ ] API endpoints work (test a form submission)
- [ ] File uploads work (certificates, invoices)

### Step 5: Database Connection
- ✅ Supabase is cloud-hosted, no local DB setup needed
- Verify Supabase API keys are correct in `config.php`

## Known Issues & Fixes

### Issue 1: Hardcoded Favicon Paths
**Status:** Non-critical, cosmetic only
**Files Affected:** Multiple PHP files
**Fix:** Can be updated later to use `BASE_PATH`

### Issue 2: BASE_PATH for Development
**Status:** Fixed
**Note:** Change `BASE_PATH` back to `'/training-management-system'` for local development

## Security Checklist

- ✅ Error reporting disabled in production
- ✅ Sensitive files protected via .htaccess
- ✅ Security headers configured
- ✅ Session management in place
- ⚠️ Consider enabling HTTPS redirect (uncomment in .htaccess)

## Post-Deployment Testing

### Functional Tests
1. **Authentication:**
   - [ ] Admin login works
   - [ ] Client portal login works
   - [ ] Candidate portal login works
   - [ ] Logout works

2. **Core Modules:**
   - [ ] Users module (CRUD)
   - [ ] Clients module (CRUD)
   - [ ] Candidates module (CRUD)
   - [ ] Inquiries module
   - [ ] Trainings module
   - [ ] Certificates module
   - [ ] Invoices module
   - [ ] Reports module

3. **File Operations:**
   - [ ] PDF generation (certificates, invoices, quotes)
   - [ ] File downloads
   - [ ] Email sending

4. **Role-Based Access:**
   - [ ] Admin sees all modules
   - [ ] BDM/BDO see appropriate modules
   - [ ] Trainer sees trainer dashboard
   - [ ] Accounts sees accounts dashboard

## Rollback Plan

If issues occur:
1. Change `BASE_PATH` back to `'/training-management-system'` if needed
2. Enable error reporting temporarily: `ini_set('display_errors', 1);`
3. Check Apache error logs: `/var/log/apache2/error.log` or cPanel error logs
4. Verify Supabase API connectivity

## Support Information

- **Supabase Project:** qqmzkqsbvsmteqdtparn
- **Application Name:** AI Resalah Consultancies & Training
- **Production URL:** https://reports.alresalahct.com/

## Notes

- All file paths now use `BASE_PATH` constant for flexibility
- Error logging is enabled but errors are not displayed to users
- Static assets are cached for 1 year (images) and 1 month (CSS/JS)
- Security headers are configured to prevent common attacks
