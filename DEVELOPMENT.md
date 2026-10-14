# Fast Index — development notes

This file is for developers. It is not part of either release package (`.distignore`).

## Free version and premium package

- The plugin in this repository is the **premium package**: it contains `includes/premium/`.
- The **WordPress.org package** is the same code without `includes/premium/` (`.distignore`). It has no premium
  code, no locked option and no license check.
- The free code offers prefixed hooks (`fast_index_*`). The premium module attaches to them. It loads only when
  `includes/premium/class-fast-index-premium.php` exists **and** Freemius allows the premium code
  (`can_use_premium_code()`). The license is checked there and nowhere else.
- Freemius `is_premium` is true only when that file exists (`Fast_Index_Freemius::has_premium_package()`).

| Hook | Type | Used by the module for |
|---|---|---|
| `fast_index_post_type_choices` | filter | every public post type except attachments |
| `fast_index_post_events` | filter | Edit and Trash |
| `fast_index_default_settings` | filter | Publish + Edit for sites that never saved the settings |
| `fast_index_use_several_keys` | filter | several service account keys |
| `fast_index_should_notify` | filter | excluded categories |
| `fast_index_sanitize_settings` / `fast_index_validate_settings` | filter | daily limit and categories |
| `fast_index_settings_rows` | action | settings rows of the daily limit and the categories |
| `fast_index_unused_settings` | filter | no "kept but not used" note |
| `fast_index_hourly` | action | hourly batch of old posts |
| `fast_index_admin_menu` | action | "Send Old Posts Now" page (`Fast_Index_Admin::add_page()`) |
| `fast_index_history_stats` | filter | History "Total Waiting" without excluded categories |
| `fast_index_premium_loaded` | action | fired by the module once it is loaded |

## Building the release packages

```bash
composer package            # or: bin/package.sh [output-dir]
```

The command writes two files to `dist/` (not tracked by git):

| File | For | Content |
|---|---|---|
| `dist/fast-index-<version>.zip` | WordPress.org (SVN trunk / tag) | `wp dist-archive` with `.distignore`: no `includes/premium/`, no development files |
| `dist/fast-index-premium-<version>.zip` | Freemius dashboard (Deployment) | the same files plus `includes/premium/` |

Both unpack to `fast-index/`. The script builds from a clean copy of the files git knows (tracked, or new and not
ignored), so `vendor/` and local caches never get in, and it stops if the WordPress.org zip contains a premium
path or the premium zip lacks the module. It needs bash, git, unzip and WP-CLI with `dist-archive`
(`WP_CLI=/path/to/wp` chooses the binary).

`tests/test-package.php` (PHPUnit group `package`) builds both packages and checks their content.

## Tests

```bash
composer lint
WP_TESTS_DIR=/path/to/wordpress-tests-lib vendor/bin/phpunit
WP_TESTS_DIR=/path/to/wordpress-tests-lib WP_MULTISITE=1 vendor/bin/phpunit
```

Premium tests replace the Freemius instance with `tests/class-fast-index-test-freemius.php` and load the module
with `Fast_Index_Test_Case::with_premium()`.

## Release order

1. `composer package`.
2. Upload `dist/fast-index-premium-<version>.zip` to the Freemius dashboard first, so licensed sites can get the
   premium package before the WordPress.org update reaches them.
3. Then release `dist/fast-index-<version>.zip` (its content) to WordPress.org SVN.
