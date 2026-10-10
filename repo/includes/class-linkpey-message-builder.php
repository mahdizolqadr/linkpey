<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LinkPey_Message_Builder {

	const MAX_IMAGE_BYTES = 20971520; // 20 MB

	public static function build( WP_Post $post, $options ) {
		$parts = array();

		if ( ! empty( $options['include_title'] ) ) {
			$title = trim( get_the_title( $post ) );
			if ( '' !== $title ) {
				$parts[] = $title;
			}
		}

		if ( ! empty( $options['include_excerpt'] ) ) {
			$excerpt = self::get_excerpt( $post );
			if ( '' !== $excerpt ) {
				$parts[] = $excerpt;
			}
		}

		if ( ! empty( $options['include_content'] ) ) {
			$content = self::get_plain_content( $post );
			if ( '' !== $content ) {
				$parts[] = $content;
			}
		}

		if ( ! empty( $options['include_link'] ) ) {
			if ( 'publish' === $post->post_status ) {
				$link = get_permalink( $post );
				if ( $link ) {
					$parts[] = $link;
				}
			}
		}

		return trim( implode( "\n\n", array_filter( $parts ) ) );
	}

	public static function count_images( WP_Post $post ) {
		return count( self::collect_image_attachment_ids( $post ) );
	}

	public static function collect_image_files( WP_Post $post ) {
		$files = array();

		foreach ( self::collect_image_attachment_ids( $post ) as $attachment_id ) {
			$file = self::attachment_to_media_file( $attachment_id );
			if ( $file ) {
				$files[] = $file;
			}
		}

		return array_slice( $files, 0, LINKPEY_MAX_ALBUM_ITEMS );
	}

	public static function collect_image_attachment_ids( WP_Post $post ) {
		$ids  = array();
		$seen = array();

		$featured_id = get_post_thumbnail_id( $post );
		if ( $featured_id ) {
			self::add_attachment_id( $featured_id, $ids, $seen );
		}

		$attached_images = get_attached_media( 'image', $post->ID );
		foreach ( $attached_images as $attachment ) {
			self::add_attachment_id( $attachment->ID, $ids, $seen );
		}

		if ( preg_match_all( '/\[gallery[^\]]*ids=["\']([^"\']+)["\']/i', $post->post_content, $gallery_matches ) ) {
			foreach ( $gallery_matches[1] as $ids_string ) {
				$gallery_ids = array_map( 'absint', explode( ',', $ids_string ) );
				foreach ( $gallery_ids as $attachment_id ) {
					self::add_attachment_id( $attachment_id, $ids, $seen );
				}
			}
		}

		if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $post->post_content, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				self::add_content_image_id( $url, $ids, $seen );
			}
		}

		return array_slice( $ids, 0, LINKPEY_MAX_ALBUM_ITEMS );
	}

	private static function add_attachment_id( $attachment_id, array &$ids, array &$seen ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || ! wp_attachment_is( 'image', $attachment_id ) ) {
			return;
		}

		if ( empty( $seen[ $attachment_id ] ) ) {
			$ids[]                  = $attachment_id;
			$seen[ $attachment_id ] = true;
		}
	}

	private static function add_content_image_id( $url, array &$ids, array &$seen ) {
		$url = esc_url_raw( $url );
		if ( ! $url ) {
			return;
		}

		$attachment_id = attachment_url_to_postid( $url );
		if ( ! $attachment_id || ! wp_attachment_is( 'image', $attachment_id ) ) {
			return;
		}

		self::add_attachment_id( $attachment_id, $ids, $seen );
	}

	private static function attachment_to_media_file( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || ! wp_attachment_is( 'image', $attachment_id ) ) {
			return null;
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			return null;
		}

		$uploads   = wp_get_upload_dir();
		$real_path = realpath( $path );
		$real_base = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		if ( ! $real_path || ! $real_base || 0 !== strpos( $real_path, $real_base ) ) {
			return null;
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! self::is_allowed_image_mime( $mime ) ) {
			return null;
		}

		$size = filesize( $real_path );
		if ( false === $size || $size <= 0 || $size > self::MAX_IMAGE_BYTES ) {
			return null;
		}

		$data = file_get_contents( $real_path );
		if ( false === $data ) {
			return null;
		}

		return array(
			'content_type' => $mime,
			'data'         => base64_encode( $data ),
		);
	}

	private static function is_allowed_image_mime( $mime ) {
		$allowed = array(
			'image/jpeg',
			'image/png',
			'image/webp',
			'image/gif',
		);

		return in_array( strtolower( (string) $mime ), $allowed, true );
	}

	private static function get_excerpt( WP_Post $post ) {
		if ( has_excerpt( $post ) ) {
			return trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter required to render post content.
		$content = wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) );
		return trim( wp_trim_words( $content, 55, '…' ) );
	}

	private static function get_plain_content( WP_Post $post ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter required to render post content.
		$content = apply_filters( 'the_content', $post->post_content );
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( "/\n{3,}/", "\n\n", $content ) );
	}
}
