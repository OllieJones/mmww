<?php
/**
 * class for inserting audio shortcodes for WordPress 3.4.2 and before.
 */

class MMWWAudioShortcode34 {

  function __construct() {
    add_filter( 'attachment_fields_to_edit', [ $this, 'fields_to_edit' ], 20, 2 );
    add_filter( 'media_send_to_editor', [ $this, 'send_to_editor' ], 11, 3 );
  }

  /**
   *  Edit fields in the media upload editor
   *
   * @param associative array of fields $file
   * @param associative array describing $post
   *
   * @return updated array of fields
   * @version 3.4 and earlier
   */
  function fields_to_edit( $fields, $post ) {
    $mime = get_post_mime_type( $post->ID );

    /* this field is only needed when embedding an audio player for an attachment */
    /* this $fields['url'] array element is not present in WP 3.5 and beyond */
    if ( isset( $fields['url'] ) && isset( $fields['url']['html'] ) ) {
      if ( $mime == 'audio/mpeg' ) {
        /* 3.4.x and before: audio file; insert a player button matching the Jetpack [audio] shortcode syntax */
        $url       = wp_get_attachment_url( $post->ID );
        $files     = esc_attr( $url );
        $titles    = $this->nocommas( $post->post_excerpt );
        $playertag = "[audio $files|titles=$titles]";
        /* translators: name of a UI button element in the insert media popup */
        $playerbuttonname = _x( 'Audio Player', 'button_name', 'mmww' );
        $postid           = $post->ID;

        $fields['url']['html'] .=
          "<button type='button' class='button urlaudioplayer audio-player-$postid' data-link-url='$playertag'>" .
          "$playerbuttonname</button>";
      }
    }

    return $fields;
  }

  /**
   * remove all commas and apostrophes from a string
   *
   * @param string $s
   *
   * @return string with blanks where commas and apostrophes were.
   */
  private function nocommas( $s ) {
    $t = implode( ' ', explode( ', ', $s ) );
    $t = implode( ' ', explode( ',', $t ) );
    $t = implode( '', explode( "'", $t ) );

    return $t;
  }

  /**
   * adjust hyperlinks and other embed codes for audio shortcode upon sending them to the editor
   *
   * @param string $html the string to be inserted in the editor, filtered
   * @param int $attachment_id attachment id
   * @param array $attachment
   *
   * @return filtered html string
   * @since 2.5.0
   *
   * this depends on the user having selected the Audio Player button in the media manager.
   *
   */
  function send_to_editor( $html, $attachment_id, $attachment ) {
    $post = get_post( $attachment_id );
    /* double check we're in the right neighborhood before bashing the html */
    $mime = $post->post_mime_type;
    if ( $mime == 'audio/mpeg' ) {
      $meta   = [];
      $result = '';
      $url    = wp_get_attachment_url( $post->ID );
      $files  = esc_attr( $url );
      /* the [audio file titles and artists lists are comma separated in Jetpack shortcode, so scrub embedded commas */
      $titles = $this->nocommas( $post->post_excerpt );
      $result = "[audio $files|titles=$titles]";

      if ( stripos( $html, '[audio ' ) ) {
        /* correct mime type AND attempt to post an audio shortcode. */
        /* filter the metadata for display according to the MIME type, extensibly */
        /* audio file; insert a player button matching the Jetpack [audio] shortcode syntax */
        $html = $result;
      }
    }

    return $html;
  }
}

/* only load this if we're on a version of WP prior to 3.5 */
if ( version_compare( get_bloginfo( 'version' ), '3.5', '<' ) ) {
  new MMWWAudioShortcode34();
}
