# Arabic Font Setup Instructions

## Quick Setup (3 Steps)

### Step 1: Download Arabic Font

Download one of these Unicode Arabic fonts:

**Option A: Amiri (Recommended)**
- Download: https://fonts.google.com/specimen/Amiri
- File needed: `Amiri-Regular.ttf`

**Option B: Noto Naskh Arabic**
- Download: https://fonts.google.com/noto/specimen/Noto+Naskh+Arabic
- File needed: `NotoNaskhArabic-Regular.ttf`

**Option C: DejaVu Sans (Includes Arabic)**
- Download: https://dejavu-fonts.github.io/Download.html
- File needed: `DejaVuSans.ttf`

### Step 2: Place Font File

Copy the `.ttf` file to:
```
includes/fonts/arabic.ttf
```

OR keep original name:
```
includes/fonts/Amiri-Regular.ttf
```

### Step 3: Generate FPDF Font Files

Run from project root:
```bash
php vendor/setasign/fpdf/makefont/makefont.php includes/fonts/arabic.ttf
```

Or if using original name:
```bash
php vendor/setasign/fpdf/makefont/makefont.php includes/fonts/Amiri-Regular.ttf
```

This generates:
- `includes/fonts/arabic.php` (or `Amiri-Regular.php`)
- `includes/fonts/arabic.z` (or `Amiri-Regular.z`)

### Step 4: Verify

The certificate generator will automatically detect and use the Arabic font.

## Verification

After setup, Arabic text in certificates should render correctly with connected letters.

If Arabic text still appears garbled:
1. Check font files exist in `includes/fonts/`
2. Verify both `.php` and `.z` files were generated
3. Check file permissions (readable by PHP)

## Technical Notes

- Font registration happens automatically in `certificate_pdf.php`
- Only Arabic text uses the Arabic font
- English text continues using Arial (unchanged)
- All coordinates, colors, and sizes remain frozen
- No layout changes occur
