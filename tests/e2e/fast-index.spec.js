/**
 * Fast Index admin screens, end to end (local QA only).
 *
 * Run from /home/samet/wordpress:
 *   PLUGIN=fast-index tools/e2e/node_modules/.bin/playwright test -c tools/e2e/playwright.config.js
 * Google is never called: a local mu-plugin answers the Google endpoints during QA.
 *
 * The spec follows the copy under test: without the premium module (the WordPress.org package, or includes/premium
 * moved away) the premium screens are absent; with it (tests/e2e/qa-premium-license.php as a must-use plugin) they
 * are checked as well.
 */
const { test, expect, admin } = require( process.env.CDO_E2E_FIXTURES );

// Page views of these specs do not start WP-Cron (tests/e2e/qa-google-stub.php reads this header): the tests run the
// Fast Index events with WP-CLI, and a parallel WP-Cron run could write back an older copy of the cron list.
test.use( { extraHTTPHeaders: { 'X-Fast-Index-QA': '1' } } );
const { wp, serviceAccountFile, premiumLoaded, passOptIn } = require( './helpers' );

const PREMIUM = premiumLoaded();

const SETTINGS = admin( 'admin.php?page=fast-index' );
const HISTORY = admin( 'admin.php?page=fast-index-history' );
const RUN = admin( 'admin.php?page=fast-index-run' );

