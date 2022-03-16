<?php

namespace OllieJones\index_wp_users_for_speed;

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/OllieJones
 * @since      1.0.0
 *
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/public
 * @author     Ollie Jones <oj@plumislandmedia.net>
 */
class Index_Wp_Users_For_Speed_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

  /**
   * Initialize the class and set its properties.
   *
   * @since    1.0.0
   */
	public function __construct(  ) {

		$this->plugin_name = INDEX_WP_USERS_FOR_SPEED_NAME;
		$this->version = INDEX_WP_USERS_FOR_SPEED_VERSION;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Index_Wp_Users_For_Speed_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Index_Wp_Users_For_Speed_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Index_Wp_Users_For_Speed_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Index_Wp_Users_For_Speed_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/index-wp-users-for-speed-public.js', array( 'jquery' ), $this->version, false );

	}

}

/** @noinspection PhpExpressionResultUnusedInspection */
new Index_Wp_Users_For_Speed_Public();
