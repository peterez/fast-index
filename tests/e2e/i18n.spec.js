/**
 * Fast Index translations in the browser (local QA only).
 *
 * Two ways to run it, both from /home/samet/wordpress:
 *
 * 1. Every bundled language on the main site, with a separate administrator so that the language of the
 *    `admin` account (used by the other specs) is never changed. Create the user once:
 *      wp user create qa_i18n qa_i18n@example.test --role=administrator --user_pass=<local password>
 *    then run (WP_USER / WP_PASS select the account for the shared runner):
 *      WP_USER=qa_i18n WP_PASS=<local password> PLUGIN=fast-index \
 *        tools/e2e/node_modules/.bin/playwright test -c tools/e2e/playwright.config.js i18n
 *    The test sets the `locale` user meta of qa_i18n for each language before it opens the screens.
 *
 * 2. The language of a multisite sub-site (http://localhost/wordpress-ms/tr/ and /ar/) with `admin`:
 *      WP_URL=http://localhost/wordpress-ms/tr/ PLUGIN=fast-index tools/e2e/node_modules/.bin/playwright test ... i18n
 *
 * In any other setup the spec skips itself. For each language and each screen (settings, History, Send Old
 * Posts Now, Help & FAQ) it checks that
 *  - strings taken from languages/fast-index-<locale>.po are on the page,
 *  - no English source string of the plugin is left on the page,
 *  - the page has no horizontal scroll bar (desktop 1440 and phone 390),
 *  - the page direction is right to left for Arabic and left to right otherwise,
 * and it saves full-page screenshots of the screens to I18N_SHOT_DIR (default: /tmp/fast-index-i18n-shots).
 * Google is never called: tests/e2e/qa-google-stub.php must be installed as a must-use plugin.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect, admin } = require( process.env.CDO_E2E_FIXTURES );
const { wp, readPo, IS_MULTISITE, SITE_URL } = require( './helpers' );

const LOCALES = [ 'tr_TR', 'de_DE', 'fr_FR', 'es_ES', 'it_IT', 'pt_BR', 'nl_NL', 'ru_RU', 'ar', 'ja' ];
const RTL = [ 'ar' ];
const SUBSITE_LOCALES = { '/wordpress-ms/tr/': 'tr_TR', '/wordpress-ms/ar/': 'ar' };
const SHOT_DIR = process.env.I18N_SHOT_DIR || '/tmp/fast-index-i18n-shots';
const I18N_USER = 'qa_i18n';

const SETTINGS = admin( 'admin.php?page=fast-index' );
const HISTORY = admin( 'admin.php?page=fast-index-history' );
const RUN = admin( 'admin.php?page=fast-index-run' );
const HELP = admin( 'admin.php?page=fast-index-help' );
const PLUGINS = admin( 'plugins.php' );

// "Settings" has a gettext context (the Search Console menu entry has another one): context, U+0004, source string.
const SETTINGS_TITLE = 'Heading of the Fast Index settings screen\u0004Settings';

// Source strings that must be on each screen in the language under test (all of them are translated
// differently from English in every bundled language, except where noted in the .po by an identical text).
const SCREENS = {
	settings: {
		path: SETTINGS,
		strings: [ SETTINGS_TITLE, 'Status', 'Active', 'Passive', 'Post Types', 'Daily Limit for Old Posts', 'Post Events', 'Exclude Categories', 'Delete data on uninstall', 'Save', 'JSON Key File', 'Upload key', 'Open Help & FAQ' ],
	},
	history: {
		path: HISTORY,
		strings: [ 'Summary', 'Total Sent', 'Total Waiting', 'Sent Today', 'Title', 'Date', 'Send Status', 'View Post', 'Next' ],
	},
	run: {
		path: RUN,
		strings: [ 'Send Old Posts Now', 'Sends one batch of old posts now, within the daily limit set on the settings screen. The same batch also runs every hour.', 'Send now' ],
	},
	help: {
		path: HELP,
		strings: [ 'Getting started', 'Settings explained', 'Troubleshooting', 'Still need help?', 'Indexing API quickstart', 'Create a service account', 'Why it happens:', 'What to do:', 'System info', 'Copy system info', 'Fast Index version' ],
	},
};

/**
 * Normalises text so that it can be compared: no-break and thin spaces, directional marks and runs of white
 * space are reduced the same way on both sides.
 *
 * @param {string} text Text.
 * @return {string} Normalised text.
 */
function norm( text ) {
	return String( text )
		.replace( /[   ]/g, ' ' )
		.replace( /[‎‏؜]/g, '' )
		.replace( /\s+/g, ' ' )
		.trim();
}

/**
 * Everything a user can read in the page content: text nodes (not scripts or styles), form control values,
 * text area contents and the text attributes (title, aria-label, placeholder, data-label).
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<string>} Normalised text of the content area.
 */
