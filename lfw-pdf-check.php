<?php
/**
 * Plugin Name: LFW PDF Check
 * Plugin URI:  https://lfw.com/
 * Description: Checks every uploaded PDF for the three things a screen reader needs first: tags (document structure), a document language, and a title. Flags a PDF that is missing any of them in the Media Library the moment it is uploaded. Runs on your own site and sends the file nowhere.
 * Version:     1.0.0
 * Author:      LFW
 * Author URI:  https://lfw.com/
 * License:     GPL-2.0-or-later
 * Text Domain: lfw-pdf-check
 *
 * What this does NOT do: it does not prove a PDF is accessible. A tagged PDF can
 * still have a wrong reading order, missing image descriptions or unlabeled
 * tables. It catches the most common failure (an untagged "print to PDF" or a
 * scan) at the moment it happens, which is the cheapest time to fix it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LFW_PDF_Check {

	const META      = '_lfw_pdf_check';
	const MAX_BYTES = 52428800; // 50 MB: larger files are marked "not checked" rather than read.

	public static function init() {
		add_action( 'add_attachment', array( __CLASS__, 'check_attachment' ) );
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'field' ), 10, 2 );
		add_filter( 'manage_media_columns', array( __CLASS__, 'column' ) );
		add_action( 'manage_media_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'lfw-pdf-check', array( __CLASS__, 'cli' ) );
		}
	}

	/** Check a newly added attachment if it is a PDF. */
	public static function check_attachment( $id ) {
		if ( 'application/pdf' !== get_post_mime_type( $id ) ) {
			return;
		}
		$file = get_attached_file( $id );
		update_post_meta( $id, self::META, self::check_file( $file ) );
	}

	/**
	 * Inspect a PDF file. Returns array( 'checked' => bool, 'tagged' => bool,
	 * 'language' => bool, 'title' => bool, 'text' => bool ). Pure function: no
	 * WordPress calls, so it can be tested on its own.
	 */
	public static function check_file( $file ) {
		$empty = array( 'checked' => false, 'tagged' => false, 'language' => false, 'title' => false, 'text' => false );
		if ( ! $file || ! is_readable( $file ) || filesize( $file ) > self::MAX_BYTES ) {
			return $empty;
		}
		$raw = file_get_contents( $file );
		if ( false === $raw || 0 !== strpos( ltrim( substr( $raw, 0, 1024 ) ), '%PDF' ) ) {
			return $empty;
		}
		// PDF 1.5+ may keep dictionaries inside compressed object streams, so the
		// raw bytes and every Flate-decoded stream are searched together.
		$haystack = $raw . self::inflated_streams( $raw );
		return array(
			'checked'  => true,
			'tagged'   => (bool) preg_match( '#/StructTreeRoot\s#', $haystack ) || (bool) preg_match( '#/Marked\s+true#', $haystack ),
			'language' => (bool) preg_match( '#/Lang\s*\(\s*[A-Za-z]{2}#', $haystack ),
			'title'    => (bool) preg_match( '#/Title\s*(\(\s*[^)\s]|<\s*[0-9A-Fa-f]{4})#', $haystack ) || (bool) preg_match( '#<dc:title>.*?<rdf:li[^>]*>\s*[^<\s]#s', $haystack ),
			'text'     => (bool) preg_match( '#/Font\s#', $haystack ),
		);
	}

	/** Concatenate the decoded contents of Flate streams, with a size cap. */
	private static function inflated_streams( $raw ) {
		if ( ! function_exists( 'gzuncompress' ) ) {
			return '';
		}
		$out   = '';
		$limit = 20971520; // 20 MB of decoded text is plenty to find the dictionaries.
		if ( preg_match_all( '#stream\r?\n(.*?)\r?\nendstream#s', $raw, $m ) ) {
			foreach ( $m[1] as $data ) {
				$decoded = @gzuncompress( $data ); // phpcs:ignore -- corrupt or non-Flate streams are skipped.
				if ( false !== $decoded ) {
					$out .= "\n" . $decoded;
					if ( strlen( $out ) > $limit ) {
						break;
					}
				}
			}
		}
		return $out;
	}

	/** The problems in plain words, or an empty array when all checks pass. */
	public static function problems( $r ) {
		if ( empty( $r['checked'] ) ) {
			return array( __( 'Not checked (unreadable or larger than 50 MB)', 'lfw-pdf-check' ) );
		}
		$p = array();
		if ( empty( $r['tagged'] ) ) {
			$p[] = __( 'No tags: screen readers cannot follow its structure', 'lfw-pdf-check' );
		}
		if ( empty( $r['language'] ) ) {
			$p[] = __( 'No document language set', 'lfw-pdf-check' );
		}
		if ( empty( $r['title'] ) ) {
			$p[] = __( 'No document title', 'lfw-pdf-check' );
		}
		if ( empty( $r['text'] ) ) {
			$p[] = __( 'No text found: possibly a scanned image', 'lfw-pdf-check' );
		}
		return $p;
	}

	private static function result( $id ) {
		$r = get_post_meta( $id, self::META, true );
		return is_array( $r ) ? $r : null;
	}

	/** Result shown in the attachment details (media modal and edit screen). */
	public static function field( $fields, $post ) {
		if ( 'application/pdf' !== $post->post_mime_type ) {
			return $fields;
		}
		$r = self::result( $post->ID );
		if ( null === $r ) {
			$html = esc_html__( 'Not checked yet.', 'lfw-pdf-check' );
		} else {
			$p    = self::problems( $r );
			$html = $p
				? '<strong style="color:#b32d2e">' . esc_html__( 'Needs attention:', 'lfw-pdf-check' ) . '</strong><br>' . implode( '<br>', array_map( 'esc_html', $p ) )
				: esc_html__( 'Passes: tagged, with a language and a title.', 'lfw-pdf-check' );
		}
		$fields['lfw_pdf_check'] = array(
			'label' => __( 'PDF check', 'lfw-pdf-check' ),
			'input' => 'html',
			'html'  => '<p>' . $html . '</p>',
		);
		return $fields;
	}

	public static function column( $cols ) {
		$cols['lfw_pdf_check'] = __( 'PDF check', 'lfw-pdf-check' );
		return $cols;
	}

	public static function column_value( $name, $id ) {
		if ( 'lfw_pdf_check' !== $name || 'application/pdf' !== get_post_mime_type( $id ) ) {
			return;
		}
		$r = self::result( $id );
		if ( null === $r ) {
			echo esc_html__( 'Not checked', 'lfw-pdf-check' );
			return;
		}
		$p = self::problems( $r );
		echo $p ? '<span style="color:#b32d2e">' . esc_html( implode( '; ', $p ) ) . '</span>' : esc_html__( 'Passes', 'lfw-pdf-check' );
	}

	/**
	 * Check PDFs already in the library.
	 *
	 * ## EXAMPLES
	 *     wp lfw-pdf-check
	 */
	public static function cli() {
		$ids = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'application/pdf', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'inherit' ) );
		foreach ( $ids as $id ) {
			self::check_attachment( $id );
			$p = self::problems( self::result( $id ) );
			WP_CLI::line( $id . "\t" . ( $p ? implode( '; ', $p ) : 'passes' ) . "\t" . basename( (string) get_attached_file( $id ) ) );
		}
	}
}

LFW_PDF_Check::init();
