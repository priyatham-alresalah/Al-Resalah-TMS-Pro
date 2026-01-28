# Backup & Recovery Procedures
**Training Management System**  
**Last Updated:** January 28, 2026

---

## OVERVIEW

This document outlines the backup and recovery procedures for the Training Management System. Regular backups are critical for data protection and business continuity.

---

## BACKUP SCOPE

### 1. Database Backups (Supabase/PostgreSQL)

**What to Backup:**
- All tables in the Supabase database
- Database schema/migrations
- Row Level Security (RLS) policies
- Database functions and triggers

**Backup Method:**
- Use Supabase Dashboard → Database → Backups
- Enable automated daily backups (recommended)
- Manual backup: Use `pg_dump` via Supabase CLI or Dashboard

**Backup Frequency:**
- **Production:** Daily automated backups (retain 30 days)
- **Development:** Weekly manual backups

**Backup Storage:**
- Store backups in secure, encrypted location
- Keep off-site copies
- Verify backup integrity regularly

### 2. File Uploads Backup

**What to Backup:**
- `uploads/lpos/` - LPO documents
- `uploads/certificates/` - Certificate PDFs (if generated)
- `uploads/invoices/` - Invoice PDFs (if generated)
- `uploads/quotes/` - Quotation PDFs (if generated)

**Backup Method:**
```bash
# Backup uploads directory
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz uploads/

# Or use rsync for incremental backups
rsync -av uploads/ /backup/location/uploads/
```

**Backup Frequency:**
- **Production:** Daily (automated via cron)
- **Development:** Weekly (manual)

**Backup Storage:**
- Store in same secure location as database backups
- Verify file integrity after backup

### 3. Application Code Backup

**What to Backup:**
- All PHP files
- Configuration files (excluding secrets)
- JavaScript/CSS assets
- `.htaccess` files

**Backup Method:**
- Use Git repository (primary backup)
- Tag releases: `git tag -a v1.0.0 -m "Release 1.0.0"`
- Keep release archives

**Backup Frequency:**
- **Production:** On every deployment (Git tags)
- **Development:** Daily commits

---

## AUTOMATED BACKUP SETUP

### Supabase Automated Backups

1. **Enable in Supabase Dashboard:**
   - Go to Project Settings → Database
   - Enable "Point-in-Time Recovery" (PITR)
   - Set retention period (30 days recommended)

2. **Verify Backup Status:**
   - Check Dashboard → Database → Backups
   - Ensure backups are created daily
   - Verify backup size is reasonable

### File Uploads Automated Backup (Linux Server)

Create cron job:
```bash
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * /path/to/backup-script.sh
```

Backup script (`backup-script.sh`):
```bash
#!/bin/bash
BACKUP_DIR="/backup/tms"
DATE=$(date +%Y%m%d)
UPLOADS_DIR="/path/to/training-management-system/uploads"

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Backup uploads
tar -czf "$BACKUP_DIR/uploads_$DATE.tar.gz" "$UPLOADS_DIR"

# Remove backups older than 30 days
find "$BACKUP_DIR" -name "uploads_*.tar.gz" -mtime +30 -delete

# Log backup
echo "$(date): Backup completed - uploads_$DATE.tar.gz" >> /var/log/tms-backup.log
```

---

## BACKUP VERIFICATION CHECKLIST

### Daily Checks:
- [ ] Verify Supabase backup was created (check Dashboard)
- [ ] Verify backup file size is reasonable (not 0 bytes)
- [ ] Check backup logs for errors

### Weekly Checks:
- [ ] Test restore from backup (on test environment)
- [ ] Verify file uploads backup completed
- [ ] Check backup storage space availability

### Monthly Checks:
- [ ] Full restore test (database + files)
- [ ] Verify backup retention policy
- [ ] Review backup logs for anomalies
- [ ] Update backup procedures if needed

---

## RESTORE PROCEDURES

### Database Restore (Supabase)

**From Supabase Dashboard:**
1. Go to Database → Backups
2. Select backup point
3. Click "Restore" (creates new database)
4. Update connection strings in application

**From pg_dump file:**
```bash
# Restore database
psql -h [host] -U [user] -d [database] < backup.sql

# Or via Supabase CLI
supabase db restore backup.sql
```

**Verification Steps:**
1. Verify all tables exist
2. Check record counts match expectations
3. Test critical queries
4. Verify RLS policies are active

### File Uploads Restore

```bash
# Extract backup
tar -xzf uploads_backup_YYYYMMDD.tar.gz

# Restore to location
cp -r uploads/* /path/to/training-management-system/uploads/

# Set permissions
chmod -R 755 /path/to/training-management-system/uploads/
chown -R www-data:www-data /path/to/training-management-system/uploads/
```

**Verification Steps:**
1. Verify files exist in correct locations
2. Check file permissions
3. Test file access via application
4. Verify file integrity (compare checksums if available)

### Application Code Restore

**From Git:**
```bash
# Clone repository
git clone [repository-url]

# Checkout specific version
git checkout v1.0.0

# Or restore from specific commit
git checkout [commit-hash]
```

**Verification Steps:**
1. Verify all files exist
2. Check file permissions
3. Test application startup
4. Verify configuration files

---

## DISASTER RECOVERY SCENARIO

### Complete System Failure

**Recovery Steps:**
1. **Restore Database:**
   - Use latest Supabase backup
   - Verify data integrity
   - Update connection strings

2. **Restore File Uploads:**
   - Extract latest backup
   - Restore to correct location
   - Set permissions

3. **Restore Application Code:**
   - Clone from Git repository
   - Checkout production tag
   - Configure environment variables

4. **Verify System:**
   - Test health check endpoint
   - Test critical workflows
   - Monitor error logs

**Recovery Time Objective (RTO):** 4 hours  
**Recovery Point Objective (RPO):** 24 hours (daily backups)

---

## BACKUP MONITORING

### Health Check Integration

The health check endpoint (`api/health.php`) can be extended to verify backups:
- Check if backup files exist
- Verify backup age (not older than 24 hours)
- Alert if backup fails

### Alerting

Set up alerts for:
- Backup failures
- Backup storage full
- Backup verification failures
- Unusual backup sizes

---

## BACKUP RETENTION POLICY

| Backup Type | Retention Period | Storage Location |
|-------------|------------------|------------------|
| Database (Daily) | 30 days | Supabase Cloud |
| Database (Weekly) | 90 days | Off-site storage |
| File Uploads (Daily) | 30 days | Local/Cloud storage |
| File Uploads (Weekly) | 90 days | Off-site storage |
| Application Code | Permanent | Git repository |

---

## NOTES

- **Never store backups on the same server as production**
- **Encrypt backups containing sensitive data**
- **Test restore procedures regularly**
- **Document any custom backup procedures**
- **Keep backup logs for audit purposes**

---

## CONTACTS

- **Database Backups:** Supabase Dashboard
- **File Backups:** System Administrator
- **Code Backups:** Git Repository Maintainer

---

**Last Verified:** [Date]  
**Next Review:** [Date + 3 months]
