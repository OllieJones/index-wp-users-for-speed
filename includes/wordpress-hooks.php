<?php

namespace IndexWpUsersForSpeed;

use ReflectionClass;
use ReflectionMethod;

/**
 * Automatically register WordPress hooks in classes derived from this one.
 *
 * Public methods with names starting with "action_" or "filter_"
 * are automatically registered with WordPress on instantiation,
 * and deregistered upon destruction.
 */
abstract class WordPressHooks {

  private $actionPrefix;
  private $filterPrefix;
  private $priority;
  private $methods;

  public function __construct( $actionPrefix = 'action', $filterPrefix = 'filter', $priority = 10 ) {
    $this->actionPrefix = $actionPrefix;
    $this->filterPrefix = $filterPrefix;
    $this->priority     = $priority;

    $this->register();
  }

  protected function register() {
    $reflector     = new ReflectionClass ( $this );
    $this->methods = $reflector->getMethods( ReflectionMethod::IS_PUBLIC );

    foreach ( $this->methods as $method ) {
      $methodName = $method->name;
      $argCount   = $method->getNumberOfParameters();

      $splits = explode( '__', $methodName );
      if ( count( $splits ) >= 2 && count( $splits ) <= 3 ) {
        /* a possible priority. */
        $priority = $this->priority;
        if ( count( $splits ) === 3 ) {
          if ( is_numeric( $splits[2] ) ) {
            $priority = 0 + $splits[2];
          } else if ( $splits[2] === 'first' ) {
            $priority = - 10;
          } else if ( $splits[2] === 'last' ) {
            $priority = 200;
          }
        }
        if ( $splits[0] === $this->actionPrefix ) {
          add_action( $splits[1], [ $this, $methodName ], $priority, $argCount );
        } else if ( $splits[0] === $this->filterPrefix ) {
          add_filter( $splits[1], [ $this, $methodName ], $priority, $argCount );
        }
      }
    }
  }

  protected function unregister() {

    foreach ( $this->methods as $method ) {
      $methodName = $method->name;

      $splits = explode( '__', $methodName );
      if ( count( $splits ) >= 2 && count( $splits ) <= 3 ) {
        if ( $splits[0] === $this->actionPrefix ) {
          remove_action( $splits[1], [ $this, $methodName ] );
        } else if ( $splits[0] === $this->filterPrefix ) {
          remove_filter( $splits[1], [ $this, $methodName ] );
        }
      }
    }
  }
}