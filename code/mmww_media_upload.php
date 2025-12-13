<?php

class MMWWMedia {

  private $post_id = 0;
  private $post = null;

  function __construct() {
    /* set up the various filters etc. */

    /* metadata cleanup filters; internal to mmww */
    add_filter( 'mmww_filter_metadata', [ $this, 'add_tidy_metadata' ], 10, 1 );
    add_filter( 'mmww_filter_metadata', [ $this, 'remove_meaningless_metadata' ], 11, 1 );
    /* metadata display filters; internal to mmww */
    add_filter( 'mmww_format_metadata', [ $this, 'format_metadata' ], 12, 1 );
    /* attachment metadata specific */
    add_filter( 'wp_generate_attachment_metadata', [ $this, 'refetch_metadata' ], 10, 3 );
    add_filter( 'wp_update_attachment_metadata', [ $this, 'update_metadata' ], 10, 2 );
    add_filter( 'wp_update_attachment_metadata', [ $this, 'apply_template_metadata' ], 60, 2 );

    add_filter( 'update_attached_file', [ $this, 'update_attached_file' ], 10, 2 );
    /* Media object type filers */
    add_filter( 'wp_read_image_metadata', [ $this, 'wp_read_image_metadata' ], 10, 5 );
    add_filter( 'wp_read_audio_metadata', [ $this, 'wp_read_audio_metadata' ], 10, 4 );
    add_filter( 'wp_read_video_metadata', [ $this, 'wp_read_video_metadata' ], 10, 4 );
    /* image metadata readers ... these only fire after handling image metadata. */
    //TODO put this in refetch_metadata add_filter( 'wp_read_image_metadata', [ $this, 'read_media_metadata' ], 11, 3 );

  }

  /**
   * hook function for filter internal to mmww
   *
   * @param array $meta of metadata key/val strings
   *
   * @return array of metadata
   */
  function add_tidy_metadata( $meta ) {

    foreach ( array( 'mmww_type', 'filename' ) as $item ) {
      $this->promote_item( $meta, $item );
    }
    /* get a creation time string from the timestamp */
    if ( ! empty ( $meta['created_timestamp'] ) ) {
      /* do the timezone stuff right; png creation time is in local time */
      $previous = date_default_timezone_get();
      @date_default_timezone_set( get_option( 'timezone_string' ) );
      $meta['created_time'] =
        date_i18n( get_option( 'date_format' ), $meta['created_timestamp'] ) . ' ' .
        date_i18n( get_option( 'time_format' ), $meta['created_timestamp'] );
      @date_default_timezone_set( $previous );
    }

    return $meta;
  }

  /**
   * hook function for filter internal to mmww
   *
   * @param array $meta of metadata key/val strings
   *
   * @return array of metadata
   */
  function remove_meaningless_metadata( $meta ) {

    if ( ! is_array( $meta ) ) {
      return $meta;
    }
    if ( isset( $meta['image_meta'] ) && is_array( $meta['image_meta'] ) ) {
      $this->cleanmeta( $meta['image_meta'] );
    }
    $this->cleanmeta( $meta );

    return $meta;
  }

  private function cleanmeta( &$meta ) {
    /* eliminate redundant items from the metadata  (Jetpack uses 'aperture' and 'shutter_speed')*/
    $tozap = [ 'warning' ];
    foreach ( $tozap as $zap ) {
      unset ( $meta[ $zap ] );
    }

    /* eliminate zero or empty items except title and caption */
    $keep = [ 'title' => 'yes', 'caption' => 'yes' ];
    foreach ( $meta as $key => $val ) {
      if ( ! array_key_exists( $key, $keep ) ) {
        if ( is_string( $val ) && 0 === strlen( $val ) ) {
          unset ( $meta[ $key ] );
        }
        if ( 0 === $val ) {
          unset ( $meta[ $key ] );
        }
      }
    }
  }

