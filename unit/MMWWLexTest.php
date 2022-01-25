<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ollie
 * Date: 3/6/13
 * Time: 12:33 PM
 */

date_default_timezone_set ( 'America/New_York' );
require_once ('code/mmwwtemplate.php');

class MMWWLexTest extends PHPUnit_Framework_TestCase
{


	public function setUp()
	{

	}


	public function testEmpty () {

		$l = new MMWWLex ('');
		$r = $l->getNext();
		$this->assertEquals ('end', $r['type']); $this->assertEquals ('', $r['value']);

	}
	public function testSimpleString () {

		$v = 'abcdefghijklmnopq123456-_';
		$l = new MMWWLex ($v);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals ($v, $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('end', $r['type']); $this->assertEquals ('', $r['value']);

		/* repeated attempts to read beyond end of expression should be idempotent */
		$r = $l->getNext();
		$this->assertEquals ('end', $r['type']); $this->assertEquals ('', $r['value']);

	}


	public function testStringWithParensMixedTokensEscTokensEscParens () {

		$v = '(abc{token1}def\{escape\}ghi){token-2}jkl\(mno\)pq123456-_{token 3}zyxw';
		$l = new MMWWLex ($v);

		$r = $l->getNext();
		$this->assertEquals ('(', $r['type']); $this->assertEquals ('(', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals ('abc', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('token', $r['type']); $this->assertEquals ('{token1}', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals ('def{escape}ghi', $r['value']);

		$r = $l->getNext();
		$this->assertEquals (')', $r['type']); $this->assertEquals (')', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('token', $r['type']); $this->assertEquals ('{token-2}', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals ('jkl(mno)pq123456-_{token 3}zyxw', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('end', $r['type']); $this->assertEquals ('', $r['value']);

	}

	public function testStringWithTokensEndBackslash () {

		$v = 'abc{token1}def {escape} ghi) {token-2} jkl\\';
		$l = new MMWWLex ($v);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals ('abc', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('token', $r['type']); $this->assertEquals ('{token1}', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals ('def ', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('token', $r['type']); $this->assertEquals ('{escape}', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals (' ghi', $r['value']);

		$r = $l->getNext();
		$this->assertEquals (')', $r['type']); $this->assertEquals (')', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals (' ', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('token', $r['type']); $this->assertEquals ('{token-2}', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals (' jkl\\', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('end', $r['type']); $this->assertEquals ('', $r['value']);

	}


	public function testStringTokensCurlies () {

		$v = 'abc{token1}def ghi{token-2}jkl{yaddamnopq123456-_{token 3}zyxw';

		$l = new MMWWLex ($v);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals ('abc', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('token', $r['type']); $this->assertEquals ('{token1}', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals ('def ghi', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('token', $r['type']); $this->assertEquals ('{token-2}', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals ('jkl{yaddamnopq123456-_{token 3}zyxw', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('end', $r['type']); $this->assertEquals ('', $r['value']);
		$r = $l->getNext(); /* idempotent end */
		$this->assertEquals ('end', $r['type']); $this->assertEquals ('', $r['value']);

	}

	public function testStringTokensParens () {

		$v = 'abc((){token1}())def ghi{token-2}j\(kl{\)yaddamnopq123456-_{token 3}zyxw';

		$l = new MMWWLex ($v);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals ('abc', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('(', $r['type']); $this->assertEquals ('(', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('(', $r['type']); $this->assertEquals ('(', $r['value']);

		$r = $l->getNext();
		$this->assertEquals (')', $r['type']); $this->assertEquals (')', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('token', $r['type']); $this->assertEquals ('{token1}', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('(', $r['type']); $this->assertEquals ('(', $r['value']);

		$r = $l->getNext();
		$this->assertEquals (')', $r['type']); $this->assertEquals (')', $r['value']);

		$r = $l->getNext();
		$this->assertEquals (')', $r['type']); $this->assertEquals (')', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals ('def ghi', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('token', $r['type']); $this->assertEquals ('{token-2}', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('text', $r['type']); $this->assertEquals ('j(kl{)yaddamnopq123456-_{token 3}zyxw', $r['value']);

		$r = $l->getNext();
		$this->assertEquals ('end', $r['type']); $this->assertEquals ('', $r['value']);
		$r = $l->getNext(); /* idempotent end */
		$this->assertEquals ('end', $r['type']); $this->assertEquals ('', $r['value']);

	}



}
