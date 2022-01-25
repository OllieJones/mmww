<?php
	/**
	 * class for inserting audio shortcodes for WordPress 3.5 and after.
	 */

	class MMWWAudioShortcode35 {

		function __construct() {
			add_filter( 'media_send_to_editor', array($this, 'send_to_editor'), 11, 3 );
		}

		/**
		 * remove all commas and apostrophes from a string
		 * @param string $s
		 * @return string with blanks where commas and apostrophes were.
		 */
		function nocommas( $s ) {
			$t = implode( ' ', explode( ', ', $s ) );
			$t = implode( ' ', explode( ',', $t ) );
			$t = implode( '', explode( "'", $t ) );
			return $t;
		}

		/**
		 * adjust hyperlinks and other embed codes for audio shortcode upon sending them to the editor
		 *
		 * @since 2.5.0
		 *
		 * @param string $html  the string to be inserted in the editor, filtered
		 * @param int $attachment_id attachment id
		 * @param array $attachment
		 * @return filtered html string
		 */
		function send_to_editor( $html, $attachment_id, $attachment ) {

			/* is the shortcode-replacement option activated? */
			$options = get_option( 'mmww_options' );
			$choice  = (empty($options['audio_shortcode'])) ? 'disabled' : $options['audio_shortcode'];
			if ( $choice == 'never' ) {
				/* it's not activated, don't do anything */
				return $html;
			}
			/* are we working on an audio file ? */
			$post = get_post( $attachment_id );
			/* double check we're in the right neighborhood before bashing the html */
			$mime = $post->post_mime_type;
			if ( $mime != 'audio/mpeg' ) {
				/* not an audio file, don't do anything */
				return $html;
			}
			/* maybe something to do */
			$url   = wp_get_attachment_url( $post->ID );
			$files = esc_attr( $url );
			/* the [audio file titles and artists lists are comma separated in Jetpack shortcode, so scrub embedded commas */
			$titles = $this->nocommas( $post->post_excerpt );
			$result = "[audio $files|titles=$titles]";

			if ( $choice == 'always' ) {
				return $result;
			}

			if ( $choice == 'media' ) {
				/* if a link to the media url is there, replace it. */
				if ( stripos( $html, 'href="' . $url . '"' ) ) {
					return $result;
				}
			}
			return $html;
		}
	}

	/* only load this if we're on a version of WP 3.5 or later */
	if ( version_compare( get_bloginfo( 'version' ), '3.5', '>=' ) ) {
		new MMWWAudioShortcode35();
	}