  /**
   * format the metadata according to templates
   *
   * @param array $meta
   *
   * @return array of formatted metadata
   */
  function format_metadata( $meta ) {
    $codes   = [ 'title', 'caption', 'alt', 'displaycaption' ];
    $newmeta = [];
    if ( ! is_array( $meta ) || ! isset( $meta['mmww_type'] ) ) {
      return $newmeta;
    }

    /* Flatten the metadata */
    $flat = $meta;
    if ( isset( $meta['image_meta'] ) && is_array( $meta['image_meta'] ) ) {
      $image = $meta['image_meta'];
      unset( $flat['image_meta'] );
      $flat = array_merge( $image, $flat );
    }

    foreach ( $codes as $code ) {
      $codetype = $meta['mmww_type'] . '_' . $code;
      $gen      = $this->make_string( $flat, $codetype );
      if ( ! empty( $gen ) ) {
        $newmeta[ $code ] = $gen;
      }
    }

    return $newmeta;
  }

  /**
   * make a description or caption string from the metadata and the template
   *
   * @param array $meta metadata array
   * @param string $item which template (e.g.  audio_caption)
   *
   * @return string description or caption string
   */
  private function make_string( $meta, $item ) {
    $options = get_option( 'mmww_options' );
    if ( ! array_key_exists( $item, $options ) ) {
      return null;
    }
    $t = ( empty( $options[ $item ] ) ) ? '' : $options[ $item ]; /* the template */

    require_once( 'mmwwtemplate.php' );
    $template = new MMWWTemplate( $meta );

    return $template->fillout( $t );
  }

  /**
   * refetch the attachment metadata for non-image file types (audio, PDF, etc)
   *
   * @param $metadata
   * @param int $id attachment id to process
   * @param string $context Additional context. Can be 'create' when metadata was initially created for new attachment
   *                               or 'update' when the metadata was updated.
   *
   * @return array usable attachment metadata
   */
  function refetch_metadata( $metadata, $id, $context ) {
    $this->post_id = $id;
    $file          = get_attached_file( $id );
    if ( is_array( $metadata['image_meta'] ) ) {
      $new_image_meta         = wp_read_image_metadata( $file );
      $new_image_meta         = is_array( $new_image_meta ) ? $new_image_meta : array();
      $metadata['image_meta'] = array_merge( $metadata['image_meta'], $new_image_meta );
    }
    if ( $this->post_id ) {
      $this->post = get_post( $this->post_id );
    }
    $this->get_wp_tags( $metadata );

    return $metadata;
  }

  /**
   * Load tags like wp:attachmentid and wp:parenttitle into the metadata.
   *
   * @param array $metadata
   *
   * @return void
   */
  private function get_wp_tags( &$metadata ) {
    if ( $this->post_id > 0 ) {
      /* store the post id for the attachment as {wp:attachmentid} */
      $metadata['wp:attachmentid'] = $this->post_id;
    }

    if ( isset( $this->post ) && $this->post->post_parent > 0 ) {
      /* store the post id for the attachment as {wp:attachmentid} */
      $metadata['wp:parentid']    = $this->post->post_parent;
      $parent                     = get_post( $this->post->post_parent );
      $metadata['wp:parenttitle'] = $parent->post_title;
      $metadata['wp:parentslug']  = $parent->post_name;

    }
  }

