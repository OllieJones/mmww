<?php

class MMWWTemplate {
  private $meta;

  function __construct( $m ) {
    $this->meta = $m;
  }

  /**
   * fill out a template string based on stuff in the metadata
   *
   * @param string $template
   *
   * @return string completed template
   */
  public function fillout( $template ) {

    $lex = new MMWWLex( $template );

    $frame = [ 'result' => '', 'match' => true ];
    $stack = [];

    $tok = $lex->getNext();
    while ( $tok['type'] != 'end' ) {
      switch ( $tok['type'] ) {
        case 'text':
          $frame['result'] .= $tok['value'];
          break;

        case 'token':
          $key = trim( $tok['value'], '{}' );
          if ( array_key_exists( $key, $this->meta ) ) {
            /* found the desired metadata */
            $frame['result'] .= $this->meta[ $key ];
          } else {
            /* didn't find it, mark that the match failed */
            $frame['result'] .= $tok['value'];
            $frame['match']  = false;
          }
          break;

        case '(' :
          /* push existing frame, start new one */
          array_push( $stack, $frame );
          $frame = [ 'result' => '', 'match' => true ];
          break;

        case ')':
          if ( ! empty( $stack ) ) {
            $save            = $frame['match'] ? $frame['result'] : '';
            $frame           = array_pop( $stack );
            $frame['result'] .= $save;
          } else {
            /* unmatched close paren ... just emit it */
            $frame['result'] .= $tok['value'];
          }
          break;
      }
      $tok = $lex->getNext();
    }
    /* close out any unmatched open paren items */
    while ( ! empty ( $stack ) ) {
      $save            = $frame['match'] ? $frame['result'] : '';
      $frame           = array_pop( $stack );
      $frame['result'] .= $save;
    }

    return ( $frame['result'] );
  }
}

class MMWWLex {
  private $d;

  function __construct( $in ) {
    $this->d = $in;
  }

  /**
   * Each call to this function returns the next token and its type.
   * types: text, token, (, ), end
   *
   * There's a unit test suite for this
   *
   * @return array('type'=>type, 'val'=>value)
   */
  function getNext() {

    $d       = $this->d;
    $r       = '';
    $matches = [];

    $done = false;
    while ( ! $done ) {
      $len = strlen( $d );
      if ( $len >= 2 && substr( $d, 0, 1 ) == '\\' && strspn( $d, '(){}\\', 1, 1 ) == 1 ) {
        /* escaped character found, strip the escape backslash and then accumulate it. */
        $r .= substr( $d, 1, 1 );
        $d = substr( $d, 2 );
      } elseif ( $len >= 1 && strspn( $d, '()', 0, 1 ) == 1 ) {
        /* push and pop characters (parens) */
        if ( strlen( $r ) > 0 ) {
          $this->d = $d;

          return [ 'type' => 'text', 'value' => $r ];
        } else {
          $r       = substr( $d, 0, 1 );
          $d       = substr( $d, 1 );
          $this->d = $d;

          return [ 'type' => $r, 'value' => $r ];
        }
      } elseif ( $len > 2 && 1 == preg_match( '/^({[a-z][-_a-z0-9:]+})(.*)$/', $d, $matches ) ) {
        /* tokens, including colons, in {curly-braces} */
        if ( strlen( $r ) > 0 ) {
          $this->d = $d;

          return [ 'type' => 'text', 'value' => $r ];
        } else {
          $d       = $matches[2];
          $this->d = $d;
          $r       = $matches[1];

          return [ 'type' => 'token', 'value' => $r ];
        }
      } elseif ( $len >= 1 ) {
        /* any other character, accumulate it */
        $r .= substr( $d, 0, 1 );
        $d = substr( $d, 1 );
      } elseif ( $len == 0 ) {
        $done = true;
        if ( strlen( $r ) > 0 ) {
          $this->d = $d;

          return [ 'type' => 'text', 'value' => $r ];
        }
      }
    } /* end while (!$done) */
    $this->d = $d;

    return [ 'type' => 'end', 'value' => '' ];
  }

}

