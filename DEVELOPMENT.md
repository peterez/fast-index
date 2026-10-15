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
| `fast_index_use_several_keys` | filter | backup service account keys (main key first; a backup only after 401/403, never after 429) |
| `fast_index_should_notify` | filter | excluded categories |
| `fast_index_sanitize_settings` / `fast_index_validate_settings` | filter | daily limit, categories, Search Console property, weekly report |
| `fast_index_settings_rows` | action | settings rows of the daily limit, the categories, the Search Console property and the weekly report |
| `fast_index_unused_settings` | filter | no "kept but not used" note |
| `fast_index_hourly` | action | hourly batch of old posts, due index status checks, planning the weekly report |
| `fast_index_admin_menu` | action | "Send Old Posts Now" page (`Fast_Index_Admin::add_page()`) |
| `fast_index_history_stats` | filter | History "Total Waiting" without excluded categories |
| `fast_index_logged` | action | plans the index status checks 24 and 72 hours after an accepted Google notification (log ID, code, URL, post ID) |
| `fast_index_token_scopes` | filter | adds the `webmasters.readonly` scope, so a deleted key also forgets that cached token |
| `fast_index_history_columns` / `fast_index_history_cell` | filter / action | History "Index Status" column and its Check button |
| `fast_index_history_before_table` | action | "N of M indexed" card above History |
| `fast_index_premium_loaded` | action | fired by the module once it is loaded |

Free extension points that the module does not use (since 3.0.0 the free version sends every public post type and
the Edit and Trash events itself):

| Hook | Type | Purpose |
|---|---|---|
| `fast_index_post_type_choices` | filter | post types offered on the settings screen (default: every public type except attachments) |
| `fast_index_post_events` | filter | events offered on the settings screen (Publish, Edit, Trash) |
| `fast_index_default_settings` | filter | settings used while they were never saved (Posts; Publish and Edit) |
| `fast_index_send_to_google` | filter | whether a notification goes to Google; IndexNow is decided by its setting |
| `fast_index_is_eligible` | filter | whether a post has JobPosting or BroadcastEvent data (saved content and WP Job Manager by default) |
| `fast_index_manual_capability` | filter | capability needed for the Notify search engines row and bulk actions of a post type, besides `edit_post` per item (default: `edit_others_posts` of the type) |
| `fast_index_history_retention_days` | filter | days History keeps its entries (default 90, 0 keeps everything); the daily event `fast_index_prune_history` removes older ones except the newest accepted Google entry of each post and entries that wait for a key |

## Queue

Saving a post never sends anything. `Fast_Index_Post_Watcher` adds the address to `Fast_Index_Queue`: one row per
address in the options table (`fast_index_queue_` + md5 of the address, autoload off, written with one
`INSERT ... ON DUPLICATE KEY UPDATE`; the newest event of an address wins and keeps its place). One single event,
`fast_index_process_queue`, sends at most 100 addresses per run within 20 seconds: Google one by one, IndexNow the whole
batch in one request (`Fast_Index_Notifier::deliver_many()`). A run claims a row by deleting it, so two runs never send
the same address. While rows are left the next run is planned right away; after a Google outage (no answer or 5xx)
the rest waits five minutes. A 5,000-post import therefore adds 5,000 small rows and at most one event, and the
autoloaded `cron` option keeps its size. The 3.0.0 development builds planned one `fast_index_submit_url` event per
address; `Fast_Index_Queue::adopt_legacy_events()` moves such events into the queue on activation, on upgrade, in the
hourly run and when one of them runs. Deactivation keeps the rows; deleting the plugin removes them.

## IndexNow

`Fast_Index_IndexNow` is off until the user checks "Also send to IndexNow". Then a 32-character key is created per
site (option `fast_index_indexnow_key`, not autoloaded), `parse_request` answers `{home}/{key}.txt` with the key
(no file, no rewrite rule; `keyLocation` is always sent, so a sub-directory site covers its own addresses), and
`Fast_Index_Notifier` sends the queued notifications to `https://api.indexnow.org/indexnow` in the same cron run as
Google, one request per batch. History entries of IndexNow are posts of the type `fast_index_now_log`, so the Google counters, the old-post
batches and the retry queue (all on `fi_log`) never see them. 200 and 202 are successes; nothing is retried.

## Index status (premium)

`Fast_Index_Premium_Inspection` asks `POST https://searchconsole.googleapis.com/v1/urlInspection/index:inspect`
(scope `webmasters.readonly`, same JWT client as the Indexing API, own token cache key). The property is the setting
`sc_property` or, when empty, the best match from `GET https://www.googleapis.com/webmasters/v3/sites` (cached one day
in a transient, forgotten when the keys change). Results are stored on the Google History entry (`_fast_index_index_state`,
`_fast_index_inspection`). Usage is counted per property and Pacific Time day in `fast_index_inspection_usage`
(2,000 per day allowed by Google; automatic checks stop at 1,000 and run at most 50 per hourly run, two per address).

## Weekly report (premium)

`Fast_Index_Premium_Report` plans the weekly event `fast_index_weekly_report` from the hourly run and sends it with
`wp_mail()` to the `admin_email` address in that user's locale (`switch_to_user_locale()`, `switch_to_locale()` before
WordPress 6.2). Weeks without any History entry send nothing; the setting `weekly_report` turns it off.

## Freemius (SDK 2.13.4)