  /**
   * Function to handle extra stuff in attachment metadata update
   *
   * @param array $data attachment data array. Can be an empty array
   * @param int $id attachment id
   *
   * @return array data, modified as needed
   */
  function update_metadata( $data, $id ) {

    $this->post_id = $id;
    if ( ! empty ( $data ) && array_key_exists( 'image_meta', $data ) ) {
      $meta    = $data['image_meta'];
      $updates = [ 'ID' => $id ];

      /* handle the caption for photos, which goes into wp_posts.post_excerpt. */
      if ( ! empty( $meta['displaycaption'] ) ) {
        $updates['post_excerpt'] = $meta['displaycaption'];
      }

      /* update the attachment post_date and post_date_gmt if that's what the admin wants and the metadata has it */
      $options = get_option( 'mmww_options' );
      $choice  = ( empty( $options['use_creation_date'] ) ) ? 'no' : $options['use_creation_date'];
      if ( $choice == 'yes' && ! empty( $meta['created_timestamp'] ) ) {
        /* a note on timezones: WP mostly keeps the PHP default timezone set to
         * UTC and computes an offset between UTC and local time when it's needed.
         * That works fine for time = now.  But for incoming timestamps
         * (embedded in media), it's necessary to set the PHP timezone to the
         * user's timezone.  This is because the current timezone offset may not be
         * the same as the offset at the time in the incoming timestamp.
         * That is, for example, the incoming timestamp may have been when daylight
         * savings time was in force, but that may not be true at time=now.
         * Hence this monkey business with saving and restoring the
         * default timezone.   //TODO this is probably overwrought.
         */
        $previous = date_default_timezone_get();
        @date_default_timezone_set( get_option( 'timezone_string' ) );
        $ltime                    = date( 'Y-m-d H:i:s', $meta['created_timestamp'] );
        $updates['post_date']     = $ltime;
        $ztime                    = gmdate( 'Y-m-d H:i:s', $meta['created_timestamp'] );
        $updates['post_date_gmt'] = $ztime;
        @date_default_timezone_set( $previous );
      }

      wp_update_post( $updates );

      /* handle the image alt text (screenreader etc.) which goes into a postmeta row */
      if ( ! empty( $meta['alt'] ) ) {
        update_post_meta( $id, '_wp_attachment_image_alt', $meta['alt'] );
      }
    }

    return $data;
  }

  /**
   * We're using this filter simply to capture the post id.
   *
   * @param $file
   * @param $id  int  Attachment id
   *
   * @return string file as altered.
   */
  function update_attached_file( $file, $id ) {
    $this->post_id = $id;

    return $file;
  }

  /**
   * Filters the array of metadata read from an image's exif data.
   *
   * @param array $meta Image meta data.
   * @param string $file Path to image file.
   * @param int $image_type Type of image, one of the `IMAGETYPE_XXX` constants. 2 for jpg
   * @param array $iptc IPTC data.
   * @param array $exif EXIF data.
   *
   * @since 5.0.0 The `$exif` parameter was added.
   *
   * @since 2.5.0
   * @since 4.4.0 The `$iptc` parameter was added.
   */
  public function wp_read_image_metadata( $meta, $file, $image_type, $iptc, $exif ) {
    switch ( $image_type ) {
      case IMAGETYPE_PNG;
        require_once 'png.php';
        $reader = new MMWWPNGReader( $file );
        $new    = $reader->get_metadata();
        $meta   = array_merge( $new, $meta );

        break;
      default:
        if ( is_array( $exif ) && count( $exif ) > 0 ) {
          require_once 'exif.php';
          $reader = new MMWWEXIFReader( $exif );
          $new    = $reader->get_metadata();
          $meta   = array_merge( $new, $meta );
        }
        if ( is_array( $iptc ) && count( $iptc ) > 0 ) {
          require_once 'iptc.php';
          $reader = new MMWWIPTCReader( $iptc );

          $new  = $reader->get_metadata();
          $meta = array_merge( $new, $meta );
        }

        break;

    }

    require_once 'xmp.php';
    $reader = new MMWWXMPReader( $file );
    $new    = $reader->get_metadata();
    $meta   = array_merge( $new, $meta );

    $meta['mmww_type'] = 'image';
    $meta['filename']  = pathinfo( $file, PATHINFO_FILENAME );

    return wp_kses_post_deep( $meta );
  }

  /**
   * Filters the array of metadata retrieved from an audio file.
   *
   * In core, usually this selection is what is stored.
   * More complete data can be parsed from the `$data` parameter.
   *
   * @param array $meta Filtered audio metadata.
   * @param string $file Path to audio file.
   * @param string|null $file_format File format of audio, as analyzed by getID3.
   *                                 Null if unknown.
   * @param array $data Raw metadata from getID3.
   *
   * @since 6.1.0
   *
   */
  public function wp_read_audio_metadata( $meta, $file, $file_format, $data ) {
    $meta['filename'] = pathinfo( $file, PATHINFO_FILENAME );

    if ( 'mp4' === $file_format ) {
      if ( is_array( $data['tags']['quicktime'] ) ) {
        $s = &$data['tags']['quicktime'];
        foreach ( $s as $tag => $val_item ) {
          if ( ! isset( $meta[ $tag ] ) ) {
            if ( is_array( $val_item ) && is_string( $val_item[0] ) ) {
              $meta[ $tag ] = $val_item[0];
            }
          }
        }
      }
      if ( isset( $meta['creation_date'] ) && is_numeric( $meta['creation_date'] ) ) {
        $this->copy_string_item( $meta, 'creation_date', 'year' );
      }
    }
    $this->copy_string_item( $meta, 'length_formatted', 'duration' );
    $this->copy_string_item( $meta, 'artist', 'author' );
    $this->copy_string_item( $meta, 'author', 'artist' );


    $meta['mmww_type'] = 'audio';

    return $meta;
  }

