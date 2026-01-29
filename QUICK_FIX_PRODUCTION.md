# QUICK FIX FOR PRODUCTION ERROR

## Error: "SUPABASE_SERVICE key not configured"

**Problem:** The app is running on production but can't find Supabase credentials.

---

## SOLUTION 1: Create .env File (RECOMMENDED)

### On your cPanel server:

1. **Go to File Manager** in cPanel
2. **Navigate to your subdomain root** (where you uploaded the files)
   - Usually: `/public_html/reports/` or `/public_html/`
3. **Create a new file** named `.env` (with the dot at the beginning)
4. **Add these lines** (replace with your actual keys):

```ini
SUPABASE_URL=https://qqmzkqsbvsmteqdtparn.supabase.co
SUPABASE_ANON=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFxbXprcXNidnNtdGVxZHRwYXJuIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjkzMjI2MjEsImV4cCI6MjA4NDg5ODYyMX0.aDCwm8cf46GGCxYhXIT0lqefLHK_5sAKEsDgEhp2158
SUPABASE_SERVICE=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFxbXprcXNidnNtdGVxZHRwYXJuIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2OTMyMjYyMSwiZXhwIjoyMDg0ODk4NjIxfQ.VbJCSHYPyhMFUosl-GRZgicdlUXSO68fEQlUgDBpsUs
```

5. **Set file permissions** to 600 (read/write for owner only):
   - Right-click `.env` → Change Permissions → 600

---

## SOLUTION 2: Set Environment Variables in cPanel

1. **Go to cPanel** → **Environment Variables** (or **Select PHP Version** → **Environment Variables**)
2. **Add these variables:**
   - Name: `SUPABASE_URL` → Value: `https://qqmzkqsbvsmteqdtparn.supabase.co`
   - Name: `SUPABASE_ANON` → Value: `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFxbXprcXNidnNtdGVxZHRwYXJuIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjkzMjI2MjEsImV4cCI6MjA4NDg5ODYyMX0.aDCwm8cf46GGCxYhXIT0lqefLHK_5sAKEsDgEhp2158`
   - Name: `SUPABASE_SERVICE` → Value: `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFxbXprcXNidnNtdGVxZHRwYXJuIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2OTMyMjYyMSwiZXhwIjoyMDg0ODk4NjIxfQ.VbJCSHYPyhMFUosl-GRZgicdlUXSO68fEQlUgDBpsUs`
3. **Save** and refresh your website

---

## SOLUTION 3: Temporary Quick Fix (Less Secure)

If you need it working immediately and can't access cPanel right now, I can modify the code to allow hardcoded keys temporarily. But this is NOT recommended for production.

---

## After Fixing:

1. **Refresh your browser** at `https://reports.alresalahct.com/`
2. **The error should be gone**
3. **Login page should appear**

---

## Which Solution to Use?

- **Solution 1 (.env file)** - Best for most cPanel setups
- **Solution 2 (Environment Variables)** - Best if your cPanel supports it
- **Solution 3 (Code change)** - Only if you can't do 1 or 2 right now

**Recommendation:** Use Solution 1 (create .env file) - it's the easiest and most reliable.
