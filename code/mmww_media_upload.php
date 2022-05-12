<?php

class MMWWMedia {

  /** a cache of metadata keyed on the name of the file */
  private $meta_cache_by_filename = [];

  private $post_id = 0;
  private $post = null;
  private $post_columns;

  function __construct() {
    /* set up the various filters etc. */

    /* metadata cleanup filters; internal to mmww */
    add_filter( 'mmww_filter_metadata', [ $this, 'add_tidy_metadata' ], 10, 1 );
    add_filter( 'mmww_filter_metadata', [ $this, 'remove_meaningless_metadata' ], 11, 1 );
    /* metadata display filters; internal to mmww */
    add_filter( 'mmww_format_metadata', [ $this, 'format_metadata' ], 12, 1 );
    /* attachment metadata specific */
    add_filter( 'wp_generate_attachment_metadata', [ $this, 'refetch_metadata' ], 10, 2 );
    add_filter( 'wp_update_attachment_metadata', [ $this, 'update_metadata' ], 10, 2 );
    add_filter( 'update_attached_file', [ $this, 'update_attached_file' ], 10, 2 );
    /* image metadata readers */
    add_filter( 'wp_read_image_metadata', [ $this, 'read_media_metadata' ], 11, 3 );
    add_filter( 'wp_read_image_metadata', [ $this, 'apply_template_metadata' ], 90, 3 );

    $this->post_columns = [
      'post_author',
      'post_date',
      'post_date_gmt',
      'post_content',
      'post_title',
      'post_excerpt',
      'post_status',
      'comment_status',
      'ping_status',
      'post_password',
      'post_name',
      'to_ping',
      'pinged',
      'post_modified',
      'post_modified_gmt',
      'post_content_filtered',
      'post_parent',
      'guid',
      'menu_order',
      'post_type',
      'post_mime_type',
      'comment_count',
    ];
  }

  /**
   * hook function for filter internal to mmww
   *
   * @param array $meta of metadata key/val strings
   *
   * @return array of metadata
   */
  function add_tidy_metadata( $meta ) {
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

    /* eliminate redundant items from the metadata  (Jetpack uses 'aperture' and 'shutter_speed')*/
    $tozap = [ 'warning' ];
    foreach ( $tozap as $zap ) {
      unset ( $meta[ $zap ] );
    }

    /* eliminate zero or empty items except title and caption */
    $keep = [ 'title' => 'yes', 'caption' => 'yes' ];
    foreach ( $meta as $key => $val ) {
      if ( ! array_key_exists( $key, $keep ) ) {
        if ( is_string( $val ) && strlen( $val ) == 0 ) {
          unset ( $meta[ $key ] );
        }
        if ( is_numeric( $val ) && $val == 0 ) {
          unset ( $meta[ $key ] );
        }
      }
    }

    return $meta;
  }

  /**
   * format the metadata according to  templates
   *
   * @param array $meta
   *
   * @return array of formatted metadata
   */
  function format_metadata( $meta ) {
    $codes   = [ 'title', 'caption', 'alt', 'displaycaption' ];
    $newmeta = [];
    foreach ( $codes as $code ) {
      $codetype = $meta['mmww_type'] . '_' . $code;
      $gen      = $this->make_string( $meta, $codetype );
      if ( ! empty( $gen ) ) {
        $newmeta[ $code ] = $gen;
      }
    }

    return $newmeta;
  }

  /**
   * make a desciption or caption string from the metadata and the template
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
   * @param int $id attachment id to process
   *
   * @return array usable attachment metadata
   */
  function refetch_metadata( $metadata, $id ) {
    $this->post_id = $id;
    /*
     * Note: sometimes WP asks us to reread the metadata.
     * $this->$meta_cache_by_filename gets us out of doing that,
     * to save file-slurping cpu and io time
     */
    if ( ! array_key_exists( 'image_meta', $metadata ) ) {
      /* no image_meta, we need to get it. */
      $file = get_attached_file( $id );
      if ( array_key_exists( $file, $this->meta_cache_by_filename ) ) {
        $image_meta = $this->meta_cache_by_filename[ $file ];
      } else {
        $image_meta = wp_read_image_metadata( $file );
      }

      if ( $image_meta ) {
        $metadata['image_meta'] = $image_meta;
      }
    }

    if ( 0 != $this->post_id ) {
      $this->post = get_post( $this->post_id );
    }

    $metadata['image_meta'] = $this->get_wp_tags( $metadata['image_meta'] );

    return $metadata;
  }

  /**
   * @param $metadata
   *
   * @return mixed
   */
  private function get_wp_tags( $metadata ) {
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

      return $metadata;
    }

