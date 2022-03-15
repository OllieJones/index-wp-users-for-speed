<?php

namespace OllieJones\index_wp_users_for_speed;

use ReflectionClass;
use ReflectionMethod;

/**
 * Automatically register WordPress hooks in classes derived from this one.
 *
 * Public methods with names starting with "action_" or "filter_"
 * are automatically registered with WordPress on instantiation,
 * and deregistered upon destruction.
 */
class WordPressHooks {

  private $actionPrefix;
  private $filterPrefix;
  private $priority;
  private $methods;

  protected function __construct( $actionPrefix = 'action_', $filterPrefix = 'filter_', $priority = 10 ) {
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

      if ( strpos( $methodName, $this->actionPrefix ) === 0 ) {
        $hookName = substr( $methodName, strlen( $this->actionPrefix ) );
        add_action( $hookName, [ $this, $methodName ], $this->priority, $argCount );
      } else if ( strpos( $methodName, $this->filterPrefix ) === 0 ) {
        $hookName = substr( $methodName, strlen( $this->filterPrefix ) );
        add_filter( $hookName, [ $this, $methodName ], $this->priority, $argCount );
      }
    }
  }

  protected function unregister() {

    foreach ( $this->methods as $method ) {
      $methodName = $method->name;
      $argCount   = $method->getNumberOfParameters();

      if ( strpos( $methodName, $this->actionPrefix ) === 0 ) {
        $hookName = substr( $methodName, strlen( $this->actionPrefix ) );
        remove_action( $hookName, [ $this, $methodName ], $this->priority );
      } else if ( strpos( $methodName, $this->filterPrefix ) === 0 ) {
        $hookName = substr( $methodName, 0, count( $this->filterPrefix ) );
        remove_action( $hookName, [ $this, $methodName ], $this->priority );
      }
    }
  }

}