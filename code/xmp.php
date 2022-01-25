<?php


class MMWWXMPReader {

	private $xmp;

	function __construct($file) {
		$this->xmps = $this->get_xmps($file);
	}
	
	function __destruct() {
		unset ($this->xmps);
	}

    private $xmp_tag_list = array (
        array('tags', '//dc:subject/rdf:Bag/rdf:li'),
    );

    private $xmp_metadata_list = array(
        array('rating', '//xmp:Rating'),
        array('rating', '//@xmp:Rating'),
        array('workflowlabel', '//@xmp:Label'),
        array('creditorganization', '//@photoshop:AuthorsPosition'),
        array('descriptionwriter', '//@photoshop:CaptionWriter'),
        array('copyrighted', '//@xmpRights:Marked'),
        array('copyrightwebstatement', '//@xmpRights:WebStatement'),
        array('credit', '//dc:creator/rdf:Seq/rdf:li'),
        array('credit', '//dc:creator/rdf:Bag/rdf:li'),
        array('tags', '//dc:subject/rdf:Bag/rdf:li'),
        array('copyright', '//dc:rights/rdf:Alt/rdf:li'),
        array('title', '//dc:title/rdf:Alt/rdf:li'),
        array('description', '//dc:description/rdf:Alt/rdf:li'),
        array('format', '//@dc:format'),
        array('format', '//dc:format'),
        array('software', '//pdf:Producer'),
        array('iso8601timestamp', '//xmp:CreateDate'),
        array('iso8601timestamp', '//xmp:ModifyDate'),
        /* get some IPTC xmp extension data if furnished */
        array('iptc:creator:address','//@Iptc4xmpCore:CiAdrExtadr'),
        array('iptc:creator:city','//@Iptc4xmpCore:CiAdrCity'),
        array('iptc:creator:state','//@Iptc4xmpCore:CiAdrRegion'),
        array('iptc:creator:postcode','//@Iptc4xmpCore:CiAdrPcode'),
        array('iptc:creator:country','//@Iptc4xmpCore:CiAdrCtry'),
        array('iptc:creator:phone','//@Iptc4xmpCore:CiAdrTelWork'),
        array('iptc:creator:email','//@Iptc4xmpCore:CiAdrEmailWork'),
        array('iptc:creator:website','//@Iptc4xmpCore:CiAdrUrlWork'),

        array('iptc:iptcsubjectcode','//Iptc4xmpCore:SubjectCode/rdf:Bag/rdf:li'),
        array('iptc:genre','//@Iptc4xmpCore:IntellectualGenre'),
        array('iptc:scenecode','//Iptc4xmpCore:Scene/rdf:Bag/rdf:li'),
        array('iptc:copyrightstatus','//@xmpRights:Marked'),
        array('iptc:rightsusageterms', '//xmpRights:UsageTerms/rdf:Alt/rdf:li'),

    );

    private $audio_metadata_list = array(
        array('workflowlabel', '//@xmp:Label'),
        array('rating', '//@xmp:Rating'),
        array('rating', '//xmp:Rating'),
        array('copyrighted', '//@xmpRights:Marked'),
        array('copyrightwebstatement', '//@xmpRights:WebStatement'),
        array('credit', '//@xmpDM:artist'),
        array('tags', '//dc:subject/rdf:Bag/rdf:li'),
        array('copyright', '//dc:rights/rdf:Alt/rdf:li'),
        array('title', '//dc:title/rdf:Alt/rdf:li'),
        array('keywords', '//dc:subject/rdf:Alt/rdf:li'),
        array('album', '//@xmpDM:album'),
        array('engineer', '//@xmpDM:engineer'),
        array('releasedate', '//@xmpDM:releaseDate'),
        array('year', '//@xmpDM:year'),
        array('description', '//dc:description/rdf:Alt/rdf:li'),
        array('format', '//@dc:format'),
        array('format', '//dc:format'),
        array('iso8601timestamp', '//xmp:CreateDate'),
        array('iso8601timestamp', '//xmp:ModifyDate'),
    );


