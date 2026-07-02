# Rollback Notes — Urban Goodz Original Landing Copy Final

## Full Rollback

Restore the previous versions of these files from git:

```bash
git checkout HEAD -- resources/views/home.blade.php
git checkout HEAD -- resources/lang/en/messages.php
git checkout HEAD -- public/assets/landing/css/landing.css
git checkout HEAD -- database/partial/data_settings.sql
```

Then clear cache:
```bash
php artisan optimize:clear
php artisan view:clear
php artisan cache:clear
```

## Database Rollback

If the SQL patch was applied, run reverse UPDATEs to restore old values.

Use the SELECT preview queries in `outputs/update-live-landing-copy-data-settings.sql` to first confirm the current values, then manually revert each key in the admin panel UI.

Old values for reference:

| Key | Old Value |
|-----|-----------|
| `fixed_header_title` | `Manage Your  Daily Life in one platform` |
| `fixed_module_title` | `Your eCommerce venture starts here !` |
| `fixed_module_sub_title` | `Enjoy all services in one platform` |
| `fixed_referal_title` | `Earn point by` |
| `fixed_footer_article_title` | `6amMart is a complete package!…` |
| `feature_title` | `Remarkable Features that You Can Count!` |
| `feature_short_description` | `Jam-packed with outstanding features…` |
| `earning_title` | `Earn Money` |
| `earning_sub_title` | `Earn money  by using different platform` |
| `why_choose_title` | `What so Special About 6amMart ?` |

## Note

The blade-template hardcoded values (earning_sub_title, CTA heading, hero, relaunch notes) are tracked in git and can be reverted with a simple `git checkout`. The DB values are NOT tracked in git — they must be reverted manually through the admin panel or SQL.