`freemius-sdk/` is the unchanged 2.13.4 tag of github.com/Freemius/wordpress-sdk. `Fast_Index_Freemius::config()` and
`Fast_Index_Freemius::register_hooks()` hold every setting:

- `is_premium` = the module file exists; `has_premium_version`, `has_paid_plans`, `is_org_compliant` and
  `enable_anonymous` (Skip) are true. The menu has no contact form and no support link.
- `show_trial` and `show_affiliate_program_notice` are off, and `show_admin_notice` hides the trial and affiliate
  notices: Freemius would show them on every admin screen.
- `should_hide_site_admin_settings_on_network_activation_mode` is off: after a network activation every site keeps
  its menu and shows the opt-in screen (with Skip) instead of "Cannot load fast-index".
- `after_skip_url`, `after_connect_url`, `after_delegation_url` and `after_network_activation_url`
  (`Fast_Index_Freemius::after_network_choice_url()`): after the network admin's opt-in choice Freemius would open
  the network page `admin.php?page=fast-index`, which exists only while the opt-in screen is shown ("Cannot load
  fast-index"); the main site's settings open instead.
- `plugin_icon` points to `assets/fast-index-icon.png`, so the SDK never downloads an icon into its folder.
- `Fast_Index_Freemius::block_pricing_analytics()` (on `admin_print_footer_scripts`, priority 1) adds an inline
  guard before the SDK's `freemius-pricing` script on the Upgrade page (`fast-index-pricing`, site or network admin):
  that script inserts `//www.google-analytics.com/analytics.js` whenever it draws the plans (WordPress.org guideline 7).
  `tests/test-freemius-analytics.php` fails when the bundled pricing script no longer contains it, so the guard can go.
- `Fast_Index_Freemius::translate_texts()` (on `init`) replaces the Upgrade, Pricing, Skip and Allow & Continue
  texts with the plugin's translated strings, because Help & FAQ names them.

Measured on a clean install of the WordPress.org package (every outgoing request logged and blocked): no request to
Freemius before the opt-in choice or after Skip; opening Upgrade makes one server request to
`api.freemius.com/v1/plugins/11893/pricing.json` without site data, and once the plans are drawn the browser loads
`checkout.freemius.com/checkout.js` (Google Analytics is blocked, see above); Allow & Continue posts the opt-in data from the
browser to `wp.freemius.com`; no Freemius update hook is attached. The readme's External services section describes
this; measure again after an SDK update.

## Uninstall

There is no `uninstall.php`: WordPress would then never call the Freemius uninstall hook, and the Freemius deployment
rejects packages that have one. `Fast_Index_Uninstaller::run_once()` is attached to the Freemius `after_uninstall`
action, to the core `uninstall_fast-index/index.php` action (priority 20, for WP-CLI without a user) and registered
with `register_uninstall_hook()` on activation. It runs once per request and does nothing while the other copy (free
or premium) is active.

## Where the free version points to the premium plan

All in `Fast_Index_Upgrade` (KARARLAR (20), (21)), only on the Fast Index screens (plus the result notice of the
bulk action on the post list) and only while the module is not loaded; never on the dashboard: the Premium box with
a Free / Premium table, Premium badges (`<details>`) on the rows of old posts and excluded categories, next to the
key upload and next to the History heading (index status), two History lines with real numbers (published posts
Google has not accepted yet; addresses Google accepted in the last 30 days, from 10 on), one line under the result
of a bulk action when more than 100 items were selected, the message when a new key replaces the only key, Settings
and Upgrade links on the Plugins screen, a thank-you notice after the first notification Google or IndexNow accepted
(with a review request, no reward) and an offer notice seven days after the first setup.
Each notice is shown until the user dismisses it (user meta `fast_index_dismissed_notices`). No discount text: add
one only for a real Freemius coupon.

## Translations

`languages/` holds the `.po`, `.mo` and `.l10n.php` files of 10 locales. When a language pack from
translate.wordpress.org is installed (`wp-content/languages/plugins/`), WordPress loads only the pack, and a pack made
for an older release shows the newer strings in English (the Dutch pack has 9 strings of version 2.x).
`Fast_Index_Plugin::add_bundled_translation()` (on `init` and `change_locale`) loads the bundled file after the pack:
the pack's strings win, the bundled file fills the rest. A translation editor of a locale can import the bundled
`.po` file on translate.wordpress.org so the packs catch up.

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

The suite passes on WordPress 5.6, 6.9 and 7.1 and on PHP 7.4 to 8.5. The test library of WordPress 5.6 works with
PHPUnit 7.x only: run it with a separate PHPUnit 7.5 install (the Yoast polyfills 1.x work with it). The library
installs WordPress in a child process: set `WP_PHP_BINARY` in `wp-tests-config.php` to the PHP that runs the tests.

## Release order

1. `composer package`.
2. Freemius dashboard (product 11893): make sure a Free plan exists next to the paid plans (without it Freemius
   builds no free version and an opt-in from the free version was answered with "Invalid license key"). Then
   Deployment > Add new version > upload `dist/fast-index-premium-<version>.zip` and release it, so licensed sites can
   get the premium package before the WordPress.org update reaches them. Freemius reads `@fs_premium_only` in
   `index.php`, so a free version it builds also leaves `includes/premium/` out; the WordPress.org release still
   uses our own package.
3. Then release `dist/fast-index-<version>.zip` (its content) to WordPress.org SVN.
