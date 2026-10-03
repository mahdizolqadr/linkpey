<?php
/**
 * Run on the customer WordPress server (CLI only):
 * wp eval-file wp-content/plugins/linkpey/tools/linkpey-server-diagnostic.php
 *
 * @package LinkPey
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via WP-CLI inside WordPress.\n" );
	exit( 1 );
}

if ( ! class_exists( 'LinkPey' ) ) {
	fwrite( STDERR, "LinkPey plugin is not loaded.\n" );
	exit( 1 );
}

$api_base = LinkPey::get_api_base_url();
$url      = trailingslashit( $api_base ) . 'channels/';
$token    = LinkPey::get_api_token();

echo "=== LinkPey server diagnostic ===\n";
echo 'Plugin version: ' . ( defined( 'LINKPEY_VERSION' ) ? LINKPEY_VERSION : 'unknown' ) . "\n";
echo 'Site URL: ' . site_url() . "\n";
echo 'API base: ' . $api_base . "\n";
echo 'Request URL: ' . $url . "\n";
echo 'Token set: ' . ( '' !== $token ? 'yes (' . substr( $token, 0, 12 ) . '…)' : 'no' ) . "\n";

if ( '' === $token ) {
	echo "ERROR: No API token stored.\n";
	exit( 1 );
}

$has_pre_http = has_filter( 'pre_http_request' );
echo 'pre_http_request filters: ' . ( $has_pre_http ? (string) $has_pre_http : '0' ) . "\n";

$args = array(
	'method'      => 'GET',
	'timeout'     => 30,
	'sslverify'   => true,
	'redirection' => 0,
	'headers'     => array(
		'API-Token'             => $token,
		'Accept'                => 'application/json',
		'X-LinkPey-Plugin-Test' => '1',
		'User-Agent'            => 'LinkPey-Diagnostic/' . LINKPEY_VERSION,
	),
);

$t0       = microtime( true );
$response = wp_remote_request( $url, $args );
$elapsed  = round( ( microtime( true ) - $t0 ) * 1000 );

if ( is_wp_error( $response ) ) {
	echo "wp_remote_request: WP_Error\n";
	echo '  code: ' . $response->get_error_code() . "\n";
	echo '  message: ' . $response->get_error_message() . "\n";
	exit( 1 );
}

$code         = (int) wp_remote_retrieve_response_code( $response );
$body         = wp_remote_retrieve_body( $response );
$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
$api_header   = (string) wp_remote_retrieve_header( $response, 'x-linkpey-api' );

echo "HTTP status: {$code}\n";
echo "Elapsed ms: {$elapsed}\n";
echo "Content-Type: {$content_type}\n";
echo "X-LinkPey-Api: {$api_header}\n";
echo 'Body length: ' . strlen( $body ) . "\n";
echo "Body preview (first 400 chars):\n" . substr( $body, 0, 400 ) . "\n";

$data = json_decode( $body, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo 'JSON decode: FAILED (' . json_last_error_msg() . ")\n";
	exit( 1 );
}

if ( ! is_array( $data ) ) {
	echo "JSON decode: not an array\n";
	exit( 1 );
}

$is_list = array_values( $data ) === $data;
echo 'JSON type: ' . ( $is_list ? 'array list' : 'object/other' ) . "\n";
echo 'Channel count: ' . count( $data ) . "\n";

if ( $is_list && ! empty( $data ) ) {
	$first = $data[0];
	echo 'First channel keys: ' . implode( ', ', array_keys( $first ) ) . "\n";
}

if ( '1' !== $api_header && preg_match( '/(?:^|\.)linkpey\.com$/i', (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) {
	echo "WARNING: Response missing X-LinkPey-Api: 1 (likely NOT from real LinkPey API).\n";
}

if ( 0 === count( $data ) && 200 === $code && '' !== trim( $body ) && '[]' === trim( $body ) ) {
	echo "NOTE: Empty JSON array []. If LinkPey panel shows channels, this response is suspicious.\n";
}

echo "=== done ===\n";
