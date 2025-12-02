<?php

class MMWWEXIFReader {
  private $exif;

  function __construct( $file ) {
    unset ( $this->exif );
    // fetch additional info from exif if available
    if ( is_callable( 'exif_read_data' ) ) {
      $this->exif = @exif_read_data( $file, 'IFD0,FILE,EXIF,COMMENT' , false, false );
    }
  }

  function __destruct() {
    unset ( $this->exif );
  }

  public function get_metadata() {

    $meta = [];

    $exif = $this->exif;
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

      if ( ! empty( $exif['Copyright'] ) && strlen( $exif['Copyright'] ) > 0 ) {
        $meta['copyright'] = mb_convert_encoding(  trim( $exif['Copyright'] ), 'ISO-8859-1', 'UTF-8' );
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
      if ( ! empty( $meta['direction'] ) && empty( $exif['GPSImgDirectionRef'] ) ) {
        $meta['direction'] .= $exif['GPSImgDirectionRef'];
      }

      if ( ! empty( $exif['Model'] ) ) {
        $meta['camera'] = mb_convert_encoding( trim( $exif['Model'] ), 'ISO-8859-1', 'UTF-8' );
      }

      if ( ! empty( $exif['DateTimeDigitized'] ) ) {
        /* do the timezone stuff right; camera metadata is in local time */
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
        /* there's a bug where this is sometimes an array and sometimes a scalar */
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
   * convert a GPS exif reference to decimal degrees
   *
   * @param string $ref N, E, S, W
   * @param array of three strings showing rational numbes $c
   *
   * @return string with degree
   */
  private function getGPS( $ref, $c ) {
    $sign = 1;
    // south, west, or negative altitude
    if ( $ref[0] == 'S' || $ref[0] == 'W' ) {
      $sign = - 1;
    }

    $d      = wp_exif_frac2dec( $c[0] );
    $m      = wp_exif_frac2dec( $c[1] );
    $s      = wp_exif_frac2dec( $c[2] );
    $result = $sign * ( ( $d ) + ( $m / 60.0 ) + ( $s / 3600.0 ) );

    return round( $result, 6 );
  }
}