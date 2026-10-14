/**
 * Fast Index admin screens, end to end (local QA only).
 *
 * Run from /home/samet/wordpress:
 *   PLUGIN=fast-index tools/e2e/node_modules/.bin/playwright test -c tools/e2e/playwright.config.js
 * Google is never called: a local mu-plugin answers the Google endpoints during QA.
 */
const { test, expect, admin } = require( process.env.CDO_E2E_FIXTURES );
const { wp, serviceAccountFile } = require( './helpers' );

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
		// Language-neutral checks: the sites run in English, Turkish and Arabic.
		for ( const [ path, marker ] of [
			[ SETTINGS, '#fast-index-status' ],
			[ HISTORY, '#fi nav .pagination' ],
			[ RUN, '#fi input[name="action"][value="fast_index_run_backlog"]' ],
		] ) {
			const res = await page.goto( path );
			expect( res.status(), path ).toBe( 200 );
			await expect( page.locator( '#fi h1.title' ).first() ).toBeVisible();
			await expect( page.locator( marker ) ).toHaveCount( 1 );
		}
	} );

	test( 'settings are saved, confirmed and kept after reload', async ( { page } ) => {
		wp( [ 'option', 'update', 'fast_index_options', '{"status":"1","post_type":{"post":"1"},"post_status":{"publish":"1"}}', '--format=json' ] );

		await page.goto( SETTINGS );
		await page.selectOption( '#fast-index-status', '2' );
		await page.check( '#fast-index-delete-data' );
		await page.click( '.settingsForm input[type="submit"]' );

		await expect( page.locator( '.notice-success' ) ).toContainText( 'Settings saved.' );

		await page.reload();
		await expect( page.locator( '#fast-index-status' ) ).toHaveValue( '2' );
		await expect( page.locator( '#fast-index-delete-data' ) ).toBeChecked();
		await expect( page.locator( '.notice-success' ) ).toHaveCount( 0 );

		await page.selectOption( '#fast-index-status', '1' );
		await page.uncheck( '#fast-index-delete-data' );
		await page.click( '.settingsForm input[type="submit"]' );
		await expect( page.locator( '.notice-success' ) ).toContainText( 'Settings saved.' );
		await expect( page.locator( '#fast-index-status' ) ).toHaveValue( '1' );
	} );

	test( 'an invalid value is rejected with an error', async ( { page } ) => {
		await page.goto( SETTINGS );
		await page.selectOption( '#fast-index-status', '2' );
		await page.uncheck( 'input[name="fast_index_options[post_type][post]"]' );
		await page.click( '.settingsForm input[type="submit"]' );

		await expect( page.locator( '.notice-error' ) ).toContainText( 'Select at least one post type.' );
		// Nothing was saved.
		await expect( page.locator( '#fast-index-status' ) ).toHaveValue( '1' );
		await expect( page.locator( 'input[name="fast_index_options[post_type][post]"]' ) ).toBeChecked();

		await page.setInputFiles( '#fast-index-key-files', {
			name: 'not-a-key.json',
			mimeType: 'application/json',
			buffer: Buffer.from( '{"type":"authorized_user"}' ),
		} );
		await page.click( 'form[enctype="multipart/form-data"] input[type="submit"]' );
		await expect( page.locator( '.notice-error' ) ).toContainText( 'not a Google service account key' );
	} );

	test( 'key upload shows only the client email and the key ID', async ( { page } ) => {
		const email = 'e2e-indexer@fast-index-e2e.iam.gserviceaccount.com';
		const keyId = 'e2ekey0123456789abcdef0123456789abcdef01';
		const { privateKey, buffer } = serviceAccountFile( email, keyId );

		await page.goto( SETTINGS );
		await page.setInputFiles( '#fast-index-key-files', { name: 'service-account.json', mimeType: 'application/json', buffer } );
		await page.click( 'form[enctype="multipart/form-data"] input[type="submit"]' );

		await expect( page.locator( '.notice-success' ) ).toContainText( email );
		await expect( page.locator( '.fast-index-accounts' ) ).toContainText( email );
		await expect( page.locator( '.fast-index-accounts' ) ).toContainText( keyId );

		const html = await page.content();
		const keyBody = privateKey.replace( /-----[A-Z ]+-----|\s/g, '' );
		expect( html ).not.toContain( 'PRIVATE KEY' );
		expect( html ).not.toContain( keyBody.slice( 20, 80 ) );

		// The key went to the database, not to the uploads folder.
		expect( wp( [ 'eval', 'echo count( glob( wp_upload_dir()["basedir"] . "/*/*/service-account*.json" ) ?: array() );' ] ) ).toBe( '0' );
	} );

	test( 'history pagination links work', async ( { page } ) => {
		wp( [ 'eval', 'for ( $i = 0; $i < 25; $i++ ) { Fast_Index_Log::add( 0, home_url( "/e2e-history-" . $i . "/" ), 0 === $i % 5 ? 429 : 200, "URL_UPDATED" ); }' ] );

		await page.goto( HISTORY );
		await expect( page.locator( '#fi table' ).nth( 1 ).locator( 'tbody tr' ) ).toHaveCount( 20 );
		await expect( page.locator( '#fi' ) ).toContainText( '429 Too Many Requests' );

		await page.click( '.pagination a[href*="pn=1"]' );
		await expect( page ).toHaveURL( /pn=1/ );
		await expect( page.locator( '#fi table' ).nth( 1 ).locator( 'tbody tr' ).first() ).toBeVisible();
		await expect( page.locator( '.pagination a[href*="pn=0"]' ) ).toBeVisible();

		await page.click( '.pagination a[href*="pn=0"]' );
		await expect( page ).toHaveURL( /pn=0/ );
		await expect( page.locator( '.pagination a[href*="pn=1"]' ) ).toBeVisible();
	} );

	test( 'manual run answers without errors', async ( { page } ) => {
		await page.goto( RUN );
		await page.click( '#fi input[type="submit"]' );
		await expect( page ).toHaveURL( /page=fast-index-run/ );
		await expect( page.locator( '#fi table' ) ).toBeVisible();
	} );

	test( 'migration notice can be dismissed and stays dismissed', async ( { page } ) => {
		wp( [ 'eval', 'Fast_Index_Notices::flag_key_rotation();' ] );

		for ( const path of [ admin( '' ), SETTINGS ] ) {
			await page.goto( path );
			const notice = page.locator( '.fast-index-notice' );
			await expect( notice ).toContainText( 'publicly accessible folder' );
			await expect( notice.locator( 'a[href*="action=fast_index_dismiss_notice"]' ) ).toBeVisible();
			await expect( notice.locator( '.notice-dismiss' ) ).toBeVisible();
		}

		// Close button on the dashboard: the dismissal is stored with the nonce-protected request.
		await page.goto( admin( '' ) );
		const stored = page.waitForResponse( ( res ) => res.url().includes( 'action=fast_index_dismiss_notice' ) );
		await page.locator( '.fast-index-notice .notice-dismiss' ).click();
		await stored;

		await page.reload();
		await expect( page.locator( '.fast-index-notice' ) ).toHaveCount( 0 );
		await page.goto( SETTINGS );
		await expect( page.locator( '.fast-index-notice' ) ).toHaveCount( 0 );

		// The link works without JavaScript too.
		wp( [ 'eval', 'Fast_Index_Notices::flag_key_rotation();' ] );
		await page.goto( SETTINGS );
		await page.locator( '.fast-index-notice a[href*="action=fast_index_dismiss_notice"]' ).click();
		await page.goto( admin( '' ) );
		await expect( page.locator( '.fast-index-notice' ) ).toHaveCount( 0 );
		expect( wp( [ 'eval', 'var_export( get_option( "fast_index_notice_key_rotation" ) );' ] ) ).toBe( 'false' );
	} );
} );
