<?php

class MMWWXMPReader {

  private $xmps;
  private $xmp_tag_list = [
    [ 'tags', '//dc:subject/rdf:Bag/rdf:li' ],
  ];
  private $xmp_metadata_list = [
    [ 'rating', '//xmp:Rating' ],
    [ 'rating', '//@xmp:Rating' ],
    [ 'workflowlabel', '//@xmp:Label' ],
    [ 'creditorganization', '//@photoshop:AuthorsPosition' ],
    [ 'descriptionwriter', '//@photoshop:CaptionWriter' ],
    [ 'copyrighted', '//@xmpRights:Marked' ],
    [ 'copyrightwebstatement', '//@xmpRights:WebStatement' ],
    [ 'credit', '//dc:creator/rdf:Seq/rdf:li' ],
    [ 'credit', '//dc:creator/rdf:Bag/rdf:li' ],
    [ 'tags', '//dc:subject/rdf:Bag/rdf:li' ],
    [ 'copyright', '//dc:rights/rdf:Alt/rdf:li' ],
    [ 'title', '//dc:title/rdf:Alt/rdf:li' ],
    [ 'description', '//dc:description/rdf:Alt/rdf:li' ],
    [ 'format', '//@dc:format' ],
    [ 'format', '//dc:format' ],
    [ 'software', '//pdf:Producer' ],
    [ 'iso8601timestamp', '//xmp:CreateDate' ],
    [ 'iso8601timestamp', '//xmp:ModifyDate' ],
    /* get some IPTC xmp extension data if furnished */
    [ 'iptc:creator:address', '//@Iptc4xmpCore:CiAdrExtadr' ],
    [ 'iptc:creator:city', '//@Iptc4xmpCore:CiAdrCity' ],
    [ 'iptc:creator:state', '//@Iptc4xmpCore:CiAdrRegion' ],
    [ 'iptc:creator:postcode', '//@Iptc4xmpCore:CiAdrPcode' ],
    [ 'iptc:creator:country', '//@Iptc4xmpCore:CiAdrCtry' ],
    [ 'iptc:creator:phone', '//@Iptc4xmpCore:CiAdrTelWork' ],
    [ 'iptc:creator:email', '//@Iptc4xmpCore:CiAdrEmailWork' ],
    [ 'iptc:creator:website', '//@Iptc4xmpCore:CiAdrUrlWork' ],

    [ 'iptc:iptcsubjectcode', '//Iptc4xmpCore:SubjectCode/rdf:Bag/rdf:li' ],
    [ 'iptc:genre', '//@Iptc4xmpCore:IntellectualGenre' ],
    [ 'iptc:scenecode', '//Iptc4xmpCore:Scene/rdf:Bag/rdf:li' ],
    [ 'iptc:copyrightstatus', '//@xmpRights:Marked' ],
    [ 'iptc:rightsusageterms', '//xmpRights:UsageTerms/rdf:Alt/rdf:li' ],

  ];
  private $audio_metadata_list = [
    [ 'workflowlabel', '//@xmp:Label' ],
    [ 'rating', '//@xmp:Rating' ],
    [ 'rating', '//xmp:Rating' ],
    [ 'copyrighted', '//@xmpRights:Marked' ],
    [ 'copyrightwebstatement', '//@xmpRights:WebStatement' ],
    [ 'credit', '//@xmpDM:artist' ],
    [ 'tags', '//dc:subject/rdf:Bag/rdf:li' ],
    [ 'copyright', '//dc:rights/rdf:Alt/rdf:li' ],
    [ 'title', '//dc:title/rdf:Alt/rdf:li' ],
    [ 'keywords', '//dc:subject/rdf:Alt/rdf:li' ],
    [ 'album', '//@xmpDM:album' ],
    [ 'engineer', '//@xmpDM:engineer' ],
    [ 'releasedate', '//@xmpDM:releaseDate' ],
    [ 'year', '//@xmpDM:year' ],
    [ 'description', '//dc:description/rdf:Alt/rdf:li' ],
    [ 'format', '//@dc:format' ],
    [ 'format', '//dc:format' ],
    [ 'iso8601timestamp', '//xmp:CreateDate' ],
    [ 'iso8601timestamp', '//xmp:ModifyDate' ],
  ];

  function __construct( $file ) {
    $this->xmps = $this->get_xmps( $file );
  }

