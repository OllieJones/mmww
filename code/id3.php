<?php

require_once ( 'Zend/Media/Id3v2.php' );
require_once ( 'Zend/Media/Id3/TextFrame.php' );

class  MMWWID3Reader {

	private $id3;

	function __construct($file) {
		try {
			$this->id3 = new Zend_Media_Id3v2($file);
		}
		catch (Zend_Media_Id3_Exception $e) {
			unset ($this->id3);
		}
	}
	
	function __destruct() {
		unset ($this->id3);
	}


	private $metadata_list = array(
		'TALB' => 'album',
		'TBPM' => 'tempo',
		'TCON' => 'genre',
		'TIT1' => 'grouptitle',
		'TIT2' => 'title',
		'TIT3' => 'description',
		'TKEY' => 'keysignature',
		'TYER' => 'year',
		'TDAT' => 'DDMM',
		'TIME' => 'HHMM',
		'TLEN' => 'duration',
		'TPE1' => 'credit',
		'TPE2' => 'creditlead',
		'TPE3' => 'creditconductor',
		'TPE4' => 'creditproducer',
		'TEXT' => 'writer',
		'TENC' => 'creditorganization',
		'TMED' => 'mediatype',
		'TOPE' => 'creditoriginal',
		'TCOP' => 'copyright' );


	/**
	 * turn a utf 16 string into utf 8
	 * (some id3v2 tags, esp. from Adobe tools, are coded in utf 16)
	 * Many thanks to Andrew Walker.
	 * @link http://www.craiglotter.co.za/2010/03/05/php-convert-a-utf-16-string-to-a-utf-8-string/
	 * @param input string $str
	 * @return string result
	 */
	private function utf16_to_utf8($str) {
		$c0 = ord($str[0]);
		$c1 = ord($str[1]);

		if ($c0 == 0xFE && $c1 == 0xFF) {
			$be = true;
		} else if ($c0 == 0xFF && $c1 == 0xFE) {
			$be = false;
		} else {
			return $str;
		}

		$str = substr($str, 2);
		$len = strlen($str);
		$dec = '';
		for ($i = 0; $i < $len; $i += 2) {
			$c = ($be) ? ord($str[$i]) << 8 | ord($str[$i + 1]) :
			ord($str[$i + 1]) << 8 | ord($str[$i]);
			if ($c >= 0x0001 && $c <= 0x007F) {
				$dec .= chr($c);
			} else if ($c > 0x07FF) {
				$dec .= chr(0xE0 | (($c >> 12) & 0x0F));
				$dec .= chr(0x80 | (($c >>  6) & 0x3F));
				$dec .= chr(0x80 | (($c >>  0) & 0x3F));
			} else {
				$dec .= chr(0xC0 | (($c >>  6) & 0x1F));
				$dec .= chr(0x80 | (($c >>  0) & 0x3F));
			}
		}
		return $dec;
	}


    public function get_metadata () {
		$id3 = $this->id3;
		$taglist = $this->metadata_list;
		$meta = array();
		if (!is_object($id3)) {
			return $meta;
		}
		try {
			$found = false;
			foreach ($id3->frames as $frames) {
				foreach ($frames as $frame) {
					if ($frame instanceof Zend_Media_Id3_TextFrame) {
						$tag = $frame->identifier;
						if (! empty ($taglist[$tag])) {
							$val = $frame->text;
							$val = $this->utf16_to_utf8 ($val);
							if ((!empty ($val)) && strlen($val) > 0 ) {
								$found = true;
								$meta[$taglist[$tag]] = $val;
							}
						}
					}
					if ($frame instanceof Zend_Media_Id3_Frame_Popm) {
						/* http://en.wikipedia.org/wiki/ID3#ID3v2_Rating_tag_issue */
						$val = $frame->getRating();
						if ($val >= 224)
							$rating = 5;
						elseif ($val >= 160)
							$rating = 4;
						elseif ($val >= 96)
							$rating = 3;
						elseif ($val >= 32)
							$rating = 2;
						elseif ($val >= 1)
							$rating = 1;
						else 
							$rating = 0;
						$meta['rating'] = $rating;
					}
				}
			}
		}
		catch (Zend_Media_Id3_Exception $e) {
			$meta['error'] = $e->getMessage() . " " . __FILE__;
		}
		if ($found) {
			$meta['format'] = 'audio/mpeg';
		}
		return $meta;
	}
	
}
