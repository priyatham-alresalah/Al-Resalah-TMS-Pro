# FPDF Installation Guide

## Quick Installation (Recommended)

### Option 1: Using Composer (Recommended)
```bash
cd c:\xampp\htdocs\training-management-system
composer require setasign/fpdf
```

### Option 2: Manual Download
1. Download FPDF 1.86 from: https://www.fpdf.org/en/download.php
2. Extract the ZIP file
3. Copy the `fpdf.php` file to `includes/fpdf186/fpdf.php`
4. The library will be automatically loaded

### Option 3: Direct Download Script
Run this PowerShell command in the project root:
```powershell
$url = "https://www.fpdf.org/en/download.php?v=186&f=zip"
$output = "fpdf186.zip"
Invoke-WebRequest -Uri $url -OutFile $output
Expand-Archive -Path $output -DestinationPath "temp_fpdf"
Copy-Item "temp_fpdf\fpdf186\fpdf.php" -Destination "includes\fpdf186\fpdf.php"
Remove-Item -Recurse -Force "temp_fpdf"
Remove-Item $output
```

## Verify Installation
After installation, check if FPDF is loaded:
- Create a test quote/invoice/certificate
- Check PHP error logs for "FPDF library not found" message
- If no error, FPDF is working correctly

## PDF Generation Modules
The following modules use PDF generation:
1. **Quotations** - `includes/quote_pdf.php`
2. **Invoices** - `includes/invoice_pdf.php`
3. **Certificates** - `includes/certificate_pdf.php`

All modules will gracefully handle missing FPDF library and log errors instead of crashing.