  /**
   *  retrieve multiple xmp stanzas from a media file.
   *
   * @param $file
   *
   * @return array of xml stanzas; empty array if none found.
   */
  private function get_xmps( $file ) {
    $result = [];
    $start  = 0;

    $ret = $this->get_xmp( $file, $start );
    while ( ! ( false === $ret ) ) {
      $result[] = $ret['xmp'];
      $start    = $ret['start'];
      $ret      = $this->get_xmp( $file, $start );
    }

    return $result;

  }

  /**
   * retrieve XMP from a media file
   *
   * @param string $file file path name
   *
   * @return bool|array(SimpleXMLElement, position) or false if no XMP was found
   */
  private function get_xmp( $file, $start = 0 ) {
    /* find a xmp metadata stanza in the file */
    $ts        = '<x:xmpmeta';
    $xmp       = false;
    $chunksize = 64 * 1024;
    $maxsize   = $chunksize;
    $content   = '';
    $s         = false;
    $size      = filesize( $file );
    $e         = $size;
    while ( $start < $size ) {
      /* read twice the chunksize */
      $content = file_get_contents( $file, false, null, $start, $chunksize + $chunksize );
      $s       = strpos( $content, $ts );
      if ( $s === false ) {
        /* move ahead by the chunksize */
        $start += $chunksize;
      } else {
        /* found the start, stop reading */
        $start += $s;
        break;
      }
    }
    if ( $start < $size ) {
      /* read the maxsize from the start point of the stanza */
      $content = file_get_contents( $file, false, null, $start, $maxsize );
      $s       = strpos( $content, $ts );
    }
    if ( ! ( $s === false ) ) {

      /* find the end */
      $te = '</x:xmpmeta>';
      $e  = strpos( $content, $te, $s + strlen( $ts ) );
      if ( ! ( $e === false ) ) {
        $e += strlen( $te );
        /* found the stanza, use it */
        $xmp = simplexml_load_string( "<?xml version='1.0'?>\n"
                                      . substr( $content, $s, $e - $s ) );

      }
    }
    unset ( $content );

    if ( ! ( $xmp === false ) ) : return [ 'xmp' => $xmp, 'start' => $start + $e ];
    else: return false;
    endif;
  }

  function __destruct() {
    unset ( $this->xmps );
  }

  /**
   * fetch items of image metadata from the xmp in an image file
   * @return string array of metadata, possibly empty if no metadata found.
   */
  public function get_metadata() {
    return $this->get_list( $this->xmps, $this->xmp_metadata_list );
  }

  /**
   * get a metadata array from an xmp stanza based on a list of itesm
   *
   * @param array $xmps array of xmp stanzas, empty array if none.
   * @param array $list metadata xpaths
   * @param string $separator a character for separating list items. default=semicolon.
   *
   * @return mixed multitype:string
   */
  private function get_list( $xmps, $list, $separator = ';' ) {
    $result = [];
    foreach ( $xmps as $xmp ) {
	    if ( is_object( $xmp ) ) {
		    foreach ( $list as $pair ) {
			    $tag    = $pair[0];
			    $xpath  = $pair[1];
			    $errors = error_reporting();
			    error_reporting( $errors & ! E_WARNING );
			    $it = $xmp->xpath( $xpath );
			    error_reporting( $errors );
			    if ( ! ( $it === false ) ) {
				    $gather = [];
				    foreach ( $it as $s ) {
					    $gather[] = $s;
				    }
				    if ( ! empty( $gather ) ) {
					    $out = implode( $separator, $gather );
					    if ( strlen( $out ) > 0 ) {
						    $result[ $tag ] = $out;
					    }
				    }
			    }
		    }
	    }
    }
    if ( array_key_exists( 'iso8601timestamp', $result ) ) {
      /* cope with iso timestamp */
      $ts                          = strtotime( $result['iso8601timestamp'] );
      $result['created_timestamp'] = $ts;
    }

    return $result;
  }

  /**
   * fetch items of audio metadata from the xmp in an audio file
   * @return array of metadata, possibly empty if no metadata found.
   */
  public function get_audio_metadata() {
    return $this->get_list( $this->xmps, $this->audio_metadata_list );
  }

  public function get_tags() {
    $result = $this->get_list( $this->xmps, $this->xmp_tag_list, "\t" );

    /* return explode("\t", $result); */

    return [];
  }
}