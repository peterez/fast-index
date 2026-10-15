/**
 * Fast Index Help & FAQ screen, help tabs and the settings page, end to end (local QA only).
 *
 * Run from /home/samet/wordpress (see fast-index.spec.js). Checks are language-neutral: the sites run in
 * English, Turkish and Arabic (right to left), so they use element IDs and attributes, not wording.
 * Google is never called: tests/e2e/qa-google-stub.php must be installed as a must-use plugin.
 */
const { test, expect, admin } = require( process.env.CDO_E2E_FIXTURES );

// Page views of these specs do not start WP-Cron (tests/e2e/qa-google-stub.php reads this header): the tests run the
// Fast Index events with WP-CLI, and a parallel WP-Cron run could write back an older copy of the cron list.
test.use( { extraHTTPHeaders: { 'X-Fast-Index-QA': '1' } } );
const { wp, serviceAccountFile, premiumLoaded } = require( './helpers' );

const PREMIUM = premiumLoaded();

const SETTINGS = admin( 'admin.php?page=fast-index' );
const HISTORY = admin( 'admin.php?page=fast-index-history' );
const RUN = admin( 'admin.php?page=fast-index-run' );
const HELP = admin( 'admin.php?page=fast-index-help' );
const SECTIONS = [ 'getting-started', 'settings', 'troubleshooting', 'faq', 'support' ];

/**
 * Whether the page is wider than the window (a horizontal scroll bar).
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<boolean>} True when it overflows.
 */
function overflows( page ) {
	return page.evaluate( () => document.documentElement.scrollWidth > document.documentElement.clientWidth );
}

