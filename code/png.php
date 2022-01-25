<?php

/** get metadata from PNG files
 * @link http://stackoverflow.com/questions/2190236/how-can-i-read-png-metadata-from-php
 * @link http://www.libpng.org/pub/png/spec/1.2/PNG-Contents.html
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
      $chunk = @unpack( 'Nsize/a4type', $chunkHeader );

      // Store position into internal array
      if ( ! array_key_exists( $chunk['type'], $this->_chunks ) ) {
        $this->_chunks[ $chunk['type'] ] = [];
      }
      $this->_chunks[ $chunk['type'] ][] = [
        'offset' => ftell( $this->_fp ),
        'size'   => $chunk['size'],
      ];

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
    $keylookup = [
      'Description' => 'description',
      'Author'      => 'credit',
      'Title'       => 'title',
      'Copyright'   => 'copyright',
    ];
    try {
      $png         = $this;
      $rawTextData = $png->get_chunks( 'tEXt' );
    } catch ( Exception $e ) {
      /* silently ignore failures to read metadata. */
      return $meta;
    }

    foreach ( $rawTextData as $data ) {
      $sections = explode( "\0", $data );

      if ( $sections > 1 ) {
        $key = array_shift( $sections );
        if ( array_key_exists( $key, $keylookup ) ) {
          $key = $keylookup[ $key ];
        } else {
          $key = strtolower( $key );
        }
        $meta[ $key ] = implode( "\0", $sections );
      } else {
        $meta[] = $data;
      }
    }
    /* handle the creation time item */
    if ( array_key_exists( 'creation time', $meta ) ) {
      /* do the timezone stuff right; png creation time is in local time */
      $previous = date_default_timezone_get();
      @date_default_timezone_set( get_option( 'timezone_string' ) );
      $meta['created_timestamp'] = strtotime( $meta['creation time'] );
      @date_default_timezone_set( $previous );
      unset ( $meta['creation time'] );
    }

    return $meta;
  }

  private function get_chunks( $type ) {
    if ( $this->_chunks[ $type ] === null ) {
      return null;
    }

    $chunks = [];

    foreach ( $this->_chunks[ $type ] as $chunk ) {
      if ( $chunk['size'] > 0 ) {
        fseek( $this->_fp, $chunk['offset'], SEEK_SET );
        $chunks[] = fread( $this->_fp, $chunk['size'] );
      } else {
        $chunks[] = '';
      }
    }

    return $chunks;
  }
}