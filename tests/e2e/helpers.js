/**
 * Helpers shared by the Fast Index end-to-end specs (local QA only).
 */
const crypto = require( 'crypto' );
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

module.exports = { wp, serviceAccountFile, SITE_URL, IS_MULTISITE };
