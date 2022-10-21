<?php

namespace IndexWpUsersForSpeed;

use DOMDocument;
use ValueError;

/**
 * Parse, edit, and reconstitute <select> tags.
 */
class SelectionBox {

  public $users;
  public $name;
  private $class;
  private $options;

  /** Constructor.
   *
   * @param string|null $html Input <select> tag.
   */
  public function __construct( $html, $options ) {
    $this->options = $options;
    if ( is_string( $html ) ) {
      $this->parseHtmlSelectInfo( $html );
    }
  }

  /**
   * @param string $html <select> tag with one or more OPTION tags
   *
   * @return void
   */
  private function parseHtmlSelectInfo( $html ) {
    $inputDom    = new DOMDocument();
    $this->users = [];
    if ( is_string( $html ) && strlen( $html ) > 0 ) {
      $inputDom->loadHTML( $html );

      $selects     = $inputDom->getElementsByTagName( 'select' );
      $selectCount = 0;
      foreach ( $selects as $select ) {
        if ( ++ $selectCount > 1 ) {
          throw new ValueError( "More than one select" );
        }
        $attributes  = $select->attributes;
        $this->name  = trim( $attributes->getNamedItem( 'name' )->nodeValue );
        $this->class = array_unique( array_filter( explode( ' ', trim( $attributes->getNamedItem( 'class' )->nodeValue ) ) ) );
        $options     = $select->getElementsByTagName( 'option' );
        foreach ( $options as $option ) {
          $id    = intval( $option->attributes->getNamedItem( 'value' )->nodeValue );
          $label = $option->textContent;
          if ( $id < 0 ) {
            $id = - 1;
            /* this is a core localization, hence no domain */
            $label = __( '&mdash; No Change &mdash;' );
          }
          $this->users [] = (object) [ 'id' => $id, 'label' => $label ];
        }
      }
      unset ( $inputDom );
    } else {
      $this->name  = 'unknown';
      $this->class = [];
    }
  }

  /** Get a class attribute string from the array of class names.
   * @return string
   */
  private function classes() {
    return implode( ' ', array_unique( array_filter( $this->class ) ) );
  }

  /** Add a class name.
   * @param string $class Class name to add.
   *
   * @return void
   */
  public function addClass( $class ) {
    if ( is_string( $class ) ) {
      $class = [ $class ];
    }
    $this->class = array_unique( array_merge( array_filter( $this->class ), array_filter( $class ) ) );
  }

  /** Remove a class name.
   *
   * Does nothing if the class isn't already there.
   *
   * @param string $class Class to remove.
   *
   * @return void
   */
  public function removeClass( $class ) {
    if ( is_string( $class ) ) {
      $class = [ $class ];
    }
    $this->class = array_unique( array_filter (array_diff( $this->class, $class )));
  }

  /** Generate a <select> tag.
   *
   * @param bool $pretty Format the output tag for reaaability.
   *
   * @return string HTML for select tag.
   */
  public function generateSelect( $pretty = false ) {
    $o     = [];
    $o []  = "<select name='$this->name' class='{$this->classes()}'>";
    $users = $this->users;
    usort( $users, function ( $a, $b ) {
      return strnatcasecmp( $a->label, $b->label );
    } );
    foreach ( $users as $user ) {
      $o [] = ( $pretty ? '  ' : '' ) . "<option value='$user->id'>$user->label</option>";
    }
    $o [] = "</select>";
    return implode( $pretty ? PHP_EOL : '', $o );
  }

  /** Generate an autocomplete tag.
   */
  public function generateAutocomplete( $requestedCapabilities, $pretty = false ) {
    $nl = $pretty ? PHP_EOL : '';

    /* pass capabilities to the dataset of the <input> tag so the REST query can include them */
    $data_capabilities = '';
    if ( $requestedCapabilities ) {
      $requestedCapabilities = is_string( $requestedCapabilities ) ? [ $requestedCapabilities ] : $requestedCapabilities;
      $data_capabilities     = esc_attr( 'data-capabilities=' . implode( ',', $requestedCapabilities ) );
    }
    /* we need to give the base URL for the REST API to Javascript
     * so it gets the right site in multisite. */
    $url         = get_site_url();
    $nonce       = wp_create_nonce( 'wp_rest' );
    $count       = $this->options['quickedit_threshold_limit'];
    $placeholder = esc_attr__( 'Type the author\'s name', 'index-wp-users-for-speed' );
    $tag         =
      "<span class='input-text-wrap'><input
 type='text' name='$this->name-auto' class='{$this->classes()}'
 data-count='$count' data-nonce='$nonce' data-url='$url' data-p1='$placeholder' data-p2=''
 $data_capabilities placeholder='$placeholder'></span>$nl";

    return $tag;
  }

  /** Prepend a user to the list of users
   *
   * @param $user
   *
   * @return SelectionBox
   */
  public function prepend( $user ) {
    if ( ! is_array( $user ) ) {
      $user = [ $user ];
    }
    $this->users = array_merge( $user, $this->users );
    return $this;
  }
}
