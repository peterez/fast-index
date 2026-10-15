/**
 * Where Fast Index points to the premium plan, and what the WordPress.org package never shows (local QA only).
 *
 * Run from /home/samet/wordpress (see fast-index.spec.js). The checks follow the copy under test:
 *  - without the premium module (the WordPress.org package, or includes/premium moved away): no license screen, no
 *    locked control, the Premium box with its comparison, the Premium hints, the Upgrade page without an iframe,
 *    the Plugins screen links, the History line, the one-time notices and the message when a second key replaces
 *    the first;
 *  - with the module (tests/e2e/qa-premium-license.php as a must-use plugin): none of the offers.
 * Checks are language-neutral (the sites run in English, Turkish and Arabic): element classes, links and numbers.
 */
const { test, expect, admin } = require( process.env.CDO_E2E_FIXTURES );

// Page views of these specs do not start WP-Cron (tests/e2e/qa-google-stub.php reads this header): the tests run the
// Fast Index events with WP-CLI, and a parallel WP-Cron run could write back an older copy of the cron list.
test.use( { extraHTTPHeaders: { 'X-Fast-Index-QA': '1' } } );
const { wp, serviceAccountFile, premiumLoaded, passOptIn, IS_MULTISITE } = require( './helpers' );

const PREMIUM = premiumLoaded();
const SETTINGS = admin( 'admin.php?page=fast-index' );
const HISTORY = admin( 'admin.php?page=fast-index-history' );
const PLUGINS = admin( 'plugins.php' );
const PRICING = /page=fast-index-pricing/;
const USER_ID = wp( [ 'user', 'get', process.env.WP_USER || 'admin', '--field=ID' ] );

/**
 * Starts the notices from a clean state: nothing dismissed, no accepted notification, first setup now.
 *
 * @return {void}
 */
function resetNotices() {
	wp( [ 'eval', 'delete_user_meta( ' + USER_ID + ', "fast_index_dismissed_notices" ); delete_option( "fast_index_first_success" ); update_option( "fast_index_first_use", time(), false );' ] );
}

/**
 * Removes every stored service account key of the site under test.
 *
 * @return {void}
 */
function deleteKeys() {
	wp( [ 'eval', 'foreach ( array_keys( Fast_Index_Accounts::all() ) as $id ) { Fast_Index_Accounts::delete( $id ); }' ] );
}