test.describe( 'Fast Index help', () => {
	test.beforeEach( () => {
		// Core CSS view transitions, aborted by Chromium's mobile emulation (not from the plugin).
		test.info().annotations.push( { type: 'allow-console', description: 'Transition was aborted because of invalid state' } );
	} );

	test( 'the help page opens and every section is there', async ( { page } ) => {
		const res = await page.goto( HELP );
		expect( res.status() ).toBe( 200 );
		await expect( page.locator( '.fast-index-help h1' ) ).toBeVisible();

		const links = page.locator( '.fi-help-nav a' );
		await expect( links ).toHaveCount( SECTIONS.length );

		for ( const section of SECTIONS ) {
			await expect( page.locator( 'section#fi-help-' + section + ' h2' ) ).toHaveCount( 1 );
			await expect( page.locator( '.fi-help-nav a[href="#fi-help-' + section + '"]' ) ).toHaveCount( 1 );
		}

		await expect( page.locator( '#fi-help-getting-started ol.fi-help-steps > li' ).first() ).toBeVisible();
		await expect( page.locator( '#fi-help-settings table tbody tr' ).first() ).toBeVisible();
		expect( await page.locator( '#fi-help-faq details' ).count() ).toBeGreaterThanOrEqual( 10 );
		expect( await page.locator( '#fi-help-troubleshooting details' ).count() ).toBeGreaterThanOrEqual( 7 );
		expect( await overflows( page ) ).toBe( false );

		// The page is plain, safe markup: no frame, no script of its own, no secret.
		await expect( page.locator( '.fast-index-help iframe, .fast-index-help video, .fast-index-help script' ) ).toHaveCount( 0 );
		for ( const link of await page.locator( '.fast-index-help a[target="_blank"]' ).all() ) {
			await expect( link ).toHaveAttribute( 'rel', 'noopener noreferrer' );
		}
		const forum = page.locator( '#fi-help-support a[href="https://wordpress.org/support/plugin/fast-index/"]' );
		await expect( forum ).toHaveCount( 1 );
		await expect( page.locator( 'a[href="https://youtu.be/RsJA66b5884"]' ) ).toHaveAttribute( 'target', '_blank' );
	} );

	test( 'every question and problem opens and closes', async ( { page } ) => {
		await page.goto( HELP );

		const items = page.locator( 'details.fi-help-item' );
		const count = await items.count();
		expect( count ).toBeGreaterThanOrEqual( 17 );

		for ( let i = 0; i < count; i++ ) {
			const item = items.nth( i );
			await expect( item ).not.toHaveAttribute( 'open', /.*/ );
			await item.locator( 'summary' ).click();
			await expect( item ).toHaveAttribute( 'open', '' );
			await expect( item.locator( '.fi-help-item__body' ) ).toBeVisible();
			expect( await overflows( page ), 'item ' + i ).toBe( false );
			await item.locator( 'summary' ).click();
			await expect( item ).not.toHaveAttribute( 'open', /.*/ );
		}
	} );

	test( 'anchor links lead to their section, and an address can open an answer', async ( { page } ) => {
		await page.goto( HELP );

		for ( const section of SECTIONS ) {
			await page.locator( '.fi-help-nav a[href="#fi-help-' + section + '"]' ).click();
			await expect( page ).toHaveURL( new RegExp( '#fi-help-' + section + '$' ) );
			await expect( page.locator( '#fi-help-' + section + ' h2' ) ).toBeInViewport();
		}

		await page.goto( HELP + '#fi-help-faq-quota' );
		await expect( page.locator( 'details#fi-help-faq-quota' ) ).toHaveAttribute( 'open', '' );
		await page.goto( HELP + '#fi-help-problem-error-403' );
		await expect( page.locator( 'details#fi-help-problem-error-403' ) ).toHaveAttribute( 'open', '' );
	} );

	test( 'system info is read-only, has the facts and no secret', async ( { page } ) => {
		const email = 'e2e-help@fast-index-e2e.iam.gserviceaccount.com';
		const { privateKey, buffer } = serviceAccountFile( email, 'e2ehelp0123456789abcdef0123456789abcdef0' );

		await page.goto( SETTINGS );
		await page.setInputFiles( '#fast-index-key-files', { name: 'service-account.json', mimeType: 'application/json', buffer } );
		await page.click( 'form[enctype="multipart/form-data"] input[type="submit"]' );
		await expect( page.locator( '.fast-index-accounts' ) ).toContainText( email );

		await page.goto( HELP );
		const box = page.locator( '#fi-help-system-info' );
		await expect( box ).toHaveAttribute( 'readonly', 'readonly' );

		const text = await box.inputValue();
		// The plugin version as the main file states it, so the test follows every release.
		expect( text ).toContain( wp( [ 'eval', 'echo FAST_INDEX_VERSION;' ] ) );
		expect( text ).toContain( wp( [ 'core', 'version' ] ) );
		expect( text ).toMatch( /\d+\.\d+\.\d+/ );
		expect( text ).toContain( wp( [ 'eval', 'echo get_locale();' ] ) );
		// One line per fact, "label: value".
		expect( text.split( '\n' ).length ).toBeGreaterThanOrEqual( 15 );

		// The same facts are shown as a list, label and value each in its own isolated element, so that the
		// screen keeps the order right in right-to-left languages (the copied plain text stays as it is).
		const rows = page.locator( '.fi-help-sysinfo__row' );
		await expect( rows ).toHaveCount( text.split( '\n' ).length );
		await expect( rows.first().locator( 'dt bdi' ) ).toHaveCount( 1 );
		await expect( rows.first().locator( 'dd bdi' ) ).toHaveCount( 1 );
		// The plain-text copy for the copy button is out of sight.
		expect( ( await box.boundingBox() ).width ).toBeLessThanOrEqual( 2 );
		expect( text ).not.toContain( '@' );

		const html = await page.content();
		const keyBody = privateKey.replace( /-----[A-Z ]+-----|\s/g, '' );
		for ( const secret of [ 'BEGIN PRIVATE KEY', 'PRIVATE KEY', keyBody.slice( 20, 80 ), email ] ) {
			expect( html ).not.toContain( secret );
		}

		await page.goto( SETTINGS );
		expect( await page.content() ).not.toContain( 'BEGIN PRIVATE KEY' );

		wp( [ 'option', 'delete', 'fast_index_service_accounts' ] );
		wp( [ 'option', 'delete', 'fast_index_account_statuses' ] );
	} );

	test( 'the copy button copies the system info', async ( { page, context, baseURL } ) => {
		await context.grantPermissions( [ 'clipboard-read', 'clipboard-write' ], { origin: new URL( baseURL ).origin } );
		await page.goto( HELP );

		const expected = await page.locator( '#fi-help-system-info' ).inputValue();
		expect( expected.length ).toBeGreaterThan( 50 );

		await page.locator( '[data-fast-index-copy]' ).click();
		await expect( page.locator( '.fi-help-copy-status' ) ).not.toBeEmpty();
		expect( await page.evaluate( () => navigator.clipboard.readText() ) ).toBe( expected );
	} );

	test( 'without the Clipboard API the copy button selects the text', async ( { page } ) => {
		await page.addInitScript( () => {
			Object.defineProperty( navigator, 'clipboard', { value: undefined, configurable: true } );
		} );
		await page.goto( HELP );

		await page.locator( '[data-fast-index-copy]' ).click();
		await expect( page.locator( '.fi-help-copy-status' ) ).not.toBeEmpty();

		const selection = await page.evaluate( () => {
			const field = document.getElementById( 'fi-help-system-info' );
			return { start: field.selectionStart, end: field.selectionEnd, length: field.value.length };
		} );
		expect( selection ).toEqual( { start: 0, end: selection.length, length: selection.length } );
		expect( selection.length ).toBeGreaterThan( 50 );

		// The field is out of sight until now: it is shown, so that the selection can be seen and copied by hand.
		const field = page.locator( '#fi-help-system-info' );
		await expect( field ).toBeVisible();
		await expect( field ).not.toHaveAttribute( 'aria-hidden', /.*/ );
		expect( ( await field.boundingBox() ).width ).toBeGreaterThan( 200 );
	} );

	test( 'every plugin screen has a help tab that links to the help page', async ( { page } ) => {
		const screens = PREMIUM ? { settings: SETTINGS, history: HISTORY, run: RUN, help: HELP } : { settings: SETTINGS, history: HISTORY, help: HELP };

		for ( const [ key, path ] of Object.entries( screens ) ) {
			await page.goto( path );
			// A Freemius notice can lie over the Help button on pages without a .wrap element, so the click is sent to the button itself.
			await page.locator( '#contextual-help-link' ).dispatchEvent( 'click' );
			await expect( page.locator( '#tab-link-fast-index-help-' + key ) ).toBeVisible();
			await expect( page.locator( '#tab-panel-fast-index-help-' + key ) ).toBeVisible();
			await expect( page.locator( '.contextual-help-sidebar a[href="https://wordpress.org/support/plugin/fast-index/"]' ) ).toHaveCount( 1 );
			await expect( page.locator( '.contextual-help-sidebar a[href*="page=fast-index-help"]' ) ).toHaveCount( 1 );

			if ( 'help' !== key ) {
				await expect( page.locator( '#tab-panel-fast-index-help-' + key + ' a[href*="page=fast-index-help"]' ) ).toHaveCount( 1 );
			}
		}
	} );

	test( 'the menu lists Help & FAQ and the settings page has no frame, a help link and field descriptions', async ( { page } ) => {
		await page.goto( SETTINGS );

		await expect( page.locator( '#adminmenu a[href$="page=fast-index-help"]' ) ).toHaveCount( 1 );
		await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
		await expect( page.locator( '#fi a[href*="page=fast-index-help"]' ).first() ).toBeVisible();
		expect( await page.locator( '#fi p.description' ).count() ).toBeGreaterThanOrEqual( 7 );
		expect( await overflows( page ) ).toBe( false );

		const html = await page.content();
		expect( html ).not.toContain( 'youtube.com/embed' );
		expect( html ).not.toContain( 'BEGIN PRIVATE KEY' );
	} );

	test( 'help assets load on the help page only, the design language on every Fast Index screen', async ( { page } ) => {
		await page.goto( HELP );
		await expect( page.locator( 'link[href*="fast-index/assets/css/cdo-ui.css"]' ) ).toHaveCount( 1 );
		expect( await page.content() ).toContain( 'fast-index-help.js' );

		for ( const path of PREMIUM ? [ SETTINGS, HISTORY, RUN ] : [ SETTINGS, HISTORY ] ) {
			await page.goto( path );
			await expect( page.locator( 'link[href*="fast-index/assets/css/cdo-ui.css"]' ), path ).toHaveCount( 1 );
			expect( await page.content(), path ).not.toContain( 'fast-index-help.js' );
		}

		await page.goto( admin( '' ) );
		const dashboard = await page.content();
		expect( dashboard ).not.toContain( 'cdo-ui.css' );
		expect( dashboard ).not.toContain( 'fast-index-help.js' );
	} );

	test( 'a published post is sent (Google answers are stubbed) and shown in History', async ( { page } ) => {
		test.skip( 'yes' !== wp( [ 'eval', 'echo function_exists( "fast_index_qa_stub" ) ? "yes" : "no";' ] ), 'Install tests/e2e/qa-google-stub.php as a must-use plugin first.' );

		const email = 'e2e-send@fast-index-e2e.iam.gserviceaccount.com';
		const { buffer } = serviceAccountFile( email, 'e2esend0123456789abcdef0123456789abcdef0' );
		const title = 'E2E help send ' + Date.now();

		wp( [ 'option', 'update', 'fast_index_options', '{"status":"1","post_type":{"post":"1"},"post_status":{"publish":"1"}}', '--format=json' ] );
		await page.goto( SETTINGS );
		await page.setInputFiles( '#fast-index-key-files', { name: 'service-account.json', mimeType: 'application/json', buffer } );
		await page.click( 'form[enctype="multipart/form-data"] input[type="submit"]' );
		await expect( page.locator( '.fast-index-accounts' ) ).toContainText( email );

		const postId = wp( [ 'post', 'create', '--post_status=publish', '--post_title=' + title, '--porcelain' ] );
		// Only this post's notification: other posts published on the site meanwhile would push its row off page 1.
		wp( [ 'eval', 'foreach ( Fast_Index_Queue::items( 100000 ) as $i ) { if ( ' + postId + ' === $i["post_id"] && null !== Fast_Index_Queue::take( $i["url"] ) ) { Fast_Index_Notifier::deliver( $i["url"], $i["type"], $i["post_id"] ); } }' ] );

		await page.goto( HISTORY );
		const row = page.locator( '#fi table tbody tr', { hasText: title } );
		await expect( row ).toHaveCount( 1 );
		await expect( row ).toContainText( '200' );

		wp( [ 'post', 'delete', postId, '--force' ] );
		wp( [ 'option', 'delete', 'fast_index_service_accounts' ] );
		wp( [ 'option', 'delete', 'fast_index_account_statuses' ] );
	} );
} );
