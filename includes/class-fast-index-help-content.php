<?php
/**
 * Texts of the Help & FAQ screen, the screen help tabs and the settings field descriptions.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * The single source of every help text.
 *
 * The Help & FAQ screen, the help tabs of the plugin screens and the field descriptions of the
 * settings screen all read from here, and a test compares faq() with the readme. Every text is
 * translatable. Answers are HTML built from translated sentences and a few link helpers; the
 * screen prints them through wp_kses() with allowed_html().
 *
 * Statements about Google come from its documentation:
 * - https://developers.google.com/search/apis/indexing-api/v3/prereqs
 * - https://developers.google.com/search/apis/indexing-api/v3/quickstart
 * - https://developers.google.com/search/apis/indexing-api/v3/using-api
 * - https://developers.google.com/search/apis/indexing-api/v3/quota-pricing
 * - https://developers.google.com/search/apis/indexing-api/v3/core-errors
 * - https://support.google.com/webmasters/answer/7687615
 */
class Fast_Index_Help_Content {

	/**
	 * Google: prerequisites page.
	 */
	const URL_PREREQS = 'https://developers.google.com/search/apis/indexing-api/v3/prereqs';

	/**
	 * Google: quickstart page (supported content, approval, spam rules).
	 */
	const URL_QUICKSTART = 'https://developers.google.com/search/apis/indexing-api/v3/quickstart';

	/**
	 * Google: using the API (notification types, removal rules).
	 */
	const URL_USING_API = 'https://developers.google.com/search/apis/indexing-api/v3/using-api';

	/**
	 * Google: quota page.
	 */
	const URL_QUOTA = 'https://developers.google.com/search/apis/indexing-api/v3/quota-pricing';

	/**
	 * Google: Indexing API errors.
	 */
	const URL_ERRORS = 'https://developers.google.com/search/apis/indexing-api/v3/core-errors';

	/**
	 * Google Cloud console.
	 */
	const URL_CLOUD_CONSOLE = 'https://console.cloud.google.com/';

	/**
	 * Search Console help: users and permissions.
	 */
	const URL_SEARCH_CONSOLE_USERS = 'https://support.google.com/webmasters/answer/7687615';

	/**
	 * Setup video (YouTube). Linked, never embedded.
	 */
	const URL_VIDEO = 'https://youtu.be/RsJA66b5884';

	/**
	 * WordPress.org support forum of the plugin.
	 */
	const URL_SUPPORT = 'https://wordpress.org/support/plugin/fast-index/';

	/**
	 * Section IDs and their titles, in page order. The IDs are the anchors of the page.
	 *
	 * @return string[] Title by section key.
	 */
	public static function sections() {
		return array(
			'getting-started' => __( 'Getting started', 'fast-index' ),
			'settings'        => __( 'Settings explained', 'fast-index' ),
			'troubleshooting' => __( 'Troubleshooting', 'fast-index' ),
			'faq'             => __( 'FAQ', 'fast-index' ),
			'support'         => __( 'Still need help?', 'fast-index' ),
		);
	}

	/**
	 * HTML elements the help texts may use.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		return array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'p'      => array(),
			'span'   => array( 'class' => true ),
			'strong' => array(),
			'em'     => array(),
			'code'   => array(),
			'br'     => array(),
		);
	}

	/**
	 * The notice shown before the first step.
	 *
	 * @return string HTML.
	 */
	public static function getting_started_notice() {
		return sprintf(
			/* translators: 1: quoted sentence from Google's documentation, 2: link to that documentation. */
			__( 'Please read this first. Google says: %1$s For other pages, Google may accept a notification, but nothing in its documentation promises that the page will be crawled or indexed sooner. See the FAQ and Google\'s documentation: %2$s.', 'fast-index' ),
			'<em>' . esc_html__( '"The Indexing API can only be used to crawl pages with either JobPosting or BroadcastEvent embedded in a VideoObject."', 'fast-index' ) . '</em>',
			self::ext( self::URL_QUICKSTART, __( 'Indexing API quickstart', 'fast-index' ) )
		);
	}

	/**
	 * The introduction of the getting started section.
	 *
	 * @return string HTML.
	 */
	public static function getting_started_intro() {
		return esc_html__( 'Fast Index tells the Google Indexing API when a post is published, changed or removed. For this, Google needs a service account that you create once in Google Cloud. The steps below follow Google\'s own instructions.', 'fast-index' );
	}

