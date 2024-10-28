<?php

/** Class wrapper for MMWW admin page
 */

class MMWWAdmin {

  /**
   * Initialize the administration page operations.
   */
  public function __construct() {
    add_action( 'admin_menu', [ $this, 'admin_actions' ] );
    add_action( 'admin_init', [ $this, 'register_setting' ] );
  }

  function admin_actions() {
    load_plugin_textdomain( 'mmww', '', 'languages' );
    add_options_page( __( 'Media Metadata Workflow Wizard', 'mmww' ),
      __( 'Media Metadata', 'mmww' ),
      'upload_files',
      'mmww',
      [ $this, 'admin_page' ] );
  }

  function register_setting() {
    register_setting( 'mmww', 'mmww_options', [ $this, 'validate_options' ] );
  }

  /**
   * emit the options heading for the overview section
   */
  function general_text() {
    //echo '<p>' . __('These settings control the media workflow in general.', 'mmww') . '</p>';
  }

  /**
   * emit the options heading for the audio section
   */
  function audio_text() {
    echo '<p>' . __( 'These settings control the insertion of ID3 and other metadata from uploaded MP3 audio files into WordPress attachment data.', 'mmww' ) . '</p>';
  }

  /**
   * emit the options heading for the image section
   */
  function image_text() {
    echo '<p>' . __( 'These settings control the insertion of EXIF, IPTC, and other metadata from uploaded image files (JPG, PNG) into WordPress attachment data.', 'mmww' ) . '</p>';
  }

  /**
   * emit the options heading for the PDF section
   */
  function application_text() {
    echo '<p>' . __( 'These settings control the insertion of PDF file properties into WordPress attachment data.', 'mmww' ) . '</p>';
  }

  /**
   * emit the creation date question
   */
  function use_creation_date_text() {
    $this->populate_question( 'use_creation_date',
      __( 'timestamps in media metadata, when available', 'mmww' ),
      __( 'dates of upload', 'mmww' )
    );
  }

  /**
   * emit a yes-no question
   */
  function populate_question( $optitem, $optyesanswer, $optnoanswer ) {
    // get option 'populate_tags' value from the database
    $options = get_option( 'mmww_options' );
    $choice  = ( empty( $options[ $optitem ] ) ) ? 'no' : $options[ $optitem ];

    $choices = [
      'yes' => $optyesanswer,
      'no'  => $optnoanswer,
    ];
    $pattern = '<input type="radio" id="mmww_admin_4$s" name="mmww_options[%4$s]" value="%1$s" %2$s> %3$s';

    $f = [];
    foreach ( $choices as $i => $k ) {
      $checked = ( $choice == $i ) ? 'checked' : '';
      $f[]     = sprintf( $pattern, $i, $checked, $k, $optitem );
    }
    echo implode( '&nbsp;&nbsp;&nbsp;&nbsp', $f );
    unset ( $f );
  }

  /**
   * emit the populate tags question
   */
  function populate_tags_text() {
    $this->populate_question( 'populate_tags',
      __( 'from tags in media files', 'mmww' ),
      __( 'never', 'mmww' )
    );
  }

  /**
   * emit the populate ratings question
   */
  function populate_ratings_text() {
    $this->populate_question( 'populate_ratings',
      __( 'from ratings (zero to five stars) in media files', 'mmww' ),
      __( 'never', 'mmww' )
    );
  }

  /**
   * emit the shortcode question
   */
  function audio_shortcode_text() {
    // get option 'audio' value from the database
    $options = get_option( 'mmww_options' );
    $choice  = ( empty( $options['audio_shortcode'] ) ) ? 'disabled' : $options['audio_shortcode'];

    $choices = [
      'media' => __( 'when the author selects Link To <em>Media File</em>', 'mmww' ),
      'never' => __( 'never', 'mmww' ),
    ];
    $pattern = '<input type="radio" id="mmww_admin_audio_shortcode" name="mmww_options[audio_shortcode]" value="%1$s" %2$s> %3$s';

    $f = [];
    foreach ( $choices as $i => $k ) {
      $checked = ( $choice == $i ) ? 'checked' : '';
      $f[]     = sprintf( $pattern, $i, $checked, $k );
    }
    echo implode( '&nbsp;&nbsp;&nbsp;&nbsp', $f );
    unset ( $f );
  }

  /**
   * emit a text question
   *
   * @param string $item contains the options item name ... e.g. audio_caption
   */
  function admin_text( $item ) {
    $options = get_option( 'mmww_options' );
    $value   = ( empty( $options[ $item ] ) ) ? ' ' : $options[ $item ];
    $pattern = '<input type="text" id="mmww_admin_%2$s" name="mmww_options[%2$s]" value="%1$s" size="80" />';
    $pattern = sprintf( $pattern, htmlspecialchars( $value ), $item );
    echo $pattern;
    echo "\n";
  }

  /**
   * validate the options settings
   *
   * @param array $input
   *
   * @return validated array
   */
  function validate_options( $input ) {
    $codes = [
      'audio_shortcode',
      'audio_title',
      'audio_caption',
      'audio_displaycaption',
      'image_title',
      'image_caption',
      'image_displaycaption',
      'image_alt',
      'application_title',
      'application_caption',
      'use_creation_date',
      'populate_tags',
      'populate_ratings',
    ];
    $valid = [];
    foreach ( $codes as $code ) {
      $valid[ $code ] = htmlspecialchars_decode( $input[ $code ] );
    }

    return $valid;
  }

