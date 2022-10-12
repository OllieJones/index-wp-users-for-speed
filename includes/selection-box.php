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
  private $serial = 0;

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

    $currentID = $this->name . '-' . ++ $this->serial;
    $this->addClass( $currentID );

    $s           = [];
    $s []        = "<script type='text/javascript' id='$currentID-script'> ";
    $s []        = "window.wp_iufs = window.wp_iufs ? window.wp_iufs : {}";
    $s []        = "wp_iufs.completionList = " . wp_json_encode( $this->users, $jsonpretty );  //TODO kill this.
    $s []        = "</script>";
    $nonce       = wp_create_nonce( 'wp_rest' );
    $count       = $this->options['quickedit_threshold_limit'];
    $script      = implode( PHP_EOL, $s ) . $nl;
    $placeholder = esc_attr__( 'Type the author\'s name', 'index-wp-users-for-speed' );
    $tag         = "<span class='input-text-wrap'><input type='text' id='$currentID' name='$this->name-auto' class='{$this->classes()}' data-count='$count' data-nonce='$nonce' data-p1='$placeholder' data-p2='' placeholder='$placeholder'></span>$nl";

    $this->removeClass( $currentID );
    return $tag . $script;
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