	/**
	 * Setup steps, in order.
	 *
	 * @return array[] Each: title (plain text), text (HTML).
	 */
	public static function getting_started_steps() {
		return array(
			array(
				'title' => __( 'Create a Google Cloud project and turn on the API', 'fast-index' ),
				'text'  => sprintf(
					/* translators: 1: link to the Google Cloud console, 2: name of the API, 3: link to Google's prerequisites page. */
					__( 'Open the %1$s and select or create a project. Then enable the %2$s for that project (Google\'s documentation calls it the Indexing API). Google also offers a setup tool that creates a project and enables the API in one step; it is linked from %3$s.', 'fast-index' ),
					self::ext( self::URL_CLOUD_CONSOLE, __( 'Google Cloud console', 'fast-index' ) ),
					self::strong( __( 'Web Search Indexing API', 'fast-index' ) ),
					self::ext( self::URL_PREREQS, __( 'Google\'s prerequisites page', 'fast-index' ) )
				),
			),
			array(
				'title' => __( 'Create a service account', 'fast-index' ),
				'text'  => sprintf(
					/* translators: 1: Google Cloud menu name "Service accounts", 2: button name "Create service account", 3: button name "Done". */
					__( 'In the Google Cloud console open the %1$s page, choose your project and click %2$s. Enter a name. You can skip the optional steps about access (IAM roles). Click %3$s.', 'fast-index' ),
					self::strong( __( 'Service accounts', 'fast-index' ) ),
					self::strong( __( 'Create service account', 'fast-index' ) ),
					self::strong( __( 'Done', 'fast-index' ) )
				),
			),
			array(
				'title' => __( 'Download a JSON key', 'fast-index' ),
				'text'  => sprintf(
					/* translators: 1: tab name "Keys", 2: menu entry "Add key", 3: menu entry "Create new key", 4: key type "JSON", 5: button name "Create". */
					__( 'Click the email address of the new service account and open the %1$s tab. Click %2$s, choose %3$s, select %4$s as the key type and click %5$s. The file downloads once and Google cannot give it to you again. Keep it private and never share or post it. If Google says you cannot create keys, your organization may block key creation with a policy; ask your Google Cloud administrator.', 'fast-index' ),
					self::strong( __( 'Keys', 'fast-index' ) ),
					self::strong( __( 'Add key', 'fast-index' ) ),
					self::strong( __( 'Create new key', 'fast-index' ) ),
					self::strong( __( 'JSON', 'fast-index' ) ),
					self::strong( __( 'Create', 'fast-index' ) )
				),
			),
			array(
				'title' => __( 'Add the service account as an Owner in Search Console', 'fast-index' ),
				'text'  => sprintf(
					/* translators: 1: Search Console menu path "Settings", 2: "Users and permissions", 3: button name "Add user", 4: permission name "Owner", 5: link to Google's help page, 6: file field name "client_email". */
					__( 'Open Search Console and choose the verified property of your site: a Domain property, or a URL-prefix property that matches your site address exactly, including https and www. Go to %1$s, then %2$s, click %3$s, enter the email address of the service account (the %6$s value in the JSON file; it ends in .iam.gserviceaccount.com) and choose %4$s as the permission. Google describes this in %5$s.', 'fast-index' ),
					self::strong( __( 'Settings', 'fast-index' ) ),
					self::strong( __( 'Users and permissions', 'fast-index' ) ),
					self::strong( __( 'Add user', 'fast-index' ) ),
					self::strong( __( 'Owner', 'fast-index' ) ),
					self::ext( self::URL_SEARCH_CONSOLE_USERS, __( 'Search Console Help', 'fast-index' ) ),
					self::code( 'client_email' )
				),
			),
			array(
				'title' => __( 'Upload the key to Fast Index', 'fast-index' ),
				'text'  => sprintf(
					/* translators: 1: heading on the settings screen, 2: file button label, 3: submit button label. */
					__( 'Open the Fast Index settings. Under %1$s choose the .json file (%2$s) and click %3$s. The screen then lists the email address and key ID of the key. The private key is stored in your database and is never shown.', 'fast-index' ),
					self::strong( __( 'Upload Google Service Account Json', 'fast-index' ) ),
					self::strong( __( 'Choose Json File/s', 'fast-index' ) ),
					self::strong( __( 'Upload key', 'fast-index' ) )
				),
			),
			array(
				'title' => __( 'Choose what to send', 'fast-index' ),
				'text'  => sprintf(
					/* translators: 1: setting name "Status", 2: value "Active", 3: setting name "Post Types", 4: button name "Save". */
					__( 'In the settings keep %1$s on %2$s, check the %3$s to send and click %4$s. Every option is explained under Settings explained.', 'fast-index' ),
					self::strong( __( 'Status', 'fast-index' ) ),
					self::strong( __( 'Active', 'fast-index' ) ),
					self::strong( __( 'Post Types', 'fast-index' ) ),
					self::strong( __( 'Save', 'fast-index' ) )
				),
			),
			array(
				'title' => __( 'Publish a test post and check History', 'fast-index' ),
				'text'  => sprintf(
					/* translators: 1: menu name "History", 2: example status "200 OK". */
					__( 'Publish a post. A short while later (when WordPress runs its scheduled tasks) it appears under Fast Index, %1$s. %2$s means Google accepted the notification; it does not mean the page is indexed. Any other code is explained under Troubleshooting.', 'fast-index' ),
					self::strong( __( 'History', 'fast-index' ) ),
					self::strong( '200 ' . __( 'OK', 'fast-index' ) )
				),
			),
		);
	}

