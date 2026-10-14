=== Fast Index ===
Contributors: cultdevops
Tags: Fast Index, Instant Index Google, Seo index, Google Index, Seo Problem
Requires at least: 5.1
Tested up to: 6.8
Stable tag: 2.2
Requires PHP: 5.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

If you have indexing and listing problem on google, you have to use this plugin. This plugin working with google API and fasted every plugin or seo.

== Description ==

If you have indexing problem on google, you have to use this plugin. This plugin working with google API and fasted every plugin or seo. When you publish new post, page or etc.. it will send your url to google.

FastIndex – 9 Results:

*Instant Indexing in Google when you Publish, Edit and Delete your post
*Fast Index can increase your visibility in google
*Working with Google Indexing API
*For real-time and instant indexing
*Increase your google indexes
*100% Legal, Safety and have high security
*Send your old posts, pages or etc.. to google
*Much more effective and faster than sitemaps
*You can exclude the categories


== Frequently Asked Questions ==

= What does Fast Index send to Google? =

Only the address (URL) of a post and the kind of notification: URL_UPDATED when a post is published or changed, URL_DELETED when it is removed. Titles, content, visitors and your database are not sent. To prove who is asking, Fast Index also sends a signed request that contains the email address and key ID of your service account; the private key itself never leaves your site. The requests go to oauth2.googleapis.com and indexing.googleapis.com. Your own History keeps the title, address and Google's answer in your database. See also Google's privacy policy: https://policies.google.com/privacy

= Will Google index every page I publish right away? =

No, and Fast Index cannot promise it. Google says: "The Indexing API can only be used to crawl pages with either JobPosting or BroadcastEvent embedded in a VideoObject." For other pages, such as ordinary posts or products, Google may accept the notification and answer 200, but its documentation does not promise that the page will be crawled or indexed, or that this happens sooner. A 200 only means that, in Google's words, it "may try to recrawl this URL soon". Google also checks every submission for spam and may revoke access for abuse. Keep your sitemap and Search Console as they are. Details: https://developers.google.com/search/apis/indexing-api/v3/using-api

= Which post types does Fast Index send? =

Every public post type of your site except media attachments can be chosen in the settings: posts, pages and custom types. Only published content is sent; drafts, pending and private posts are not. By default only Posts are checked. In this version, types other than Posts need the premium plan.

= What happens when I trash or delete a post? =

If Trash is checked in the settings, a published post that is moved to the trash, or deleted without the trash, is reported as URL_DELETED with the address it had before. Deleting it later from the trash sends nothing more. Changing a published post to draft or private is not reported. Google asks that the address returns 404 or 410, or carries a noindex tag, when you send a removal. In this version, Edit and Trash need the premium plan.

= Can Fast Index send my old posts? =

Yes. Set a daily limit under Daily Limit for Old Posts. Every hour Fast Index sends a small random batch of published posts that Google has not accepted yet, newest first, until the daily limit is reached. The limit counts all successful notifications of the last 24 hours. Send Old Posts Now runs one batch immediately, and History shows how many posts are still waiting. Keep the limit within Google's daily quota. In this version, this needs the premium plan.

= How many URLs can I send per day? =

Google's default is 200 publish requests per day for each Google Cloud project; notifications of both kinds, updated and removed, count. Google says larger usage needs its approval and that quota may increase or decrease based on document quality. Fast Index does not know your quota in advance: when Google answers 429 it lets that key rest for six hours. Quota page: https://developers.google.com/search/apis/indexing-api/v3/quota-pricing

= Can I use more than one service account? =

With the premium plan you can upload several keys. For each notification Fast Index picks one of the keys that are not resting, in random order. If Google answers 401, 403 or 429 (or refuses the key), that key rests for six hours and the next one is tried. Other errors (such as 400, 5xx or no answer) end the attempt without resting a key. Note that Google counts quota for each project, not for each key, so keys from the same project share the same 200 per day. Google also writes that attempts to abuse the API, including the use of multiple accounts to exceed usage quotas, may result in access being revoked. Use extra keys for reliability or for sites in separate projects, and ask Google for more quota if you need it.

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

= What is Freemius, and do I have to connect an account? =

Fast Index includes the Freemius SDK, a service for licenses and optional account connection. It decides which options are unlocked. Freemius may show its own screens or notices in the admin: a license screen (with a link to continue with the free version) and an opt-in screen with a Skip button, whose text says that the plugin still works if you skip. What the SDK collects is described on those screens and in the Freemius privacy policy (https://freemius.com/privacy/). The code of Fast Index itself sends only addresses to Google, as described in the first question.

= Does Fast Index slow down saving posts? =

No. Saving a post only queues a scheduled task. The request to Google is made later by WP-Cron, with a time limit of 15 seconds, and a batch of old posts stops after about 20 seconds.

== Screenshots ==

1-screenshot-1.png
2-screenshot-2.png
2-screenshot-3.png
2-screenshot-4.png

== Changelog ==
= 2.1 =
* Download bug fixed

== Changelog ==
= 2.0 =
* Download bug fixed

== Changelog ==
= 1.10 =
* Added new post status
* Added licence key manager

= 1.9 =
* Bug fixed

= 1.8 =
* Added Manuel Cron Trigger

= 1.7 =
* Updated

= 1.6 =
* Php8 google API problem solved

= 1.5 =
* Php Tags bugs fixed

= 1.4 =
* Added "Exclude Categories"

= 1.3 =
* Fixed Bugs

= 1.2 =
* Added Language

= 1.1 =
* Added new language

= 1.0 =
* First Version All tested and working.


`<?php code(); ?>`