test.describe( 'Fast Index premium plan', () => {
	test.beforeEach( () => {
		// Core CSS view transitions, aborted by Chromium's mobile emulation (not from the plugin).
		test.info().annotations.push( { type: 'allow-console', description: 'Transition was aborted because of invalid state' } );
	} );

	test.afterAll( () => {
		resetNotices();
	} );

	test( 'the free version has no license screen, no lock and one Premium box with a comparison', async ( { page } ) => {
		test.skip( PREMIUM, 'The premium module is loaded.' );
		resetNotices();

		await page.goto( SETTINGS );
		await passOptIn( page, expect );
		await expect( page.locator( 'form.settingsForm' ) ).toHaveCount( 1 );
		await expect( page.locator( 'input#fs_license_key, input.fs-license-key' ) ).toHaveCount( 0 );
		await expect( page.locator( '#fi input[disabled], #fi select[disabled], #fi input[readonly]' ) ).toHaveCount( 0 );

		const card = page.locator( '.fi-premium-card' );
		await expect( card ).toHaveCount( 1 );
		expect( await card.locator( '.fi-compare tbody tr' ).count() ).toBeGreaterThanOrEqual( 7 );
		await expect( card.locator( '.fi-compare .fi-no' ).first() ).toBeVisible();
		await expect( card.locator( 'a.button' ) ).toHaveAttribute( 'href', PRICING );
		// No discount and no deadline. The comparison table may name hours ("checks 24 and 72 hours after sending").
		const offer = await card.evaluate( ( el ) => {
			const copy = el.cloneNode( true );
			copy.querySelectorAll( '.fi-compare' ).forEach( ( table ) => table.remove() );
			return copy.textContent;
		} );
		expect( offer, 'no discount or deadline' ).not.toMatch( /\d+\s?%|\d+\s?(day|hour)s?\b/i );
		expect( await card.locator( '.fi-compare' ).innerText(), 'no discount' ).not.toMatch( /\d+\s?%/ );

		// The hints are a badge that opens a short text with the upgrade link; not a control of a setting: the old
		// posts and the excluded categories (named in their own rows, without a field) and the key upload.
		const hints = page.locator( 'details.fi-premium-hint' );
		await expect( hints ).toHaveCount( 3 );
		await expect( page.locator( 'tr.fi-premium-row details.fi-premium-hint' ) ).toHaveCount( 2 );
		const first = hints.first();
		await expect( first.locator( 'p' ) ).toBeHidden();
		await first.locator( 'summary' ).click();
		await expect( first.locator( 'p' ) ).toBeVisible();
		await expect( first.locator( 'p a' ) ).toHaveAttribute( 'href', PRICING );

		// History: one badge next to the heading (index status), not an empty column.
		await page.goto( HISTORY );
		await expect( page.locator( '.fi-index-status-hint details.fi-premium-hint' ) ).toHaveCount( 1 );
		await expect( page.locator( 'th.column-fi-index' ) ).toHaveCount( 0 );
	} );

	test( 'the Upgrade page is part of the plugin, without an iframe', async ( { page } ) => {
		test.skip( PREMIUM, 'The premium module is loaded.' );
		const external = [];
		page.on( 'request', ( request ) => {
			if ( /freemius\.com/.test( new URL( request.url() ).host ) ) {
				external.push( request.url() );
			}
		} );

		await page.goto( SETTINGS );
		// On a network activation the plans page is in the network admin; the link says where.
		const href = await page.locator( '.fi-premium-card a.button' ).getAttribute( 'href' );
		const res = await page.goto( href );
		expect( res.status() ).toBe( 200 );
		await expect( page.locator( '#fs_pricing_wrapper' ) ).toHaveCount( 1 );
		await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
		await page.waitForLoadState( 'networkidle' );
		expect( external, 'the browser loads nothing from Freemius' ).toEqual( [] );

		// The menu entry leads to the same page (single site; after a network activation it is in the network admin).
		if ( ! IS_MULTISITE ) {
			await expect( page.locator( '#adminmenu a[href*="page=fast-index-pricing"]' ) ).toHaveCount( 1 );
		}
	} );

	test( 'the Plugins screen has Settings and one Upgrade link', async ( { page } ) => {
		const url = IS_MULTISITE ? wp( [ 'eval', 'echo network_admin_url( "plugins.php" );' ] ) : PLUGINS;
		await page.goto( url );
		const row = page.locator( 'tr[data-plugin="fast-index/index.php"]' ).first();
		await expect( row ).toBeVisible();
		const upgrade = row.locator( '.row-actions a[href*="page=fast-index-pricing"]' );
		if ( PREMIUM && IS_MULTISITE ) {
			// The network screen shows the links of Freemius itself. The QA stub loads the module without a license,
			// so Freemius still offers Upgrade and Activate License there; a real license removes Upgrade.
			await expect( row.locator( '.row-actions span.activate-license' ) ).toHaveCount( 1 );
			await expect( row.locator( '.row-actions span.upgrade' ) ).toHaveCount( 1 );
			return;
		}
		// One Upgrade entry in the row, whatever Freemius adds next to it (Opt In, Activate License).
		await expect( row.locator( '.row-actions span.upgrade' ) ).toHaveCount( PREMIUM ? 0 : 1 );

		if ( PREMIUM ) {
			await expect( upgrade ).toHaveCount( 0 );
			return;
		}

		await expect( upgrade ).toHaveCount( 1 );
		if ( ! IS_MULTISITE ) {
			// Network admin has no Fast Index screen, so the Settings link is on the site's Plugins screen only.
			await expect( row.locator( '.row-actions span.settings a' ) ).toHaveAttribute( 'href', /page=fast-index$/ );
		}
	} );

	test( 'History says how many published posts Google has not accepted yet', async ( { page } ) => {
		test.skip( PREMIUM, 'The premium module sends old posts itself.' );
		const postId = wp( [ 'post', 'create', '--post_title=E2E waiting ' + Date.now(), '--post_status=draft', '--porcelain' ] );
		// Published without the post hooks, so nothing is queued and the post stays waiting.
		wp( [ 'eval', 'global $wpdb; $wpdb->update( $wpdb->posts, array( "post_status" => "publish" ), array( "ID" => ' + postId + ' ) ); clean_post_cache( ' + postId + ' ); delete_transient( "fast_index_stats" );' ] );

		await page.goto( HISTORY );
		const waiting = ( await page.locator( '#fi .fi-summary .cdo-stat__value' ).nth( 1 ).innerText() ).trim();
		expect( Number( waiting.replace( /\D/g, '' ) ) ).toBeGreaterThan( 0 );
		const line = page.locator( '.fi-upgrade-line[data-notice="history_waiting"]' );
		await expect( line ).toBeVisible();
		await expect( line ).toContainText( waiting );
		await expect( line.locator( 'a[href*="page=fast-index-pricing"]' ) ).toHaveCount( 1 );

		wp( [ 'post', 'delete', postId, '--force' ] );
		wp( [ 'eval', 'delete_transient( "fast_index_stats" );' ] );
	} );

	test( 'History rows open on a phone', async ( { page }, testInfo ) => {
		wp( [ 'eval', 'Fast_Index_Log::add( 0, home_url( "/e2e-phone-row/" ), 200, "URL_UPDATED" );' ] );

		await page.goto( HISTORY );
		const row = page.locator( '#fi table.fi-history tbody tr' ).first();
		const status = row.locator( 'td.column-fi-status' );
		const date = row.locator( 'td.column-date' );

		if ( 'mobile' === testInfo.project.name ) {
			await expect( status ).toBeHidden();
			await expect( row.locator( '.toggle-row' ) ).toBeVisible();
			await row.locator( '.toggle-row' ).click();
			await expect( status ).toBeVisible();
			await expect( date ).toBeVisible();
			await expect( row.locator( 'td.column-fi-post a' ) ).toBeVisible();
			const overflow = await page.evaluate( () => document.documentElement.scrollWidth > document.documentElement.clientWidth );
			expect( overflow ).toBe( false );
		} else {
			await expect( status ).toBeVisible();
			await expect( row.locator( '.toggle-row' ) ).toBeHidden();
		}

		wp( [ 'eval', 'foreach ( get_posts( array( "post_type" => "fi_log", "post_status" => "any", "numberposts" => -1, "fields" => "ids", "s" => "e2e-phone-row" ) ) as $id ) { wp_delete_post( $id, true ); } delete_transient( "fast_index_stats" );' ] );
	} );

	test( 'the offer comes a week after the first setup, only on Fast Index screens, and stays dismissed', async ( { page } ) => {
		test.skip( PREMIUM, 'Nothing is offered while the premium module is loaded.' );
		resetNotices();
		const offer = page.locator( '.fi-upgrade-notice[data-notice="premium_offer"]' );

		// Six days: not yet.
		wp( [ 'eval', 'update_option( "fast_index_first_use", time() - 6 * DAY_IN_SECONDS, false );' ] );
		await page.goto( SETTINGS );
		await expect( offer ).toHaveCount( 0 );

		wp( [ 'eval', 'update_option( "fast_index_first_use", time() - 8 * DAY_IN_SECONDS, false );' ] );
		await page.goto( SETTINGS );
		await expect( offer ).toHaveCount( 1 );
		await expect( offer.locator( 'a.button' ) ).toHaveAttribute( 'href', PRICING );

		// Never on the dashboard or the Plugins screen.
		for ( const path of [ admin( '' ), PLUGINS ] ) {
			await page.goto( path );
			await expect( page.locator( '.fi-upgrade-notice' ), path ).toHaveCount( 0 );
		}

		// The close button stores the dismissal for this user; it never comes back.
		await page.goto( HISTORY );
		await expect( offer ).toHaveCount( 1 );
		const stored = page.waitForResponse( ( res ) => res.url().includes( 'action=fast_index_dismiss_notice' ) );
		await offer.locator( '.notice-dismiss' ).click();
		await stored;
		for ( const path of [ HISTORY, SETTINGS ] ) {
			await page.goto( path );
			await expect( page.locator( '.fi-upgrade-notice' ), path ).toHaveCount( 0 );
		}
		expect( wp( [ 'eval', 'echo implode( ",", array_keys( Fast_Index_Upgrade::dismissed( ' + USER_ID + ' ) ) );' ] ) ).toBe( 'premium_offer' );
	} );

	test( 'the thank-you comes after Google accepted a notification, once', async ( { page } ) => {
		resetNotices();
		wp( [ 'eval', 'Fast_Index_Upgrade::remember_success();' ] );
		const thanks = page.locator( '.fi-upgrade-notice[data-notice="first_success"]' );

		await page.goto( admin( '' ) );
		await expect( page.locator( '.fi-upgrade-notice' ) ).toHaveCount( 0 );

		await page.goto( SETTINGS );
		await expect( thanks ).toHaveCount( 1 );
		const review = thanks.locator( 'a[href="https://wordpress.org/support/plugin/fast-index/reviews/"]' );
		await expect( review ).toHaveAttribute( 'target', '_blank' );
		await expect( thanks.locator( 'a[href*="page=fast-index-pricing"]' ) ).toHaveCount( PREMIUM ? 0 : 1 );

		// The link works without JavaScript.
		await thanks.locator( 'a[href*="action=fast_index_dismiss_notice"]' ).click();
		await page.goto( SETTINGS );
		await expect( page.locator( '.fi-upgrade-notice' ) ).toHaveCount( 0 );
		await page.goto( HISTORY );
		await expect( page.locator( '.fi-upgrade-notice' ) ).toHaveCount( 0 );
	} );

	test( 'a key of another account replaces the only key, and the message says so', async ( { page } ) => {
		test.skip( PREMIUM, 'The premium module keeps several keys.' );
		deleteKeys();
		const first = 'e2e-first@fast-index-e2e.iam.gserviceaccount.com';
		const second = 'e2e-second@fast-index-e2e.iam.gserviceaccount.com';

		await page.goto( SETTINGS );
		await page.setInputFiles( '#fast-index-key-files', { name: 'first.json', mimeType: 'application/json', buffer: serviceAccountFile( first, 'e2efirst0123456789abcdef0123456789abcdef' ).buffer } );
		await page.click( 'form[enctype="multipart/form-data"] input[type="submit"]' );
		await expect( page.locator( '.notice-success:not(.fi-upgrade-notice)' ) ).toContainText( first );
		await expect( page.locator( '.notice-success:not(.fi-upgrade-notice) a[href*="page=fast-index-pricing"]' ) ).toHaveCount( 0 );

		await page.setInputFiles( '#fast-index-key-files', { name: 'second.json', mimeType: 'application/json', buffer: serviceAccountFile( second, 'e2esecond123456789abcdef0123456789abcdef' ).buffer } );
		await page.click( 'form[enctype="multipart/form-data"] input[type="submit"]' );
		const message = page.locator( '.notice-success:not(.fi-upgrade-notice)' );
		await expect( message ).toContainText( second );
		await expect( message ).toContainText( first );
		await expect( message.locator( 'a' ) ).toHaveAttribute( 'href', PRICING );
		await expect( page.locator( '.fast-index-accounts' ) ).toContainText( second );
		await expect( page.locator( '.fast-index-accounts' ) ).not.toContainText( first );
		await expect( page.locator( '#fast-index-key-files' ) ).not.toHaveAttribute( 'multiple', /.*/ );

		deleteKeys();
	} );

	test( 'the premium package shows its options and offers nothing', async ( { page } ) => {
		test.skip( ! PREMIUM, 'Only with the premium module.' );

		await page.goto( SETTINGS );
		await expect( page.locator( '#fast-index-old-posts' ) ).toHaveCount( 1 );
		await expect( page.locator( '#fast-index-key-files' ) ).toHaveAttribute( 'multiple', /.*/ );
		await expect( page.locator( '.fi-premium-card, details.fi-premium-hint' ) ).toHaveCount( 0 );
		await expect( page.locator( '#adminmenu a[href*="page=fast-index-run"]' ) ).toHaveCount( 1 );

		await page.goto( HISTORY );
		await expect( page.locator( '.fi-upgrade-line, .fi-index-status-hint, details.fi-premium-hint' ) ).toHaveCount( 0 );
		await expect( page.locator( 'th.column-fi-index' ) ).toHaveCount( 1 );
	} );
} );