	/**
	 * The paragraph that links to the setup video.
	 *
	 * @return string HTML.
	 */
	public static function getting_started_video() {
		return sprintf(
			/* translators: %s: link to a video on YouTube. */
			__( 'Prefer a video? %s The video was recorded for an earlier version, so some screens may look different from this guide.', 'fast-index' ),
			self::ext( self::URL_VIDEO, __( 'Watch the setup video on YouTube (external link, opens in a new tab)', 'fast-index' ), false )
		);
	}

	/**
	 * Explanation of every setting.
	 *
	 * @return array[] Each: id, name, short (field description), what (HTML), default, when (HTML).
	 */
	public static function settings() {
		return array(
			array(
				'id'      => 'status',
				'name'    => __( 'Status', 'fast-index' ),
				'short'   => self::field_description( 'status' ),
				'what'    => esc_html__( 'Active: Fast Index sends notifications when posts change, and sends old posts if you asked for that. Passive: nothing is sent. A post saved while Passive is not queued for later.', 'fast-index' ),
				'default' => __( 'Active', 'fast-index' ),
				'when'    => esc_html__( 'Switch to Passive before a bulk import or a large edit, so that hundreds of addresses do not use up Google\'s daily quota. Your keys and history are kept.', 'fast-index' ),
			),
			array(
				'id'      => 'post_type',
				'name'    => __( 'Post Types', 'fast-index' ),
				'short'   => self::field_description( 'post_type' ),
				'what'    => esc_html__( 'Lists every public post type of the site (posts, pages and custom types) except media attachments. Only the checked types are sent. At least one type is required.', 'fast-index' ) . ' ' . esc_html( self::premium_note( 'post_type' ) ),
				'default' => __( 'Posts', 'fast-index' ),
				'when'    => esc_html__( 'Check another type when its pages should be sent too, for example a custom post type that holds job postings or live videos.', 'fast-index' ),
			),
			array(
				'id'      => 'old_post_number',
				'name'    => __( 'Daily Old Content Post?', 'fast-index' ),
				'short'   => self::field_description( 'old_post_number' ),
				'what'    => esc_html__( 'Sends published posts that Google has never accepted from this site, newest first, in small batches every hour. The number counts every successful notification of the last 24 hours, new posts included, and can be 0 to 10,000. Each batch is a random number from 0 to a third of the daily number, so some hours send nothing.', 'fast-index' ) . ' ' . esc_html( self::premium_note( 'old_post_number' ) ),
				'default' => __( '0 (off)', 'fast-index' ),
				'when'    => esc_html__( 'Turn it on after your new posts are sent correctly. Keep the number low enough to leave room for new posts within Google\'s daily quota (see the FAQ).', 'fast-index' ),
			),
			array(
				'id'      => 'post_status',
				'name'    => __( 'Post Status', 'fast-index' ),
				'short'   => self::field_description( 'post_status' ),
				'what'    => esc_html__( 'Publish: a post becomes published. Edit: a post that is already published is saved again. Trash: a published post is moved to the trash or deleted, and Google is told that the address was removed. Changing a published post back to draft or private is not sent.', 'fast-index' ) . ' ' . esc_html( self::premium_note( 'post_status' ) ),
				'default' => __( 'Publish (Edit is also on by default with the premium plan)', 'fast-index' ),
				'when'    => esc_html__( 'Turn on Edit if changed content should be recrawled. Turn on Trash if removed pages should be reported; Google asks that the address already returns 404 or 410, or has a noindex tag, when you send a removal.', 'fast-index' ),
			),
			array(
				'id'      => 'exclude_category',
				'name'    => __( 'Exclude Categories', 'fast-index' ),
				'short'   => self::field_description( 'exclude_category' ),
				'what'    => esc_html__( 'A post in at least one checked category is never sent, whether it is new, changed, removed or part of an old-post batch, even if it is also in other categories.', 'fast-index' ) . ' ' . esc_html( self::premium_note( 'exclude_category' ) ),
				'default' => __( 'No category is excluded', 'fast-index' ),
				'when'    => esc_html__( 'Use it for content you do not want Google to hear about, for example internal or duplicate material.', 'fast-index' ),
			),
			array(
				'id'      => 'delete_data',
				'name'    => __( 'Delete data on uninstall', 'fast-index' ),
				'short'   => self::field_description( 'delete_data' ),
				'what'    => esc_html__( 'Applies when the plugin is deleted from the Plugins screen, not when it is deactivated. Settings, keys and history are removed, on every site of a multisite network. Records kept by Freemius are not touched.', 'fast-index' ),
				'default' => __( 'Off', 'fast-index' ),
				'when'    => esc_html__( 'Turn it on only if you are removing Fast Index for good. The key also stays valid in Google Cloud until you delete it there.', 'fast-index' ),
			),
			array(
				'id'      => 'service_account',
				'name'    => __( 'Upload Google Service Account Json', 'fast-index' ),
				'short'   => '',
				'what'    => esc_html__( 'Reads the .json key that you downloaded from Google Cloud, checks it and stores it in the database, not in the uploads folder. Each stored key is listed with its email address, key ID and the last answer from Google. A key can be deleted from the list.', 'fast-index' ) . ' ' . esc_html( self::premium_note( 'service_account' ) ),
				'default' => __( 'No key', 'fast-index' ),
				'when'    => esc_html__( 'Upload again after you create a new key. Uploading a key for an email address that is already stored replaces the stored key.', 'fast-index' ),
			),
		);
	}