    return $metadata;
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
      $updates = [];

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
         * default timezone.
         */
        $previous = date_default_timezone_get();
        @date_default_timezone_set( get_option( 'timezone_string' ) );
        $ltime                    = date( 'Y-m-d H:i:s', $meta['created_timestamp'] );
        $updates['post_date']     = $ltime;
        $ztime                    = gmdate( 'Y-m-d H:i:s', $meta['created_timestamp'] );
        $updates['post_date_gmt'] = $ztime;
        @date_default_timezone_set( $previous );
      }

      $updates = $this->get_wp_tags( $updates );
      $this->updatePost( $id, $updates );

      /* handle the image alt text (screenreader etc) which goes into a postmeta row */
      if ( ! empty( $meta['alt'] ) ) {
        update_post_meta( $id, '_wp_attachment_image_alt', $meta['alt'] );
      }

      /* stash tne metadata itself so we don't have to reread it from the file for site visitors */
      // need this? update_post_meta ($id, MMWW_POSTMETA_KEY, json_encode($meta));
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
   * filter to extend the stuff in wp_admin/includes/image.php
   *        and store the metadata in the right place.
   *        This function handles xmp, iptc, exif, png, and id3v2
   *        This function handles xmp, iptc, exif, png, and id3v2
   *        and so copes pretty well with pdf, mp3, jpg, png etc.
   *
   * @param array $meta associative array containing pre-loaded metadata
   * @param string $file file name
   * @param string $sourceImageType encoding of a few MIME types
   *
   * @return bool|array False on failure. Image metadata array on success.
   * @noinspection PhpUnusedParameterInspection
   */
  function read_media_metadata( $meta, $file, $sourceImageType ) {

    if ( ! file_exists( $file ) ) {
      return $meta;
    }

    /* if the metadata is cached, return it right away */
    if ( array_key_exists( $file, $this->meta_cache_by_filename ) ) {
      return $this->meta_cache_by_filename[ $file ];
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
      case 'audio':
        require_once 'id3.php';
        $readers[] = new MMWWID3Reader( $file );
        break;

      case 'image':
        require_once 'exif.php';
        require_once 'png.php';
        require_once 'iptc.php';
        $readers[] = new MMWWEXIFReader( $file );
        $readers[] = new MMWWPNGReader( $file );
        $readers[] = new MMWWIPTCReader( $file );
        break;

      case 'application':
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

    if ( 0 == $this->post_id ) {
      $this->post_id = $GLOBALS['post_id'];
      $this->post    = get_post( $this->post_id );
    }

    $meta_accum = $this->get_wp_tags( $meta_accum );

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
   * @param string $file file name
   * @param string $sourceImageType encoding of a few MIME types
   *
   * @return bool|array False on failure. Image metadata array on success.
   * @noinspection PhpUnusedParameterInspection
   */
  function apply_template_metadata( $meta, $file, $sourceImageType ) {
    if ( empty ( $meta ) || empty ( $meta['mmww_type'] ) ) {
      /* if there's no mmww metadata detected, don't do anything more */
      return $meta;
    }

    /* if the metadata is cached, return it right away */
    if ( array_key_exists( $file, $this->meta_cache_by_filename ) ) {
      return $this->meta_cache_by_filename[ $file ];
    }

    $cleanmeta = apply_filters( 'mmww_filter_metadata', $meta );

    /* $meta[caption] goes into wp_posts.post_content. This is shown as "description" in the UI.
     * $meta[title] goes into wp_posts.post_title. This is shown as "title"
     * we don't have a $meta item to go into wp_posts.post_excerpt. This is shown as "caption" in the UI.
     */

    $newmeta = apply_filters( 'mmww_format_metadata', $cleanmeta );

    $meta = array_merge( $cleanmeta, $newmeta );
    /* cache the resulting metadata */
    $this->meta_cache_by_filename[ $file ] = $meta;

    return $meta;
  }

  /** update the posts table as needed
   *
   * @param $id
   * @param $updates
   *
   * @return void
   * @noinspection GrazieInspection
   */
  private function updatePost( $id, $updates ) {
    global $wpdb;
    if ( empty ( $updates ) ) {
      return;
    }

    $fields = [];
    foreach ( $updates as $key => $value ) {
      if ( in_array( $key, $this->post_columns ) ) {
        $fields[ $key ] = $value;
      }
    }
    $where = [ 'ID' => $id ];
    $wpdb->update( $wpdb->posts, $fields, $where );
    clean_post_cache( $id );
  }

  /**
   * get an html table made of an item's metadata
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
}

new MMWWMedia();
