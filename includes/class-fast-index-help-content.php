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
	 * IndexNow documentation.
	 */
	const URL_INDEXNOW_DOCS = 'https://www.indexnow.org/documentation';

	/**
	 * IndexNow terms of service.
	 */
	const URL_INDEXNOW_TERMS = 'https://www.indexnow.org/terms';

	/**
	 * WordPress.org support forum of the plugin.
	 */
	const URL_SUPPORT = 'https://wordpress.org/support/plugin/fast-index/';

	/**
	 * Freemius customer portal: purchases, downloads and license keys.
	 */
	const URL_FREEMIUS_ACCOUNT = 'https://customers.freemius.com/';

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
			'bdi'    => array( 'dir' => true ),
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
			__( 'Read this first. Google says: %1$s For other pages, Google may accept a notification, but nothing in its documentation promises that the page will be crawled or indexed sooner. See the FAQ and Google\'s documentation: %2$s.', 'fast-index' ),
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
					self::strong( _x( 'Settings', 'Menu entry in Google Search Console', 'fast-index' ) ),
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
					/* translators: 1: heading on the settings screen, 2: label of the file field, 3: submit button label. */
					__( 'Open the Fast Index settings. Under %1$s choose the .json file (%2$s) and click %3$s. The screen then lists the email address and key ID of the key. The private key is stored in your database and is never shown.', 'fast-index' ),
					self::strong( __( 'Upload Google Service Account Key', 'fast-index' ) ),
					self::strong( __( 'JSON Key File', 'fast-index' ) ),
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
					'<strong><bdi dir="ltr">200 OK</bdi></strong>'
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
	 * @return array[] Each: id, name, short (field description), what (HTML), note (plain text, may be empty: the premium
	 *                 plan sentence, shown as its own paragraph), default, when (HTML).
	 */
	public static function settings() {
		return array(
			array(
				'id'      => 'status',
				'name'    => __( 'Status', 'fast-index' ),
				'short'   => self::field_description( 'status' ),
				'what'    => esc_html__( 'Active: Fast Index sends the notifications chosen on the settings screen. Passive: nothing is sent. A post saved while Passive is not queued for later.', 'fast-index' ),
				'default' => __( 'Active', 'fast-index' ),
				'when'    => esc_html__( 'Switch to Passive before a bulk import or a large edit, so that hundreds of addresses do not use up Google\'s daily quota. Your keys and history are kept.', 'fast-index' ),
			),
			array(
				'id'      => 'post_type',
				'name'    => __( 'Post Types', 'fast-index' ),
				'short'   => self::field_description( 'post_type' ),
				'what'    => esc_html__( 'Only the checked post types are sent, and at least one is required. Every public post type of the site is offered, such as Posts, Pages and the custom post types of other plugins; media attachments are never offered.', 'fast-index' ),
				'default' => __( 'Posts', 'fast-index' ),
				'when'    => esc_html__( 'Check another type when its pages should be sent too, for example a custom post type that holds job postings or live videos.', 'fast-index' ),
			),
			array(
				'id'      => 'old_post_number',
				'name'    => __( 'Daily Limit for Old Posts', 'fast-index' ),
				'short'   => self::field_description( 'old_post_number' ),
				'what'    => esc_html__( 'Sends published posts that Google has never accepted from this site, newest first, in small batches every hour. The limit counts every successful notification of the last 24 hours, new posts included, and can be set from 0 to 10,000. The size of each batch is random, from 0 up to a third of the daily limit, so some hours send nothing.', 'fast-index' ),
				'note'    => self::premium_note( 'old_post_number' ),
				'default' => __( '0 (off)', 'fast-index' ),
				'when'    => esc_html__( 'Turn it on after your new posts are sent correctly. Keep the limit low enough to leave room for new posts within Google\'s daily quota (see the FAQ).', 'fast-index' ),
			),
			array(
				'id'      => 'post_status',
				'name'    => __( 'Post Events', 'fast-index' ),
				'short'   => self::field_description( 'post_status' ),
				'what'    => esc_html__( 'Publish: a post becomes published. Edit: a post that is already published is saved again. Trash: a published post is moved to the trash or deleted, and Google is told that the address was removed. Changing a published post back to draft or private is not sent.', 'fast-index' ),
				'default' => __( 'Publish and Edit', 'fast-index' ),
				'when'    => esc_html__( 'Turn on Edit if changed content should be recrawled. Turn on Trash if removed pages should be reported; Google asks that the address already returns 404 or 410, or has a noindex tag, when you send a removal.', 'fast-index' ),
			),
			array(
				'id'      => 'indexnow',
				'name'    => 'IndexNow',
				'short'   => self::field_description( 'indexnow' ),
				'what'    => sprintf(
					/* translators: %s: link to the IndexNow documentation. */
					esc_html__( 'When checked, every notification also goes to IndexNow (api.indexnow.org) in the same scheduled task, and IndexNow shares it with the search engines that take part. IndexNow needs a key that proves the site is yours: Fast Index creates one for each site (32 letters and digits) and answers it at the key file address shown on the settings screen, without writing a file. While no Google key is stored, only IndexNow is used. See %s.', 'fast-index' ),
					self::ext( self::URL_INDEXNOW_DOCS, __( 'the IndexNow documentation', 'fast-index' ) )
				),
				'default' => __( 'Off', 'fast-index' ),
				'when'    => esc_html__( 'Turn it on if Bing and the other IndexNow search engines should hear about your changes too. IndexNow accepts every kind of page, not only job postings and livestreams.', 'fast-index' ),
			),
			array(
				'id'      => 'exclude_category',
				'name'    => __( 'Exclude Categories', 'fast-index' ),
				'short'   => self::field_description( 'exclude_category' ),
				'what'    => esc_html__( 'A post in at least one checked category is never sent, whether it is new, changed, removed or part of an old-post batch, even if it is also in other categories.', 'fast-index' ),
				'note'    => self::premium_note( 'exclude_category' ),
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
				'name'    => __( 'Upload Google Service Account Key', 'fast-index' ),
				'short'   => '',
				'what'    => esc_html__( 'Reads the .json key that you downloaded from Google Cloud, checks it and stores it in the database, not in the uploads folder. Each stored key is listed with its email address, key ID and the last answer from Google. A key can be deleted from the list.', 'fast-index' ),
				'note'    => self::premium_note( 'service_account' ),
				'default' => __( 'No key', 'fast-index' ),
				'when'    => esc_html__( 'Upload again after you create a new key. Uploading a key for an email address that is already stored replaces the stored key.', 'fast-index' ),
			),
		);
	}

	/**
	 * Sentences printed under a field of the settings screen, one paragraph each, so no language gets a space
	 * between sentences that it does not write (Japanese). The premium plan is described only in the Premium box
	 * of the settings screen and in Help & FAQ, never under a field.
	 *
	 * @param string $field Field name, as for field_description().
	 * @return string[] Plain text.
	 */
	public static function field_notes( $field ) {
		return array_values( array_filter( array( self::field_description( $field ) ) ) );
	}

	/**
	 * Description of one post event, printed under the event checkboxes.
	 *
	 * @param string $event publish, edit or trash.
	 * @return string Plain text; empty for an unknown event.
	 */
	public static function event_description( $event ) {
		switch ( $event ) {
			case 'publish':
				return __( 'Publish: a post is published.', 'fast-index' );
			case 'edit':
				return __( 'Edit: a published post is saved again.', 'fast-index' );
			case 'trash':
				return __( 'Trash: a published post is moved to the trash or deleted.', 'fast-index' );
			default:
				return '';
		}
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
				return __( 'Only the checked types are sent. Media attachments are not listed.', 'fast-index' );
			case 'old_post_number':
				return __( 'How many old posts may be sent per day, in small hourly batches. 0 turns this off.', 'fast-index' );
			case 'post_status':
				return __( 'Publish: a post is published. Edit: a published post is saved again. Trash: a published post is moved to the trash or deleted.', 'fast-index' );
			case 'exclude_category':
				return __( 'Posts in a checked category are never sent.', 'fast-index' );
			case 'delete_data':
				return __( 'Off by default: deleting the plugin keeps your settings, keys and history, so a reinstall continues where you left off.', 'fast-index' );
			case 'indexnow':
				return __( 'Off by default. When on, each address also goes to api.indexnow.org, which shares it with Bing, Yandex, Naver, Seznam.cz, Yep and other search engines.', 'fast-index' );
			default:
				return '';
		}
	}

	/**
	 * The sentence of Help & FAQ that says which part of an option comes with the premium plan. The premium plan
	 * is a separate package: the free version does not contain its code, and nothing in it is locked.
	 *
	 * @param string $field Field name.
	 * @return string Plain text; empty when the whole option is in the free version.
	 */
	public static function premium_note( $field ) {
		switch ( $field ) {
			case 'old_post_number':
			case 'exclude_category':
				return __( 'This setting is part of the premium plan, a separate package.', 'fast-index' );
			case 'service_account':
				return __( 'The free version uses one key, and a new upload replaces it. Backup keys are part of the premium plan, a separate package.', 'fast-index' );
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
				'fix'     => esc_html__( 'In Search Console open the property, go to Settings and Users and permissions, and check that the service account email is listed with the permission Owner. Make sure the property matches your site address exactly (a Domain property covers every address). Then check in Google Cloud that the Web Search Indexing API is enabled for the key\'s project. After a 403 Fast Index pauses the key for six hours; to retry at once, delete the key on the settings screen and upload it again.', 'fast-index' ),
			),
			array(
				'id'      => 'error-401',
				'symptom' => __( '401: Unauthorized, or the key is refused', 'fast-index' ),
				'cause'   => esc_html__( 'Google did not accept the key. The key may have been deleted or disabled in Google Cloud, the uploaded file may be an older key, or the clock of your server may be far off, because the signed request carries the current time. Any 4xx answer to the request for an access token is treated the same way.', 'fast-index' ),
				'fix'     => esc_html__( 'Create a new JSON key (Service accounts, your account, Keys, Add key, Create new key, JSON), upload it on the settings screen (the same email address replaces the stored key) and check that your server time is correct. The key is paused for six hours after a 401; deleting it and uploading it again ends the pause.', 'fast-index' ),
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
					__( 'Wait for the reset: Fast Index pauses every key until Google resets the daily quota at midnight Pacific Time, and the notifications that could not be sent wait until then. No other key is tried, because Google counts the quota for each project and does not allow extra accounts to get past it; uploading another key does not end the pause. Lower the daily limit for old posts if you use it, and turn Fast Index to Passive during bulk imports. If you need more, ask Google for more quota on the %s; Google notes that quota may increase or decrease based on document quality.', 'fast-index' ),
					self::ext( self::URL_QUOTA, __( 'quota page', 'fast-index' ) )
				),
			),
			array(
				'id'      => 'no-answer',
				'symptom' => sprintf(
					/* translators: %s: status text "No answer from Google". */
					__( '"%s", or a 5xx code', 'fast-index' ),
					__( 'No answer from Google', 'fast-index' )
				),
				'cause'   => esc_html__( 'Your server could not reach Google within 15 seconds (firewall, DNS or a slow connection), Google had a temporary problem, or its answer could not be read. These answers do not pause a key.', 'fast-index' ),
				'fix'     => esc_html__( 'Allow outgoing HTTPS requests to oauth2.googleapis.com and indexing.googleapis.com, and try again later. Saving or publishing another post sends a new notification.', 'fast-index' ),
			),
			array(
				'id'      => 'openssl',
				'symptom' => sprintf(
					/* translators: %s: status text "Not sent: OpenSSL is missing". */
					__( 'A red notice says the PHP OpenSSL extension is not available, or the status "%s"', 'fast-index' ),
					Fast_Index_Admin::status_label( Fast_Index_Submitter::UNSUPPORTED )
				),
				'cause'   => esc_html__( 'Fast Index signs its requests to Google with OpenSSL. PHP without OpenSSL cannot do that, so nothing can be sent. Each notification appears in History with this status and is not sent later.', 'fast-index' ),
				'fix'     => esc_html__( 'Ask your hosting provider to enable the OpenSSL extension for the PHP version your site uses. The system information below shows whether OpenSSL is available. Then save the posts that should reach Google again; if old-post sending is on, the hourly batches also pick up posts that never reached Google.', 'fast-index' ),
			),
			array(
				'id'      => 'not-sent',
				'symptom' => sprintf(
					/* translators: 1: status text "Not sent: no service account key", 2: status text "Not sent: every key was paused". */
					__( 'History shows "%1$s" or "%2$s"', 'fast-index' ),
					Fast_Index_Admin::status_label( Fast_Index_Submitter::NO_ACCOUNT ),
					Fast_Index_Admin::status_label( Fast_Index_Submitter::RESTING )
				),
				'cause'   => sprintf(
					/* translators: 1: note shown under a waiting notification, 2: largest number of waiting notifications. */
					esc_html__( 'No key is stored on the settings screen, or every stored key is paused: for six hours after Google rejected it (401 or 403), or until midnight Pacific Time after the daily quota was used up (429). Without a key the notification is only recorded. While every key is paused, the notification waits, and History shows "%1$s" under it: it is sent when the first key is ready again, or soon after you upload a key. For each address only the newest event waits; at most %2$s notifications wait, for up to three days.', 'fast-index' ),
					esc_html( Fast_Index_Admin::retry_note() ),
					esc_html( number_format_i18n( Fast_Index_Retry::MAX ) )
				),
				'fix'     => esc_html__( 'Upload a key on the settings screen. For a paused key, read the entry for its code above. A post that Google has not accepted yet counts as Total Waiting on the History screen: save it again to send it. With the premium plan, the hourly batches of old posts can pick it up too.', 'fast-index' ),
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
				'cause'   => esc_html__( 'Fast Index skips a post when Status is Passive, when its post type or the event (Publish, Edit, Trash) is not checked, when it is in a category excluded by the premium plan, or when it is not published. Nothing is written to History then. While IndexNow is on and no Google key is stored, only IndexNow entries are written.', 'fast-index' ),
				'fix'     => esc_html__( 'Check the settings above. Changing a setting does not send earlier saves: after you fix the cause, save the post again to send it. If old-post sending is on, posts that never reached Google are also picked up by the hourly batches.', 'fast-index' ),
			),
			array(
				'id'      => 'old-posts',
				'symptom' => sprintf(
					/* translators: %s: message of the Send Old Posts Now screen. */
					__( 'Old posts are not sent, or the message "%s" appears', 'fast-index' ),
					__( 'There are no posts to send now. Check your settings and the daily limit.', 'fast-index' )
				),
				'cause'   => esc_html__( 'The daily limit is 0, Status is Passive, the daily limit was already reached in the last 24 hours (new posts count too), no key is available, or every eligible post was sent. Each batch has a random size, so a run can also be empty by chance.', 'fast-index' ),
				'fix'     => esc_html__( 'Check the daily limit and the counters on the History screen (Total Waiting shows how many posts have not been accepted by Google yet). Run the batch again later; it also runs every hour on its own.', 'fast-index' ),
			),
			array(
				'id'      => 'key-notice',
				'symptom' => sprintf(
					/* translators: %s: title of the second notice. */
					__( 'A notice about keys stored in a public folder, or "%s"', 'fast-index' ),
					__( 'Fast Index could not finish moving your service account keys to the database.', 'fast-index' )
				),
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
				'id'      => 'indexnow-key',
				'symptom' => sprintf(
					/* translators: %s: status text "No answer from IndexNow". */
					__( 'IndexNow answers 403 or 422, or "%s"', 'fast-index' ),
					__( 'No answer from IndexNow', 'fast-index' )
				),
				'cause'   => esc_html__( '403: IndexNow could not read the key file at the address shown on the settings screen, or the file did not contain the key. This happens when the request never reaches WordPress, for example with plain permalinks on some servers, or when a cache, firewall or security plugin answers it. 422: an address belongs to another host than the key, for example a page that redirects to another domain. No answer: your server could not reach api.indexnow.org within 15 seconds.', 'fast-index' ),
				'fix'     => esc_html__( 'Open the key file address on the settings screen: it must show the key and nothing else. If it shows a page of your site or an error, choose a permalink structure other than Plain under Settings, Permalinks, or let the address through your cache or firewall. Allow outgoing HTTPS requests to api.indexnow.org. Fast Index does not resend these notifications; save the post again to send it again.', 'fast-index' ),
			),
			array(
				'id'      => 'indexnow-429',
				'symptom' => __( 'IndexNow answers 429', 'fast-index' ),
				'cause'   => esc_html__( 'IndexNow received too many requests from your site and treats them as possible spam.', 'fast-index' ),
				'fix'     => esc_html__( 'Send fewer addresses at once, and do not send the same addresses again and again. Wait before you send more; Fast Index does not resend these notifications.', 'fast-index' ),
			),
			array(
				'id'      => 'premium-options',
				'symptom' => __( 'The settings screen has no option for old posts or excluded categories', 'fast-index' ),
				'cause'   => esc_html__( 'These options are part of the premium plan, which is a separate package. The free version from WordPress.org does not contain their code, so nothing on the screen is locked: in their place the settings screen shows only their name and a Premium badge that explains what they do. Settings explained says which options belong to the premium plan.', 'fast-index' ),
				'fix'     => esc_html__( 'Read the Premium box on the settings screen and the FAQ entry about the premium plan. If you bought the plan, install the premium package as that entry describes.', 'fast-index' ),
			),
		);
	}

	/**
	 * Frequently asked questions. The readme has the same questions in the same order.
	 *
	 * @return array[] Each: id, question (plain text), answer (HTML; the premium plan sentence, if any, is its own paragraph).
	 */
	public static function faq() {
		return array(
			array(
				'id'       => 'what-is-sent',
				'question' => __( 'What does Fast Index send to Google?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: 1: notification type "URL_UPDATED", 2: notification type "URL_DELETED", 3: link to Google's privacy policy. */
					__( 'Only the address (URL) of a post and the kind of notification: %1$s when a post is published or changed, %2$s when it is removed. Titles, content, visitors and your database are not sent. To prove who is asking, Fast Index also sends a signed request that contains the email address and key ID of your service account; the private key itself never leaves your site. The requests go to oauth2.googleapis.com and indexing.googleapis.com. If IndexNow is turned on, the same addresses, the host name of your site and the IndexNow key also go to api.indexnow.org. Your own History keeps the title, address and each answer in your database. See also %3$s.', 'fast-index' ),
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
				'answer'   => esc_html__( 'Fast Index sends published content of the post types checked in the settings; drafts, pending and private posts are not sent. Every public post type of the site can be checked: Posts (checked by default), Pages, and custom post types such as products or job listings. Media attachments are not offered.', 'fast-index' ),
			),
			array(
				'id'       => 'deleted-posts',
				'question' => __( 'What happens when I trash or delete a post?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: 1: notification type "URL_DELETED", 2: link to Google's documentation. */
					__( 'If Trash is checked in the settings, a published post that is moved to the trash, or deleted without the trash, is reported as %1$s with the address it had before. Deleting it later from the trash sends nothing more. Changing a published post to draft or private is not reported. Google asks that the address returns 404 or 410, or carries a noindex tag, when you send a removal (%2$s).', 'fast-index' ),
					self::code( 'URL_DELETED' ),
					self::ext( self::URL_USING_API, __( 'Google\'s documentation', 'fast-index' ) )
				),
			),
			array(
				'id'       => 'by-hand',
				'question' => __( 'Can I send posts by hand?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: 1: action name "Notify search engines", 2: most items sent at a time. */
					esc_html__( 'Yes. In the list of posts, pages or another post type that Fast Index sends, hover over a published item and click %1$s, or select up to %2$s items, choose %1$s under Bulk actions and click Apply. Fast Index sends them to Google right away, and to IndexNow when it is on, and shows the result on the same screen; History lists each answer. You need permission to edit each item, and Status must be Active. Every address counts against Google\'s daily quota.', 'fast-index' ),
					self::strong( __( 'Notify search engines', 'fast-index' ) ),
					esc_html( number_format_i18n( Fast_Index_Manual::MAX ) )
				),
			),
			array(
				'id'       => 'old-posts',
				'question' => __( 'Can Fast Index send my old posts?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: 1: setting name "Daily Limit for Old Posts", 2: menu entry "Send Old Posts Now". */
					esc_html__( 'Yes. Set a daily limit under %1$s. Every hour Fast Index sends a small random batch of published posts that Google has not accepted yet, newest first, until the daily limit is reached. The limit counts all successful notifications of the last 24 hours. %2$s runs one batch immediately, and History shows how many posts are still waiting. Keep the limit within Google\'s daily quota.', 'fast-index' ),
					self::strong( __( 'Daily Limit for Old Posts', 'fast-index' ) ),
					self::strong( __( 'Send Old Posts Now', 'fast-index' ) )
				) . '</p><p>' . esc_html( self::premium_note( 'old_post_number' ) ),
			),
			array(
				'id'       => 'quota',
				'question' => __( 'How many URLs can I send per day?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: %s: link to Google's quota page. */
					__( 'Google\'s default is 200 publish requests per day for each Google Cloud project; notifications of both kinds, updated and removed, count (%s). Google says larger usage needs its approval and that quota may increase or decrease based on document quality. Fast Index does not know your quota in advance: when Google answers 429 it pauses every key until the quota resets at midnight Pacific Time, and the notifications that could not be sent wait until then.', 'fast-index' ),
					self::ext( self::URL_QUOTA, __( 'quota page', 'fast-index' ) )
				),
			),
			array(
				'id'       => 'multiple-accounts',
				'question' => __( 'Can I use more than one service account?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: %s: link to Google's quickstart page. */
					__( 'With the premium plan you can upload backup keys. Fast Index always sends with the main key, the first one in the list. Only when Google rejects it (401 or 403, or the key is refused) is that key paused for six hours and the next key tried. A 429 answer means the daily quota is used up: every key is then paused until the quota resets at midnight Pacific Time, and no other key is tried. Other errors (such as 400, 5xx or no answer) end the attempt without pausing a key. Google counts quota for each project, not for each key, and writes that attempts to abuse the API, including the use of multiple accounts or other means to exceed usage quotas, may result in access being revoked (%s). Ask Google for more quota if you need it.', 'fast-index' ),
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
				'answer'   => sprintf(
					/* translators: %s: setting name "Delete data on uninstall". */
					esc_html__( 'Deactivating removes the scheduled tasks and keeps everything else. Deleting the plugin removes temporary data (queued tasks, caches, locks) and, by default, keeps your settings, keys and history, so a reinstall continues where you left off. If %s was checked before you deleted the plugin, settings, keys and history are removed too, on every site of a network. Records kept by Freemius are not touched, and the key stays valid in Google Cloud until you delete it there.', 'fast-index' ),
					self::strong( __( 'Delete data on uninstall', 'fast-index' ) )
				),
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
				'answer'   => sprintf(
					/* translators: 1: option name "Also send to IndexNow", 2: address of the key file, 3: link to the IndexNow terms. */
					__( 'Yes, through IndexNow, which is off by default. Turn it on with %1$s on the settings screen. Fast Index then also sends each address to api.indexnow.org, which shares it with Bing, Yandex, Naver, Seznam.cz, Yep and the other search engines that take part. Unlike the Google Indexing API, IndexNow accepts every kind of page and needs no account: Fast Index creates a key for your site and answers it at %2$s. The request contains the host name of your site, the key, the address of the key file and the changed addresses. History lists the answers in the IndexNow channel. Fast Index does not use the Bing Webmaster API. See %3$s.', 'fast-index' ),
					self::strong( __( 'Also send to IndexNow', 'fast-index' ) ),
					self::code( '/{key}.txt' ),
					self::ext( self::URL_INDEXNOW_TERMS, __( 'the IndexNow terms', 'fast-index' ) )
				),
			),
			array(
				'id'       => 'premium-features',
				'question' => __( 'What does the premium plan add?', 'fast-index' ),
				'answer'   => esc_html__( 'The free version is not limited in time or in the number of notifications. It notifies Google when content of any public post type is published, updated, trashed or deleted, keeps a History of Google\'s answers and includes Help & FAQ.', 'fast-index' )
					. '</p><p>' . esc_html( Fast_Index_Upgrade::summary() )
					. '</p><p>' . sprintf(
						/* translators: %s: menu entry "Upgrade". */
						esc_html__( 'The premium plan is a separate package, sold through Freemius. The Premium box on the settings screen compares both versions, and %s in the Fast Index menu shows the plans and prices.', 'fast-index' ),
						self::strong( __( 'Upgrade', 'fast-index' ) )
					),
			),
			array(
				'id'       => 'premium',
				'question' => __( 'How do I get the premium plan, and how do I install it?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: 1: menu entry "Upgrade", 2: button "Upgrade to Premium", 3: link to the Freemius customer portal. */
					__( 'Open %1$s in the Fast Index menu, or click %2$s on the settings screen, and buy a plan there; Freemius handles the payment. Freemius then emails you the premium package (a .zip file) and a license key; both are also in your account at %3$s. In WordPress go to Plugins, add a new plugin, click Upload Plugin and choose that .zip file, then activate it and enter the license key when you are asked. If both versions are installed, Freemius deactivates the free one. Your settings, keys and history are kept. The free version from WordPress.org never contains the premium code.', 'fast-index' ),
					self::strong( __( 'Upgrade', 'fast-index' ) ),
					self::strong( __( 'Upgrade to Premium', 'fast-index' ) ),
					self::ext( self::URL_FREEMIUS_ACCOUNT, 'customers.freemius.com' )
				),
			),
			array(
				'id'       => 'freemius',
				'question' => __( 'What is Freemius, and do I have to connect an account?', 'fast-index' ),
				'answer'   => sprintf(
					/* translators: 1: button "Skip", 2: button "Allow & Continue", 3: link to the Freemius privacy policy. */
					__( 'No. Fast Index includes the Freemius SDK, which sells the premium plan and manages its licenses. The free version has no license screen. The first time you open Fast Index, Freemius shows an opt-in screen. If you choose %1$s, nothing is sent to Freemius and Fast Index works the same. If you choose %2$s, your browser sends what that screen lists to Freemius: your name and email address, the site address and title, the language, the WordPress, PHP, plugin and SDK versions, and later the list of your plugins and themes. Opening Upgrade loads the plans from Freemius without any site data, and choosing a plan opens the Freemius checkout. See %3$s.', 'fast-index' ),
					self::strong( _x( 'Skip', 'Button of the Freemius opt-in screen', 'fast-index' ) ),
					self::strong( _x( 'Allow & Continue', 'Button of the Freemius opt-in screen', 'fast-index' ) ),
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
			'history'  => __( 'Shows the Google totals and the latest notifications with the answer of Google or IndexNow; the links above the list show one channel. 200 OK means the notification was accepted; it does not mean the page is indexed. Other codes are explained under Troubleshooting.', 'fast-index' ),
			'run'      => __( 'Sends one batch of old posts now, within the daily limit set on the settings screen. The batch has a random size, so a run can send nothing. The same batch also runs every hour.', 'fast-index' ),
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
