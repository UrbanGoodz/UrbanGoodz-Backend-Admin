# cPanel Manual Deployment Guide — Urban Goodz Landing Package

**Package:** `outputs/urban-goodz-landing-copy-image-final-package.zip`

**Live site:** https://admin.urbangoodzdelivery.com

---

## Step 1 — Download Package

Keep the ZIP file on your local machine. Extract it so you can upload individual files to matching directories.

---

## Step 2 — Back Up Live Files (cPanel File Manager)

Before uploading anything, back up the following live files by **copying them to a safe folder** (e.g., `/backup-2026-06-15/` inside your site root):

| Live File Path | Destination (backup) |
|---|---|
| `resources/views/home.blade.php` | `backup-2026-06-15/resources/views/home.blade.php` |
| `resources/lang/en/messages.php` | `backup-2026-06-15/resources/lang/en/messages.php` |
| `public/assets/landing/css/landing.css` | `backup-2026-06-15/public/assets/landing/css/landing.css` |
| `public/assets/landing/css/ug-landing.css` | `backup-2026-06-15/public/assets/landing/css/ug-landing.css` |
| Any existing hero illustration image | `backup-2026-06-15/public/assets/landing/img/` |

To back up: In cPanel File Manager, right-click each file → **Copy**, navigate to the backup folder, and paste.

Alternatively, use **Download** to save copies to your local computer.

---

## Step 3 — Upload Changed Files to Matching Paths

**IMPORTANT:** Do NOT upload the entire `outputs/` folder into the public web root. Upload only the individual changed files to their exact Laravel paths.

### Files to upload:

| Package File | Upload To (Laravel path) |
|---|---|
| `resources/views/home.blade.php` | `/resources/views/home.blade.php` |
| `resources/lang/en/messages.php` | `/resources/lang/en/messages.php` |
| `public/assets/landing/css/landing.css` | `/public/assets/landing/css/landing.css` |
| `public/assets/landing/css/ug-landing.css` | `/public/assets/landing/css/ug-landing.css` |
| `public/assets/landing/img/urban-goodz-marketplace-hero-approved.png` | `/public/assets/landing/img/urban-goodz-marketplace-hero-approved.png` |

**The following are reference/admin files — keep them locally, do NOT upload to public web root:**
- `database/partial/data_settings.sql` — reference only
- `outputs/ADMIN-UPDATE-CHECKLIST.md` — reference only
- `outputs/deployment-notes.txt` — reference only
- `outputs/rollback-notes.txt` — reference only
- `outputs/update-live-landing-copy-data-settings.sql` — **database patch (see step 4)**

In cPanel File Manager:
1. Navigate to the corresponding folder on the server.
2. Click **Upload** and select the file from your extracted package.
3. Overwrite when prompted.

---

## Step 4 — Update Live Database (phpMyAdmin)

### ⚠️ CRITICAL — Back Up Database First

1. Open **phpMyAdmin** from cPanel.
2. Select your site's database.
3. Click **Export** → **Quick** → **Go**. Save the `.sql` dump locally.

### Verify Target Rows Before Updating

Open `outputs/update-live-landing-copy-data-settings.sql` in a text editor.

In phpMyAdmin, click the **SQL** tab and run each `SELECT` query from the patch **first** to confirm the rows exist as expected:

```sql
-- Example: Run this SELECT to preview what will be updated
SELECT * FROM `data_settings` WHERE `key` LIKE 'admin_landing_page%';
```

**Only proceed if the SELECT results show the expected data_settings rows for the landing page.**

### Run the UPDATE statements

Once confirmed, run the `UPDATE` statements from the patch file. You can copy-paste them one by one or run the entire UPDATE section.

### Verify after update

Run the SELECT again to confirm the values changed to the approved copy.

---

## Step 5 — Clear Laravel Cache

### Option A — cPanel Terminal (recommended)

If your cPanel has **Terminal** access:

```bash
cd /path/to/site-root
php artisan optimize:clear
php artisan view:clear
php artisan cache:clear
```

### Option B — No Terminal Access

1. Log in to **Urban Goodz Admin** → **Business Settings** → **Cache Clear** (if the option exists).
2. If no cache clear button exists, manually delete the cached views:
   - In cPanel File Manager, delete everything inside `/storage/framework/views/` (do NOT delete the folder itself).
   - Delete everything inside `/storage/framework/cache/data/`.
3. As a last resort, rename and replace the files — Laravel's file-based view cache will regenerate new cached copies on next page load for changed Blade files.

---

## Step 6 — Post-Deploy Verification Checklist

| Check | Expected Result |
|---|---|
| Landing page loads | https://admin.urbangoodzdelivery.com returns 200 |
| Approved hero image appears | Marketplace visual, **not** faded template SVG |
| Image is full visibility | Not washed out (opacity: 1) |
| Image does not repeat | Single centered image |
| Original module cards remain | 10-card marketplace category grid intact |
| Approved copy shows | "Relaunching Soon", "Your Connection To Local Everything", "Urban Goodz connects you..." etc. |
| No 6amMart public text | No visible "6amMart" or "Sixam Mart" on landing page |
| No "eCommerce venture" text | Not present |
| No "complete package" text | Not present |
| No placeholder images | All card icons load correctly |
| Admin login works | https://admin.urbangoodzdelivery.com/login/admin loads |
| Vendor login works | https://admin.urbangoodzdelivery.com/login/vendor loads |
| Mobile view works | Resize browser or check on phone — layout adapts, image visible |

---

## Step 7 — Rollback (If Needed)

1. **Restore files:** In cPanel File Manager, copy the backup files back to their original paths.
2. **Restore database:** In phpMyAdmin, drop the current tables and **Import** your pre-update `.sql` backup.
3. **Clear cache:** Run `php artisan optimize:clear` or manually clear view cache files.
4. **Verify:** Confirm landing page, admin login, and vendor login all work.

---

## Quick Reference Checklist

```
[ ] 1. Extracted package ZIP locally
[ ] 2. Backed up live files to /backup-2026-06-15/
[ ] 3. Uploaded 5 changed files to matching Laravel paths
[ ] 4. Exported database backup via phpMyAdmin
[ ] 5. Ran SELECT preview queries — rows confirmed
[ ] 6. Ran UPDATE patch — rows updated
[ ] 7. Cleared cache (terminal or manual)
[ ] 8. Verified landing page loads correctly
[ ] 9. Verified admin login works
[ ] 10. Verified vendor login works
[ ] 11. Verified mobile view
```

**Database caution:** The patch targets specific `data_settings` keys (`admin_landing_page_*`). If your live database uses different key names, do NOT run the UPDATE statements — manually update through the Admin panel instead (Business Settings → Landing Page).

---

*Package prepared 2026-06-15 | Manual cPanel deployment | No automated deploy*
