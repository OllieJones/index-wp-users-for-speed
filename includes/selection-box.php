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
  public $class;

  /** Constructor.
   * @param string|null $html Input <select> tag.
   */
  public function __construct( $html ) {
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

  /** Generate a <select> tag.
   * @param bool $pretty Format the output tag for reaaability.
   *
   * @return string HTML for select tag.
   */
  public function generateSelect( $pretty = false ) {
    $o     = [];
    $class = is_array( $this->class ) ? implode( ' ', array_unique( $this->class ) ) : $this->class;
    $o []  = "<select name='$this->name' class='$class'>";
    foreach ( $this->users as $user ) {
      $o [] = ( $pretty ? '  ' : '' ) . "<option value='$user->id'>$user->label</option>";
    }
    $o [] = "</select>";
    return implode( $pretty ? PHP_EOL : '', $o );
  }

  public function prepend( $user ) {
    if ( ! is_array( $user ) ) {
      $user = [ $user ];
    }
    $this->users = array_merge( $user, $this->users );
  }
}
