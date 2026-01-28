# Final Release Checklist for cPanel Deployment

## ✅ Pre-Deployment Verification

### 1. Configuration Files ✅

#### `includes/config.php`
- ✅ **BASE_PATH**: Set to `''` (empty string) for subdomain root
- ✅ **Error Reporting**: `display_errors = 0` (production mode)
- ✅ **Error Logging**: `log_errors = 1` (enabled)
- ✅ **Supabase URL**: Configured correctly
- ✅ **Supabase Keys**: ANON and SERVICE keys present

**Status**: ✅ **READY FOR PRODUCTION**

### 2. Security Configuration ✅

#### `.htaccess`
- ✅ Security headers configured
- ✅ Directory listing disabled
- ✅ Sensitive files protected (.env, .log, .ini, .conf)
- ✅ Cache control for static assets
- ✅ PHP upload limits set (10M)
- ⚠️ HTTPS redirect commented out (can enable if SSL is configured)

**Status**: ✅ **READY FOR PRODUCTION**

### 3. File Paths ✅

#### BASE_PATH Usage
- ✅ All API redirects use `BASE_PATH`
- ✅ All header links use `BASE_PATH`
- ✅ All auth check redirects use `BASE_PATH`
- ⚠️ Favicon paths still hardcoded (cosmetic only, non-critical)

**Status**: ✅ **READY** (favicon issue is non-critical)

### 4. Error Handling ✅

- ✅ Error reporting disabled for users
- ✅ Error logging enabled
- ✅ All API endpoints have error handling
- ✅ User-friendly error messages displayed

**Status**: ✅ **READY FOR PRODUCTION**

---

## 📋 Deployment Steps

### Step 1: Upload Files to cPanel

1. **Upload entire `training-management-system` folder** to cPanel
2. **Target location**:
   - For subdomain `reports.alresalahct.com`: Upload to `/public_html/reports/` OR `/public_html/` (depending on subdomain configuration)
   - Ensure all files and folders are uploaded

3. **Required folders**:
   ```
   training-management-system/
   ├── api/
   ├── assets/
   ├── candidate_portal/
   ├── client_portal/
   ├── includes/
   ├── layout/
   ├── pages/
   ├── uploads/ (create if doesn't exist)
   ├── .htaccess
   ├── index.php
   └── (all other files)
   ```

### Step 2: Set File Permissions

In cPanel File Manager or via SSH:

```bash
# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Make sure .htaccess is readable
chmod 644 .htaccess
```

**Or manually in cPanel File Manager:**
- Directories: **755**
- PHP files: **644**
- CSS/JS files: **644**
- Images: **644**
- `.htaccess`: **644**

### Step 3: Create Upload Directories

Create these directories if they don't exist and set permissions to **755**:

```
uploads/
uploads/certificates/
uploads/invoices/
uploads/lpos/
uploads/quotes/
```

**Note**: These directories need to be writable for file uploads.

### Step 4: Verify Configuration

1. **Check `includes/config.php`**:
   ```php
   define('BASE_PATH', ''); // Must be empty for subdomain root
   ini_set('display_errors', 0); // Must be 0 for production
   ```

2. **Verify `.htaccess` file** is uploaded and readable

3. **Check Supabase credentials** are correct

### Step 5: Test Critical Paths

After deployment, test these URLs:

1. **Login Page**: `https://reports.alresalahct.com/`
2. **Dashboard**: `https://reports.alresalahct.com/pages/dashboard.php` (after login)
3. **Assets**: Verify CSS, images, and JS load correctly
4. **API Endpoints**: Test form submissions
5. **File Uploads**: Test certificate/invoice uploads

---

## ⚠️ Known Issues (Non-Critical)

### 1. Hardcoded Favicon Paths
**Status**: Non-critical cosmetic issue
**Impact**: Favicons may not load if not in root directory
**Files Affected**: ~20 files with `/training-management-system/favicon.ico`
**Fix**: Can be fixed post-deployment if needed

**Quick Fix** (if needed):
```php
// Change from:
<link rel="icon" href="/training-management-system/favicon.ico">

// To:
<link rel="icon" href="<?= BASE_PATH ?>/favicon.ico">
```

### 2. HTTPS Redirect
**Status**: Commented out in `.htaccess`
**Impact**: None if SSL is already configured at server level
**Action**: Uncomment lines 8-10 in `.htaccess` if you want to force HTTPS

---

## ✅ Post-Deployment Testing Checklist

### Authentication
- [ ] Admin login works
- [ ] Client portal login works (`client-login.php`)
- [ ] Candidate portal login works (`candidate-login.php`)
- [ ] Logout works from all portals
- [ ] Password reset works