	/**
	 * Short description printed under a field of the settings screen.
	 *
	 * @param string $field status, post_type, old_post_number, post_status, exclude_category or delete_data.
	 * @return string Plain text; empty for an unknown field.
	 */
	public static function field_description( $field ) {
		switch ( $field ) {
			case 'status':
				return __( 'Active sends notifications to Google. Passive pauses everything; your keys and history are kept.', 'fast-index' );
			case 'post_type':
				return trim( __( 'Only the checked types are sent. Media attachments are not listed.', 'fast-index' ) . ' ' . self::premium_note( 'post_type' ) );
			case 'old_post_number':
				return trim( __( 'How many old posts may be sent per day, in small hourly batches. 0 turns this off.', 'fast-index' ) . ' ' . self::premium_note( 'old_post_number' ) );
			case 'post_status':
				return trim( __( 'Publish: a post goes live. Edit: a live post is saved again. Trash: a live post is trashed or deleted.', 'fast-index' ) . ' ' . self::premium_note( 'post_status' ) );
			case 'exclude_category':
				return trim( __( 'Posts in a checked category are never sent.', 'fast-index' ) . ' ' . self::premium_note( 'exclude_category' ) );
			case 'delete_data':
				return __( 'Off by default: deleting the plugin keeps your settings, keys and history, so a reinstall continues where you left off.', 'fast-index' );
			default:
				return '';
		}
	}

	/**
	 * The sentence that says an option needs the premium plan. One place, so it can change with the plan decision.
	 *
	 * @param string $field Field name.
	 * @return string Plain text; empty when the option is free.
	 */
	public static function premium_note( $field ) {
		switch ( $field ) {
			case 'post_type':
				return __( 'In this version, types other than Posts need the premium plan.', 'fast-index' );
			case 'old_post_number':
				return __( 'In this version, this needs the premium plan.', 'fast-index' );
			case 'post_status':
				return __( 'In this version, Edit and Trash need the premium plan.', 'fast-index' );
			case 'exclude_category':
				return __( 'In this version, this needs the premium plan.', 'fast-index' );
			case 'service_account':
				return __( 'In this version, the free plan keeps one key (a new upload replaces it); the premium plan keeps several.', 'fast-index' );
			default:
				return '';
		}
	}

