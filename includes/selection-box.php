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

  private function parseHtmlSelectInfo( $html ) {
    $inputDom = new DOMDocument();
    $inputDom->loadHTML( $html );
    $this->users = [];

    $selects     = $inputDom->getElementsByTagName( 'select' );
    $selectCount = 0;
    foreach ( $selects as $select ) {
      if ( ++ $selectCount > 1 ) {
        throw new ValueError( "More than one select" );
      }
      $attributes  = $select->attributes;
      $this->name  = $attributes->getNamedItem( 'name' )->nodeValue;
      $this->class = array_unique( explode( ' ', $attributes->getNamedItem( 'class' )->nodeValue ) );
      $options     = $select->getElementsByTagName( 'option' );
      foreach ( $options as $option ) {
        $id             = intval( $option->attributes->getNamedItem( 'value' )->nodeValue );
        $label          = $option->textContent;
        $this->users [] = (object) [ 'id' => $id, 'label' => $label ];
      }
    }
    unset ( $inputDom );
    return;
  }

  private function classes() {
    return implode( ' ', array_unique( $this->class ) );
  }

  public function addClass( $class ) {
    if ( is_string( $class ) ) {
      $class = [ $class ];
    }
    $this->class = array_unique( array_merge( $this->class, $class ) );
  }

  public function removeClass( $class ) {
    if ( is_string( $class ) ) {
      $class = [ $class ];
    }
    $this->class = array_diff( $this->class, $class );
  }

  /** Generate a <select> tag.
   *
   * @param bool $pretty Format the output tag for reaaability.
   *
   * @return string HTML for select tag.
   */
  public function generateSelect( $pretty = false ) {
    $o    = [];
    $o [] = "<select name='$this->name' class='{$this->classes()}'>";
    foreach ( $this->users as $user ) {
      $o [] = ( $pretty ? '  ' : '' ) . "<option value='$user->id'>$user->label</option>";
    }
    $o [] = "</select>";
    return implode( $pretty ? PHP_EOL : '', $o );
  }

  /** Generate an autocomplete tag.
   */
  public function generateAutocomplete( $pretty = false ) {
    $nl         = $pretty ? PHP_EOL : '';
    $jsonpretty = $pretty ? JSON_PRETTY_PRINT : JSON_ERROR_NONE;

    $nonce       = wp_create_nonce( 'wp_rest' );
    $count       = $this->options['quickedit_threshold_limit'];
    $placeholder = esc_attr__( 'Type the author\'s name', 'index-wp-users-for-speed' );
    $tag         = "<span class='input-text-wrap'><input type='text' name='$this->name-auto' class='{$this->classes()}' data-count='$count' data-nonce='$nonce' data-p1='$placeholder' data-p2='' placeholder='$placeholder'></span>$nl";

    return $tag ;
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