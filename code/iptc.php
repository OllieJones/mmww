<?php

class MMWWIPTCReader {

  private $iptc;

  /* table of iptc data items and indexes */

  /* the following data items show up in xmp, so see xmp.php
    {iptc:creator:address}      The creator's address.
    {iptc:creator:city}         The creator's city.
    {iptc:creator:state}        The creator's state or province.
    {iptc:creator:postcode}   The creator's post / zip code.
    {iptc:creator:country}      The creator's country.
    {iptc:creator:phone}        The creator's phone(s).
    {iptc:creator:email}        The creator's email(s).
    {iptc:creator:website}      The creator's web site(s).
    {iptc:iptcsubjectcode}      IPTC subject code.
    {iptc:genre}                Intellectual genre.
    {iptc:scenecode}            IPTC scene code.
    {iptc:copyrightstatus}      Copyright Status.
    {iptc:rightsusageterms}     Terms of usage.
      */


  /* 2#055 date, 2#056 hhmmss time with +xxxx */
  /* {iptc:datecreated}          Creation date. */

  private $taglist = [
    [ '2#080', 'iptc:creator' ],          /* The creator's name. */
    [ '2#085', 'iptc:creator:jobtitle' ], /* The creator's job title. */
    [ '2#105', 'iptc:headline' ],         /* Headline. */
    [ '2#120', 'iptc:description' ],      /* Description. */
    [ '2#112', 'iptc:descriptionwriter' ],/* Author of the description. */
    [ '2#025', 'iptc:keywords' ],         /* Keywords, separated with comma or semicolon. */
    [ '2#092', 'iptc:sublocation' ],      /* Location within city. */
    [ '2#090', 'iptc:city' ],             /* City. */
    [ '2#095', 'iptc:state' ],            /* State/Province. */
    [ '2#101', 'iptc:country' ],          /* Country. */
    [ '2#100', 'iptc:iscocountrycode' ],  /* Country code per ISO 3166. */
    [ '2#005', 'iptc:title' ],            /* Title. */
    [ '2#103', 'iptc:jobidentifier' ],    /* Job Identifier. */
    [ '2#040', 'iptc:instructions' ],     /* Instructions. */
    [ '2#110', 'iptc:creditline' ],       /* Credit line. */
    [ '2#115', 'iptc:source' ],           /* Source. */
    [ '2#116', 'iptc:copyright' ],        /* Copyright Notice. */
  ];


  function __construct( $init ) {
    if ( is_array( $init ) ) {
      $this->iptc = $init;
    } else {
      $this->iptc = false;
      // fetch additional info from iptc if available
      if ( is_callable( 'iptcparse' ) ) {
        getimagesize( $init, $info );
        if ( array_key_exists( 'APP13', $info ) ) {
          $this->iptc = iptcparse( $info['APP13'] );
        }
      }
    }
  }

  function __destruct() {
    $this->iptc = false;
  }

  /**
   * fetch metadata from an APP13 segment in an iptc data item in a file.
   * @return array of metadata strings, or an empty array if no app13 data was found.
   */
  public function get_metadata() {
    $meta = [];
    // read iptc, since it might contain data not available in exif such
    // as caption, description etc

    if ( ! ( false === $this->iptc ) ) {
      $iptc = $this->iptc;

      /* do the list of IPTC items */
      foreach ( $this->taglist as $pair ) {
        $tag   = $pair[1];
        $index = $pair[0];
        if ( ! empty( $iptc[ $index ][0] ) ) {
          $tempString   = $iptc[ $index ][0];
          $meta[ $tag ] = $this->make_utf8( $tempString );
        }
      }

      /* do the specific items */
      // headline, "A brief synopsis of the caption."
      if ( ! empty( $iptc['2#105'][0] ) ) {
        $tempString    = trim( $iptc['2#105'][0] );
        $meta['title'] = $this->make_utf8( $tempString );
      } // title, "Many use the Title field to store the filename of the image, though the field may be used in many ways."
      elseif ( ! empty( $iptc['2#005'][0] ) ) {
        $tempString    = trim( $iptc['2#005'][0] );
        $meta['title'] = $this->make_utf8( $tempString );
      }
      if ( ! empty( $iptc['2#120'][0] ) ) { // description / legacy caption
        $tempString = trim( $iptc['2#120'][0] );
        $caption    = $this->make_utf8( $tempString );
        if ( empty( $meta['title'] ) ) {
          // Assume the title is stored in 2:120 if it's short.
          if ( strlen( $caption ) < 80 ) {
            $meta['title'] = $caption;
          } else {
            $meta['description'] = $caption;
          }
        } elseif ( $caption != $meta['title'] ) {
          $meta['description'] = $caption;
        }
      }

      if ( ! empty( $iptc['2#110'][0] ) ) // credit
      {
        $meta['credit'] = $this->make_utf8( trim( $iptc['2#110'][0] ) );
      } elseif ( ! empty( $iptc['2#080'][0] ) ) // creator / legacy byline
      {
        $meta['credit'] = $this->make_utf8( trim( $iptc['2#080'][0] ) );
      }

      /* https://exiftool.org/TagNames/IPTC.html#ApplicationRecord
       * 055 is DateCreated like "20251217".
       * 060 is TimeCreated with a zone offset like "1234-0500" */
      if ( ( ! empty( $iptc['2#055'][0] ) ) and ( ! empty( $iptc['2#060'][0] ) ) ) {
        $meta['created_timestamp'] = strtotime( $iptc['2#055'][0] . ' ' . $iptc['2#060'][0] );
      }
      if ( ! empty( $iptc['2#116'][0] ) ) // copyright
      {
        $meta['copyright'] = $this->make_utf8( trim( $iptc['2#116'][0] ) );
      }
    }

    return $meta;
  }

  private function make_utf8( $string ) {
    if ( $this->is_utf8( $string ) ) {
      return $string;
    } else {
      return mb_convert_encoding( $string, 'ISO-8859-1', 'UTF-8' );
    }
  }

  /**
   * Returns true if $string is valid UTF-8 and false otherwise.
   *
   * @param [mixed] $string     string to be tested
   *
   * @since        1.14
   * @subpackage
   */
  private function is_utf8( $string ) {

    // From http://w3.org/International/questions/qa-forms-utf-8.html
    return preg_match( '%^(?:
              [\x09\x0A\x0D\x20-\x7E]            # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
            |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
        )*$%xs', $string );

  }
}
