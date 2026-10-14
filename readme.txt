=== Fast Index for Google Indexing API ===
Contributors: cultdevops
Tags: indexing api, instant indexing, google indexing, search console, seo
Requires at least: 5.6
Tested up to: 7.1
Stable tag: 3.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Notify the Google Indexing API when you publish, update or delete posts. Google officially supports the API for job and livestream pages.

== Description ==

Fast Index tells the Google Indexing API when a post is published, updated or removed. You connect your own Google Cloud service account once. After that, Fast Index sends the address of the post in the background and keeps a History of every answer from Google.

**What Google supports.** Google states that the Indexing API can only be used to crawl pages with either JobPosting or BroadcastEvent embedded in a VideoObject. For other pages, such as ordinary posts, pages or products, Google may accept a notification and answer 200, but its documentation does not promise that the page will be crawled or indexed, or that this happens sooner. Fast Index cannot change that. Keep your sitemap and Search Console as they are. Google also checks every submission for spam and may revoke access for abuse. Details: https://developers.google.com/search/apis/indexing-api/v3/quickstart

**Who it is for**

* Sites with job postings or livestream pages that want to tell Google about changes through the Indexing API.
* Site owners who already use the API and want WordPress to send the notifications and show Google's answers.
* Developers and agencies who want a clear record of what was sent and what Google answered.

**How it works**

1. You create a Google Cloud project, turn on the Web Search Indexing API and download a JSON key for a service account.
2. You add that service account as an Owner of your site in Google Search Console.
3. You upload the key on the Fast Index settings screen.
4. When a post is published, Fast Index queues a notification. WP-Cron sends it a moment later, so saving a post is not slowed down.
5. The History screen shows each notification with the answer of Google, for example 200 OK.

**Features**

* Notification when a post is published. Notifications for edits (URL_UPDATED) and for trashed or deleted posts (URL_DELETED) are options.
* Background sending through WP-Cron, with a time limit for each request.
* History screen with a summary (sent in total, waiting, sent today) and the answer of Google for every notification.
* Optional hourly batches of old published posts under a daily limit, and a Send Old Posts Now button.
* More than one service account key: when Google answers 401, 403 or 429 for a key, that key is paused for six hours and the next one is tried.
* A notification that cannot be sent is listed in History as Not sent. Without a key or without OpenSSL it is only recorded; while every key is paused it waits and is sent when a key is ready again.
* Choice of post types and of categories to leave out.
* Service account keys are stored in the database, never in a file. The screens show only the email address and the key ID.
* Help & FAQ screen with step-by-step Google Cloud setup, an explanation of every setting, troubleshooting and system information.
* Multisite: activate for the whole network or for single sites. Settings, keys and history belong to each site.
* Translated into 10 languages: Arabic, Dutch, French, German, Italian, Japanese, Portuguese (Brazil), Russian, Spanish and Turkish.
* Deleting the plugin keeps your settings, keys and history unless you turn on Delete data on uninstall.

**Free and premium options**

In this version some options need the premium plan that Freemius provides: post types other than Posts, notifications for edits and for trash, sending old posts, leaving out categories, and more than one service account key. Without the premium plan Fast Index keeps one key, sends Posts and notifies on publish. The settings screen says which options are affected.

**What it does not do**