    /**
     *  retrieve multiple xmp stanzas from a media file.
     * @param $file
     * @return array of xml stanzas; empty array if none found.
     */
    private function get_xmps ($file) {
        $result = array();
        $start = 0;

        $ret = $this->get_xmp($file, $start);
        while ( ! (False === $ret)) {
            $result[] = $ret['xmp'];
            $start = $ret['start'];
            $ret = $this->get_xmp($file, $start);
        }

        return $result;

    }

    /**
	 * retrieve XMP from a media file
	 * @param string $file file path name
	 * @return array(SimpleXMLElement, position) or false if no XMP was found
	 */
	private function get_xmp ($file, $start = 0) {
		/* find a xmp metadata stanza in the file */
		$ts = '<x:xmpmeta';
		$xmp = false;
		$chunksize = 64*1024;
		$maxsize = $chunksize;
		$content = '';
		$s = False;
		$size = filesize($file);
        $e = $size;
		while ($start < $size) {
			/* read twice the chunksize */
			$content = file_get_contents ($file, false, NULL, $start, $chunksize+$chunksize);
			$s = strpos($content, $ts);
			if ($s === False) {
				/* move ahead by the chunksize */
				$start += $chunksize;
			} else  {
				/* found the start, stop reading */
				$start += $s;
				break;
			}
		}
		if ($start < $size ) {
			/* read the maxsize from the start point of the stanza */
			$content = file_get_contents ($file, false, NULL, $start, $maxsize);
			$s = strpos($content, $ts);
		}
		if (! ($s === False)) {

			/* find the end */
			$te = '</x:xmpmeta>';
			$e = strpos($content, $te, $s+strlen($ts));
			if (! ($e === False)) {
				$e += strlen($te);
				/* found the stanza, use it */
				$xmp = simplexml_load_string("<?xml version='1.0'?>\n" . substr($content, $s, $e - $s));

				/* deal with the plethora of namespaces in XMP */
				$ns = $xmp->getNamespaces(true);
				foreach ($ns as $key => $val) {
					$xmp->registerXPathNamespace($key, $val);
				}
				unset($ns);
			}
		}
		unset ($content);

        if ( ! ($xmp === False)) :	return array('xmp' => $xmp, 'start' => $start + $e);
        else: return False;
        endif;
	}

	/**
	 * get a metadata array from an xmp stanza based on a list of itesm
	 * @param string xmps array of xmp stanzas, empty array if none.
	 * @param metadata list $list
     * @param separator a character for separating list items. default=semicolon.
	 * @return multitype:string
	 */
	private function get_list($xmps, $list, $separator=';') {
		$result = array();
        foreach ($xmps as $xmp) {
            if (is_object($xmp)) {
                foreach ($list as $pair) {
                    $tag=$pair[0]; $xpath=$pair[1];
                    /* use @ here to avoid error messages when XML namespaces are unexpected */
                    $it = @$xmp->xpath($xpath);
                    if (!($it === False)) {
                        $gather = array();
                        foreach ($it as $s) {
                            $gather[] = $s;
                        }
                        if (!empty($gather)) {
                            $out = implode($separator, $gather);
                            if (is_string($out) && strlen($out) > 0) {
                                $result[$tag] = $out;
                            }
                        }
                    }
                }
            }
        }
        if (array_key_exists('iso8601timestamp', $result)) {
            /* cope with iso timestamp */
            $ts = strtotime($result['iso8601timestamp']);
            $result['created_timestamp'] = $ts;
        }
		return $result;
	}

	/**
	 * fetch items of image metadata from the xmp in an image file
	 * @return string array of metadata, possibly empty if no metadata found.
	 */
	public function get_metadata () {
		return $this->get_list($this->xmps, $this->xmp_metadata_list);
	}
	/**
	 * fetch items of audio metadata from the xmp in an audio file
	 * @return string array of metadata, possibly empty if no metadata found.
	 */
	public function get_audio_metadata () {
		return $this->get_list($this->xmps, $this->audio_metadata_list);
	}

    public function get_tags() {
        $result = $this->get_list($this->xmps, $this->xmp_tag_list, "\t");
        /* return explode("\t", $result); */
        return array();
    }
}