	/**
	 * Problems and their solutions.
	 *
	 * @return array[] Each: id, symptom (plain text), cause (HTML), fix (HTML).
	 */
	public static function troubleshooting() {
		return array(
			array(
				'id'      => 'error-403',
				'symptom' => __( '403: "Permission denied. Failed to verify the URL ownership"', 'fast-index' ),
				'cause'   => sprintf(
					/* translators: %s: link to Google's error list. */
					__( 'Google answers 403 when the service account is not an Owner of the Search Console property that contains the page address (%s). Common reasons: the account was never added, it was added with a lower permission than Owner, the property is for another address (http or https, www or no www, another site), or the Indexing API is not enabled in the project that owns the key.', 'fast-index' ),
					self::ext( self::URL_ERRORS, __( 'Google\'s error list', 'fast-index' ) )
				),
				'fix'     => esc_html__( 'In Search Console open the property, go to Settings and Users and permissions, and check that the service account email is listed with the permission Owner. Make sure the property matches your site address exactly (a Domain property covers every address). Then check in Google Cloud that the Web Search Indexing API is enabled for the key\'s project. After a 403 Fast Index lets the key rest for six hours; to retry at once, delete the key on the settings screen and upload it again.', 'fast-index' ),
			),
			array(
				'id'      => 'error-401',
				'symptom' => __( '401: Unauthorized, or the key is refused', 'fast-index' ),
				'cause'   => esc_html__( 'Google did not accept the key. The key may have been deleted or disabled in Google Cloud, the uploaded file may be an older key, or the clock of your server may be far off, because the signed request carries the current time. Any 4xx answer to the request for an access token is treated the same way.', 'fast-index' ),
				'fix'     => esc_html__( 'Create a new JSON key (Service accounts, your account, Keys, Add key, Create new key, JSON), upload it on the settings screen (the same email address replaces the stored key) and check that your server time is correct. The key rests for six hours after a 401; deleting it and uploading it again ends the rest.', 'fast-index' ),
			),
			array(
				'id'      => 'error-429',
				'symptom' => __( '429: Too Many Requests', 'fast-index' ),
				'cause'   => sprintf(
					/* translators: %s: link to Google's quota page. */
					__( 'The quota of Google is used up. Google\'s default is 200 publish requests per day for each Google Cloud project (%s). New, changed and removed addresses all count, and all keys of one project share the same quota.', 'fast-index' ),
					self::ext( self::URL_QUOTA, __( 'quota page', 'fast-index' ) )
				),
				'fix'     => sprintf(
					/* translators: %s: link to Google's quota page. */
					__( 'Wait: Fast Index lets the key rest for six hours and tries your other keys meanwhile. Lower the daily number of old posts, and turn Fast Index to Passive during bulk imports. If you need more, ask Google for more quota on %s; Google notes that quota may increase or decrease based on document quality.', 'fast-index' ),
					self::ext( self::URL_QUOTA, __( 'the quota page', 'fast-index' ) )
				),
			),
			array(
				'id'      => 'no-answer',
				'symptom' => __( '"No answer from Google", or a 5xx code', 'fast-index' ),
				'cause'   => esc_html__( 'Your server could not reach Google within 15 seconds (firewall, DNS or a slow connection), Google had a temporary problem, or its answer could not be read. These answers do not make a key rest.', 'fast-index' ),
				'fix'     => esc_html__( 'Allow outgoing HTTPS requests to oauth2.googleapis.com and indexing.googleapis.com, and try again later. Saving or publishing another post sends a new notification.', 'fast-index' ),
			),
			array(
				'id'      => 'openssl',
				'symptom' => __( 'A red notice says the PHP OpenSSL extension is not available, or the status "OpenSSL is missing"', 'fast-index' ),
				'cause'   => esc_html__( 'Fast Index signs its requests to Google with OpenSSL. PHP without OpenSSL cannot do that, so nothing can be sent. While OpenSSL is missing, nothing is written to History.', 'fast-index' ),
				'fix'     => esc_html__( 'Ask your hosting provider to enable the OpenSSL extension for the PHP version your site uses. The system information below shows whether OpenSSL is available.', 'fast-index' ),
			),
			array(
				'id'      => 'wp-cron',
				'symptom' => __( 'Posts are published but nothing appears in History, or it appears much later', 'fast-index' ),
				'cause'   => esc_html__( 'Fast Index queues each notification as a WordPress scheduled task (WP-Cron), so saving a post never waits for Google. WP-Cron runs when someone visits the site. If wp-config.php sets DISABLE_WP_CRON to true, WordPress does not start it, and the queue only moves when a real cron job calls wp-cron.php. Sites with very few visits can run late too.', 'fast-index' ),
				'fix'     => sprintf(
					/* translators: 1: example cron line, 2: example WP-CLI command. */
					__( 'If you disabled WP-Cron on purpose, set up a real cron job on your server that runs every 5 minutes, for example %1$s or, with WP-CLI, %2$s. Otherwise remove DISABLE_WP_CRON from wp-config.php. The system information below shows whether it is set.', 'fast-index' ),
					self::code( '*/5 * * * * curl -s "https://example.com/wp-cron.php?doing_wp_cron" > /dev/null' ),
					self::code( 'wp cron event run --due-now' )
				),
			),
			array(
				'id'      => 'nothing-in-history',
				'symptom' => __( 'A post is missing from History', 'fast-index' ),
				'cause'   => esc_html__( 'Fast Index skips a post when Status is Passive, when its post type or the event (Publish, Edit, Trash) is not checked, when it is in an excluded category, or when it is not published. Nothing is written to History, and the notification is not repeated, when no key is stored, when every key is resting after an error, or when OpenSSL is missing.', 'fast-index' ),
				'fix'     => esc_html__( 'Check the settings above. On the settings screen each key shows the last answer from Google; a key in 401, 403 or 429 rests for six hours. After you fix the cause, save the post again to send it. If old-post sending is on, posts that never reached Google are also picked up by the hourly batches.', 'fast-index' ),
			),
			array(
				'id'      => 'old-posts',
				'symptom' => __( '"There are no posts to send now", or old posts are not sent', 'fast-index' ),
				'cause'   => esc_html__( 'The daily number is 0, Status is Passive, the daily number was already reached in the last 24 hours (new posts count too), no key is available, or every eligible post was sent. Each batch has a random size, so a run can also be empty by chance.', 'fast-index' ),
				'fix'     => esc_html__( 'Check the daily number and the counters on the History screen (Total Waiting shows how many posts have not been accepted by Google yet). Run the batch again later; it also runs every hour on its own.', 'fast-index' ),
			),
			array(
				'id'      => 'key-notice',
				'symptom' => __( 'A notice about keys stored in a public folder, or "Fast Index could not finish moving your service account keys"', 'fast-index' ),
				'cause'   => esc_html__( 'Version 2.x saved key files in the uploads folder, where anyone who knew the address could download them. Version 3 moves each key into the database and deletes the file. The first notice recommends replacing those keys. The second notice means one file could not be read, found or deleted; it names the file.', 'fast-index' ),
				'fix'     => esc_html__( 'Create a new JSON key in Google Cloud, upload it on the settings screen (the same email address replaces the stored key), then delete the old key on the Keys tab of the service account in Google Cloud. If a file could not be deleted, delete it from the uploads folder yourself. Both notices have a link that hides them for good.', 'fast-index' ),
			),
			array(
				'id'      => 'multisite',
				'symptom' => __( 'On a multisite network, one site sends and another does not', 'fast-index' ),
				'cause'   => esc_html__( 'Settings, keys, history and the schedule belong to each site. Network activation sets up every site, but a key uploaded on one site is not shared with the others, and Fast Index has no network admin screen.', 'fast-index' ),
				'fix'     => esc_html__( 'Open Fast Index on each site and upload a key there. The same key file may be used on several sites. Their notifications then share the quota of the key\'s Google Cloud project.', 'fast-index' ),
			),
			array(
				'id'      => 'locked',
				'symptom' => __( 'An option is locked, or asks me to upgrade to premium', 'fast-index' ),
				'cause'   => esc_html__( 'In this version, some options need the premium plan. Settings explained says which ones. Fast Index uses Freemius for the plan.', 'fast-index' ),
				'fix'     => esc_html__( 'Use the free options, or see the FAQ entry about Freemius.', 'fast-index' ),
			),
		);
	}

