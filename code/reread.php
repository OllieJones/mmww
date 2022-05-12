<?php

class MMWWRereader {

  /**
   * list of meta fields corresponding to wp_posts columns.
   * @var associative array
   */
  private $fields = [
    'title'          => 'post_title',
    'caption'        => 'post_content',
    'displaycaption' => 'post_excerpt',
  ];

  public function __construct() {
    add_action( 'dbx_post_advanced', [ $this, 'reread_before_form_populate' ] );
    add_action( 'edit_form_after_editor', [ $this, 'reread_after_form_populate' ] );
    add_filter( 'media_row_actions', [ $this, 'add_reread_action' ], 10, 3 );
    add_filter( 'attachment_fields_to_edit', [ $this, 'add_grid_reread_action' ], 10, 2 );
    add_action( 'admin_notices', [ $this, 'reread_notice' ] );
  }

  public function reread_notice() {
    if ( $this->isRereading( 3 ) ) {

      /* we're sure we're displaying the reread-metadata result */
      echo '<div id="message" class="updated"><p>';
      _e( 'Media attachment metadata reloaded from file. Update to save it.', 'mmww' );
      echo '</p></div>';
    }
  }

  private function isRereading( $phase ) {
    if ( array_key_exists( 'action', $_REQUEST ) && $_REQUEST['action'] == 'edit' &&
         array_key_exists( 'mmww', $_REQUEST ) &&
         array_key_exists( 'post', $_REQUEST )
    ) {

      /* we're actually doing mmww processing on this edit request */

      $mmwwop = $_REQUEST['mmww'];
      if ( $mmwwop == $phase ) {
        return true;
      }
    }

    return false;
  }

  /**
   * this action gets called after the edit form is populated.
   * it's activated by mmww=3 and a valid nonce.
   * The desired behavior is to require the user to SAVE the
   * form, so this action restores the old metadata values to the
   * dbms. The Save will overwrite them, and they'll get left alone
   * if the user abandons the edit.
   */
  function reread_after_form_populate() {
    if ( $this->isRereading( 3 ) ) {
      $post = get_post();
      /* we have stuff to do at this stage */
      /* make sure our nonce is good */
      check_admin_referer( "edit-post_{$post->ID}" );

      /* this saves the previous values, so an update is required to actually
       * apply the new values. Gives the user a chance to vet the reloaded metadata
       */
      $meta = $this->retrieve_old( $post );
      $this->store( $post, $meta );
    }
  }

  function retrieve_old( $post ) {
    $saved = get_post_meta( $post->ID, '_mmww_saved_attachment_metadata', true );
    $meta  = [];
    $meta  = $saved['image_meta'];
    foreach ( $this->fields as $k => $v ) {
      if ( ! empty( $saved[ $k ] ) ) {
        $meta[ $k ] = $saved[ $k ];
      }
    }
    delete_post_meta( $post->ID, '_mmww_saved_attachment_metadata' );

    return $meta;
  }

  function store( $post, $meta ) {

    /* $meta[caption] goes into wp_posts.post_content. This is shown as "description" in the UI.
     * $meta[title] goes into wp_posts.post_title. This is shown as "title"
    *  $meta[displaycaption] into wp_posts.post_excerpt. This is shown as "caption" in the UI.
    *  $meta[alt] into post metadata
    */

    $updates = [];

    $id = $post->ID;
    foreach ( $this->fields as $k => $v ) {
      if ( ! empty( $meta[ $k ] ) ) {
        $updates[ $v ] = $meta[ $k ];
      }
    }

    /* make any updates needed to the posts table. */
    if ( ! empty ( $updates ) ) {
      global $wpdb;
      $where = [ 'ID' => $id ];
      $wpdb->update( $wpdb->posts, $updates, $where );
      clean_post_cache( $id );
    }

    /* handle the image alt text (screenreader etc) which goes into a postmeta row */
    if ( ! empty( $meta['alt'] ) ) {
      update_post_meta( $id, '_wp_attachment_image_alt', $meta['alt'] );
    }

    /* handle the image_meta subfield of the attachment metadata */
    $oldmeta = get_post_meta( $id, '_wp_attachment_metadata', true );
    if ( array_key_exists( 'image_meta', $oldmeta ) && array_key_exists( 'image_meta', $meta ) ) {
      $newmeta               = array_merge( $oldmeta['image_meta'], $meta['image_meta'] );
      $oldmeta['image_meta'] = $newmeta;
      update_post_meta( $id, '_wp_attachment_metadata', $oldmeta );
    }
  }

  /**
   * this action gets called before the edit form is populated.
   * It's activated by mmww=1 or mmww=2 and a valid nonce.
   * (mmww=1 is a bulk reread function not yet implemented.)
   * It puts the new values of the metadata into the DBMS, and
   * then forces a redirect and exit, so the editor gets reloaded.
   */
  function reread_before_form_populate() {
    if ( $this->isRereading( 2 ) || $this->isRereading( 1 ) ) {
      $post = get_post();
      check_admin_referer( "edit-post_{$post->ID}" );

      $meta = $this->get_current( $post );
      $this->save_old( $post, $meta );

      $meta = $this->get_new( $post );
      $this->store( $post, $meta );

      /* now reissue the request with mmww=3 */
      $_REQUEST['mmww'] = 3;
      $url              = admin_url( '/post.php?' . http_build_query( $_REQUEST ) );
      wp_redirect( $url );
      exit();
    }
    /* only return if we're supposed to proceed, otherwise redirect and exit */
  }

