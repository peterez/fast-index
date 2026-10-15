=== Fast Index for Google Indexing API ===
Contributors: cultdevops
Tags: indexing api, instant indexing, google indexing, search console, seo
Requires at least: 5.6
Tested up to: 7.1
Stable tag: 3.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Notify the Google Indexing API when you publish, update or delete posts. Google officially supports the API for job and livestream pages.

== Description ==

Fast Index tells the Google Indexing API when a post is published, updated or removed. You connect your own Google Cloud service account once. After that, Fast Index sends the address of the post in the background and keeps a History of every answer from Google. If you turn it on, the same addresses also go to IndexNow, which shares them with Bing, Yandex, Naver, Seznam.cz, Yep and other search engines.

**What Google supports.** Google states that the Indexing API can only be used to crawl pages with either JobPosting or BroadcastEvent embedded in a VideoObject. For other pages, such as ordinary posts, pages or products, Google may accept a notification and answer 200, but its documentation does not promise that the page will be crawled or indexed, or that this happens sooner. Fast Index cannot change that. Keep your sitemap and Search Console as they are. Google also checks every submission for spam and may revoke access for abuse. Details: https://developers.google.com/search/apis/indexing-api/v3/quickstart

**Who it is for**

* Sites with job postings or livestream pages that want to tell Google about changes through the Indexing API.
* Site owners who already use the API and want WordPress to send the notifications and show Google's answers.
* Sites that want Bing and the other IndexNow search engines to hear about changes too.
* Developers and agencies who want a clear record of what was sent and what each search engine answered.

**How it works**

1. You create a Google Cloud project, turn on the Web Search Indexing API and download a JSON key for a service account.
2. You add that service account as an Owner of your site in Google Search Console.
3. You upload the key on the Fast Index settings screen.
4. When a post is published, updated, trashed or deleted, Fast Index queues a notification. WP-Cron sends it a moment later, so saving a post is not slowed down.
5. The History screen shows each notification with the answer of Google, for example 200 OK.

**Features**

* Notifications for every public post type (posts, pages, custom post types such as products or job listings): URL_UPDATED when content is published or updated, URL_DELETED when it is trashed or deleted. You choose the post types and the events. Password-protected posts are never sent.
* IndexNow, off by default: the same addresses also go to api.indexnow.org, which shares them with Bing, Yandex, Naver, Seznam.cz, Yep and the other search engines that take part. Fast Index creates the key and answers the key file itself, without a file on disk.
* History marks pages with JobPosting or BroadcastEvent data, the kinds Google supports, as Eligible for Google Indexing API. An option sends only those pages to Google; IndexNow still gets the others.
* Sending by hand: a Notify search engines row action and bulk action in the post lists, up to 100 items at a time, with the result on the same screen.
* Background sending through WP-Cron, with a time limit for each request.
* History screen with a summary (sent in total, waiting, sent today) and every answer of Google and IndexNow, with a Channel column and a filter for each channel. History keeps the entries of the last 90 days and removes older ones once a day; the newest accepted entry of each post stays.
* When Google rejects the key (401 or 403), it is paused for six hours. When the daily quota is used up (429), every key is paused until Google resets the quota at midnight Pacific Time, and no other key is tried.
* A notification that cannot be sent is listed in History as Not sent. Without a key or without OpenSSL it is only recorded; while the key is paused it waits and is sent when the key is ready again.
* The service account key is stored in the database, never in a file. The screens show only the email address and the key ID.
* Help & FAQ screen with step-by-step Google Cloud setup, an explanation of every setting, troubleshooting and system information.
* Multisite: activate for the whole network or for single sites. Settings, keys and history belong to each site.
* Translated into 10 languages: Arabic, Dutch, French, German, Italian, Japanese, Portuguese (Brazil), Russian, Spanish and Turkish.
* Deleting the plugin keeps your settings, keys and history unless you turn on Delete data on uninstall.

**Free and premium**

The free version is not limited in time or in the number of notifications, and it contains no premium code and no locked option. The premium plan is a separate package, sold through Freemius, for older content, larger sites and the question whether your pages are indexed. It adds:

* Old posts sent in small hourly batches, within a daily limit you set, and a Send Old Posts Now screen.
* Categories whose posts are never sent.
* Backup service account keys, used only when Google rejects the main key (401 or 403). They are never used to get past the daily quota.
* Index status from Google Search Console (URL Inspection API) in a History column: Indexed; Crawled, not indexed; Discovered, not indexed; or Unknown to Google, with the last crawl date. A Check button checks one address now, every accepted address is checked again 24 and 72 hours after sending, and a card counts the indexed addresses of the last 30 days.
* A weekly email report to the administration address of the site, in the language of the recipient, sent with WordPress's own email function. It can be turned off.

To get it, open Fast Index > Upgrade, or click Upgrade to Premium in the Premium box of the settings screen; on a network activation the plans page is in the network admin. After the purchase Freemius emails you the premium package (a .zip file) and a license key. Upload the package under Plugins > Add New > Upload Plugin, activate it and enter the key; Freemius deactivates the free version. Your settings, keys and history are kept. Settings of these options that an earlier version saved are kept by the free version, unused, and the premium package uses them again.

**What it does not do**

Fast Index does not submit sitemaps, does not use the Bing Webmaster API, and cannot make Google or any other search engine index a page.

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/fast-index` directory, or install it from Plugins > Add New in WordPress.
2. Activate the plugin through the Plugins screen. On a multisite network you can activate it for the whole network or for single sites.
3. Open Fast Index in the admin menu. The first screen is the Freemius opt-in screen: choose Skip or Allow & Continue. Fast Index works the same either way; see External services for what each choice sends. After a network activation this screen appears once in the network admin and on each site until a choice is made.
4. In the Google Cloud console create a project, enable the Web Search Indexing API, create a service account and download a JSON key for it.
5. In Google Search Console open Settings, Users and permissions, and add the email address of the service account as an Owner of your site.
6. On the Fast Index settings screen choose Active, check the post types to send, and save. Then upload the JSON key under Upload Google Service Account Key.
7. Publish a test post. A short while later Fast Index > History shows the notification with the answer of Google.

The Help & FAQ screen of the plugin (Fast Index > Help & FAQ) repeats these steps with more detail and explains every setting and every error code.

== Frequently Asked Questions ==

= What does Fast Index send to Google? =

Only the address (URL) of a post and the kind of notification: URL_UPDATED when a post is published or changed, URL_DELETED when it is removed. Titles, content, visitors and your database are not sent. To prove who is asking, Fast Index also sends a signed request that contains the email address and key ID of your service account; the private key itself never leaves your site. The requests go to oauth2.googleapis.com and indexing.googleapis.com. If IndexNow is turned on, the same addresses, the host name of your site and the IndexNow key also go to api.indexnow.org. Your own History keeps the title, address and each answer in your database. See also Google's privacy policy: https://policies.google.com/privacy

= Will Google index every page I publish right away? =

No, and Fast Index cannot promise it. Google says: "The Indexing API can only be used to crawl pages with either JobPosting or BroadcastEvent embedded in a VideoObject." For other pages, such as ordinary posts or products, Google may accept the notification and answer 200, but its documentation does not promise that the page will be crawled or indexed, or that this happens sooner. A 200 only means that, in Google's words, it "may try to recrawl this URL soon". Google also checks every submission for spam and may revoke access for abuse. Keep your sitemap and Search Console as they are. Details: https://developers.google.com/search/apis/indexing-api/v3/using-api

= How do I know which pages Google supports? =

Fast Index looks at the saved content of each post for structured data of the types JobPosting or BroadcastEvent (JSON-LD or microdata), and at the job listings of WP Job Manager, without any outside request. History marks such entries with Eligible for Google Indexing API. If you check Send to Google only pages with JobPosting or BroadcastEvent data in the settings, only those pages go to Google; the others still go to IndexNow when it is on. Data that a theme or another plugin adds only when the page is shown, and not in the saved content, is not seen; developers can add it with the fast_index_is_eligible filter.

= Which post types does Fast Index send? =

Fast Index sends published content of the post types checked in the settings; drafts, pending and private posts are not sent. Every public post type of the site can be checked: Posts (checked by default), Pages, and custom post types such as products or job listings. Media attachments are not offered.

= What happens when I trash or delete a post? =

If Trash is checked in the settings, a published post that is moved to the trash, or deleted without the trash, is reported as URL_DELETED with the address it had before. Deleting it later from the trash sends nothing more. Changing a published post to draft or private is not reported. Google asks that the address returns 404 or 410, or carries a noindex tag, when you send a removal.

= Can I send posts by hand? =

Yes. In the list of posts, pages or another post type that Fast Index sends, hover over a published item and click Notify search engines, or select up to 100 items, choose Notify search engines under Bulk actions and click Apply. Fast Index sends them to Google right away, and to IndexNow when it is on, and shows the result on the same screen; History lists each answer. You need permission to edit each item and the items of other users, as Editors and Administrators have, and Status must be Active. Password-protected items are not sent. Every address counts against Google's daily quota.

= Can Fast Index send my old posts? =

Yes. Set a daily limit under Daily Limit for Old Posts. Every hour Fast Index sends a small random batch of published posts that Google has not accepted yet, newest first, until the daily limit is reached. The limit counts all successful notifications of the last 24 hours. Send Old Posts Now runs one batch immediately, and History shows how many posts are still waiting. Keep the limit within Google's daily quota.

This setting is part of the premium plan, a separate package.

= How many URLs can I send per day? =

Google's default is 200 publish requests per day for each Google Cloud project; notifications of both kinds, updated and removed, count. Google says larger usage needs its approval and that quota may increase or decrease based on document quality. Fast Index does not know your quota in advance: when Google answers 429 it pauses every key until the quota resets at midnight Pacific Time, and the notifications that could not be sent wait until then. Quota page: https://developers.google.com/search/apis/indexing-api/v3/quota-pricing

= Can I use more than one service account? =

With the premium plan you can upload backup keys. Fast Index always sends with the main key, the first one in the list. Only when Google rejects it (401 or 403, or the key is refused) is that key paused for six hours and the next key tried. A 429 answer means the daily quota is used up: every key is then paused until the quota resets at midnight Pacific Time, and no other key is tried. Other errors (such as 400, 5xx or no answer) end the attempt without pausing a key. Google counts quota for each project, not for each key, and writes that attempts to abuse the API, including the use of multiple accounts or other means to exceed usage quotas, may result in access being revoked: https://developers.google.com/search/apis/indexing-api/v3/quickstart Ask Google for more quota if you need it.

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

Yes, through IndexNow, which is off by default. Turn it on with Also send to IndexNow on the settings screen. Fast Index then also sends each address to api.indexnow.org, which shares it with Bing, Yandex, Naver, Seznam.cz, Yep and the other search engines that take part. Unlike the Google Indexing API, IndexNow accepts every kind of page and needs no account: Fast Index creates a key for your site and answers it at /{key}.txt. The request contains the host name of your site, the key, the address of the key file and the changed addresses. History lists the answers in the IndexNow channel. Fast Index does not use the Bing Webmaster API. See the IndexNow terms: https://www.indexnow.org/terms

= What does the premium plan add? =

The free version is not limited in time or in the number of notifications. It notifies Google when content of any public post type is published, updated, trashed or deleted, keeps a History of Google's answers and includes Help & FAQ.

The premium plan adds the index status of every address from Search Console, a weekly email report, hourly batches of old posts within a daily limit you set, excluded categories and backup service account keys.

The premium plan is a separate package, sold through Freemius. The Premium box on the settings screen compares both versions, and Upgrade in the Fast Index menu shows the plans and prices.

= How does the premium plan check whether a page is indexed? =

It asks the URL Inspection API of Google Search Console with the service account key you already use, and shows the answer in the Index Status column of History: Indexed; Crawled, not indexed; Discovered, not indexed; or Unknown to Google, with the date of the last crawl. Each address Google accepted is checked automatically 24 hours after sending, and once more after 72 hours if it was not indexed yet; the Check button checks one address now. A card above History counts the addresses accepted in the last 30 days, the checked ones and the indexed ones. Enable the Google Search Console API in your Google Cloud project first. Google allows 2,000 checks per day for each property; the automatic checks use at most half of them. The address and the property go to searchconsole.googleapis.com.

= How do I get the premium plan, and how do I install it? =

Open Upgrade in the Fast Index menu, or click Upgrade to Premium on the settings screen, and buy a plan there; Freemius handles the payment. Freemius then emails you the premium package (a .zip file) and a license key; both are also in your account at https://customers.freemius.com/. In WordPress go to Plugins, add a new plugin, click Upload Plugin and choose that .zip file, then activate it and enter the license key when you are asked. If both versions are installed, Freemius deactivates the free one. Your settings, keys and history are kept. The free version from WordPress.org never contains the premium code.

= What is Freemius, and do I have to connect an account? =

No. Fast Index includes the Freemius SDK, which sells the premium plan and manages its licenses. The free version has no license screen. The first time you open Fast Index, Freemius shows an opt-in screen. If you choose Skip, nothing is sent to Freemius and Fast Index works the same. If you choose Allow & Continue, your browser sends what that screen lists to Freemius: your name and email address, the site address and title, the language, the WordPress, PHP, plugin and SDK versions, and later the list of your plugins and themes. Opening Upgrade loads the plans from Freemius without any site data, and choosing a plan opens the Freemius checkout. See the Freemius privacy policy: https://freemius.com/privacy/

= Does Fast Index slow down saving posts? =

No. Saving a post only queues a scheduled task. The request to Google is made later by WP-Cron, with a time limit of 15 seconds, and a batch of old posts stops after about 20 seconds.

== Screenshots ==

1. The settings screen: status, post types, post events, IndexNow and the upload of the service account key.
2. History: a summary, a filter by channel and every notification with the answer of Google or IndexNow.
3. Help & FAQ: step-by-step setup of the Google service account.
4. System info on the Help & FAQ screen, to attach when you ask for support.

== External services ==

Fast Index connects to the services below. It sends nothing to Google until you have uploaded a service account key and the status is Active, and nothing to IndexNow until you turn IndexNow on.

**Google OAuth 2.0 token service** (https://oauth2.googleapis.com/token)

* What it is used for: to exchange a signed request for a short-lived access token for the Indexing API.
* When: before the first notification, and again when the stored access token (valid for about an hour) has expired.
* Data sent: a signed JWT. It contains the email address of your service account, the scope https://www.googleapis.com/auth/indexing (in the premium package also https://www.googleapis.com/auth/webmasters.readonly for the index status), the time of the request and the key ID. It is signed on your site with the private key; the private key itself is never sent. Google also receives the IP address of your server.
* Why: Google requires an access token for every Indexing API request.
* Terms of Service: https://policies.google.com/terms and https://developers.google.com/terms
* Privacy Policy: https://policies.google.com/privacy

**Google Indexing API** (https://indexing.googleapis.com/v3/urlNotifications:publish)

* What it is used for: to tell Google that a page was updated or removed.
* When: after a post is published, edited, trashed or deleted, as the settings say, when you send posts by hand from a post list, and in the premium package for each post of an old-posts batch.
* Data sent: the address (URL) of the post, the kind of notification (URL_UPDATED or URL_DELETED) and the access token. Titles, content and visitor data are not sent. Google also receives the IP address of your server.
* Why: this is the purpose of the plugin.
* Terms of Service: https://policies.google.com/terms and https://developers.google.com/terms
* Privacy Policy: https://policies.google.com/privacy

**Google Search Console API** (premium package only: https://www.googleapis.com/webmasters/v3/sites and https://searchconsole.googleapis.com/v1/urlInspection/index:inspect)

* What it is used for: to check whether an address that Google accepted is indexed (URL Inspection), and to find the Search Console property of the site.
* When: only in the premium package, never in the version from WordPress.org: when you click Check in History, and automatically 24 hours and, if the address was not indexed yet, 72 hours after Google accepted it. The property is looked up once a day when you have not entered it on the settings screen.
* Data sent: the address (URL), the Search Console property, the language code en-US and an access token of your service account. Google also receives the IP address of your server.
* Why: to show whether the pages you sent are indexed.
* Terms of Service: https://policies.google.com/terms and https://developers.google.com/terms
* Privacy Policy: https://policies.google.com/privacy

**IndexNow** (https://api.indexnow.org/indexnow)

* What it is used for: to tell the search engines that take part in IndexNow, such as Bing, Yandex, Naver, Seznam.cz and Yep, that a page was added, updated or removed. api.indexnow.org shares each submission with all of them.
* When: only after you turn on Also send to IndexNow on the settings screen (it is off by default), after a post is published, updated, trashed or deleted, and when you send posts by hand from a post list. While IndexNow is off, no request is made and no key file is answered.
* Data sent: the host name of your site, the IndexNow key that Fast Index created for the site, the address of the key file and the addresses (URLs) of the changed posts. The search engines then read the key file from your site. Titles, content and visitor data are not sent. The receiving servers also get the IP address of your server.
* Why: so that search engines other than Google hear about your changes.
* Terms of Service: https://www.indexnow.org/terms
* Privacy: the Privacy section of the IndexNow terms, and the privacy statement of each search engine, for Bing: https://privacy.microsoft.com/privacystatement

**Freemius** (https://api.freemius.com, https://wp.freemius.com and https://checkout.freemius.com)

Fast Index includes the Freemius SDK (version 2.13.4), which sells the premium plan and manages its licenses. What it sends was measured on a new installation of this version, with every outgoing request recorded:

* Before you choose anything on the Freemius opt-in screen, and if you choose Skip: nothing is sent to Freemius, from your server or from your browser.
* If you choose Allow & Continue: your browser sends the data listed on that screen to wp.freemius.com: the first and last name and email address of your WordPress user, the site address and title, the site language, the WordPress, PHP, plugin and SDK versions, an anonymous site identifier and whether the plugin is active. After that the SDK reports later events (activation, deactivation, uninstall), sends the names, slugs, versions and active state of your plugins and themes as that screen lists, and keeps this data in sync with a daily WP-Cron task. You can opt out later on the Account screen of Fast Index.
* If you open Upgrade: your server asks api.freemius.com for the plans and prices of Fast Index. The request contains the plugin ID and the SDK version and no data about your site; Freemius receives the IP address of your server. The page itself is part of the plugin, not an iframe. Once the plans are shown, your browser loads the Freemius checkout script from checkout.freemius.com. The pricing script of the SDK would also load Google Analytics; Fast Index stops that script on this page, so nothing goes to Google Analytics.
* If you choose a plan there: your browser opens the Freemius checkout at checkout.freemius.com with the details needed for the purchase and the license: the name and email address of your WordPress user and the site address.
* If you deactivate the plugin: Freemius may ask why. A reason you submit is kept on your site and sent to api.freemius.com when the plugin is deleted, with an anonymous identifier (with your details only if you untick the anonymous option in that form). Nothing is sent if you skip the form.
* Why: to sell the premium plan and manage its licenses.
* Terms of Service: https://freemius.com/terms/ and https://freemius.com/product/opt-in/11893/fast-index/
* Privacy Policy: https://freemius.com/privacy/

== Privacy ==

Fast Index stores your settings, the service account keys you upload (in the database, not in a file) and a History of notifications (post title, address, time and the answer of Google or IndexNow) in your own database. It collects nothing about your visitors, sets no cookies and shows nothing on the public side of your site, apart from the IndexNow key file when you turn IndexNow on. The premium package can email a weekly report to the administration address of your site with WordPress's own email function. It does not add text to the privacy policy of your site.

What leaves your site is described under External services: post addresses and signed requests go to Google, post addresses go to IndexNow only if you turn it on, and the Freemius SDK contacts Freemius only after one of the choices described there. Deleting the plugin removes temporary data and, if you turned on Delete data on uninstall, your settings, keys and history. Records kept by Freemius are not touched, and a key stays valid in Google Cloud until you delete it there.

== Changelog ==

= 3.0.1 =
* Changed: the settings screen explains itself: a short introduction, a "What is this?" button next to each setting that says what it does and when to change it, and a confirmation before a key is deleted. Fields, check boxes and the status menu use the CultDevOps UI 2.0 design, with larger touch targets on phones.

= 3.0.0 =
* Changed: the admin screens have the design that all CultDevOps plugins share: a header with the plugin name, the plan and a link to Help & FAQ, cards, status badges and a summary of the History.
* Security: settings, key uploads and data deletion now require the right capability and a nonce, database queries use prepared statements, output is escaped, and the override that allowed unfiltered uploads is gone. Service account keys are no longer stored in a public folder.
* Changed: the bundled Google API client was replaced by a small client that uses the WordPress HTTP API and a signed request. TLS verification is always on.
* Changed: keys are stored in the database. On the first load after the update the key files of version 2.x are moved there and deleted. A notice recommends creating a new key and deleting the old one in Google Cloud. If you go back to 2.2 you must upload the key again.
* Changed: sending runs in the background through WP-Cron, so saving a post is faster. Saved posts wait in one queue that a single scheduled task sends in batches of up to 100 addresses, and IndexNow gets each batch in one request, so a large import does not fill the list of scheduled tasks.
* Changed: the free version from WordPress.org contains no premium code and no locked option, and it has no license check. It sends every public post type (posts, pages, custom post types) when content is published, updated, trashed or deleted, with one key. Old-post batches, excluded categories and backup keys are in the premium package, sold through Freemius. Settings of these options saved by an earlier version are kept, unused, and the premium package uses them again. Saved choices of post types and events are kept and used.
* Fixed: the settings screen shows every event that is sent. Version 2.x sent edits on sites that never saved the settings while the screen showed Publish only.
* Fixed: a new installation no longer opens on a Freemius license screen. The Freemius opt-in screen has a Skip button, and Fast Index works the same either way. After a network activation the sites no longer answer "Cannot load fast-index" before the network admin chooses, and after the choice in the network admin the settings of the main site open.
* Changed: Freemius SDK 2.13.4. It sends nothing before you opt in (the previous SDK contacted Freemius on activation), its plans page is part of the plugin instead of an iframe and loads no Google Analytics, and it no longer writes a plugin icon into its own folder.
* New: the settings screen compares the free and premium versions, History says how many published posts Google has not accepted yet, and the Plugins screen has Settings and Upgrade links.
* Changed: trashing or deleting a published post sends URL_DELETED when the Trash event is checked.
* Changed: password-protected posts are never sent to Google or IndexNow: not when they are published, changed or trashed, not by hand and not in the old-post batches, and they do not count as waiting.
* Changed: when Google rejects a key (401 or 403), the key is paused for six hours and a backup key of the premium package is tried. When Google answers 429 (daily quota used up), every key is paused until the quota resets at midnight Pacific Time and no other key is tried: Google does not allow several accounts to get past the quota.
* New: IndexNow, off by default. When you turn it on, the same addresses also go to api.indexnow.org, which shares them with Bing, Yandex, Naver, Seznam.cz, Yep and other search engines. Fast Index creates a key for each site and answers the key file at /{key}.txt without a file. History has a Channel column and a filter.
* New: a Notify search engines row action and bulk action in the post lists send up to 100 published items at a time, checked against the right to edit each item, and show the result on the same screen. They are offered to users who can edit the items of other users (Editors and Administrators), so an Author cannot use up the daily quota of the site; Google gets the items one by one for up to 8 seconds and the rest is queued.
* New: History marks pages with JobPosting or BroadcastEvent data as Eligible for Google Indexing API, found in the saved content without any request. The option Supported Pages Only (off by default) sends only those pages to Google.
* New (premium package): index status from the Search Console URL Inspection API in History, with a Check button, automatic checks 24 and 72 hours after sending, a card of the indexed addresses and a daily counter that stops at 2,000 checks per property.
* New (premium package): a weekly email report with the numbers of History, in the language of the recipient, that can be turned off.
* Changed: failed attempts are recorded in History too. History keeps 90 days of entries (filter `fast_index_history_retention_days`); a daily scheduled task removes older ones, except the newest accepted entry of each post and entries that wait for a key.
* New: a notification that cannot be sent shows as Not sent in History. Without a key or without OpenSSL it is only recorded. While every key is paused it waits and is sent when the first key is ready again, or soon after you upload a key (the newest event for each address, up to 200, for up to three days).
* Changed: old posts are sent hourly in small batches under the daily limit (premium package).
* Changed: deactivating the plugin only removes its scheduled tasks. A new uninstall routine keeps your data by default and deletes it, on every site of a network, when Delete data on uninstall is on. It runs through the uninstall hook of WordPress, so Freemius can record the uninstall.
* Changed: the History and Send Old Posts Now screens have new addresses (`fast-index-history` and `fast-index-run`).
* Fixed: on phone screens History shows the date, the answer of Google and the post link behind the toggle of each row.
* Changed: an invalid setting is rejected with an error instead of being saved.
* New: Help & FAQ screen with the Google Cloud setup, an explanation of every setting, troubleshooting and system information. The embedded video was replaced by a link.
* New: multisite support: network activation, setup of sites created later, and settings, keys and history for each site.
* New: translations into 10 languages. When an older language pack from translate.wordpress.org is installed, the bundled translation fills the strings it lacks.
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

= 3.0.1 =
Clearer settings screen: every setting explains itself, and deleting a key asks first.

= 3.0.0 =
Security release: keys move from a public uploads folder to the database; create a new Google key and delete the old one. Pages, custom post types, edits, deletions, IndexNow and sending by hand are now free. Old-post batches and the other premium options need the premium package.
