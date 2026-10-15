/**
 * Fast Index 3.0.0 features, end to end (local QA only): quota pause, every post type and event in the free
 * version, IndexNow, sending by hand, the nudges, the eligibility flag and, with the premium module, the index status.
 *
 * Run from /home/samet/wordpress (see fast-index.spec.js). tests/e2e/qa-google-stub.php must be installed as a
 * must-use plugin: it answers Google, Search Console and IndexNow and counts every request by host in the option
 * fast_index_qa_requests, which these tests read. Checks are language-neutral (the sites run in English, Turkish and
 * Arabic): element classes, links, counts and request numbers.
 */
const { test, expect, admin } = require( process.env.CDO_E2E_FIXTURES );

// Page views of these specs do not start WP-Cron (tests/e2e/qa-google-stub.php reads this header): the tests run the
// Fast Index events with WP-CLI, and a parallel WP-Cron run could write back an older copy of the cron list.
test.use( { extraHTTPHeaders: { 'X-Fast-Index-QA': '1' } } );
const { wp, premiumLoaded, passOptIn, IS_MULTISITE } = require( './helpers' );

const PREMIUM = premiumLoaded();
const SETTINGS = admin( 'admin.php?page=fast-index' );
const HISTORY = admin( 'admin.php?page=fast-index-history' );
const USER = process.env.WP_USER || 'admin';
const USER_ID = wp( [ 'user', 'get', USER, '--field=ID' ] );
const STAMP = Date.now();

/**
 * Saves the settings directly (only the keys given; the rest takes the defaults).
 *
 * @param {Object} values Settings.
 * @return {void}
 */
function settings( values ) {
	wp( [ 'option', 'update', 'fast_index_options', JSON.stringify( Object.assign( { status: '1', post_type: { post: '1', page: '1' }, post_status: { publish: '1', edit: '1', trash: '1' } }, values ) ), '--format=json' ] );
}

/**
 * Stores one service account key generated for the test (the stub answers Google, so it is never used outside).
 *
 * @return {void}
 */
function storeKey() {
	wp( [ 'eval', 'foreach ( array_keys( Fast_Index_Accounts::all() ) as $id ) { Fast_Index_Accounts::delete( $id ); } delete_transient( Fast_Index_Accounts::QUOTA_TRANSIENT ); $k = openssl_pkey_new( array( "private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA ) ); openssl_pkey_export( $k, $pem ); Fast_Index_Accounts::save( array( "client_email" => "e2e-roadmap@fast-index-e2e.iam.gserviceaccount.com", "private_key" => $pem, "private_key_id" => "e2eroadmap" ) );' ] );
}

/**
 * Runs the queued notifications of this spec's posts at once, as WP-Cron would. Notifications of other posts (another
 * test run may publish posts on the same site) stay queued, so they do not fill History meanwhile.
 *
 * @return {void}
 */
function runQueue() {
	wp( [ 'eval', 'foreach ( Fast_Index_Queue::items( 100000 ) as $i ) { if ( false === strpos( $i["url"], "e2e-roadmap" ) || null === Fast_Index_Queue::take( $i["url"] ) ) { continue; } Fast_Index_Notifier::deliver( $i["url"], $i["type"], $i["post_id"] ); }' ] );
}

/**
 * Outgoing requests counted by the QA stub since the last reset, by host.
 *
 * @return {Object<string,number>} Count by host.
 */
function requests() {
	return JSON.parse( wp( [ 'eval', 'echo wp_json_encode( (object) get_option( "fast_index_qa_requests", array() ) );' ] ) );
}

/**
 * Requests the QA stub saw that name one of some addresses. Counting by address keeps the posts that another test
 * run may publish on the same site at the same time out of the numbers.
 *
 * @param {string} host Host, for example indexing.googleapis.com.
 * @param {Array<string|RegExp>} urls Addresses (exact) or patterns.
 * @return {number} Requests.
 */