  private function copy_string_item( &$array, $fr, $to, $source_array = null ) {
    $source_array = ( null === $source_array ) ? $array : $source_array;
    if ( ! is_array( $array ) ) {
      return;
    }
    if ( is_array( $source_array ) && isset( $source_array[ $fr ] ) && is_string( $source_array[ $fr ] ) && ! isset( $array[ $to ] ) ) {
      $array[ $to ] = $source_array[ $fr ];
    }
  }


  /**
   * Filters the array of metadata retrieved from a video.
   *
   *  In core, usually this selection is what is stored.
   *  More complete data can be parsed from the `$data` parameter.
   *
   * @param array $metadata Filtered video metadata.
   * @param string $file Path to video file.
   * @param string|null $file_format File format of video, as analyzed by getID3.
   *                                  Null if unknown.
   * @param array $data Raw metadata from getID3.
   *
   * @since 4.9.0
   *
   */
  public function wp_read_video_metadata( $meta, $file, $file_format, $data ) {
    /* Stub. Don't try to handle metadata */
    unset ( $meta['mmww_type'] );

    return $meta;
  }


  /**
   * filter to extend the stuff in wp_admin/includes/image.php
   *        and store the metadata in the right place.
   *        This function handles xmp, iptc, exif, png, and id3v2
   *        and so copes pretty well with pdf, mp3, jpg, png etc.
   *
   * This doesn't get hooked unless the file is a known type.
   *
   * @param array $meta associative array containing pre-loaded metadata
   * @param string $file file name
   * @param string $sourceImageType encoding of a few MIME types
   *
   * @return bool|array False on failure. Image metadata array on success.
   * @noinspection PhpUnusedParameterInspection
   */
  function read_media_metadata( $meta, $file, $sourceImageType ) {   //HACK HACK retire this?

    if ( ! file_exists( $file ) ) {
      return $meta;
    }

    /* figure out the filetype */
    $ft       = wp_check_filetype( $file );
    $filetype = $ft['type'];
    $filetype = $this->getfiletype( $filetype );

    /* figure out the file's leafname */

    /* create a media-specific ordered list of metadata readers
     * avoid doing the require operations unless
     * the code for the particular data type
     * is required -- this is a server-side operation.
     */
    $readers = [];
    switch ( $filetype ) {

      case 'image':
        require_once 'exif.php';
        require_once 'png.php';
        require_once 'iptc.php';
        $readers[] = new MMWWEXIFReader( $file );
        $readers[] = new MMWWPNGReader( $file );
        $readers[] = new MMWWIPTCReader( $file );
        break;

      case 'application':
        if ( 'application/pdf' !== $ft['type'] ) {
          return $meta;
        }
        /* this is for pdf. Processing below for that */
        break;

      default:
        $meta_accum['warning'] = __( 'Unrecognized media type in file ', 'mmww' ) . "$file ($filetype)";
    }

    require_once 'xmp.php';
    $readers[] = new MMWWXMPReader( $file );

    /* merge up the metadata  -- later merges overwrite earlier ones*/
    $meta_accum              = [];
    $tag_accum               = [];
    $meta_accum['mmww_type'] = $filetype;
    $meta_accum['filename']  = pathinfo( $file, PATHINFO_FILENAME );
    foreach ( $readers as $reader ) {
      if ( method_exists( $reader, 'get_audio_metadata' ) ) {
        $newmeta    = $reader->get_audio_metadata();
        $meta_accum = array_merge( $meta_accum, $newmeta );
      }
      $newmeta    = $reader->get_metadata();
      $meta_accum = array_merge( $meta_accum, $newmeta );

      if ( method_exists( $reader, 'get_tags' ) ) {
        $newtag    = $reader->get_tags();
        $tag_accum = array_merge( $tag_accum, $newtag );
      }
    }

    if ( 0 === $this->post_id ) {
      $this->post_id = $GLOBALS['post_id'];
      $this->post    = get_post( $this->post_id );
    }

    $this->get_wp_tags( $meta_accum );

    /** @noinspection PhpUnnecessaryLocalVariableInspection */
    $meta = array_merge( $meta, $meta_accum );

    /* handle tags */

    //TODO  put in the tag array to the resulting meta array.

    return $meta;
  }

