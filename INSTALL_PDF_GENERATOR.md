# PDF Generator Installation Guide

## Quick Installation (Recommended)

### Option 1: Using Composer (Easiest)
```bash
cd c:\xampp\htdocs\training-management-system
composer install
```

Or if composer is not installed, download composer first:
```bash
# Download composer.phar
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"

# Install dependencies
php composer.phar install
```

### Option 2: Manual Installation
1. Download FPDF 1.86 from: https://www.fpdf.org/en/download.php
2. Extract the ZIP file
3. Copy `fpdf.php` from the extracted folder to `includes/fpdf186/fpdf.php`
4. The library will be automatically loaded

### Option 3: Direct Download (PowerShell)
```powershell
# Create directory
New-Item -ItemType Directory -Path "includes\fpdf186" -Force

# Download FPDF
$url = "https://www.fpdf.org/en/download.php?v=186&f=zip"
$output = "fpdf.zip"
Invoke-WebRequest -Uri $url -OutFile $output

# Extract (requires 7-Zip or similar)
# Or manually extract and copy fpdf.php to includes/fpdf186/
```

## Verify Installation

After installation, verify FPDF is working:

1. **Check PHP error logs** - Should NOT see "FPDF library not found" messages
2. **Test Quote PDF** - Create a quote and check if PDF is generated
3. **Test Invoice PDF** - Create an invoice and download PDF
4. **Test Certificate PDF** - Issue a certificate and check PDF generation

## PDF Generation Modules

The system uses PDF generation in these modules:

1. **Quotations** (`includes/quote_pdf.php`)
   - Generates quote PDFs when creating quotations
   - Used in: `api/inquiries/create_quote.php`

2. **Invoices** (`includes/invoice_pdf.php`)
   - Generates invoice PDFs for billing
   - Used in: `api/invoices/print_pdf.php`, `api/invoices/download.php`

3. **Certificates** (`includes/certificate_pdf.php`)
   - Generates training completion certificates
   - Used in: `api/certificates/print_pdf.php`, certificate issuance

## Troubleshooting

### "FPDF library not found" error
- Check if `includes/fpdf186/fpdf.php` exists
- Check if `vendor/setasign/fpdf/fpdf.php` exists (if using composer)
- Check PHP error logs for specific errors

### PDF generation fails silently
- Check `logs/php_errors.log` for errors
- Ensure `uploads/quotes/`, `uploads/invoices/`, `uploads/certificates/` directories exist and are writable
- Check file permissions on uploads directories

### PDF files are empty or corrupted
- Ensure FPDF library is properly installed
- Check PHP memory limit (should be at least 128M)
- Verify all required PHP extensions are enabled

## File Structure

```
training-management-system/
├── includes/
│   ├── pdf_library.php          # Centralized PDF loader
│   ├── quote_pdf.php            # Quote PDF generator
│   ├── invoice_pdf.php          # Invoice PDF generator
│   ├── certificate_pdf.php      # Certificate PDF generator
│   └── fpdf186/
│       └── fpdf.php             # FPDF library (after installation)
├── vendor/                       # Composer dependencies (if using composer)
│   └── setasign/
│       └── fpdf/
└── uploads/
    ├── quotes/                  # Generated quote PDFs
    ├── invoices/                # Generated invoice PDFs
    └── certificates/            # Generated certificate PDFs
```

## Notes

- All PDF generation functions gracefully handle missing FPDF library
- If FPDF is not installed, PDF generation is skipped with error logging
- PDF files are stored in `uploads/` directory
- All PDF functions return `null` if FPDF is not available (instead of crashing)
