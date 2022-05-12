<?php


/**
 * retrieve media metadata tags, if any, from attachment metadata.
 * These tags are stashed here by the MMWW plugin.
 *
 * @param int $id attachment post id
 *
 * @return array of tags, or null if no tags were found.
 *
 * Note that other items of media metadata are also in the attachment metadata
 */
function mmww_sample_get_tags( $id ) {
  $postmeta = get_post_meta( $id, '_wp_attachment_metadata', true );
  $meta     = [];
  if ( array_key_exists( 'image_meta', $postmeta ) ) {
    $meta = $postmeta['image_meta'];
  }
  /* now $meta contains the metadata extracted by MMWW */
  if ( array_key_exists( 'tags', $meta ) ) {
    $tags = explode( ';', $meta['tags'] );

    return $tags;
  }

  return null;
}