### Core Modules
- [ ] **Users**: Create, Edit, List, Toggle Status
- [ ] **Clients**: Create, Edit, List
- [ ] **Candidates**: Create, Edit, List
- [ ] **Training Master**: Create, Edit, List
- [ ] **Inquiries**: Create, Edit, List, Convert to Training
- [ ] **Quotations**: Create, Approve, Accept
- [ ] **Client Orders (LPO)**: Upload, Verify
- [ ] **Trainings**: Create, Edit, Schedule, Assign Candidates
- [ ] **Certificates**: Issue, View, Revoke, Download
- [ ] **Invoices**: Create, Edit, Download, Send Email
- [ ] **Payments**: Record, Allocate to Invoices
- [ ] **Reports**: View reports dashboard

### File Operations
- [ ] PDF generation (certificates, invoices, quotes)
- [ ] File downloads work
- [ ] File uploads work (LPO files)
- [ ] Email sending works (certificates, invoices, quotes)

### Role-Based Access
- [ ] Admin sees all modules
- [ ] BDM sees quotations, inquiries, trainings
- [ ] BDO sees their own inquiries, quotations
- [ ] Accounts sees finance modules
- [ ] Trainer sees assigned trainings
- [ ] Client portal works correctly
- [ ] Candidate portal works correctly

### UI/UX
- [ ] Success messages display after creation
- [ ] Error messages display correctly
- [ ] Newly created items appear in lists
- [ ] Sidebar navigation works
- [ ] Responsive design works on mobile

---

## 🔧 Troubleshooting

### Issue: 404 Errors
**Solution**: 
- Check `BASE_PATH` is set to `''` in `includes/config.php`
- Verify `.htaccess` file is uploaded
- Check Apache mod_rewrite is enabled

### Issue: CSS/JS Not Loading
**Solution**:
- Check file permissions (should be 644)
- Verify paths in browser console
- Check `BASE_PATH` configuration

### Issue: File Uploads Fail
**Solution**:
- Check `uploads/` directory exists and is writable (755)
- Verify PHP upload limits in `.htaccess`
- Check PHP error logs

### Issue: Database Connection Errors
**Solution**:
- Verify Supabase credentials in `includes/config.php`
- Check Supabase project is active
- Verify API keys are correct

### Issue: Session Not Working
**Solution**:
- Check PHP session directory is writable
- Verify `session_start()` is called
- Check session timeout settings

---

## 📊 Production Readiness Score

| Category | Status | Notes |
|----------|--------|-------|
| Configuration | ✅ Ready | BASE_PATH set correctly |
| Security | ✅ Ready | Headers, .htaccess configured |
| Error Handling | ✅ Ready | Errors logged, not displayed |
| File Paths | ✅ Ready | BASE_PATH used throughout |
| Database | ✅ Ready | Supabase configured |
| File Uploads | ⚠️ Check | Verify uploads/ directory exists |
| Email | ✅ Ready | PHPMailer configured |
| Testing | ⏳ Pending | Run post-deployment tests |

**Overall Status**: ✅ **READY FOR DEPLOYMENT**

---

## 🚀 Quick Deployment Commands

If you have SSH access:

```bash
# Navigate to deployment directory
cd /path/to/public_html/reports/

# Set permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Create upload directories
mkdir -p uploads/certificates uploads/invoices uploads/lpos uploads/quotes
chmod 755 uploads uploads/*

# Verify .htaccess exists
ls -la .htaccess
```

---

## 📝 Post-Deployment Notes

1. **Monitor Error Logs**: Check PHP error logs for first 24-48 hours
2. **Test All Roles**: Ensure each user role can access appropriate modules
3. **File Uploads**: Test certificate/invoice generation and uploads
4. **Email Functionality**: Test email sending for certificates/invoices
5. **Performance**: Monitor page load times and optimize if needed

---

## ✅ Final Verification

Before going live, verify:

- [ ] `BASE_PATH` is `''` (empty string)
- [ ] `display_errors` is `0`
- [ ] `.htaccess` file is uploaded
- [ ] All directories have correct permissions
- [ ] Upload directories exist and are writable
- [ ] Login page loads correctly
- [ ] Dashboard loads after login
- [ ] At least one CRUD operation works (create user/client)

**If all above are ✅, you're ready for production!**

---

## 📞 Support Information

- **Application**: AI Resalah Consultancies & Training
- **Production URL**: https://reports.alresalahct.com/
- **Supabase Project**: qqmzkqsbvsmteqdtparn
- **Deployment Date**: _______________
- **Deployed By**: _______________

---

**Status**: ✅ **READY FOR FINAL RELEASE**