function hits( host, urls ) {
	const log = JSON.parse( wp( [ 'eval', 'echo wp_json_encode( array_values( (array) get_option( "fast_index_qa_urls", array() ) ) );' ] ) );
	const match = ( url ) => urls.some( ( want ) => ( want instanceof RegExp ? want.test( url ) : url === want ) );
	return log.filter( ( entry ) => entry.host === host && entry.urls.some( match ) ).length;
}

/**
 * Address of a post.
 *
 * @param {string} id Post ID.
 * @return {string} Permalink.
 */
function permalink( id ) {
	return wp( [ 'eval', 'echo get_permalink( ' + id + ' );' ] );
}

/**
 * Starts counting requests again.
 *
 * @return {void}
 */
function resetRequests() {
	wp( [ 'eval', 'delete_option( "fast_index_qa_requests" ); delete_option( "fast_index_qa_urls" );' ] );
}

/**
 * Creates a post (or page) that is published without the post hooks, so nothing is queued. It gets a slug, so its
 * address is its own (a post without a slug would get the address of the day archive).
 *
 * @param {string} title     Title.
 * @param {string} type      Post type.
 * @param {string} [content] Content, stored as given (no kses).
 * @return {string} Post ID.
 */
function quietPost( title, type, content ) {
	return wp( [ 'eval', 'global $wpdb; $id = wp_insert_post( array( "post_title" => ' + JSON.stringify( title ) + ', "post_type" => "' + type + '", "post_status" => "draft" ) ); $wpdb->update( $wpdb->posts, array( "post_status" => "publish", "post_name" => sanitize_title( ' + JSON.stringify( title ) + ' ), "post_content" => ' + JSON.stringify( content || '' ) + ' ), array( "ID" => $id ) ); clean_post_cache( $id ); echo $id;' ] );
}

/**
 * Removes the History rows and posts of this run, the queue and the counters.
 *
 * @return {void}
 */
function cleanUp() {
	wp( [ 'eval', '$o = get_option( "fast_index_options" ); update_option( "fast_index_options", array_merge( (array) $o, array( "status" => "2" ) ) ); foreach ( get_posts( array( "post_type" => array( "post", "page" ), "post_status" => array( "any", "trash" ), "numberposts" => -1, "fields" => "ids", "s" => "E2E roadmap" ) ) as $id ) { wp_delete_post( $id, true ); } foreach ( array( "e2e-roadmap", "E2E roadmap" ) as $s ) { foreach ( get_posts( array( "post_type" => Fast_Index_Log::post_types(), "post_status" => "any", "numberposts" => -1, "fields" => "ids", "s" => $s ) ) as $id ) { wp_delete_post( $id, true ); } } foreach ( Fast_Index_Queue::items( 100000 ) as $i ) { if ( false !== strpos( $i["url"], "e2e-roadmap" ) ) { Fast_Index_Queue::take( $i["url"] ); } } wp_unschedule_hook( Fast_Index_Retry::HOOK ); delete_transient( Fast_Index_Accounts::QUOTA_TRANSIENT ); delete_transient( "fast_index_stats" ); delete_option( "fast_index_qa_requests" ); delete_option( "fast_index_qa_urls" ); delete_option( "fast_index_indexnow_key" ); delete_option( "fast_index_first_indexnow" ); delete_option( "fast_index_inspection_usage" ); delete_transient( "fast_index_sc_property" ); delete_user_meta( ' + USER_ID + ', "fast_index_dismissed_notices" ); delete_user_meta( ' + USER_ID + ', "edit_post_per_page" ); foreach ( array_keys( Fast_Index_Accounts::all() ) as $id ) { Fast_Index_Accounts::delete( $id ); }' ] );
	settings( { post_type: { post: '1' }, post_status: { publish: '1', edit: '1' } } );
}

/**
 * History rows whose text contains a string (title or address).
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string} text Text.
 * @return {import('@playwright/test').Locator} Rows.
 */
function rows( page, text ) {
	return page.locator( '#fi table.fi-history tbody tr', { hasText: text } );
}