  function get_current( $post ) {
    $meta = [];;
    $poststuff = get_post( $post->ID, ARRAY_A );
    foreach ( $this->fields as $k => $v ) {
      if ( ! empty( $poststuff[ $v ] ) ) {
        $meta[ $k ] = $poststuff[ $v ];
      }
    }

    $meta['alt']        = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
    $oldmeta            = get_post_meta( $post->ID, '_wp_attachment_metadata', true );
    $meta['image_meta'] = $oldmeta['image_meta'];

    return $meta;
  }

  function save_old( $post, $meta ) {
    update_post_meta( $post->ID, '_mmww_saved_attachment_metadata', $meta );
  }

  function get_new( $post ) {

    $meta      = wp_read_image_metadata( get_attached_file( $post->ID ) );
    $cleanmeta = apply_filters( 'mmww_filter_metadata', $meta );
    $newmeta   = apply_filters( 'mmww_format_metadata', $cleanmeta );

    $newmeta['image_meta'] = $meta;

    return $newmeta;
  }

  /**
   * result looks like this:
   *   $action['reread'] = http://host/wp-admin/post.php?post=999&action=reread&_wpnonce=abcdef9879
   *
   * @param array $actions
   * @param Post $post
   * @param boolean $detached
   *
   * @returns array of actions
   */
  function add_reread_action( $actions, $post, $detached ) {
    $addlink = false;
    $link    = '';
    $url     = $this->get_reread_metadata_post_link( $post->ID );
    if ( ! empty( $url ) ) {
      $link    = '<a href="' . $url . '">' . __( 'Reload Metadata', 'mmww' ) . '</a>';
      $addlink = true;
    }
    if ( $post->post_mime_type == 'image/gif' ) {
      /* gifs lack metadata, don't offer to read it. */
      $addlink = false;
    }
    if ( $addlink ) {
      /* reorder the links to make more sense */
      $actions['reread'] = $link;
      $result            = [];
      $order             = [ 'edit', 'reread', 'delete', 'view' ];
      foreach ( $order as $o ) {
        if ( array_key_exists( $o, $actions ) ) {
          $result[ $o ] = $actions[ $o ];
          unset ( $actions[ $o ] );
        }
      }
      foreach ( $actions as $o => $v ) {
        $result[ $o ] = $v;
      }

      return $result;
    }

    /* nothing to change, pass through */

    return $actions;
  }

  /**
   * Retrieve reread-metadata link for post
   * cribbed from link-template.php
   *
   * Could be used within the WordPress loop or outside of it, with an attachment post type.
   *
   * @param int $id Optional. Post ID.
   *
   * @return string or null if it makes no sense to try to reread the metadata
   * @since 2.9.0
   *
   */
  function get_reread_metadata_post_link( $id = 0 ) {
    /* valid post */
    if ( ! $post = get_post( $id ) ) {
      return null;
    }
    /* is it an attachment (media file) ? */
    if ( $post->post_type != 'attachment' ) {
      return null;
    }
    /* can we find, and read, the original media file */
    $file = get_attached_file( $id );
    if ( empty( $file ) ) {
      return null;
    }
    if ( ! file_exists( $file ) ) {
      return null;
    }

    $post_type_object = get_post_type_object( $post->post_type );
    if ( ! $post_type_object ) {
      return null;
    }

    if ( ! current_user_can( $post_type_object->cap->edit_post, $post->ID ) ) {
      return null;
    }

    $action = 'edit';

    $reread_link = add_query_arg( 'action', $action, admin_url( sprintf( $post_type_object->_edit_link, $post->ID ) ) );
    $reread_link = add_query_arg( 'mmww', '2', $reread_link );

    return apply_filters( 'get_reread_post_link', wp_nonce_url( $reread_link, "$action-post_{$post->ID}" ), $post->ID );
  }

  /**
   * insert a "reread metadata" action into the popup in the media grid view.
   * using the attachment_fields_to_edit filter.
   *
   * @param $form_fields
   * @param $post
   *
   * @return $form_fields with the new field.
   */
  function add_grid_reread_action( $form_fields, $post ) {
    if ( isset( $post ) ) {
      $url = $this->get_reread_metadata_post_link( $post->ID );

      $s = sprintf(
        '<div class="actions">&nbsp;<a href="%s">%s</a></div>',
        $url,
        __( 'Reload Metadata', 'mmww' ) );

      $form_fields["my_action"] = [
        'label'        => __( "" ),
        'input'        => "html",
        'html'         => $s,
        'show_in_edit' => false,
      ];
    }

    return $form_fields;
  }

}

new MMWWRereader ();