test.describe( 'Fast Index admin', () => {
	test.beforeEach( () => {
		// WordPress 7.x admin pages use cross-document view transitions; Chromium's mobile emulation
		// aborts some of them during navigation. This comes from core CSS, not from the plugin.
		test.info().annotations.push( { type: 'allow-console', description: 'Transition was aborted because of invalid state' } );
	} );

	test( 'every plugin screen opens', async ( { page } ) => {
		// The first visit of a site may show the Freemius opt-in screen: it has Skip and no license field.
		await page.goto( SETTINGS );
		await passOptIn( page, expect );

		// Language-neutral checks: the sites run in English, Turkish and Arabic (wording lives in i18n.spec.js).
		const screens = [
			[ SETTINGS, '#fast-index-status' ],
			[ HISTORY, '#fi table.fi-history' ],
		];
		if ( PREMIUM ) {
			screens.push( [ RUN, '#fi input[name="action"][value="fast_index_run_backlog"]' ] );
		}
		for ( const [ path, marker ] of screens ) {
			const res = await page.goto( path );
			expect( res.status(), path ).toBe( 200 );
			await expect( page.locator( '#fi h1.cdo-band__title' ) ).toBeVisible();
			await expect( page.locator( marker ) ).toHaveCount( 1 );
		}
	} );

	test( 'settings are saved, confirmed and kept after reload', async ( { page } ) => {
		wp( [ 'option', 'update', 'fast_index_options', '{"status":"1","post_type":{"post":"1"},"post_status":{"publish":"1"}}', '--format=json' ] );

		await page.goto( SETTINGS );
		await page.selectOption( '#fast-index-status', '2' );
		await page.check( '#fast-index-delete-data' );
		await page.click( '.settingsForm input[type="submit"]' );

		await expect( page.locator( '.notice-success:not(.fi-upgrade-notice)' ) ).toHaveCount( 1 );

		await page.reload();
		await expect( page.locator( '#fast-index-status' ) ).toHaveValue( '2' );
		await expect( page.locator( '#fast-index-delete-data' ) ).toBeChecked();
		await expect( page.locator( '.notice-success:not(.fi-upgrade-notice)' ) ).toHaveCount( 0 );

		await page.selectOption( '#fast-index-status', '1' );
		await page.uncheck( '#fast-index-delete-data' );
		await page.click( '.settingsForm input[type="submit"]' );
		await expect( page.locator( '.notice-success:not(.fi-upgrade-notice)' ) ).toHaveCount( 1 );
		await expect( page.locator( '#fast-index-status' ) ).toHaveValue( '1' );
	} );

	test( 'an invalid value is rejected with an error', async ( { page } ) => {
		await page.goto( SETTINGS );
		await page.selectOption( '#fast-index-status', '2' );
		await page.uncheck( 'input[name="fast_index_options[post_type][post]"]' );
		await page.click( '.settingsForm input[type="submit"]' );

		await expect( page.locator( '.notice-error' ) ).toHaveCount( 1 );
		// Nothing was saved.
		await expect( page.locator( '#fast-index-status' ) ).toHaveValue( '1' );
		await expect( page.locator( 'input[name="fast_index_options[post_type][post]"]' ) ).toBeChecked();

		await page.setInputFiles( '#fast-index-key-files', {
			name: 'not-a-key.json',
			mimeType: 'application/json',
			buffer: Buffer.from( '{"type":"authorized_user"}' ),
		} );
		await page.click( 'form[enctype="multipart/form-data"] input[type="submit"]' );
		await expect( page.locator( '.notice-error' ) ).toHaveCount( 1 );
		await expect( page.locator( '.notice-error' ) ).toContainText( 'not-a-key.json' );
	} );

	test( 'key upload shows only the client email and the key ID', async ( { page } ) => {
		const email = 'e2e-indexer@fast-index-e2e.iam.gserviceaccount.com';
		const keyId = 'e2ekey0123456789abcdef0123456789abcdef01';
		const { privateKey, buffer } = serviceAccountFile( email, keyId );

		await page.goto( SETTINGS );
		await page.setInputFiles( '#fast-index-key-files', { name: 'service-account.json', mimeType: 'application/json', buffer } );
		await page.click( 'form[enctype="multipart/form-data"] input[type="submit"]' );

		await expect( page.locator( '.notice-success:not(.fi-upgrade-notice)' ) ).toContainText( email );
		await expect( page.locator( '.fast-index-accounts' ) ).toContainText( email );
		await expect( page.locator( '.fast-index-accounts' ) ).toContainText( keyId );

		const html = await page.content();
		const keyBody = privateKey.replace( /-----[A-Z ]+-----|\s/g, '' );
		expect( html ).not.toContain( 'PRIVATE KEY' );
		expect( html ).not.toContain( keyBody.slice( 20, 80 ) );

		// The key went to the database, not to the uploads folder.
		expect( wp( [ 'eval', 'echo count( glob( wp_upload_dir()["basedir"] . "/*/*/service-account*.json" ) ?: array() );' ] ) ).toBe( '0' );
	} );

	test( 'the settings screen has no locked or disabled control', async ( { page } ) => {
		await page.goto( SETTINGS );
		await expect( page.locator( '#fi input[disabled], #fi select[disabled], #fi textarea[disabled], #fi input[readonly]' ) ).toHaveCount( 0 );
		await expect( page.locator( '.licenceAlert, .fi-locked' ) ).toHaveCount( 0 );
		// The premium options exist only with the premium module; they are never shown as locked fields.
		await expect( page.locator( '#fast-index-old-posts' ) ).toHaveCount( PREMIUM ? 1 : 0 );
		if ( ! PREMIUM ) {
			await expect( page.locator( 'input[name^="fast_index_options[exclude_category]"]' ) ).toHaveCount( 0 );
		}
		// Since 3.0.0 the free version sends every event and post type itself; the premium options are named with a
		// Premium badge in the free version, never as fields.
		await expect( page.locator( 'input[name^="fast_index_options[post_status]"]' ) ).toHaveCount( 3 );
		expect( await page.locator( 'input[name^="fast_index_options[post_type]"]' ).count() ).toBeGreaterThanOrEqual( 2 );
		await expect( page.locator( 'tr.fi-premium-row' ) ).toHaveCount( PREMIUM ? 0 : 2 );
		await expect( page.locator( 'tr.fi-premium-row input, tr.fi-premium-row select' ) ).toHaveCount( 0 );
		await expect( page.locator( '#fast-index-indexnow' ) ).not.toBeChecked();
		await expect( page.locator( '#fast-index-eligible-only' ) ).not.toBeChecked();
		await expect( page.locator( '#fast-index-sc-property, #fast-index-weekly-report' ) ).toHaveCount( PREMIUM ? 2 : 0 );
	} );

	test( 'history pagination links work', async ( { page } ) => {
		wp( [ 'eval', 'for ( $i = 0; $i < 25; $i++ ) { Fast_Index_Log::add( 0, home_url( "/e2e-history-" . $i . "/" ), 0 === $i % 5 ? 429 : 200, "URL_UPDATED" ); }' ] );

		await page.goto( HISTORY );
		await expect( page.locator( '#fi table.fi-history tbody tr' ) ).toHaveCount( 20 );
		await expect( page.locator( '#fi' ) ).toContainText( '429 Too Many Requests' );

		await page.click( '.pagination a[href*="pn=1"]' );
		await expect( page ).toHaveURL( /pn=1/ );
		await expect( page.locator( '#fi table.fi-history tbody tr' ).first() ).toBeVisible();
		await expect( page.locator( '.pagination a[href*="pn=0"]' ) ).toBeVisible();

		await page.click( '.pagination a[href*="pn=0"]' );
		await expect( page ).toHaveURL( /pn=0/ );
		await expect( page.locator( '.pagination a[href*="pn=1"]' ) ).toBeVisible();
	} );

	test( 'a notification that no key can take is shown as not sent, and waits only for a resting key', async ( { page } ) => {
		// Labels in the language of the site under test (English, Turkish or Arabic).
		const label = ( constant ) => wp( [ 'eval', 'echo Fast_Index_Admin::status_label( Fast_Index_Submitter::' + constant + ' );' ] );
		const note = wp( [ 'eval', 'echo Fast_Index_Admin::retry_note();' ] );
		// Runs the queued notification of a post at once, as WP-Cron would.
		const runQueued = ( id ) => wp( [ 'eval', '$u = get_permalink( ' + id + ' ); Fast_Index_Queue::take( $u ); Fast_Index_Notifier::deliver( $u, "URL_UPDATED", ' + id + ' );' ] );
		const row = ( title ) => page.locator( '#fi table.fi-history tbody tr', { hasText: title } );
		const stamp = Date.now();

		wp( [ 'option', 'update', 'fast_index_options', '{"status":"1","post_type":{"post":"1"},"post_status":{"publish":"1"}}', '--format=json' ] );
		// Start from an empty queue: a run that stopped half-way may have left a waiting notification.
		wp( [ 'eval', 'foreach ( array_keys( Fast_Index_Accounts::all() ) as $id ) { Fast_Index_Accounts::delete( $id ); } foreach ( Fast_Index_Log::held( 1000 ) as $held ) { Fast_Index_Log::release( $held["id"] ); } wp_unschedule_hook( Fast_Index_Retry::HOOK );' ] );

		// No key: History says so, and nothing waits.
		const noKey = 'E2E no key ' + stamp;
		const noKeyId = wp( [ 'post', 'create', '--post_title=' + noKey, '--post_status=publish', '--porcelain' ] );
		runQueued( noKeyId );
		await page.goto( HISTORY );
		await expect( row( noKey ) ).toHaveCount( 1 );
		await expect( row( noKey ).locator( '.fi-history-status--not-sent' ) ).toContainText( label( 'NO_ACCOUNT' ) );
		await expect( row( noKey ).locator( '.fi-history-retry' ) ).toHaveCount( 0 );
		expect( wp( [ 'eval', 'echo Fast_Index_Log::count_held();' ] ) ).toBe( '0' );

		// Every key rests: History says so, and the notification waits for the end of the rest.
		wp( [ 'eval', '$k = openssl_pkey_new( array( "private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA ) ); openssl_pkey_export( $k, $pem ); $id = Fast_Index_Accounts::save( array( "client_email" => "e2e-resting@fast-index-e2e.iam.gserviceaccount.com", "private_key" => $pem, "private_key_id" => "e2eresting" ) ); Fast_Index_Accounts::set_status( $id, 429 );' ] );
		const resting = 'E2E resting ' + stamp;
		const restingId = wp( [ 'post', 'create', '--post_title=' + resting, '--post_status=publish', '--porcelain' ] );
		runQueued( restingId );
		// Read the plan before a page view: a WP-Cron run started by another page view can write back an older copy
		// of the cron list (a WordPress race the tests should not depend on).
		const planned = Number( wp( [ 'eval', 'echo wp_next_scheduled( Fast_Index_Retry::HOOK ) - time();' ] ) );
		await page.goto( HISTORY );
		await expect( row( resting ).locator( '.fi-history-status--not-sent' ) ).toContainText( label( 'RESTING' ) );
		await expect( row( resting ).locator( '.fi-history-retry' ) ).toHaveText( note );
		expect( wp( [ 'eval', 'echo Fast_Index_Log::count_held();' ] ) ).toBe( '1' );
		expect( planned ).toBeGreaterThan( 5 * 3600 );

		// A key upload sends the waiting notification with the next scheduled-task run.
		const { buffer } = serviceAccountFile( 'e2e-fresh@fast-index-e2e.iam.gserviceaccount.com', 'e2efresh0123456789abcdef0123456789abcdef' );
		await page.goto( SETTINGS );
		await page.setInputFiles( '#fast-index-key-files', { name: 'fresh.json', mimeType: 'application/json', buffer } );
		await page.click( 'form[enctype="multipart/form-data"] input[type="submit"]' );
		// The new key in the list: the upload request has finished (a success notice alone may be an older one).
		await expect( page.locator( '.fast-index-accounts' ) ).toContainText( 'e2e-fresh@fast-index-e2e.iam.gserviceaccount.com' );
		await expect( page.locator( '.notice-success:not(.fi-upgrade-notice)' ) ).toHaveCount( 1 );
		expect( Number( wp( [ 'eval', 'echo wp_next_scheduled( Fast_Index_Retry::HOOK ) - time();' ] ) ) ).toBeLessThanOrEqual( 0 );

		wp( [ 'eval', 'wp_unschedule_hook( Fast_Index_Retry::HOOK ); do_action( Fast_Index_Retry::HOOK );' ] );
		await page.goto( HISTORY );
		await expect( row( resting ) ).toHaveCount( 2 );
		await expect( row( resting ).first() ).toContainText( '200' );
		await expect( row( resting ).locator( '.fi-history-retry' ) ).toHaveCount( 0 );
		expect( wp( [ 'eval', 'echo Fast_Index_Log::count_held();' ] ) ).toBe( '0' );

		// The two posts go (while Fast Index is Passive, so nothing is queued), with their History rows.
		wp( [ 'eval', '$o = get_option( "fast_index_options" ); update_option( "fast_index_options", array_merge( (array) $o, array( "status" => "2" ) ) ); foreach ( array( ' + noKeyId + ', ' + restingId + ' ) as $id ) { wp_delete_post( $id, true ); } foreach ( get_posts( array( "post_type" => Fast_Index_Log::post_types(), "post_status" => "any", "numberposts" => -1, "fields" => "ids", "s" => "' + stamp + '" ) ) as $id ) { wp_delete_post( $id, true ); } update_option( "fast_index_options", $o ); delete_transient( "fast_index_stats" );' ] );
	} );

	test( 'manual run answers without errors', async ( { page } ) => {
		test.skip( ! PREMIUM, 'Send Old Posts Now is part of the premium module.' );
		await page.goto( RUN );
		await page.click( '#fi input[type="submit"]' );
		await expect( page ).toHaveURL( /page=fast-index-run/ );
		await expect( page.locator( '#fi table' ) ).toBeVisible();
	} );

	test( 'migration notice can be dismissed and stays dismissed', async ( { page } ) => {
		wp( [ 'eval', 'Fast_Index_Notices::flag_key_rotation();' ] );

		for ( const path of [ admin( '' ), SETTINGS ] ) {
			await page.goto( path );
			const notice = page.locator( '.fast-index-notice:not(.fi-upgrade-notice)' );
			await expect( notice ).toHaveCount( 1 );
			await expect( notice ).toContainText( 'Fast Index' );
			await expect( notice.locator( 'a[href*="action=fast_index_dismiss_notice"]' ) ).toBeVisible();
			await expect( notice.locator( '.notice-dismiss' ) ).toBeVisible();
		}

		// Close button on the dashboard: the dismissal is stored with the nonce-protected request.
		await page.goto( admin( '' ) );
		const stored = page.waitForResponse( ( res ) => res.url().includes( 'action=fast_index_dismiss_notice' ) );
		await page.locator( '.fast-index-notice:not(.fi-upgrade-notice) .notice-dismiss' ).click();
		await stored;

		await page.reload();
		await expect( page.locator( '.fast-index-notice:not(.fi-upgrade-notice)' ) ).toHaveCount( 0 );
		await page.goto( SETTINGS );
		await expect( page.locator( '.fast-index-notice:not(.fi-upgrade-notice)' ) ).toHaveCount( 0 );

		// The link works without JavaScript too.
		wp( [ 'eval', 'Fast_Index_Notices::flag_key_rotation();' ] );
		await page.goto( SETTINGS );
		await page.locator( '.fast-index-notice:not(.fi-upgrade-notice) a[href*="action=fast_index_dismiss_notice"]' ).click();
		await page.goto( admin( '' ) );
		await expect( page.locator( '.fast-index-notice:not(.fi-upgrade-notice)' ) ).toHaveCount( 0 );
		expect( wp( [ 'eval', 'var_export( get_option( "fast_index_notice_key_rotation" ) );' ] ) ).toBe( 'false' );
	} );
} );