test.describe( 'Fast Index 3.0.0', () => {
	test.beforeEach( () => {
		// Core CSS view transitions, aborted by Chromium's mobile emulation (not from the plugin).
		test.info().annotations.push( { type: 'allow-console', description: 'Transition was aborted because of invalid state' } );
	} );

	// The tests look for the slug of their posts in History and in the queue and fetch the IndexNow key file by its
	// address. Both need a pretty permalink structure, so a site that has none (the WordPress 5.6 install) gets one
	// for the run and goes back to its own at the end; the structure the site had is not part of what is tested.
	let plainStructure = false;

	test.beforeAll( () => {
		plainStructure = '' === wp( [ 'eval', 'echo get_option( "permalink_structure" );' ] );
		if ( plainStructure ) {
			wp( [ 'eval', 'global $wp_rewrite; $wp_rewrite->set_permalink_structure( "/%postname%/" ); flush_rewrite_rules( false );' ] );
		}
		cleanUp();
	} );

	test.afterAll( () => {
		cleanUp();
		if ( plainStructure ) {
			wp( [ 'eval', 'global $wp_rewrite; $wp_rewrite->set_permalink_structure( "" ); flush_rewrite_rules( false );' ] );
		}
	} );

	test( 'FI-R01: a 429 pauses every key until the quota resets, and History says the notification waits', async ( { page } ) => {
		settings( {} );
		storeKey();
		wp( [ 'eval', 'Fast_Index_Accounts::pause_for_quota();' ] );

		await page.goto( SETTINGS );
		await passOptIn( page, expect );
		await expect( page.locator( '.fast-index-accounts .fi-key-paused' ) ).toHaveCount( 1 );

		resetRequests();
		const id = quietPost( 'E2E roadmap quota ' + STAMP, 'post' );
		wp( [ 'eval', 'Fast_Index_Cron::queue_submission( get_permalink( ' + id + ' ), "URL_UPDATED", ' + id + ' );' ] );
		runQueue();
		expect( hits( 'indexing.googleapis.com', [ permalink( id ) ] ), 'no key is tried during the pause' ).toBe( 0 );

		await page.goto( HISTORY );
		const row = rows( page, 'E2E roadmap quota ' + STAMP );
		await expect( row.locator( '.fi-history-status--not-sent' ) ).toHaveCount( 1 );
		await expect( row.locator( '.fi-history-retry' ) ).toHaveCount( 1 );
		// The waiting notification is sent after the reset (Pacific Time midnight), not after six hours.
		const wait = Number( wp( [ 'eval', 'echo wp_next_scheduled( Fast_Index_Retry::HOOK ) - time();' ] ) );
		expect( wait ).toBeGreaterThan( 0 );
		expect( wait ).toBeLessThanOrEqual( 25 * 3600 );
		expect( Number( wp( [ 'eval', 'echo Fast_Index_Accounts::quota_pause_end() - time();' ] ) ) ).toBeGreaterThan( 0 );

		// A key uploaded during the pause does not end it.
		wp( [ 'eval', '$k = openssl_pkey_new( array( "private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA ) ); openssl_pkey_export( $k, $pem ); Fast_Index_Accounts::save( array( "client_email" => "e2e-roadmap-new@fast-index-e2e.iam.gserviceaccount.com", "private_key" => $pem, "private_key_id" => "e2eroadmapnew" ) );' ] );
		expect( wp( [ 'eval', 'echo ( count( Fast_Index_Accounts::all() ) > 0 ? "keys" : "none" ) . "/" . count( Fast_Index_Accounts::available() );' ] ) ).toBe( 'keys/0' );
		// The waiting notification of this test must not be sent later by another test.
		wp( [ 'eval', 'delete_transient( Fast_Index_Accounts::QUOTA_TRANSIENT ); wp_unschedule_hook( Fast_Index_Retry::HOOK ); foreach ( get_posts( array( "post_type" => Fast_Index_Log::POST_TYPE, "post_status" => "any", "numberposts" => -1, "fields" => "ids", "meta_key" => Fast_Index_Log::META_RETRY ) ) as $id ) { Fast_Index_Log::release( $id ); }' ] );
	} );

	test( 'FI-R02: a page is sent when it is published, updated, trashed or deleted', async ( { page } ) => {
		settings( {} );
		storeKey();
		resetRequests();

		const title = 'E2E roadmap page ' + STAMP;
		const id = wp( [ 'post', 'create', '--post_type=page', '--post_title=' + title, '--post_name=e2e-roadmap-page-' + STAMP, '--post_status=publish', '--porcelain' ] );
		const url = permalink( id );
		runQueue();
		wp( [ 'post', 'update', id, '--post_content=Changed ' + STAMP ] );
		runQueue();
		wp( [ 'post', 'delete', id ] );
		runQueue();

		const gone = 'e2e-roadmap-gone-' + STAMP;
		const goneId = wp( [ 'post', 'create', '--post_type=page', '--post_title=E2E roadmap gone ' + STAMP, '--post_name=' + gone, '--post_status=publish', '--porcelain' ] );
		const goneUrl = permalink( goneId );
		runQueue();
		wp( [ 'post', 'delete', goneId, '--force' ] );
		runQueue();

		// Publish, edit, trash of the first page; publish and delete of the second.
		expect( hits( 'indexing.googleapis.com', [ url ] ) ).toBe( 3 );
		expect( hits( 'indexing.googleapis.com', [ goneUrl ] ) ).toBe( 2 );
		expect( hits( 'api.indexnow.org', [ url, goneUrl ] ), 'IndexNow is off by default' ).toBe( 0 );

		await page.goto( HISTORY );
		await expect( rows( page, title ) ).toHaveCount( 3 );
		await expect( rows( page, 'E2E roadmap gone ' + STAMP ) ).toHaveCount( 1 );
		// The deleted page has no title any more: History shows its address.
		await expect( rows( page, gone ) ).toHaveCount( 1 );
		expect( wp( [ 'eval', 'echo count( get_posts( array( "post_type" => "fi_log", "post_status" => "any", "numberposts" => -1, "s" => "e2e-roadmap", "meta_key" => "_fast_index_type", "meta_value" => "URL_DELETED" ) ) );' ] ) ).toBe( '2' );
	} );

	test( 'FI-R03: IndexNow is off until it is turned on, and then answers its key file and gets one batch', async ( { page } ) => {
		settings( {} );
		storeKey();
		const home = wp( [ 'eval', 'echo home_url( "/" );' ] );

		// Off: no key file (any key), no request.
		const probe = await page.request.get( home + 'e2e0123456789abcdef0123456789ab.txt', { maxRedirects: 0 } );
		expect( probe.status() ).toBe( 404 );
		resetRequests();
		const off = quietPost( 'E2E roadmap off ' + STAMP, 'post' );
		wp( [ 'eval', 'Fast_Index_Cron::queue_submission( get_permalink( ' + off + ' ), "URL_UPDATED", ' + off + ' );' ] );
		runQueue();
		expect( hits( 'api.indexnow.org', [ permalink( off ) ] ) ).toBe( 0 );
		expect( requests()[ 'api.indexnow.org' ] || 0, 'no IndexNow request at all while it is off' ).toBe( 0 );
		expect( hits( 'indexing.googleapis.com', [ permalink( off ) ] ) ).toBe( 1 );

		// On: the settings screen shows the key file; it answers the key as plain text.
		await page.goto( SETTINGS );
		await page.check( '#fast-index-indexnow' );
		await page.click( '.settingsForm input[type="submit"]' );
		await expect( page.locator( '.notice-success:not(.fi-upgrade-notice)' ) ).toHaveCount( 1 );
		await expect( page.locator( '#fast-index-indexnow' ) ).toBeChecked();
		const keyLink = page.locator( '.fi-indexnow-key a' );
		await expect( keyLink ).toHaveCount( 1 );
		const keyUrl = await keyLink.getAttribute( 'href' );
		const key = wp( [ 'eval', 'echo Fast_Index_IndexNow::key( false );' ] );
		expect( key ).toMatch( /^[a-zA-Z0-9]{32}$/ );
		expect( keyUrl ).toBe( home + key + '.txt' );
		const file = await page.request.get( keyUrl, { maxRedirects: 0 } );
		expect( file.status() ).toBe( 200 );
		expect( ( await file.text() ).trim() ).toBe( key );
		expect( file.headers()[ 'content-type' ] ).toContain( 'text/plain' );
		expect( file.headers()[ 'x-robots-tag' ] ).toContain( 'noindex' );
		// SEC-FI-07: no content sniffing, short cache time.
		expect( file.headers()[ 'x-content-type-options' ] ).toBe( 'nosniff' );
		expect( file.headers()[ 'cache-control' ] ).toContain( 'max-age=300' );

		resetRequests();
		const on = quietPost( 'E2E roadmap on ' + STAMP, 'post' );
		wp( [ 'eval', 'Fast_Index_Cron::queue_submission( get_permalink( ' + on + ' ), "URL_UPDATED", ' + on + ' );' ] );
		runQueue();
		expect( hits( 'api.indexnow.org', [ permalink( on ) ] ) ).toBe( 1 );
		expect( hits( 'indexing.googleapis.com', [ permalink( on ) ] ) ).toBe( 1 );

		// History: a Channel column and a filter per channel.
		await page.goto( HISTORY );
		await expect( rows( page, 'E2E roadmap on ' + STAMP ) ).toHaveCount( 2 );
		await expect( page.locator( '#fi .fi-summary .cdo-stat' ) ).toHaveCount( 4 );
		await expect( page.locator( '.fi-channel-filter a' ) ).toHaveCount( 3 );
		await page.locator( '.fi-channel-filter a[href*="channel=indexnow"]' ).click();
		await expect( page ).toHaveURL( /channel=indexnow/ );
		await expect( rows( page, 'E2E roadmap on ' + STAMP ) ).toHaveCount( 1 );
		await expect( rows( page, 'E2E roadmap off ' + STAMP ) ).toHaveCount( 0 );
		await page.locator( '.fi-channel-filter a[href*="channel=google"]' ).click();
		await expect( rows( page, 'E2E roadmap off ' + STAMP ) ).toHaveCount( 1 );

		// Off again: the key file is gone.
		await page.goto( SETTINGS );
		await page.uncheck( '#fast-index-indexnow' );
		await page.click( '.settingsForm input[type="submit"]' );
		await expect( page.locator( '.notice-success:not(.fi-upgrade-notice)' ) ).toHaveCount( 1 );
		expect( ( await page.request.get( keyUrl, { maxRedirects: 0 } ) ).status() ).toBe( 404 );
	} );

	test( 'FI-R04: the row action and the bulk action send at once and answer on the same screen', async ( { page } ) => {
		settings( {} );
		storeKey();
		const a = quietPost( 'E2E roadmap hand A ' + STAMP, 'post' );
		const b = quietPost( 'E2E roadmap hand B ' + STAMP, 'post' );
		const draft = wp( [ 'post', 'create', '--post_title=E2E roadmap hand draft ' + STAMP, '--post_status=draft', '--porcelain' ] );
		const list = admin( 'edit.php?s=' + encodeURIComponent( 'E2E roadmap hand' ) + '&post_status=all' );
		const urls = [ permalink( a ), permalink( b ), permalink( draft ) ];
		resetRequests();

		await page.goto( list );
		// Row action: only on published posts.
		await expect( page.locator( '#post-' + draft + ' .row-actions .fast_index_notify' ) ).toHaveCount( 0 );
		const rowAction = page.locator( '#post-' + a + ' .row-actions .fast_index_notify a' );
		await expect( rowAction ).toHaveAttribute( 'href', /_wpnonce=/ );
		await page.locator( '#post-' + a ).hover();
		await rowAction.click();
		await expect( page ).toHaveURL( /edit\.php/ );
		await expect( page.locator( '.fast-index-manual-result' ) ).toHaveCount( 1 );
		await expect( page.locator( '.fast-index-manual-result.notice-success' ) ).toHaveCount( 1 );
		expect( hits( 'indexing.googleapis.com', urls ) ).toBe( 1 );

		// Bulk action: the published posts are sent, the draft is counted as not sent.
		await page.goto( list );
		for ( const id of [ a, b, draft ] ) {
			await page.locator( '#cb-select-' + id ).check();
		}
		// The bottom bulk action bar: WordPress hides the top one on phones.
		await page.selectOption( '#bulk-action-selector-bottom', 'fast_index_notify' );
		await page.click( '#doaction2' );
		const result = page.locator( '.fast-index-manual-result' );
		await expect( result ).toHaveCount( 1 );
		await expect( result ).toHaveClass( /notice-warning/ );
		await expect( result.locator( '.fi-upgrade-line' ) ).toHaveCount( 0 );
		expect( hits( 'indexing.googleapis.com', urls ) ).toBe( 3 );

		// The result is shown once.
		await page.reload();
		await expect( page.locator( '.fast-index-manual-result' ) ).toHaveCount( 0 );

		// A forged row action without a valid nonce is refused.
		const forged = await page.request.get( admin( 'admin-post.php?action=fast_index_notify_post&post=' + a + '&_wpnonce=0' ) );
		expect( forged.status() ).toBe( 403 );
		expect( hits( 'indexing.googleapis.com', urls ) ).toBe( 3 );
	} );

	test( 'FI-R04/R05: more than 100 selected: 100 are sent, and only the free version names the premium plan', async ( { page }, testInfo ) => {
		test.skip( 'mobile' === testInfo.project.name, 'Same server work as on the desktop run.' );
		test.setTimeout( 180000 );
		settings( { status: '2' } );
		storeKey();
		wp( [ 'eval', 'for ( $i = 0; $i < 101; $i++ ) { wp_insert_post( array( "post_title" => "E2E roadmap bulk ' + STAMP + ' " . $i, "post_status" => "publish" ) ); }' ] );
		settings( {} );
		wp( [ 'eval', 'foreach ( Fast_Index_Queue::items( 100000 ) as $i ) { if ( false !== strpos( $i["url"], "e2e-roadmap" ) ) { Fast_Index_Queue::take( $i["url"] ); } } update_user_meta( ' + USER_ID + ', "edit_post_per_page", 150 );' ] );
		resetRequests();

		await page.goto( admin( 'edit.php?s=' + encodeURIComponent( 'E2E roadmap bulk ' + STAMP ) ) );
		await expect( page.locator( '#the-list tr' ) ).toHaveCount( 101 );
		await page.locator( '#cb-select-all-1' ).check();
		// The bottom bulk action bar: WordPress hides the top one on phones.
		await page.selectOption( '#bulk-action-selector-bottom', 'fast_index_notify' );
		await page.click( '#doaction2' );
		const result = page.locator( '.fast-index-manual-result' );
		await expect( result ).toHaveCount( 1, { timeout: 60000 } );
		await expect( result.locator( '.fi-upgrade-line' ) ).toHaveCount( PREMIUM ? 0 : 1 );
		if ( ! PREMIUM ) {
			await expect( result.locator( '.fi-upgrade-line a' ) ).toHaveAttribute( 'href', /page=fast-index-pricing/ );
		}
		// Google within the time budget, the rest of the first 100 queued; the 101st is not sent.
		const slug = 'e2e-roadmap-bulk-' + STAMP;
		const queued = Number( wp( [ 'eval', '$n = 0; foreach ( Fast_Index_Queue::items( 100000 ) as $i ) { $n += false !== strpos( $i["url"], "' + slug + '" ) ? 1 : 0; } echo $n;' ] ) );
		expect( hits( 'indexing.googleapis.com', [ new RegExp( slug ) ] ) + queued ).toBe( 100 );

		wp( [ 'eval', 'foreach ( Fast_Index_Queue::items( 100000 ) as $i ) { if ( false !== strpos( $i["url"], "e2e-roadmap" ) ) { Fast_Index_Queue::take( $i["url"] ); } }' ] );
	} );

	test( 'FI-R05: no nudge outside the Fast Index screens; the History lines have real numbers and stay dismissed', async ( { page } ) => {
		settings( {} );
		wp( [ 'eval', 'delete_user_meta( ' + USER_ID + ', "fast_index_dismissed_notices" ); for ( $i = 0; $i < 12; $i++ ) { Fast_Index_Log::add( 0, home_url( "/e2e-roadmap-accepted-' + STAMP + '-" . $i . "/" ), 200, "URL_UPDATED" ); } delete_transient( "fast_index_stats" ); update_option( "fast_index_first_use", time() - 8 * DAY_IN_SECONDS, false ); Fast_Index_Upgrade::remember_success();' ] );

		const outside = [ admin( '' ), admin( 'edit.php' ), admin( 'edit.php?post_type=page' ), admin( 'options-general.php' ) ];
		if ( ! IS_MULTISITE ) {
			outside.push( admin( 'plugins.php' ) );
		}
		for ( const path of outside ) {
			await page.goto( path );
			await expect( page.locator( '.fi-upgrade-notice, .fi-upgrade-line, details.fi-premium-hint, .fi-premium-card, .fi-index-status-hint' ), path ).toHaveCount( 0 );
		}

		await page.goto( HISTORY );
		const accepted = page.locator( '.fi-upgrade-line[data-notice="history_accepted"]' );
		if ( PREMIUM ) {
			// Only the thank-you may show (it asks for a review); nothing names the premium plan.
			await expect( page.locator( '.fi-upgrade-line, .fi-upgrade-notice[data-notice="premium_offer"]' ) ).toHaveCount( 0 );
			await expect( page.locator( '#fi a[href*="page=fast-index-pricing"], .fi-upgrade-notice a[href*="page=fast-index-pricing"]' ) ).toHaveCount( 0 );
			return;
		}
		await expect( accepted ).toHaveCount( 1 );
		// The real number: at least the 12 addresses of this test, at most what History counts now (a background
		// run may accept more after the page was built).
		// (The sentence also names "30" days; the word order differs between languages.)
		const numbers = ( await accepted.locator( 'p' ).first().innerText() ).match( /\d(?:[\d.,\u00a0\u202f]*\d)?/g ).map( ( n ) => Number( n.replace( /\D/g, '' ) ) ).filter( ( n ) => 30 !== n );
		const shown = Math.max( ...numbers );
		expect( shown ).toBeGreaterThanOrEqual( 12 );
		expect( shown ).toBeLessThanOrEqual( Number( wp( [ 'eval', 'echo Fast_Index_Log::count_accepted_addresses( time() - 30 * DAY_IN_SECONDS );' ] ) ) );
		await expect( accepted.locator( 'a[href*="page=fast-index-pricing"]' ) ).toHaveCount( 1 );

		// "Do not show this again" stores the choice for this user.
		await accepted.locator( 'a[href*="action=fast_index_dismiss_notice"]' ).click();
		await page.goto( HISTORY );
		await expect( page.locator( '.fi-upgrade-line[data-notice="history_accepted"]' ) ).toHaveCount( 0 );
		await page.reload();
		await expect( page.locator( '.fi-upgrade-line[data-notice="history_accepted"]' ) ).toHaveCount( 0 );
		expect( wp( [ 'eval', 'echo isset( Fast_Index_Upgrade::dismissed( ' + USER_ID + ' )["history_accepted"] ) ? 1 : 0;' ] ) ).toBe( '1' );
	} );

	test( 'FI-R07: pages with JobPosting data are marked, and only they go to Google when the option is on', async ( { page } ) => {
		settings( { indexnow: true, eligible_only: true } );
		storeKey();
		const job = quietPost( 'E2E roadmap job ' + STAMP, 'post', '<script type="application/ld+json">{"@context":"https://schema.org","@type":"JobPosting","title":"QA"}</script><p>Job</p>' );
		const plain = quietPost( 'E2E roadmap plain ' + STAMP, 'post', '<p>Plain</p>' );
		resetRequests();
		for ( const id of [ job, plain ] ) {
			wp( [ 'eval', 'Fast_Index_Cron::queue_submission( get_permalink( ' + id + ' ), "URL_UPDATED", ' + id + ' );' ] );
		}
		runQueue();
		expect( hits( 'indexing.googleapis.com', [ permalink( job ) ] ) ).toBe( 1 );
		expect( hits( 'indexing.googleapis.com', [ permalink( plain ) ] ) ).toBe( 0 );
		expect( hits( 'api.indexnow.org', [ permalink( job ) ] ) ).toBe( 1 );
		expect( hits( 'api.indexnow.org', [ permalink( plain ) ] ) ).toBe( 1 );

		await page.goto( HISTORY + '&channel=google' );
		await expect( rows( page, 'E2E roadmap job ' + STAMP ) ).toHaveCount( 1 );
		await expect( rows( page, 'E2E roadmap job ' + STAMP ).locator( '.fi-eligible' ) ).toHaveCount( 1 );
		await expect( rows( page, 'E2E roadmap plain ' + STAMP ) ).toHaveCount( 0 );
		await page.goto( HISTORY + '&channel=indexnow' );
		await expect( rows( page, 'E2E roadmap plain ' + STAMP ) ).toHaveCount( 1 );

		await page.goto( SETTINGS );
		await expect( page.locator( '#fast-index-eligible-only' ) ).toBeChecked();
		settings( {} );
	} );

	test( 'FI-R06/R08: the premium module checks the index status and keeps its report and property settings', async ( { page }, testInfo ) => {
		test.skip( ! PREMIUM, 'Only with the premium module.' );
		settings( {} );
		storeKey();
		const id = quietPost( 'E2E roadmap inspect ' + STAMP, 'post' );
		wp( [ 'eval', 'Fast_Index_Log::add( ' + id + ', get_permalink( ' + id + ' ), 200, "URL_UPDATED" ); delete_transient( "fast_index_sc_property" );' ] );
		resetRequests();

		await page.goto( HISTORY );
		await expect( page.locator( 'th.column-fi-index' ) ).toHaveCount( 1 );
		await expect( page.locator( '.fi-index-card' ) ).toHaveCount( 1 );
		const row = rows( page, 'E2E roadmap inspect ' + STAMP );
		if ( 'mobile' === testInfo.project.name ) {
			// WordPress shows only the title on a phone; the toggle opens the other columns.
			await row.locator( '.toggle-row' ).click();
		}
		await row.locator( 'form.fi-index-check button' ).click();
		await expect( page ).toHaveURL( /page=fast-index-history/ );
		await expect( rows( page, 'E2E roadmap inspect ' + STAMP ).locator( '.fi-index-state--indexed' ) ).toHaveCount( 1 );
		expect( hits( 'searchconsole.googleapis.com', [ permalink( id ) ] ) ).toBe( 1 );
		expect( requests()[ 'www.googleapis.com' ], 'the property is found once' ).toBe( 1 );

		// Settings: a wrong property is refused; the report can be turned off and on.
		await page.goto( SETTINGS );
		await page.fill( '#fast-index-sc-property', 'not a property' );
		await page.click( '.settingsForm input[type="submit"]' );
		await expect( page.locator( '.notice-error' ) ).toHaveCount( 1 );
		await page.fill( '#fast-index-sc-property', 'sc-domain:example.com' );
		await page.uncheck( '#fast-index-weekly-report' );
		await page.click( '.settingsForm input[type="submit"]' );
		await expect( page.locator( '.notice-success:not(.fi-upgrade-notice)' ) ).toHaveCount( 1 );
		await expect( page.locator( '#fast-index-sc-property' ) ).toHaveValue( 'sc-domain:example.com' );
		await expect( page.locator( '#fast-index-weekly-report' ) ).not.toBeChecked();
		await page.fill( '#fast-index-sc-property', '' );
		await page.check( '#fast-index-weekly-report' );
		await page.click( '.settingsForm input[type="submit"]' );
		await expect( page.locator( '#fast-index-weekly-report' ) ).toBeChecked();
	} );
} );
