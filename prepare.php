<?php

/**
 * Prepares the environment for the test run.
 */

require __DIR__ . '/functions.php';

// Check required environment variables
check_required_env();

// Bring some environment variables into scope
$WPT_PREPARE_DIR = getenv( 'WPT_PREPARE_DIR' );
$WPT_SSH_CONNECT = getenv( 'WPT_SSH_CONNECT' );
$WPT_SSH_OPTIONS = getenv( 'WPT_SSH_OPTIONS' ) ? : '-o StrictHostKeyChecking=no';
$WPT_TEST_DIR = getenv( 'WPT_TEST_DIR' );

// Set the ssh private key if it's set
$WPT_SSH_PRIVATE_KEY_BASE64 = getenv( 'WPT_SSH_PRIVATE_KEY_BASE64' );
if ( false !== $WPT_SSH_PRIVATE_KEY_BASE64 ) {
	log_message( 'Securely extracting WPT_SSH_PRIVATE_KEY_BASE64 into ~/.ssh/id_rsa' );
	file_put_contents( getenv( 'HOME' ) . '/.ssh/id_rsa', base64_decode( $WPT_SSH_PRIVATE_KEY_BASE64 ) );
	perform_operations( array(
		'chmod 600 ~/.ssh/id_rsa',
		'ssh -q ' . $WPT_SSH_OPTIONS . ' ' . escapeshellarg( $WPT_SSH_CONNECT ) . ' wp cli info',
	) );
}

// Create the prepation directory and fetch corresponding files
perform_operations( array(
	'mkdir -p ' . escapeshellarg( $WPT_PREPARE_DIR ),
	'git clone --depth=1 https://github.com/WordPress/wordpress-develop.git ' . escapeshellarg( $WPT_PREPARE_DIR ),
	'wget -O ' .  escapeshellarg( $WPT_PREPARE_DIR . '/phpunit.phar' ) . ' https://phar.phpunit.de/phpunit-5.7.phar',
	'wget -O ' . escapeshellarg( $WPT_PREPARE_DIR . '/tests/phpunit/data/plugins/wordpress-importer.zip' ) . ' https://downloads.wordpress.org/plugin/wordpress-importer.zip',
	'cd ' . escapeshellarg( $WPT_PREPARE_DIR . '/tests/phpunit/data/plugins/' ) . '; unzip wordpress-importer.zip; rm wordpress-importer.zip',
) );

// Replace variables in the wp-config.php file
log_message( 'Replacing variables in wp-tests-config.php' );
$contents = file_get_contents( $WPT_PREPARE_DIR . '/wp-tests-config-sample.php' );
$search_replace = array(
	'wptests_'                => getenv( 'WPT_TABLE_PREFIX' ) ? : 'wptests_',
	'youremptytestdbnamehere' => getenv( 'WPT_DB_NAME' ),
	'yourusernamehere'        => getenv( 'WPT_DB_USER' ),
	'yourpasswordhere'        => getenv( 'WPT_DB_PASSWORD' ),
	'localhost'               => getenv( 'WPT_DB_HOST' ),
);
$contents = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $contents );
file_put_contents( $WPT_PREPARE_DIR . '/wp-tests-config.php', $contents );

// Deliver all files to test environment
if ( false !== $WPT_SSH_CONNECT ) {
	perform_operations( array(
		'rsync -rv --exclude=".git/" -e "ssh ' . $WPT_SSH_OPTIONS . '" ' . escapeshellarg( trailingslashit( $WPT_PREPARE_DIR )  ) . ' ' . escapeshellarg( $WPT_SSH_CONNECT . ':' . $WPT_TEST_DIR ),
	) );
}

log_message( 'Success: Prepared environment.' );
