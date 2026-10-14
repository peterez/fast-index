/**
 * Helpers shared by the Fast Index end-to-end specs (local QA only).
 */
const crypto = require( 'crypto' );
const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

const SITE_URL = process.env.WP_URL;
const IS_MULTISITE = /wordpress-ms/.test( SITE_URL );

// Site under test. Defaults are the two local installs; WP_PATH (and WP_PHP for another PHP binary,
// for example php7.4 on the WordPress 5.6 compatibility install) point WP-CLI at any other site.
const WP_PATH = process.env.WP_PATH || ( IS_MULTISITE ? '/home/samet/wordpress-ms' : '/home/samet/wordpress' );
const WP_BIN = '/home/samet/.local/bin/wp';

/**
 * Runs WP-CLI against the site under test (state set-up and checks only).
 *
 * @param {string[]} args WP-CLI arguments.
 * @return {string} Output.
 */
function wp( args ) {
	const target = [
		'--path=' + WP_PATH,
		// PHP CLI has no OPcache, so every set-up call compiles the bundled Freemius SDK again and its
		// PHP 8.4 deprecation notices (third-party code) would land in the debug.log this suite checks.
		// Only messages from freemius-sdk/ are dropped; the plugin's own messages are still logged.
		'--exec=set_error_handler( function ( $no, $str, $file ) { return false !== strpos( (string) $file, "/fast-index/freemius-sdk/" ); } );',
	];
	if ( IS_MULTISITE ) {
		target.push( '--url=' + SITE_URL );
	}
	const command = process.env.WP_PHP ? process.env.WP_PHP : WP_BIN;
	const prefix = process.env.WP_PHP ? [ WP_BIN ] : [];
	return execFileSync( command, prefix.concat( target, args ), {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'ignore' ],
	} ).trim();
}

/**
 * Builds a service account key file in memory. The key is generated for the test and never stored.
 *
 * @param {string} email Client email.
 * @param {string} keyId Key ID.
 * @return {{privateKey: string, buffer: Buffer}} Key and file body.
 */
function serviceAccountFile( email, keyId ) {
	const { privateKey } = crypto.generateKeyPairSync( 'rsa', {
		modulusLength: 2048,
		privateKeyEncoding: { type: 'pkcs8', format: 'pem' },
		publicKeyEncoding: { type: 'spki', format: 'pem' },
	} );
	const body = {
		type: 'service_account',
		project_id: 'fast-index-e2e',
		private_key_id: keyId,
		private_key: privateKey,
		client_email: email,
		client_id: '100000000000000000002',
		token_uri: 'https://oauth2.googleapis.com/token',
	};
	return { privateKey, buffer: Buffer.from( JSON.stringify( body ) ) };
}

/**
 * Reads languages/fast-index-<locale>.po into a map of source string to translation.
 *
 * Only what the i18n spec needs: single-line and wrapped strings, \n \t \" \\ escapes and contexts, no plurals
 * (the plugin has none). A string with a context (_x) is stored under "context + U+0004 + source string", like
 * gettext does. Entries with an empty translation are left out.
 *
 * @param {string} locale Locale, for example de_DE.
 * @return {Map<string,string>} Source string => translation.
 */
function readPo( locale ) {
	const file = path.join( __dirname, '..', '..', 'languages', 'fast-index-' + locale + '.po' );
	const unescape = ( text ) => text.replace( /\\(n|t|"|\\)/g, ( m, c ) => ( { n: '\n', t: '\t', '"': '"', '\\': '\\' }[ c ] ) );
	const map = new Map();
	let id = null;
	let str = null;
	let ctx = null;
	let field = null;
	const flush = () => {
		if ( id !== null && str !== null && '' !== id && '' !== str ) {
			map.set( null === ctx ? id : ctx + '\u0004' + id, str );
		}
		id = null;
		str = null;
		ctx = null;
		field = null;
	};
	for ( const line of fs.readFileSync( file, 'utf8' ).split( '\n' ) ) {
		let m = line.match( /^msgctxt "(.*)"$/ );
		if ( m ) {
			flush();
			ctx = unescape( m[ 1 ] );
			field = 'ctx';
			continue;
		}
		m = line.match( /^msgid "(.*)"$/ );
		if ( m ) {
			if ( null === ctx || null !== id ) {
				flush();
			}
			id = unescape( m[ 1 ] );
			field = 'id';
			continue;
		}
		m = line.match( /^msgstr "(.*)"$/ );
		if ( m ) {
			str = unescape( m[ 1 ] );
			field = 'str';
			continue;
		}
		m = line.match( /^"(.*)"$/ );
		if ( m && 'ctx' === field ) {
			ctx += unescape( m[ 1 ] );
		} else if ( m && 'id' === field ) {
			id += unescape( m[ 1 ] );
		} else if ( m && 'str' === field ) {
			str += unescape( m[ 1 ] );
		} else if ( '' === line.trim() ) {
			flush();
		}
	}
	flush();
	return map;
}

module.exports = { wp, serviceAccountFile, readPo, SITE_URL, IS_MULTISITE };