	/**
	 * Frequently asked questions. The readme has the same questions in the same order.
	 *
	 * @return array[] Each: id, question (plain text), answer (HTML).
	 */
	public static function faq() {
		return array(
			array(
				'id'       => 'what-is-sent',
				'question' => __( 'What does Fast Index send to Google?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: 1: notification type "URL_UPDATED", 2: notification type "URL_DELETED", 3: link to Google's privacy policy. */
					__( 'Only the address (URL) of a post and the kind of notification: %1$s when a post is published or changed, %2$s when it is removed. Titles, content, visitors and your database are not sent. To prove who is asking, Fast Index also sends a signed request that contains the email address and key ID of your service account; the private key itself never leaves your site. The requests go to oauth2.googleapis.com and indexing.googleapis.com. Your own History keeps the title, address and Google\'s answer in your database. See also %3$s.', 'fast-index' ),
					self::code( 'URL_UPDATED' ),
					self::code( 'URL_DELETED' ),
					self::ext( 'https://policies.google.com/privacy', __( 'Google\'s privacy policy', 'fast-index' ) )
				),
			),
			array(
				'id'       => 'guarantee',
				'question' => __( 'Will Google index every page I publish right away?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: 1: quoted sentence from Google's documentation, 2: link to Google's documentation. */
					__( 'No, and Fast Index cannot promise it. Google says: %1$s For other pages, such as ordinary posts or products, Google may accept the notification and answer 200, but its documentation does not promise that the page will be crawled or indexed, or that this happens sooner. A 200 only means that, in Google\'s words, it "may try to recrawl this URL soon". Google also checks every submission for spam and may revoke access for abuse. Keep your sitemap and Search Console as they are. Details: %2$s.', 'fast-index' ),
					'<em>' . esc_html__( '"The Indexing API can only be used to crawl pages with either JobPosting or BroadcastEvent embedded in a VideoObject."', 'fast-index' ) . '</em>',
					self::ext( self::URL_USING_API, __( 'Using the Indexing API', 'fast-index' ) )
				),
			),
			array(
				'id'       => 'post-types',
				'question' => __( 'Which post types does Fast Index send?', 'fast-index' ),
				'answer'   => esc_html__( 'Every public post type of your site except media attachments can be chosen in the settings: posts, pages and custom types. Only published content is sent; drafts, pending and private posts are not. By default only Posts are checked.', 'fast-index' ) . ' ' . esc_html( self::premium_note( 'post_type' ) ),
			),
			array(
				'id'       => 'deleted-posts',
				'question' => __( 'What happens when I trash or delete a post?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: 1: notification type "URL_DELETED", 2: link to Google's documentation. */
					__( 'If Trash is checked in the settings, a published post that is moved to the trash, or deleted without the trash, is reported as %1$s with the address it had before. Deleting it later from the trash sends nothing more. Changing a published post to draft or private is not reported. Google asks that the address returns 404 or 410, or carries a noindex tag, when you send a removal (%2$s).', 'fast-index' ),
					self::code( 'URL_DELETED' ),
					self::ext( self::URL_USING_API, __( 'Google\'s documentation', 'fast-index' ) )
				) . ' ' . esc_html( self::premium_note( 'post_status' ) ),
			),
			array(
				'id'       => 'old-posts',
				'question' => __( 'Can Fast Index send my old posts?', 'fast-index' ),
				'answer'   => esc_html__( 'Yes. Set a daily number under Daily Old Content Post. Every hour Fast Index sends a small random batch of published posts that Google has not accepted yet, newest first, until the daily number is reached. The number counts all successful notifications of the last 24 hours. "Send Old Posts Now" runs one batch immediately, and History shows how many posts are still waiting. Keep the number within Google\'s daily quota.', 'fast-index' ) . ' ' . esc_html( self::premium_note( 'old_post_number' ) ),
			),
			array(
				'id'       => 'quota',
				'question' => __( 'How many URLs can I send per day?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: %s: link to Google's quota page. */
					__( 'Google\'s default is 200 publish requests per day for each Google Cloud project; notifications of both kinds, updated and removed, count (%s). Google says larger usage needs its approval and that quota may increase or decrease based on document quality. Fast Index does not know your quota in advance: when Google answers 429 it lets that key rest for six hours.', 'fast-index' ),
					self::ext( self::URL_QUOTA, __( 'quota page', 'fast-index' ) )
				),
			),
			array(
				'id'       => 'multiple-accounts',
				'question' => __( 'Can I use more than one service account?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: %s: link to Google's quickstart page. */
					__( 'With the premium plan you can upload several keys. For each notification Fast Index picks one of the keys that are not resting, in random order. If Google answers 401, 403 or 429 (or refuses the key), that key rests for six hours and the next one is tried. Other errors (such as 400, 5xx or no answer) end the attempt without resting a key. Please note: Google counts quota for each project, not for each key, so keys from the same project share the same 200 per day. Google also writes that attempts to abuse the API, including the use of multiple accounts to exceed usage quotas, may result in access being revoked (%s). Use extra keys for reliability or for sites in separate projects, and ask Google for more quota if you need it.', 'fast-index' ),
					self::ext( self::URL_QUICKSTART, __( 'quickstart', 'fast-index' ) )
				),
			),
			array(
				'id'       => 'key-safety',
				'question' => __( 'Where is my service account key stored, and is it safe?', 'fast-index' ),
				'answer'   => esc_html__( 'The key is kept in your WordPress database (an option that is not loaded on every request). It is never written to the uploads folder or any other file, and the screens show only the email address and key ID. Because the service account is an Owner of your Search Console property, treat the key like a password. If it may have leaked, delete the key in Google Cloud and remove the service account from the users of your Search Console property.', 'fast-index' ),
			),
			array(
				'id'       => 'uninstall',
				'question' => __( 'What happens to my data if I deactivate or delete Fast Index?', 'fast-index' ),
				'answer'   => esc_html__( 'Deactivating removes the scheduled tasks and keeps everything else. Deleting the plugin removes temporary data (queued tasks, caches, locks) and, by default, keeps your settings, keys and history, so a reinstall continues where you left off. If "Delete data on uninstall" was checked before you deleted the plugin, settings, keys and history are removed too, on every site of a network. Records kept by Freemius are not touched, and the key stays valid in Google Cloud until you delete it there.', 'fast-index' ),
			),
			array(
				'id'       => 'upgrade',
				'question' => __( 'I am upgrading from version 2.2. What happens to my keys?', 'fast-index' ),
				'answer'   => esc_html__( 'On the first page load after the update, Fast Index reads each key file that version 2.x saved in the uploads folder, checks it, stores it in the database and deletes the file. Your settings and history are kept, and the old ten-minute scheduled task is replaced by an hourly one. Earlier versions kept the files in a folder that anyone could reach, so a notice recommends creating new keys and deleting the old ones in Google Cloud (see Troubleshooting). If a file cannot be moved, a notice names it, and you can upload that key again.', 'fast-index' ),
			),
			array(
				'id'       => 'multisite',
				'question' => __( 'Does Fast Index work on multisite?', 'fast-index' ),
				'answer'   => esc_html__( 'Yes. You can activate it for the whole network or for single sites; sites created later are set up automatically after a network activation. Settings, keys, history and the schedule belong to each site, so upload a key on every site that should send. There is no network admin screen.', 'fast-index' ),
			),
			array(
				'id'       => 'multilingual',
				'question' => __( 'Does Fast Index work on multilingual sites?', 'fast-index' ),
				'answer'   => esc_html__( 'Fast Index does not read language plugins. It sends the address that WordPress gives for the post that was saved, so a translation that is stored as its own post is sent when that post changes. The service account must be an Owner of a Search Console property that covers the addresses of every language, for example a Domain property. The plugin\'s own screens follow the language of your site or user profile.', 'fast-index' ),
			),
			array(
				'id'       => 'indexnow',
				'question' => __( 'Does Fast Index support Bing or IndexNow?', 'fast-index' ),
				'answer'   => esc_html__( 'No. Fast Index talks only to the Google Indexing API.', 'fast-index' ),
			),
			array(
				'id'       => 'freemius',
				'question' => __( 'What is Freemius, and do I have to connect an account?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: %s: link to the Freemius privacy policy. */
					__( 'Fast Index includes the Freemius SDK, a service for licences and optional account connection. It decides which options are unlocked (see Settings explained). Freemius may show its own screens or notices in the admin: a license screen (with a link to continue with the free version) and an opt-in screen with a Skip button, whose text says that the plugin still works if you skip. What the SDK collects is described on those screens and in %s. The code of Fast Index itself sends only addresses to Google, as described in the first question.', 'fast-index' ),
					self::ext( 'https://freemius.com/privacy/', __( 'the Freemius privacy policy', 'fast-index' ) )
				),
			),
			array(
				'id'       => 'speed',
				'question' => __( 'Does Fast Index slow down saving posts?', 'fast-index' ),
				'answer'   => esc_html__( 'No. Saving a post only queues a scheduled task. The request to Google is made later by WP-Cron, with a time limit of 15 seconds, and a batch of old posts stops after about 20 seconds.', 'fast-index' ),
			),
		);
	}

