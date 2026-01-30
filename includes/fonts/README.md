# Arabic Font Setup for FPDF

## Required Font File

Place an Arabic-compatible Unicode TTF font file in this directory.

**Recommended fonts:**
- `Amiri-Regular.ttf` - Classical Arabic style
- `Scheherazade-Regular.ttf` - Naskh style
- `NotoNaskhArabic-Regular.ttf` - Google Noto Arabic
- `DejaVuSans.ttf` - Includes Arabic support

**Download sources:**
- Google Fonts: https://fonts.google.com/noto/specimen/Noto+Naskh+Arabic
- Amiri: https://fonts.google.com/specimen/Amiri
- DejaVu: https://dejavu-fonts.github.io/

## Font Generation Steps

1. Place the `.ttf` font file in this directory (e.g., `Amiri-Regular.ttf`)

2. Run the font generator script:
   ```bash
   php vendor/setasign/fpdf/makefont/makefont.php includes/fonts/Amiri-Regular.ttf
   ```

3. This will generate:
   - `Amiri-Regular.php` (font definition)
   - `Amiri-Regular.z` (compressed font metrics)

4. The certificate generator will automatically use this font for Arabic text.

## Font File Naming

The font file should be named: `arabic.ttf` or `Amiri-Regular.ttf`

The certificate generator will look for:
- `includes/fonts/arabic.ttf` (primary)
- `includes/fonts/Amiri-Regular.ttf` (fallback)
