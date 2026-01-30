# Email configuration (SMTP / app password)

The app sends email for **quotations**, **invoices**, **certificates**, and **password reset**. To change your email or app password, use one of the options below.

---

## Option 1: Use a `.env` file (recommended)

1. In the **app root** (same folder as `index.php`), create or edit a file named **`.env`**.
2. Add or update these lines (use your real email and **app password**):

```ini
# SMTP / Email (Gmail example)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-16-char-app-password
SMTP_FROM=your-email@gmail.com
SMTP_FROM_NAME=Al Resalah Consultancies & Training
```

3. **Gmail:** Do not use your normal password. Use an [App Password](https://support.google.com/accounts/answer/185833):
   - Turn on 2-Step Verification for your Google account.
   - Go to **Google Account → Security → App passwords** and create a new app password for “Mail”.
   - Put that 16-character password in `SMTP_PASS` in `.env`.

4. Save `.env`. The app loads it automatically; no code change needed.

---

## Option 2: Environment variables (cPanel / Hostinger)

In cPanel → **MultiPHP INI Editor** or **Environment Variables**, set:

- `SMTP_HOST` – e.g. `smtp.gmail.com`
- `SMTP_PORT` – e.g. `587`
- `SMTP_USER` – your sending email
- `SMTP_PASS` – your **app password** (not your normal email password)
- `SMTP_FROM` – from address (usually same as `SMTP_USER`)
- `SMTP_FROM_NAME` – e.g. `Al Resalah Consultancies & Training`

---

## Other providers

| Provider   | SMTP_HOST           | SMTP_PORT |
|-----------|---------------------|-----------|
| Gmail     | smtp.gmail.com      | 587       |
| Outlook   | smtp.office365.com  | 587       |
| Yahoo     | smtp.mail.yahoo.com | 587       |
| Hostinger | (from your hosting) | 587 or 465 |

Use TLS (port 587) when possible. For port 465 (SSL), the app may need a small code change; currently it uses TLS on 587.

---

## Changing only the app password

1. Generate a new app password in your email provider (e.g. Gmail App Passwords).
2. In `.env`, set:  
   `SMTP_PASS=new-16-char-app-password`
3. Save `.env`. No restart needed; the next email sent will use the new password.
