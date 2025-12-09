<?php

/** get metadata from PNG files
 * @link http://stackoverflow.com/questions/2190236/how-can-i-read-png-metadata-from-php
 * @link http://www.libpng.org/pub/png/spec/1.2/PNG-Contents.html
 * @link http://www.schaik.com/pngsuite/
 */

class MMWWPNGReader {
  private $_chunks;
  private $_fp;

  function __construct( $file ) {
    if ( ! file_exists( $file ) ) {
      $this->_fp = - 1;

      return;
    }

    $this->_chunks = [];

    // Open the file
    $this->_fp = fopen( $file, 'r' );

    if ( ! $this->_fp ) {
      $this->_fp = - 1;

      return;
    }

    // Read the magic bytes and verify
    $header = fread( $this->_fp, 8 );

    if ( $header != "\x89PNG\x0d\x0a\x1a\x0a" ) {
      /* not a PNG */
      fclose( $this->_fp );
      $this->_fp = - 1;

      return;
    }

    // Loop through the chunks. Byte 0-3 is length, Byte 4-7 is type
    $chunkHeader = fread( $this->_fp, 8 );

    while ( $chunkHeader ) {
      // Extract length and type from binary data
      $chunk           = @unpack( 'Nsize/a4type', $chunkHeader );
      $chunk['offset'] = ftell( $this->_fp );
      $this->_chunks[] = $chunk;
      // Skip to next chunk (over body and CRC)
      fseek( $this->_fp, $chunk['size'] + 4, SEEK_CUR );
      // Read next chunk header
      $chunkHeader = fread( $this->_fp, 8 );
    }
  }

  function __destruct() {
    if ( ! isset ( $this->_fp ) ) {
      fclose( $this->_fp );
      $this->_fp = - 1;
    }
    unset ( $this->_chunks );
  }

  // Returns all chunks of said type

  public function get_metadata() {
    $meta = [];
    if ( $this->_fp == - 1 ) {
      return $meta;
    }

    /* Get the image modification time if any. https://www.libpng.org/pub/png/spec/1.2/PNG-Chunks.html#C.tIME
     * We assume big-endian data. */
    try {
      $raw = $this->get_chunks( 'tIME' );
    } catch ( Exception $e ) {
      /* silently ignore failures to read metadata. */
      return $meta;
    }
    if ( is_array( $raw ) ) {
      foreach ( $raw as $data ) {
        if ( strlen( $data ) < 7 ) {
          /* Too short, bail. */
          continue;
        }
        try {
          $t = @unpack( 'nyear/Cmonth/Cday/Chour/Cmin/Csec', substr( $data, 0, 7 ) );
          if ( ! $t ) {
            continue;
          }
          $meta['modified_timestamp'] = mktime( $t['hour'], $t['min'], $t['sec'], $t['year'], $t['month'], $t['day'] );;
        } catch ( Exception $e ) {
          /* Ignore failures */
          unset ( $meta ['modified_timestamp'] );
        }
      }
    }
    $keylookup = [
      'Description'   => 'description',
      'Author'        => 'credit',
      'Title'         => 'title',
      'Copyright'     => 'copyright',
      'Creation Time' => 'creation time',
      'Software'      => 'writer',
      'Comment'       => 'description',
      'Device'        => 'camera',
    ];
    /* Get the tEXt chunks. https://www.libpng.org/pub/png/spec/1.2/PNG-Chunks.html#C.tEXt
     * Supposedly iso8859-1 text, but some programs jam utf-8 text in them. */
    try {
      $raw = $this->get_chunks( 'tEXt' );
    } catch ( Exception $e ) {
      /* silently ignore failures to read metadata. */
      return $meta;
    }

    if ( is_array( $raw ) ) {
      foreach ( $raw as $data ) {
        $sects    = explode( "\0", $data );
        $sections = array();
        foreach ( $sects as $sect ) {
          $encoding    = mb_detect_encoding( $sect );
          $sections [] = $encoding ? mb_convert_encoding( $sect, $encoding, 'UTF-8' ) : $sect;
        }
        if ( $sections > 1 ) {
          $key = array_shift( $sections );
          if ( array_key_exists( $key, $keylookup ) ) {
            $key = $keylookup[ $key ];
          } else {
            $key = strtolower( $key );
          }
          $meta[ $key ] = implode( '', $sections );
        }
      }
    }
    /* Handle i18n text. https://www.libpng.org/pub/png/spec/1.2/PNG-Chunks.html#C.iTXt */
    /* Get the tEXt chunks. Supposedly iso8859-1 text, but some programs jam utf-8 text in them. */
    try {
      $raw = $this->get_chunks( 'iTXt' );
    } catch ( Exception $e ) {
      /* silently ignore failures to read metadata. */
      return $meta;
    }

    if ( is_array( $raw ) ) {
      foreach ( $raw as $data ) {
        $sects = explode( "\0", $data, 2 );
        if ( count( $sects ) !== 2 ) {
          /* Bail out. */
          continue;
        }
        $key  = $sects[0];
        $data = $sects[1];
        if ( strlen( $data ) < 4 ) {
          /* Bail out if too short */
          continue;
        }

        $compression = @unpack( 'Cflag/Cmethod', substr( $data, 0, 2 ) );
        if ( 0 !== $compression['flag'] ) {
          /* Don't attempt to decompress text. */
          continue;
        }
        $data  = substr( $data, 2 );
        $sects = explode( "\0", $data );
        if ( count( $sects ) !== 3 ) {
          /* Bail out if unexpected */
          continue;
        }
        if ( array_key_exists( $key, $keylookup ) ) {
          $key = $keylookup[ $key ];
        } else {
          $key = strtolower( $key );
        }
        $meta[ $key ] = $sects[2];
      }
    }

    /* handle the creation time item */
    if ( array_key_exists( 'creation time', $meta ) ) {
      $timestamp = strtotime( $meta['creation time'] );
      if ( false !== $timestamp ) {
        $meta['created_timestamp'] = $timestamp;
        unset ( $meta['creation time'] );
      }
    }
    if ( ! isset( $meta['created_timestamp'] ) && isset( $meta['modified_timestamp'] ) ) {
      $meta['created_timestamp'] = $meta['modified_timestamp'];
    }

    return $meta;
  }

  private function get_chunks( $type ) {

    $chunks = [];

    foreach ( $this->_chunks as $chunk ) {
      if ( $type === $chunk['type'] ) {
        if ( $chunk['size'] > 0 ) {
          fseek( $this->_fp, $chunk['offset'], SEEK_SET );
          $chunks[] = fread( $this->_fp, $chunk['size'] );
        } else {
          $chunks[] = '';
        }
      }
    }

    return $chunks;
  }
}