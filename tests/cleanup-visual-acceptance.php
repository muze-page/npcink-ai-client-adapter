<?php
/**
 * Cleans retained Gutenberg visual acceptance fixtures from a manifest.
 *
 * @package NpcinkOpenClawAdapter
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress is not loaded.\n" );
	exit( 1 );
}

$manifest_path = getenv( 'MAA_ADAPTER_VISUAL_ACCEPTANCE_OUT' );
if ( ! is_string( $manifest_path ) || '' === trim( $manifest_path ) || ! is_readable( $manifest_path ) ) {
	fwrite( STDERR, "Missing readable visual acceptance manifest.\n" );
	exit( 2 );
}

$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
if ( ! is_array( $manifest ) ) {
	fwrite( STDERR, "Invalid visual acceptance manifest JSON.\n" );
	exit( 2 );
}

$post_ids       = array();
$attachment_ids = array();
foreach ( (array) ( $manifest['fixtures'] ?? array() ) as $fixture ) {
	if ( ! is_array( $fixture ) ) {
		continue;
	}
	$post_ids[] = absint( $fixture['post_id'] ?? 0 );
	foreach ( (array) ( $fixture['attachment_ids'] ?? array() ) as $attachment_id ) {
		$attachment_ids[] = absint( $attachment_id );
	}
}

foreach ( array_values( array_unique( array_filter( $attachment_ids ) ) ) as $attachment_id ) {
	wp_delete_attachment( $attachment_id, true );
}

foreach ( array_values( array_unique( array_filter( $post_ids ) ) ) as $post_id ) {
	wp_delete_post( $post_id, true );
}

echo "Cleaned visual acceptance fixtures.\n";