async function pageText( page ) {
	const raw = await page.evaluate( () => {
		const root = document.querySelector( '#wpbody-content' ) || document.body;
		const parts = [];
		const walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT );
		let node = walker.nextNode();
		while ( node ) {
			if ( ! node.parentElement.closest( 'script, style, noscript, template' ) ) {
				parts.push( node.nodeValue );
			}
			node = walker.nextNode();
		}
		root.querySelectorAll( 'input, textarea, button' ).forEach( ( el ) => parts.push( el.value || '' ) );
		root.querySelectorAll( '[title], [aria-label], [placeholder], [data-label]' ).forEach( ( el ) => {
			[ 'title', 'aria-label', 'placeholder', 'data-label' ].forEach( ( name ) => parts.push( el.getAttribute( name ) || '' ) );
		} );
		return parts.join( '\n' );
	} );
	return norm( raw );
}

/**
 * Whether the page is wider than the window (a horizontal scroll bar).
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<{scroll: number, client: number}>} Widths.
 */
function widths( page ) {
	return page.evaluate( () => ( {
		scroll: document.documentElement.scrollWidth,
		client: document.documentElement.clientWidth,
	} ) );
}

/**
 * Checks one screen in one language and saves its screenshots.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string} locale Locale under test.
 * @param {string} view Screen key from SCREENS.
 * @param {Map<string,string>} po Translations.
 * @param {string} projectName Playwright project (desktop or mobile).
 * @param {string} prefix Prefix of the screenshot file names.
 * @return {Promise<void>}
 */
async function checkScreen( page, locale, view, po, projectName, prefix ) {
	const screen = SCREENS[ view ];
	const res = await page.goto( screen.path );
	expect( res.status(), locale + ' ' + view ).toBe( 200 );
	await expect( page.locator( '#fi h1.title, .fast-index-help h1' ).first() ).toBeVisible();

	// Direction and language of the page.
	const dir = RTL.includes( locale ) ? 'rtl' : 'ltr';
	expect( await page.evaluate( () => getComputedStyle( document.documentElement ).direction ), locale + ' direction' ).toBe( dir );
	if ( 'rtl' === dir ) {
		await expect( page.locator( 'html[dir=rtl]' ) ).toHaveCount( 1 );
		await expect( page.locator( 'body.rtl' ) ).toHaveCount( 1 );
	} else {
		await expect( page.locator( 'html[dir=rtl]' ) ).toHaveCount( 0 );
	}
	const lang = await page.locator( 'html' ).getAttribute( 'lang' );
	expect( lang.toLowerCase().split( '-' )[ 0 ], locale + ' html lang' ).toBe( locale.toLowerCase().split( '_' )[ 0 ] );

	// Translated strings from the .po file are on the page.
	const text = await pageText( page );
	for ( const id of screen.strings ) {
		const translated = po.get( id );
		expect( translated, 'translation of "' + id.replace( '\u0004', ' / ' ) + '" exists in ' + locale ).toBeTruthy();
		expect( text, locale + ' ' + view + ': "' + translated + '"' ).toContain( norm( translated ) );
	}

	// No English source string of the plugin is left on the page. Short strings ("Save", "Posts") and strings
	// with placeholders are skipped; so are strings whose translation equals the source or keeps it on purpose
	// (a Google menu name followed by its English name in parentheses, an English Google error text).
	const leftovers = [];
	for ( const [ id, translated ] of po ) {
		if ( id.length < 20 || /%(\d+\$)?[sd]/.test( id ) || norm( translated ).includes( norm( id ) ) || /^https?:/.test( id ) ) {
			continue;
		}
		if ( text.includes( norm( id ) ) ) {
			leftovers.push( id );
		}
	}
	expect( leftovers, locale + ' ' + view + ': English text left on the page' ).toEqual( [] );

	// The admin menu entries of the plugin are translated.
	const menu = norm( await page.locator( '#adminmenu' ).evaluate( ( el ) => el.textContent ) );
	for ( const id of [ 'History', 'Send Old Posts Now', 'Help & FAQ' ] ) {
		expect( menu, locale + ' admin menu: "' + po.get( id ) + '"' ).toContain( norm( po.get( id ) ) );
	}

	// Right to left: in the system info list the label is on the right, its value on the left of it; Japanese
	// breaks lines between phrases (where the browser supports it).
	if ( 'help' === view && 'rtl' === dir && 'mobile' !== projectName ) {
		const sides = await page.locator( '.fi-help-sysinfo__row' ).first().evaluate( ( row ) => ( {
			label: row.querySelector( 'dt' ).getBoundingClientRect().left,
			value: row.querySelector( 'dd' ).getBoundingClientRect().left,
		} ) );
		expect( sides.label, locale + ' system info: label right of the value' ).toBeGreaterThan( sides.value );
	}
	if ( 'ja' === locale ) {
		const phrases = await page.evaluate( () => ( {
			supported: CSS.supports( 'word-break', 'auto-phrase' ),
			value: getComputedStyle( document.querySelector( '#fi, .fast-index-help' ) ).wordBreak,
		} ) );
		if ( phrases.supported ) {
			expect( phrases.value, 'ja word-break' ).toBe( 'auto-phrase' );
		}
	}

	// No horizontal scroll bar.
	const w = await widths( page );
	expect( w.scroll, locale + ' ' + view + ' (' + projectName + ') scrollWidth ' + w.scroll + ' > clientWidth ' + w.client ).toBeLessThanOrEqual( w.client + 1 );

	// Full-page screenshots (the settings and the help screens are the ones that get a visual review).
	if ( 'settings' === view || 'help' === view ) {
		fs.mkdirSync( SHOT_DIR, { recursive: true } );
		const suffix = 'mobile' === projectName ? '-mobile' : '';
		await page.screenshot( { path: path.join( SHOT_DIR, prefix + '-' + view + suffix + '.png' ), fullPage: true } );
		if ( 'help' === view ) {
			// The same page with every question open, so that the long answers are visible too.
			await page.evaluate( () => document.querySelectorAll( '.fast-index-help details' ).forEach( ( d ) => d.setAttribute( 'open', '' ) ) );
			const open = await widths( page );
			expect( open.scroll, locale + ' help with open questions (' + projectName + ')' ).toBeLessThanOrEqual( open.client + 1 );
			await page.screenshot( { path: path.join( SHOT_DIR, prefix + '-help-open' + suffix + '.png' ), fullPage: true } );
		}
	}
}