Fast Index talks only to the Google Indexing API. It does not support Bing or IndexNow, it does not submit sitemaps, and it cannot make Google index a page.

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/fast-index` directory, or install it from Plugins > Add New in WordPress.
2. Activate the plugin through the Plugins screen. On a multisite network you can activate it for the whole network or for single sites.
3. Open Fast Index in the admin menu. The first screen may be a Freemius license screen: choose Activate Free Version, then Skip or Allow & Continue. See External services for what each choice does.
4. In the Google Cloud console create a project, enable the Web Search Indexing API, create a service account and download a JSON key for it.
5. In Google Search Console open Settings, Users and permissions, and add the email address of the service account as an Owner of your site.
6. On the Fast Index settings screen choose Active, check the post types to send, and save. Then upload the JSON key under Upload Google Service Account Key.
7. Publish a test post. A short while later Fast Index > History shows the notification with the answer of Google.

The Help & FAQ screen of the plugin (Fast Index > Help & FAQ) repeats these steps with more detail and explains every setting and every error code.

== Frequently Asked Questions ==

= What does Fast Index send to Google? =

Only the address (URL) of a post and the kind of notification: URL_UPDATED when a post is published or changed, URL_DELETED when it is removed. Titles, content, visitors and your database are not sent. To prove who is asking, Fast Index also sends a signed request that contains the email address and key ID of your service account; the private key itself never leaves your site. The requests go to oauth2.googleapis.com and indexing.googleapis.com. Your own History keeps the title, address and Google's answer in your database. See also Google's privacy policy: https://policies.google.com/privacy

= Will Google index every page I publish right away? =

No, and Fast Index cannot promise it. Google says: "The Indexing API can only be used to crawl pages with either JobPosting or BroadcastEvent embedded in a VideoObject." For other pages, such as ordinary posts or products, Google may accept the notification and answer 200, but its documentation does not promise that the page will be crawled or indexed, or that this happens sooner. A 200 only means that, in Google's words, it "may try to recrawl this URL soon". Google also checks every submission for spam and may revoke access for abuse. Keep your sitemap and Search Console as they are. Details: https://developers.google.com/search/apis/indexing-api/v3/using-api

= Which post types does Fast Index send? =

Fast Index sends published content of the post types checked in the settings; drafts, pending and private posts are not sent. The free version offers Posts, which are checked by default.

Pages and custom post types are part of the premium plan, a separate package.

= What happens when I trash or delete a post? =

If Trash is checked in the settings, a published post that is moved to the trash, or deleted without the trash, is reported as URL_DELETED with the address it had before. Deleting it later from the trash sends nothing more. Changing a published post to draft or private is not reported. Google asks that the address returns 404 or 410, or carries a noindex tag, when you send a removal.

Edit and Trash are part of the premium plan, a separate package.

= Can Fast Index send my old posts? =

Yes. Set a daily limit under Daily Limit for Old Posts. Every hour Fast Index sends a small random batch of published posts that Google has not accepted yet, newest first, until the daily limit is reached. The limit counts all successful notifications of the last 24 hours. Send Old Posts Now runs one batch immediately, and History shows how many posts are still waiting. Keep the limit within Google's daily quota.

This setting is part of the premium plan, a separate package.

= How many URLs can I send per day? =

Google's default is 200 publish requests per day for each Google Cloud project; notifications of both kinds, updated and removed, count. Google says larger usage needs its approval and that quota may increase or decrease based on document quality. Fast Index does not know your quota in advance: when Google answers 429 it pauses that key for six hours. Quota page: https://developers.google.com/search/apis/indexing-api/v3/quota-pricing

= Can I use more than one service account? =

With the premium plan you can upload several keys. For each notification Fast Index picks one of the keys that are not paused, in random order. If Google answers 401, 403 or 429 (or refuses the key), that key is paused for six hours and the next one is tried. Other errors (such as 400, 5xx or no answer) end the attempt without pausing a key. Note that Google counts quota for each project, not for each key, so keys from the same project share the same 200 per day. Google also writes that attempts to abuse the API, including the use of multiple accounts to exceed usage quotas, may result in access being revoked. Use extra keys for reliability or for sites in separate projects, and ask Google for more quota if you need it.

= Where is my service account key stored, and is it safe? =

The key is kept in your WordPress database (an option that is not loaded on every request). It is never written to the uploads folder or any other file, and the screens show only the email address and key ID. Because the service account is an Owner of your Search Console property, treat the key like a password. If it may have leaked, delete the key in Google Cloud and remove the service account from the users of your Search Console property.

= What happens to my data if I deactivate or delete Fast Index? =

Deactivating removes the scheduled tasks and keeps everything else. Deleting the plugin removes temporary data (queued tasks, caches, locks) and, by default, keeps your settings, keys and history, so a reinstall continues where you left off. If Delete data on uninstall was checked before you deleted the plugin, settings, keys and history are removed too, on every site of a network. Records kept by Freemius are not touched, and the key stays valid in Google Cloud until you delete it there.

= I am upgrading from version 2.2. What happens to my keys? =

On the first page load after the update, Fast Index reads each key file that version 2.x saved in the uploads folder, checks it, stores it in the database and deletes the file. Your settings and history are kept, and the old ten-minute scheduled task is replaced by an hourly one. Earlier versions kept the files in a folder that anyone could reach, so a notice recommends creating new keys and deleting the old ones in Google Cloud. If a file cannot be moved, a notice names it, and you can upload that key again.

= Does Fast Index work on multisite? =

Yes. You can activate it for the whole network or for single sites; sites created later are set up automatically after a network activation. Settings, keys, history and the schedule belong to each site, so upload a key on every site that should send. There is no network admin screen.

= Does Fast Index work on multilingual sites? =

Fast Index does not read language plugins. It sends the address that WordPress gives for the post that was saved, so a translation that is stored as its own post is sent when that post changes. The service account must be an Owner of a Search Console property that covers the addresses of every language, for example a Domain property. The plugin's own screens follow the language of your site or user profile.

= Does Fast Index support Bing or IndexNow? =

No. Fast Index talks only to the Google Indexing API.

= How do I get the premium plan, and how do I install it? =

Open Upgrade in the Fast Index menu, or click Upgrade to Premium on the settings screen, and buy a plan there; Freemius handles the payment. Freemius then emails you the premium package (a .zip file) and a license key; both are also in your account at https://customers.freemius.com/. In WordPress go to Plugins, add a new plugin, click Upload Plugin and choose that .zip file, then activate it and enter the license key when you are asked. If both versions are installed, Freemius deactivates the free one. Your settings, keys and history are kept. The free version from WordPress.org never contains the premium code.

= What is Freemius, and do I have to connect an account? =

Fast Index includes the Freemius SDK, a service for licenses and optional account connection. It decides which options are unlocked. Freemius may show its own screens or notices in the admin: a license screen (with a link to continue with the free version) and an opt-in screen with a Skip button, whose text says that the plugin still works if you skip. What the SDK collects is described on those screens and in the Freemius privacy policy (https://freemius.com/privacy/). The code of Fast Index itself sends only addresses to Google, as described in the first question.

= Does Fast Index slow down saving posts? =

No. Saving a post only queues a scheduled task. The request to Google is made later by WP-Cron, with a time limit of 15 seconds, and a batch of old posts stops after about 20 seconds.

== Screenshots ==

1. The settings screen: status, post types, post events and the upload of the service account key.
2. History: every notification with the answer of Google.
3. Help & FAQ: step-by-step setup of the Google service account.
4. System info on the Help & FAQ screen, to attach when you ask for support.

== External services ==

Fast Index connects to the services below. It sends nothing to Google until you have uploaded a service account key and the status is Active.

**Google OAuth 2.0 token service** (https://oauth2.googleapis.com/token)

* What it is used for: to exchange a signed request for a short-lived access token for the Indexing API.
* When: before the first notification, and again when the stored access token (valid for about an hour) has expired.
* Data sent: a signed JWT. It contains the email address of your service account, the scope https://www.googleapis.com/auth/indexing, the time of the request and the key ID. It is signed on your site with the private key; the private key itself is never sent. Google also receives the IP address of your server.
* Why: Google requires an access token for every Indexing API request.
* Terms of Service: https://policies.google.com/terms and https://developers.google.com/terms
* Privacy Policy: https://policies.google.com/privacy

**Google Indexing API** (https://indexing.googleapis.com/v3/urlNotifications:publish)

* What it is used for: to tell Google that a page was updated or removed.
* When: after a post is published, and, if the options are on, after it is edited, trashed or deleted, and for each post of an old-posts batch.
* Data sent: the address (URL) of the post, the kind of notification (URL_UPDATED or URL_DELETED) and the access token. Titles, content and visitor data are not sent. Google also receives the IP address of your server.
* Why: this is the purpose of the plugin.
* Terms of Service: https://policies.google.com/terms and https://developers.google.com/terms
* Privacy Policy: https://policies.google.com/privacy

**Freemius** (https://api.freemius.com and https://wp.freemius.com)

Fast Index includes the Freemius SDK, a service for licenses, the premium plan and an optional account connection.

* What happens before you opt in: when you activate the plugin, the SDK makes one request to api.freemius.com (a "ping" to check that the service is reachable). It sends an anonymous site identifier (a hash made from your site address and your secret keys), the plugin version, the SDK version and whether the request comes from the admin, an AJAX call or WP-Cron. Freemius also receives the IP address of your server and answers whether its opt-in notice is required for the visitor's location. The ping is repeated after a plugin update. In the tested flow no other request is made until you choose.
* If you choose Allow & Continue on the opt-in screen: your browser sends the data listed on that screen to wp.freemius.com: the first and last name and email address of your WordPress user, the site address and title, the WordPress and PHP versions, the site language, the plugin and SDK versions, whether the plugin is active, and the names, slugs, versions and active state of your plugins and themes. After that the SDK sends later events (activation, deactivation, uninstall) and keeps this data in sync with a daily WP-Cron task.
* If you enter a license key on the license screen: the key and the site details above are sent to api.freemius.com to activate the license.
* If you choose Skip, or Activate Free Version and then Skip: no data is sent.
* If you open the Upgrade screen: a pricing page hosted by Freemius is loaded inside the admin screen. It is given your site address and may load its own scripts and analytics.
* Why: to manage licenses and the premium plan.
* Terms of Service: https://freemius.com/terms/ and https://freemius.com/product/opt-in/11893/fast-index/
* Privacy Policy: https://freemius.com/privacy/

== Privacy ==

Fast Index stores your settings, the service account keys you upload (in the database, not in a file) and a History of notifications (post title, address, time and the answer of Google) in your own database. It collects nothing about your visitors, sets no cookies and shows nothing on the public side of your site. It does not add text to the privacy policy of your site.

What leaves your site is described under External services: post addresses and signed requests go to Google, and the Freemius SDK contacts Freemius as described there. Deleting the plugin removes temporary data and, if you turned on Delete data on uninstall, your settings, keys and history. Records kept by Freemius are not touched, and a key stays valid in Google Cloud until you delete it there.

== Changelog ==

= 3.0.0 =
* Security: settings, key uploads and data deletion now require the right capability and a nonce, database queries use prepared statements, output is escaped, and the override that allowed unfiltered uploads is gone. Service account keys are no longer stored in a public folder.
* Changed: the bundled Google API client was replaced by a small client that uses the WordPress HTTP API and a signed request. TLS verification is always on.
* Changed: keys are stored in the database. On the first load after the update the key files of version 2.x are moved there and deleted. A notice recommends creating a new key and deleting the old one in Google Cloud. If you go back to 2.2 you must upload the key again.
* Changed: sending runs in the background through WP-Cron, so saving a post is faster.
* Changed: trashing or deleting a published post sends URL_DELETED when the Trash option is on.
* Changed: when Google answers 401, 403 or 429, the key is paused for six hours and the next key is tried.
* Changed: failed attempts are recorded in History too.
* New: a notification that cannot be sent shows as Not sent in History. Without a key or without OpenSSL it is only recorded. While every key is paused it waits and is sent when the first key is ready again, or soon after you upload a key (the newest event for each address, up to 200, for up to three days).
* Changed: old posts are sent hourly in small batches under the daily limit.
* Changed: deactivating the plugin only removes its scheduled tasks. A new uninstall routine keeps your data by default and deletes it, on every site of a network, when Delete data on uninstall is on.
* Changed: the History and Send Old Posts Now screens have new addresses (`fast-index-history` and `fast-index-run`).
* Changed: an invalid setting is rejected with an error instead of being saved.
* New: Help & FAQ screen with the Google Cloud setup, an explanation of every setting, troubleshooting and system information. The embedded video was replaced by a link.
* New: multisite support: network activation, setup of sites created later, and settings, keys and history for each site.
* New: translations into 10 languages.
* Fixed: PHP 8 errors and notices, the daily time window of old-post batches, and the list of post types.
* Fixed: scripts and styles load only on the plugin screens, and no request is made on page load.
* Requires WordPress 5.6 and PHP 7.4. Tested with WordPress 7.1.

= 2.2 =
* Tested with WordPress 6.8.

= 2.1 =
* Fixed a download problem.

= 2.0 =
* Fixed a download problem.

= Earlier releases =
* Versions 1.0 to 1.10 added the first release, more languages, category exclusion, a manual cron trigger, a license key manager, a new post status and PHP 8 fixes.

== Upgrade Notice ==

= 3.0.0 =
Security release. Version 2.x kept service account keys in a public uploads folder; 3.0.0 moves them to the database and deletes the files. Create a new Google key afterwards and delete the old one in Google Cloud.