  /**
   * turn audio/mpeg into audio, image/tiff into image, etc
   *
   * @param string $f MIME type
   *
   * @return string basic data type
   */
  private function getfiletype( $f ) {
    $ff = explode( '/', $f );

    return strtolower( $ff[0] );
  }

  /**
   * filter to use the metadata to construct title and caption
   *        using appropriate templates
   *
   * @param array $meta associative array containing pre-loaded metadata
   * @param int $id Post ID
   *
   * @return bool|array False on failure. Image metadata array on success.
   * @noinspection PhpUnusedParameterInspection
   */
  public function apply_template_metadata( $meta, $id ) {
    if ( empty ( $meta ) || empty ( $meta['mmww_type'] ) ) {
      /* if there's no mmww metadata detected, don't do anything more */
      return $meta;
    }

    $cleanmeta = apply_filters( 'mmww_filter_metadata', $meta );

    /* $meta[caption] goes into wp_posts.post_content. This is shown as "description" in the UI.
     * $meta[title] goes into wp_posts.post_title. This is shown as "title"
     * we don't have a $meta item to go into wp_posts.post_excerpt. This is shown as "caption" in the UI.
     */

    $newmeta = apply_filters( 'mmww_format_metadata', $cleanmeta );

    $meta = array_merge( $cleanmeta, $newmeta );

    return $meta;
  }

  /**
   * get a html table made of an item's metadata
   *
   * @param array $meta of metadata strings
   *
   * @return string html
   */
  private function get_metadata_table( $meta ) {
    /* filter the metadata for display according to the MIME type, extensibly */
    $meta   = apply_filters( 'mmww_filter_metadata', $meta );
    $string = '<table><tr><td>tag</td><td>value</td></tr>' . "\n";
    foreach ( $meta as $tag => $value ) {
      $string .= '<tr><td>' . $tag . '</td><td>' . $value . '</td></tr>' . "\n";
    }
    $string .= '</table>' . "\n";

    return $string;
  }

  public function get_iptc( $meta, $exif ) {
    return;  //stub
  }

  /**
   * Convert a GPS exif reference to decimal degrees
   *
   * EXIF represents values with rational numbers in strings.
   * For example the string "9/2" is used to represent 3.5.
   *
   * @param string $ref N, E, S, W
   * @param array $c One to three strings of rational numbers: degrees, degrees/minutes, or degrees/minutes/seconds.
   *
   * @return float Degrees.
   */
  private function getGPS( $ref, $c ) {
    // south, west, or negative altitude
    $sign = ( $ref[0] == 'S' || $ref[0] == 'W' ) ? - 1 : 1;
    $val  = 0.0;
    while ( $r = array_pop( $c ) ) {
      $val /= 60.0;
      $val += wp_exif_frac2dec( $r );
    }

    return round( $sign * $val, 6 );
  }

  private function promote_item( &$meta, $item, $nest = 'image_meta' ) {
    /* If we have image metadata move  the item. */
    if ( isset( $meta[ $nest ][ $item ] ) && is_string( $meta[ $nest ][ $item ] ) ) {
      $meta[ $item ] = $meta[ $nest ][ $item ];
      unset( $meta[ $nest ][ $item ] );
    }


  }
}

new MMWWMedia();