  function admin_page() {
    ?>
      <div class="wrap">
          <div id="icon-plugins" class="icon32"></div>
          <div id="icon-options-general" class="icon32"></div>
        <?php
        /* translators: 1: semantic version number like 12.34.56 */
        printf( '<div class="wrap"><h2>' . __( 'Media Metadata Workflow Wizard (Version %1s) Settings', 'mmww' ) . '</h2></div>', MMWW_VERSION_NUM );

        add_settings_section(
          'mmww_admin_general',
          __( 'Settings for all media types', 'mmww' ),
          [ $this, 'general_text' ],
          'mmww' );

        add_settings_field(
          'mmww_admin_use_creation_date',
          __( 'Set attachment dates using the ...', 'mmww' ),
          [ $this, 'use_creation_date_text' ],
          'mmww',
          'mmww_admin_general'
        );

        add_settings_field(
          'mmww_admin_populate_tags',
          __( 'Set attachment tags ...', 'mmww' ),
          [ $this, 'populate_tags_text' ],
          'mmww',
          'mmww_admin_general'
        );

        add_settings_field(
          'mmww_admin_populate_ratings',
          __( 'Set attachment ratings ...', 'mmww' ),
          [ $this, 'populate_ratings_text' ],
          'mmww',
          'mmww_admin_general'
        );

        add_settings_section(
          'mmww_admin_audio',
          __( 'Audio Settings', 'mmww' ),
          [ $this, 'audio_text' ],
          'mmww' );

        add_settings_field(
          'mmww_admin_audio_title',
          __( 'Audio title template', 'mmww' ),
          [ $this, 'admin_text' ],
          'mmww',
          'mmww_admin_audio',
          'audio_title'
        );
        add_settings_field(
          'mmww_admin_audio_caption',
          __( 'Audio description template', 'mmww' ),
          [ $this, 'admin_text' ],
          'mmww',
          'mmww_admin_audio',
          'audio_caption'
        );
        add_settings_field(
          'mmww_admin_audio_displaycaption',
          __( 'Audio player scrolling-text template', 'mmww' ),
          [ $this, 'admin_text' ],
          'mmww',
          'mmww_admin_audio',
          'audio_displaycaption' /* wp_posts.post_excerpt */
        );

        if ( version_compare( get_bloginfo( 'version' ), '3.5', '>=' ) ) {
          /* this is for the new media insertion modal dialog only */
          add_settings_field(
            'mmww_admin_audio_shortcode',
            __( 'Automatically insert audio player shortcodes into posts ... ', 'mmww' ),
            [ $this, 'audio_shortcode_text' ],
            'mmww',
            'mmww_admin_audio'
          );
        }

        add_settings_section(
          'mmww_admin_image',
          __( 'Image Settings', 'mmww' ),
          [ $this, 'image_text' ],
          'mmww' );

        add_settings_field(
          'mmww_admin_image_title',
          __( 'Image title template', 'mmww' ),
          [ $this, 'admin_text' ],
          'mmww',
          'mmww_admin_image',
          'image_title'
        );
        add_settings_field(
          'mmww_admin_image_alt',
          __( 'Image alternate text template (accessibility)', 'mmww' ),
          [ $this, 'admin_text' ],
          'mmww',
          'mmww_admin_image',
          'image_alt' /*wp_postmeta ... meta_key = '_wp_attachment_image_alt', value in meta_value */
        );

        add_settings_field(
          'mmww_admin_image_displaycaption',
          __( 'Image caption template', 'mmww' ),
          [ $this, 'admin_text' ],
          'mmww',
          'mmww_admin_image',
          'image_displaycaption' /* wp_posts.post_excerpt */
        );
        add_settings_field(
          'mmww_admin_image_caption',
          __( 'Image description template', 'mmww' ),
          [ $this, 'admin_text' ],
          'mmww',
          'mmww_admin_image',
          'image_caption' /* wp_posts.post_description */
        );


        add_settings_section(
          'mmww_admin_application',
          __( 'PDF Settings', 'mmww' ),
          [ $this, 'application_text' ],
          'mmww' );

        add_settings_field(
          'mmww_admin_application_title',
          __( 'PDF title template', 'mmww' ),
          [ $this, 'admin_text' ],
          'mmww',
          'mmww_admin_application',
          'application_title'
        );
        add_settings_field(
          'mmww_admin_application_caption',
          __( 'PDF description template', 'mmww' ),
          [ $this, 'admin_text' ],
          'mmww',
          'mmww_admin_application',
          'application_caption'
        );

        ?>
          <form action="options.php" method="post">

            <?php
            settings_fields( 'mmww' );
            do_settings_sections( 'mmww' );
            ?>

              <p class="submit">
                  <input name="Submit" type="submit" id="submit"
                         class="button button-primary"
                         value="<?php _e( 'Save Changes', 'mmww' ); ?>"/>
              </p></form>
      </div>

    <?php

  }
}

new MMWWAdmin();