	/**
	 * The questions of faq(), in order. The readme must list exactly these.
	 *
	 * @return string[]
	 */
	public static function faq_questions() {
		return wp_list_pluck( self::faq(), 'question' );
	}

	/**
	 * The checklist before asking for support.
	 *
	 * @return string[] HTML items.
	 */
	public static function support_checklist() {
		return array(
			esc_html__( 'Read the Troubleshooting entry that matches the code in History, and the FAQ.', 'fast-index' ),
			esc_html__( 'On the settings screen, look at the last answer shown next to each key.', 'fast-index' ),
			esc_html__( 'In Search Console, check that the service account is an Owner of a property that matches your site address exactly.', 'fast-index' ),
			esc_html__( 'If WP_DEBUG_LOG is on, look in wp-content/debug.log for lines that mention fast-index.', 'fast-index' ),
			esc_html__( 'Search the support forum for your question first.', 'fast-index' ),
			esc_html__( 'When you write, say what you expected and what happened, and paste the system information below. Never post your JSON key file or any part of the private key.', 'fast-index' ),
		);
	}

	/**
	 * Title and HTML of the help tab of a plugin screen.
	 *
	 * @param string $screen settings, history, run or help.
	 * @return array Keys: title, content (HTML).
	 */
	public static function screen_help( $screen ) {
		$texts = array(
			'settings' => __( 'Choose what Fast Index sends to Google and upload the service account key. Each option is explained under its field and in Settings explained on the Help & FAQ screen.', 'fast-index' ),
			'history'  => __( 'Shows the totals and the latest notifications with the answer from Google. 200 OK means Google accepted the notification; it does not mean the page is indexed. Other codes are explained under Troubleshooting.', 'fast-index' ),
			'run'      => __( 'Sends one batch of old posts now, within the daily number set on the settings screen. The batch has a random size, so a run can send nothing. The same batch also runs every hour.', 'fast-index' ),
			'help'     => __( 'Step-by-step setup, an explanation of every setting, troubleshooting, frequently asked questions and the system information to include when you ask for support.', 'fast-index' ),
		);
		$text  = isset( $texts[ $screen ] ) ? $texts[ $screen ] : $texts['help'];

		return array(
			'title'   => __( 'About this screen', 'fast-index' ),
			'content' => '<p>' . esc_html( $text ) . '</p>' . ( 'help' === $screen ? '' : '<p>' . self::page_link( Fast_Index_Help::SLUG, __( 'Open Help & FAQ', 'fast-index' ) ) . '</p>' ),
		);
	}

