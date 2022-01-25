<?php
/**
 * Filter for PDFs in the media manager, and other support
 * TODO  could be a separate plugin
 * @author Ollie
 *
 */
class MMWWPDFSupport {

	function __construct() {
		add_filter( 'post_mime_types', array($this, 'add_pdf') );
	}

	function add_pdf ( $post_mime_types ) {
		$post_mime_types['application/pdf'] = array( __( 'PDFs', 'mmww' ), __( 'Manage PDFs', 'mmww' ), _n_noop( 'PDF <span class="count">(%s)</span>', 'PDFs <span class="count">(%s)</span>', 'mmww') );
		return $post_mime_types;
	}
}

new MMWWPDFSupport();

