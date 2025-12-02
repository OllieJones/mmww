<?php

date_default_timezone_set ( 'America/New_York' );
require_once ('code/mmwwtemplate.php');

class MMWWTemplateTest extends PHPUnit_Framework_TestCase
{

	protected $template;
	protected $metaList = array();

	public function setUp()
	{
		$metaList = $this->getMetaList('good');
		$this->template = new MMWWTemplate($metaList[0]);
	}

	public function testEmpty () {

		$t = $this->template;
		$result =  $t->fillout('');
		$this->assertEquals('',$result);
	}

	/**
	 * @dataProvider goodTemplates
	 */
	public function testSomeTemplate ($a, $expected) {

		$t = $this->template;
		$result =  $t->fillout($a);
		$this->assertEquals($expected, $result);

	}

	public function goodTemplates (){
		return array (
			array('', ''),
			array('(--{title}-- )(Description: {description} )({exposuremode})', '--Item Title-- Description: Yadda yadda blah blah Manual'),
			array('(Copyright {copyright})', 'Copyright 2012 Ollie' ),
			array('(\(Copyright {copyright}\))', '(Copyright 2012 Ollie)' ),
			array('Not much:( Farkle {farkle})', 'Not much:' ),
            array('(Copr {copyright} ) extra close paren)', 'Copr 2012 Ollie  extra close paren)' )
		);
	}

	private function getMetalist ($list) {
		if ($list == 'good') {
			return array (
				array (
					'TESTCASE' => 'Lots of JPEG metadata',
					'mmww_type' => 'image',
					'title' => 'Item Title', 'copyright' => '2012 Ollie', 'description' => 'Yadda yadda blah blah',
					'tags' => 'tag1;tag2;tag3;tag4',
					'rating'=>'3',
					'workflowlabel' => 'Keep',
					'camera' => 'iPhone 4S fake', 'shutter' => '1/60', 'fstop' => 'f/8',
					'flash' => 'No Flash', 'lightsource' => 'Tungsten', 'meteringmode' => 'Spot',
					'sensingmethod' => 'Bayes filter one chip', 'exposuremode' => 'Manual', 'exposureprogram' => 'Aperture Priority',
					'brightness' => '811',
					'latitude' => '42.81288', 'longitude' => '-70.8107',
					'created_time' => 'October 25, 2012 8:53 am',
					),
				array (
					'TESTCASE' => 'JPEG metadata, no copyright',
					'mmww_type' => 'image',
					'title' => 'Item Title', 'description' => 'Yadda yadda blah blah',
					'tags' => 'tag1;tag2;tag3;tag4',
					'rating'=>'3',
					'workflowlabel' => 'Keep',
					'camera' => 'iPhone 4S fake', 'shutter' => '1/60', 'fstop' => 'f/8',
					'flash' => 'No Flash', 'lightsource' => 'Tungsten', 'meteringmode' => 'Spot',
					'sensingmethod' => 'Bayes filter one chip', 'exposuremode' => 'Manual', 'exposureprogram' => 'Aperture Priority',
					'brightness' => '811',
					'latitude' => '42.81288', 'longitude' => '-70.8107',
					'created_time' => 'October 25, 2012 8:53 am',
					),
				array (
					'TESTCASE' => 'sparse JPEG data',
					'mmww_type' => 'image',
					'title' => 'Item Title',
					'created_time' => 'October 25, 2012 8:53 am',
					),
				);
		}
		return array();
	}
}