	/**
	 * HTML of the help sidebar.
	 *
	 * @return string
	 */
	public static function sidebar() {
		return '<p><strong>' . esc_html__( 'For more information:', 'fast-index' ) . '</strong></p>'
			. '<p>' . self::page_link( Fast_Index_Help::SLUG, __( 'Help & FAQ', 'fast-index' ) ) . '</p>'
			. '<p>' . self::ext( self::URL_SUPPORT, __( 'Support forum', 'fast-index' ) ) . '</p>';
	}

	/**
	 * Link to a page of Fast Index.
	 *
	 * @param string $slug  Page slug.
	 * @param string $label Link text.
	 * @return string HTML.
	 */
	public static function page_link( $slug, $label ) {
		return '<a href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Link to another site. It opens in a new tab and says so for screen readers.
	 *
	 * @param string $url   Address.
	 * @param string $label Link text.
	 * @param bool   $mark  Whether to add the hidden "external link" text; false when the label says it.
	 * @return string HTML.
	 */
	public static function ext( $url, $label, $mark = true ) {
		$note = $mark ? '<span class="screen-reader-text"> ' . esc_html__( '(external link, opens in a new tab)', 'fast-index' ) . '</span>' : '';

		return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . $note . '</a>';
	}

	/**
	 * Bold text.
	 *
	 * @param string $text Plain text.
	 * @return string HTML.
	 */
	private static function strong( $text ) {
		return '<strong>' . esc_html( $text ) . '</strong>';
	}

	/**
	 * Code text.
	 *
	 * @param string $text Plain text.
	 * @return string HTML.
	 */
	private static function code( $text ) {
		return '<code>' . esc_html( $text ) . '</code>';
	}
}
