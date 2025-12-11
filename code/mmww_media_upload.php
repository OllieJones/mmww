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
    add_filter( 'wp_update_attachment_metadata', [ $this, 'apply_template_metadata' ], 10, 60 );

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

    return $meta;
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
    if ( is_array( $metadata['image_meta'])) {
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
        $meta   = $reader->get_metadata();

        break;
      default:
        break;
    }

    if ( is_array( $exif ) && count( $exif ) > 0 ) {
      $this->get_exif( $meta, $exif );
    }

    if ( is_array( $iptc ) && count( $iptc ) > 0 ) {
      $this->get_iptc( $meta, $iptc );
    }


    /*  require_once 'exif.php';
      require_once 'iptc.php';
      $readers[] = new MMWWEXIFReader( $file );
      require_once 'png.php';
      $readers[] = new MMWWPNGReader( $file );
      $readers[] = new MMWWIPTCReader( $file ); */

    return wp_kses_post_deep( $meta );
  }

  /**
   * Filters the array of metadata retrieved from an audio file.
   *
   * In core, usually this selection is what is stored.
   * More complete data can be parsed from the `$data` parameter.
   *
   * @param array $metadata Filtered audio metadata.
   * @param string $file Path to audio file.
   * @param string|null $file_format File format of audio, as analyzed by getID3.
   *                                 Null if unknown.
   * @param array $data Raw metadata from getID3.
   *
   * @since 6.1.0
   *
   */
  public function wp_read_audio_metadata( $metadata, $file, $file_format, $data ) {
    $metadata['filename'] = pathinfo( $file, PATHINFO_FILENAME );

    if ( 'mp4' === $file_format ) {
      if ( is_array( $data['tags']['quicktime'] ) ) {
        $s = &$data['tags']['quicktime'];
        foreach ( $s as $tag => $val_item ) {
          if ( ! isset( $metadata[ $tag ] ) ) {
            if ( is_array( $val_item ) && is_string( $val_item[0] ) ) {
              $metadata[ $tag ] = $val_item[0];
            }
          }
        }
      }
      if ( isset( $metadata['creation_date'] ) && is_numeric( $metadata['creation_date'] ) ) {
        $this->copy_string_item( $metadata, 'creation_date', 'year' );
      }
    }
    $this->copy_string_item( $metadata, 'length_formatted', 'duration' );
    $this->copy_string_item( $metadata, 'artist', 'author' );
    $this->copy_string_item( $metadata, 'author', 'artist' );


    return $metadata;
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
  public function wp_read_video_metadata( $metadata, $file, $file_format, $data ) {
    return $metadata;
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
  function read_media_metadata( $meta, $file, $sourceImageType ) {

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

  public function get_iptc ($meta, $exif ) {
    return;  //stub
  }

  public function get_exif( &$meta, $exif ) {

    if ( ! empty( $exif ) ) {

      if ( ! empty ( $exif['UndefinedTag:0xEA1C'] ) &&
           ! empty( $exif['Title'] ) && $exif['Title'][0] == chr( 0x3f )
      ) {

        $meta['warning'] = __( 'EXIF metadata corrupted by Microsoft Windows properties defect', 'mmww' );

        /* deal with the bogus junk that MS's property editor puts into EXIF  */
        if ( ! empty( $exif['ImageDescription'] ) ) {
          // Assume the title is stored in ImageDescription
          $tempString    = substr( trim( $exif['ImageDescription'] ), 0, 80 );
          $meta['title'] = mb_convert_encoding( $tempString, 'ISO-8859-1', 'UTF-8' );
          if ( ! empty( $exif['COMPUTED']['UserComment'] ) && trim( $exif['COMPUTED']['UserComment'] ) != $meta['title'] ) {
            $tempString          = trim( $exif['COMPUTED']['UserComment'] );
            $meta['description'] = mb_convert_encoding( $tempString, 'ISO-8859-1', 'UTF-8' );
          } else {
            $tempString          = trim( $exif['ImageDescription'] );
            $meta['description'] = mb_convert_encoding( $tempString, 'ISO-8859-1', 'UTF-8' );
          }
        } elseif ( ! empty( $exif['Comments'] ) ) {
          $tempString          = trim( $exif['Comments'] );
          $meta['description'] = mb_convert_encoding( $tempString, 'ISO-8859-1', 'UTF-8' );
          $meta['title']       = '';
        }
      }

      /* do the version */
      if ( ! empty( $exif['ExifVersion'] ) ) {
        $meta['exifversion'] = floatval( $exif['ExifVersion'] ) / 100 . '';
      }

      if ( ( ! empty( $exif['GPSLongitudeRef'] ) ) &&
           ( ! empty( $exif['GPSLongitude'] ) ) &&
           ( ! empty( $exif['GPSLatitudeRef'] ) ) &&
           ( ! empty( $exif['GPSLatitude'] ) )
      ) {
        // looks like we have valid lat/long data
        $meta['longitude'] = $this->getGPS( $exif['GPSLongitudeRef'], $exif['GPSLongitude'] );
        $meta['latitude']  = $this->getGPS( $exif['GPSLatitudeRef'], $exif['GPSLatitude'] );
      }

      if ( ! empty( $exif['GPSAltitude'] ) ) {
        $meta['altitude'] = round( wp_exif_frac2dec( $exif['GPSAltitude'] ), 1 );
      }

      if ( ! empty ( $meta['altitude'] ) && ! empty( $exif['GPSAltitudeRef'] ) ) {
        if ( intval( $exif['GPSAltitudeRef'] ) != 0 ) {
          $meta['altitude'] = '-' . $meta['altitude'];
        }
      }

      if ( ! empty( $exif['SubjectDistance'] ) ) {
        $meta['subject_distance'] = round( wp_exif_frac2dec( $exif['SubjectDistance'] ), 1 );
      }

      if ( ! empty( $exif['ExposureBiasValue'] ) ) {
        $meta['exposure_bias'] = round( wp_exif_frac2dec( $exif['ExposureBiasValue'] ), 1 );
      }

      if ( ! empty( $exif['GPSImgDirection'] ) ) {
        $meta['direction'] = round( wp_exif_frac2dec( $exif['GPSImgDirection'] ), 1 );
      }

      /* T for true or M for magnetic direction */
      if ( ! empty( $meta['direction'] ) && ! empty( $exif['GPSImgDirectionRef'] ) ) {
        $meta['direction'] .= $exif['GPSImgDirectionRef'];
      }

      if ( ! empty( $exif['DateTimeDigitized'] ) ) {
        /* do the timezone stuff right; camera metadata is in local time */   //TODO UndefinedTag:0x9012 has the tz offset.
        $previous = date_default_timezone_get();
        @date_default_timezone_set( get_option( 'timezone_string' ) );
        $meta['created_timestamp'] = wp_exif_date2ts( $exif['DateTimeDigitized'] );
        @date_default_timezone_set( $previous );
      }
      if ( empty( $meta['created_timestamp'] ) && ! empty( $exif['FileDateTime'] ) ) {
        $meta['created_timestamp'] = $exif['FileDateTime'];
      }
      if ( ! empty( $exif['FocalLength'] ) ) {
        $meta['focal_length'] = wp_exif_frac2dec( $exif['FocalLength'] );
      }

      if ( ! empty( $exif['FocalLengthIn35mmFilm'] ) ) {
        $meta['focal_length35'] = wp_exif_frac2dec( $exif['FocalLengthIn35mmFilm'] );
      }

      if ( array_key_exists( 'Flash', $exif ) ) {
        /* translators: renderings of the EXIF flash codes*/
        $flash_data    = [
          0x0  => _x( 'No Flash', 'flash', 'mmww' ),
          0x1  => _x( 'Fired', 'flash', 'mmww' ),
          0x5  => _x( 'Fired, Return not detected', 'flash', 'mmww' ),
          0x7  => _x( 'Fired, Return detected', 'flash', 'mmww' ),
          0x8  => _x( 'On, Did not fire', 'flash', 'mmww' ),
          0x9  => _x( 'On, Fired', 'flash', 'mmww' ),
          0xd  => _x( 'On, Return not detected', 'flash', 'mmww' ),
          0xf  => _x( 'On, Return detected', 'flash', 'mmww' ),
          0x10 => _x( 'Off, Did not fire', 'flash', 'mmww' ),
          0x14 => _x( 'Off, Did not fire, Return not detected', 'flash', 'mmww' ),
          0x18 => _x( 'Auto, Did not fire', 'flash', 'mmww' ),
          0x19 => _x( 'Auto, Fired', 'flash', 'mmww' ),
          0x1d => _x( 'Auto, Fired, Return not detected', 'flash', 'mmww' ),
          0x1f => _x( 'Auto, Fired, Return detected', 'flash', 'mmww' ),
          0x20 => _x( 'No flash function', 'flash', 'mmww' ),
          0x30 => _x( 'Off, No flash function', 'flash', 'mmww' ),
          0x41 => _x( 'Fired, Red-eye reduction', 'flash', 'mmww' ),
          0x45 => _x( 'Fired, Red-eye reduction, Return not detected', 'flash', 'mmww' ),
          0x47 => _x( 'Fired, Red-eye reduction, Return detected', 'flash', 'mmww' ),
          0x49 => _x( 'On, Red-eye reduction', 'flash', 'mmww' ),
          0x4d => _x( 'On, Red-eye reduction, Return not detected', 'flash', 'mmww' ),
          0x4f => _x( 'On, Red-eye reduction, Return detected', 'flash', 'mmww' ),
          0x50 => _x( 'Off, Red-eye reduction', 'flash', 'mmww' ),
          0x58 => _x( 'Auto, Did not fire, Red-eye reduction', 'flash', 'mmww' ),
          0x59 => _x( 'Auto, Fired, Red-eye reduction', 'flash', 'mmww' ),
          0x5d => _x( 'Auto, Fired, Red-eye reduction, Return not detected', 'flash', 'mmww' ),
          0x5f => _x( 'Auto, Fired, Red-eye reduction, Return detected', 'flash', 'mmww' ),
        ];
        $meta['flash'] = $flash_data[ intval( $exif['Flash'] ) ];
      }

      if ( array_key_exists( 'SceneCaptureType', $exif ) ) {
        /* translators: renderings of the EXIF scene capture type http://www.sno.phy.queensu.ca/~phil/exiftool/TagNames/EXIF.html*/
        $scenecap_data              = [
          0 => _x( 'Standard', 'scene_capture_type', 'mmww' ),
          1 => _x( 'Landscape', 'scene_capture_type', 'mmww' ),
          2 => _x( 'Portrait', 'scene_capture_type', 'mmww' ),
          3 => _x( 'Night', 'scene_capture_type', 'mmww' ),
        ];
        $meta['scene_capture_type'] = $scenecap_data[ intval( $exif['SceneCaptureType'] ) ];
      }

      if ( array_key_exists( 'Sharpness', $exif ) ) {
        /* translators: renderings of the EXIF sharpness type http://www.sno.phy.queensu.ca/~phil/exiftool/TagNames/EXIF.html*/
        $sharp_data        = [
          0 => _x( 'Normal', 'sharpness', 'mmww' ),
          1 => _x( 'Soft', 'sharpness', 'mmww' ),
          2 => _x( 'Hard', 'sharpness', 'mmww' ),
        ];
        $meta['sharpness'] = $sharp_data[ intval( $exif['Sharpness'] ) ];
      }

      if ( array_key_exists( 'LightSource', $exif ) ) {
        /* translators: renderings of the EXIF light source codes http://www.sno.phy.queensu.ca/~phil/exiftool/TagNames/EXIF.html#LightSource*/
        $light_source_data   = [
          0   => _x( 'Unknown', 'light_source', 'mmww' ),
          1   => _x( 'Daylight', 'light_source', 'mmww' ),
          2   => _x( 'Fluorescent', 'light_source', 'mmww' ),
          3   => _x( 'Tungsten', 'light_source', 'mmww' ),
          4   => _x( 'Flash', 'light_source', 'mmww' ),
          9   => _x( 'Fine weather', 'light_source', 'mmww' ),
          10  => _x( 'Cloudy weather', 'light_source', 'mmww' ),
          11  => _x( 'Shade', 'light_source', 'mmww' ),
          12  => _x( 'Daylight fluorescent', 'light_source', 'mmww' ),
          13  => _x( 'Day white fluorescent', 'light_source', 'mmww' ),
          14  => _x( 'Cool white fluorescent', 'light_source', 'mmww' ),
          15  => _x( 'White fluorescent', 'light_source', 'mmww' ),
          17  => _x( 'Standard light A', 'light_source', 'mmww' ),
          18  => _x( 'Standard light B', 'light_source', 'mmww' ),
          19  => _x( 'Standard light C', 'light_source', 'mmww' ),
          20  => _x( 'D55', 'light_source', 'mmww' ),
          21  => _x( 'D65', 'light_source', 'mmww' ),
          22  => _x( 'D75', 'light_source', 'mmww' ),
          23  => _x( 'D50', 'light_source', 'mmww' ),
          24  => _x( 'ISO studio tungsten', 'light_source', 'mmww' ),
          255 => _x( 'Other light source', 'light_source', 'mmww' ),
        ];
        $meta['lightsource'] = $light_source_data[ intval( $exif['LightSource'] ) ];
      }

      if ( array_key_exists( 'MeteringMode', $exif ) ) {
        /* translators: these are human-readable renderings of the EXIF metering mode codes */
        $metering_mode_data   = [
          0   => _x( 'Unknown', 'metering_mode', 'mmww' ),
          1   => _x( 'Average', 'metering_mode', 'mmww' ),
          2   => _x( 'Center weighted average', 'metering_mode', 'mmww' ),
          3   => _x( 'Spot', 'metering_mode', 'mmww' ),
          4   => _x( 'Multi Spot', 'metering_mode', 'mmww' ),
          5   => _x( 'Pattern', 'metering_mode', 'mmww' ),
          6   => _x( 'Partial', 'metering_mode', 'mmww' ),
          255 => _x( 'Other', 'metering_mode', 'mmww' ),
        ];
        $meta['meteringmode'] = $metering_mode_data[ intval( $exif['MeteringMode'] ) ];
      }

      if ( array_key_exists( 'SensingMethod', $exif ) ) {
        /* translators: these are human-readable renderings of the EXIF sensor type codes */
        $sensing_method_data   = [
          2 => _x( 'One-chip color area sensor', 'sensing_method', 'mmww' ),
          3 => _x( 'Two-chip color area sensor', 'sensing_method', 'mmww' ),
          4 => _x( 'Three-chip color area sensor', 'sensing_method', 'mmww' ),
          5 => _x( 'Color sequential area sensor', 'sensing_method', 'mmww' ),
          7 => _x( 'Trilinear sensor', 'sensing_method', 'mmww' ),
          8 => _x( 'Color sequential linear sensor', 'sensing_method', 'mmww' ),
        ];
        $meta['sensingmethod'] = $sensing_method_data[ intval( $exif['SensingMethod'] ) ];
      }

      if ( array_key_exists( 'ExposureMode', $exif ) ) {
        /* translators: these are human-readable renderings of the EXIF exposure mode codes */
        $exposure_mode_data   = [
          0 => _x( 'Auto', 'exposure_mode', 'mmww' ),
          1 => _x( 'Manual', 'exposure_mode', 'mmww' ),
          2 => _x( 'Auto bracket', 'exposure_mode', 'mmww' ),
        ];
        $meta['exposuremode'] = $exposure_mode_data[ intval( $exif['ExposureMode'] ) ];
      }

      if ( array_key_exists( 'ExposureProgram', $exif ) ) {
        /* translators: these are human-readable renderings of the EXIF exposure program codes */
        $exposure_program_data    = [
          1 => _x( 'Manual', 'exposure_program', 'mmww' ),
          2 => _x( 'Normal Program', 'exposure_program', 'mmww' ),
          3 => _x( 'Aperture Priority', 'exposure_program', 'mmww' ),
          4 => _x( 'Shutter Priority', 'exposure_program', 'mmww' ),
          5 => _x( 'Creative Program', 'exposure_program', 'mmww' ),
          6 => _x( 'Action Program', 'exposure_program', 'mmww' ),
          7 => _x( 'Portrait Mode', 'exposure_program', 'mmww' ),
          8 => _x( 'Landscape Mode', 'exposure_program', 'mmww' ),
        ];
        $meta['exposure_program'] = $exposure_program_data[ intval( $exif['ExposureProgram'] ) ];
      }

      if ( ! empty( $exif['BrightnessValue'] ) ) {
        $meta['brightness'] = round( wp_exif_frac2dec( $exif['BrightnessValue'] ), 2 );
      }

      if ( ! empty( $exif['FNumber'] ) ) {
        $meta['fstop'] = 'f/' . round( wp_exif_frac2dec( $exif['FNumber'] ), 1 );
      }

      if ( ! empty( $exif['ISOSpeedRatings'] ) ) {
        $val     = '';
        $isoitem = $exif['ISOSpeedRatings'];
        /* This is sometimes an array and sometimes a scalar. If an array we'll flatten it. */
        if ( is_array( $isoitem ) ) {
          $isoitems = [];
          foreach ( $isoitem as $isodetail ) {
            $isoitems[] = sprintf( '%d', $isodetail );
          }
          $val = implode( '/', $isoitems );
        } else {
          $val = sprintf( '%d', $isoitem );
        }
        $meta['iso'] = $val;
      }

      if ( ! empty( $exif['ExposureTime'] ) ) {
        $exposure = wp_exif_frac2dec( $exif['ExposureTime'] );
        if ( $exposure < 0.51 ) {
          $meta['shutter'] = '1/' . round( ( 1.0 / $exposure ), 1 );
        } else if ( $exposure < 2.01 ) {
          $meta['shutter'] = round( $exposure, 1 );
        } else {
          $meta['shutter'] = round( $exposure, 0 );
        }
      }
    }

    return $meta;
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
}

new MMWWMedia();