/**
 * Seeds a few history rows so that the History screen has a table, a status text and a second page.
 *
 * @return {void}
 */
function seedHistory() {
	wp( [ 'eval', 'for ( $i = 0; $i < 22; $i++ ) { Fast_Index_Log::add( 0, home_url( "/i18n-qa-" . $i . "/" ), 0 === $i % 7 ? 429 : 200, 0 === $i % 3 ? "URL_DELETED" : "URL_UPDATED" ); }' ] );
}

/**
 * Removes the history rows that seedHistory() created.
 *
 * @return {void}
 */
function cleanHistory() {
	wp( [ 'eval', 'foreach ( get_posts( array( "post_type" => "fi_log", "post_status" => "any", "numberposts" => -1, "fields" => "ids", "s" => "i18n-qa-" ) ) as $id ) { wp_delete_post( $id, true ); } delete_transient( "fast_index_stats" );' ] );
}

const subsiteKey = Object.keys( SUBSITE_LOCALES ).find( ( key ) => SITE_URL.includes( key ) );

test.describe( 'Fast Index translations', () => {
	test.beforeEach( () => {
		// Core CSS view transitions, aborted by Chromium's mobile emulation (not from the plugin).
		test.info().annotations.push( { type: 'allow-console', description: 'Transition was aborted because of invalid state' } );
	} );

	test.beforeAll( () => {
		if ( I18N_USER === process.env.WP_USER || subsiteKey ) {
			seedHistory();
		}
	} );

	test.afterAll( () => {
		if ( I18N_USER === process.env.WP_USER || subsiteKey ) {
			cleanHistory();
		}
	} );

	for ( const locale of LOCALES ) {
		test( 'every screen in ' + locale, async ( { page }, testInfo ) => {
			test.skip( I18N_USER !== process.env.WP_USER || IS_MULTISITE, 'Run with WP_USER=' + I18N_USER + ' on the main site.' );

			wp( [ 'user', 'meta', 'update', I18N_USER, 'locale', locale ] );
			const po = readPo( locale );

			for ( const view of Object.keys( SCREENS ) ) {
				await checkScreen( page, locale, view, po, testInfo.project.name, locale );
			}

			// The plugin list shows the translated description of the plugin header.
			await page.goto( PLUGINS );
			await expect( page.locator( 'tr[data-slug="fast-index"] .plugin-description' ) ).toContainText( norm( po.get( 'Sends new, updated and deleted posts to the Google Indexing API so Google can crawl them sooner.' ) ) );

			// The manual run answers in the same language.
			await page.goto( RUN );
			await page.click( '#fi input[type="submit"]' );
			await expect( page ).toHaveURL( /page=fast-index-run/ );
			await expect( page.locator( '#fi table' ) ).toBeVisible();
			expect( await pageText( page ) ).toContain( norm( po.get( 'There are no posts to send now. Check your settings and the daily limit.' ) ) );
		} );
	}

	test( 'every screen in the language of the sub-site', async ( { page }, testInfo ) => {
		test.skip( ! subsiteKey, 'Run with WP_URL=http://localhost/wordpress-ms/tr/ or /ar/ (admin).' );

		const locale = SUBSITE_LOCALES[ subsiteKey ];
		expect( wp( [ 'eval', 'echo get_locale();' ] ), 'site language' ).toBe( locale );
		const po = readPo( locale );

		for ( const view of Object.keys( SCREENS ) ) {
			await checkScreen( page, locale, view, po, testInfo.project.name, 'ms-' + locale );
		}
	} );
} );
