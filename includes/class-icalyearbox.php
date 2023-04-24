<?php

if (!defined('ABSPATH')) {
  exit;
}

/*
Copyright (c) 2023 Kai Thoene
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

include 'Idearia-Logger.php';


/**
 * Main plugin class file.
 *
 * @package WordPress Plugin Icalyearbox
 */

/*
if (!defined('ABSPATH')) {
exit;
}
*/

/**
 * Main plugin class.
 */
class Icalyearbox {
  /**
   * The single instance of Icalyearbox.
   *
   * @var     object
   * @access  private
   * @since   1.0.0
   */
  private static $_instance = null; //TODO //phpcs:ignore

  /**
   * Default shortcode parameters.
   * 
   * @var     object
   * @access  private
   * @since   1.0.0
   */
  private static $_default_shortcode_params = null;

  /**
   * The debug trigger.
   *
   * @var     object
   * @access  private
   * @since   1.0.0
   */
  private static $_enable_debugging = true; //TODO //phpcs:ignore
  private static $_log_initialized = false;
  private static $_log_class = null;

  private static $_directories_initialized = false;
  public static $token = 'icalyearbox';
  private static $_my_plugin_directory = null;
  private static $_my_log_directory = null;
  private static $_my_cache_directory = null;

  /**
   * Local instance of Icalyearbox_Admin_API
   *
   * @var Icalyearbox_Admin_API|null
   */
  public $admin = null;

  /**
   * Settings class object
   *
   * @var     object
   * @access  public
   * @since   1.0.0
   */
  public $settings = null;

  /**
   * The version number.
   *
   * @var     string
   * @access  public
   * @since   1.0.0
   */
  public $_version; //phpcs:ignore

  /**
   * The main plugin file.
   *
   * @var     string
   * @access  public
   * @since   1.0.0
   */
  public $file;

  /**
   * The main plugin directory.
   *
   * @var     string
   * @access  public
   * @since   1.0.0
   */
  public $dir;

  /**
   * The plugin assets directory.
   *
   * @var     string
   * @access  public
   * @since   1.0.0
   */
  public $assets_dir;

  /**
   * The plugin assets URL.
   *
   * @var     string
   * @access  public
   * @since   1.0.0
   */
  public $assets_url;

  /**
   * Suffix for JavaScripts.
   *
   * @var     string
   * @access  public
   * @since   1.0.0
   */
  public $script_suffix;

  /* ---------------------------------------------------------------------
   * Add log function.
   */
  private static function _init_directories() {
    if (!self::$_directories_initialized) {
      self::$_my_plugin_directory = WP_PLUGIN_DIR . '/' . self::$token;
      if (!is_dir(self::$_my_plugin_directory)) {
        mkdir(self::$_my_plugin_directory, 0777, true);
      }
      // Create logging directory.
      self::$_my_log_directory = self::$_my_plugin_directory . '/log';
      if (!is_dir(self::$_my_log_directory)) {
        mkdir(self::$_my_log_directory, 0777, true);
      }
      // Create cache directory.
      self::$_my_cache_directory = self::$_my_plugin_directory . '/cache';
      if (!is_dir(self::$_my_cache_directory)) {
        mkdir(self::$_my_cache_directory);
      }
      self::$_directories_initialized = true;
    }
  }

  private static function _init_log() {
    if (!self::$_log_initialized) {
      if (self::$_enable_debugging) {
        if (!self::$_directories_initialized) {
          self::_init_directories();
        }
        if (class_exists('Idearia\Logger')) {
          self::$_log_class = 'Idearia\Logger';
          self::$_log_class::$log_level = 'debug';
          self::$_log_class::$write_log = true;
          self::$_log_class::$log_dir = self::$_my_log_directory;
          self::$_log_class::$log_file_name = self::$token;
          self::$_log_class::$log_file_extension = 'log';
          self::$_log_class::$print_log = false;
        }
      }
      self::$_log_initialized = true;
    }
  } // self::_init_log

  public static function write_log($log = NULL) {
    if (self::$_enable_debugging) {
      self::_init_directories();
      self::_init_log();
      $bn = basename(__FILE__);
      $msg = '[' . $bn . ':' . __LINE__ . '] ' . ((is_array($log) || is_object($log)) ? print_r($log, true) : $log);
      if (is_null(self::$_log_class)) {
        error_log($msg . PHP_EOL);
      } else {
        self::$_log_class::debug($msg);
      }
    }
  } // self::write_log

  /**
   * Constructor funtion.
   *
   * @param string $file File constructor.
   * @param string $version Plugin version.
   */
  public function __construct($file = '', $version = '1.0.0') {
    $this->_version = $version;
    //self::$token = 'icalyearbox';

    // Load plugin environment variables.
    $this->file = $file;
    $this->dir = dirname($this->file);
    $this->assets_dir = trailingslashit($this->dir) . 'assets';
    $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));

    $this->script_suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

    register_activation_hook($this->file, array($this, 'install'));

    // Load frontend JS & CSS.
    add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'), 10);
    add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 10);

    // Load admin JS & CSS.
    add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'), 10, 1);
    add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_styles'), 10, 1);

    // Load API for generic admin functions.
    if (is_admin()) {
      $this->admin = new Icalyearbox_Admin_API();
    }

    // Handle localisation.
    $this->load_plugin_textdomain();
    add_action('init', array($this, 'load_localisation'), 0);
  } // __construct

  /**
   * Setup and return class default shortcode parameters.
   *
   * @return Array Associative Array with default parameters.
   */
  private static function default_shortcode_params() {
    if (self::$_default_shortcode_params === null) {
      self::$_default_shortcode_params = array(
        'open' => '\[',
        'close' => '\]',
        'deconstruct' => false,
        'year' => "now", // List of years to show.
        //'recent' => 21, // Display this days before today. TODO: Not used.
        'months' => "all", // List of months to print.
        'align' => "center", // Alignment of output in page.
        //'weekdays' => "all", // Limit weekdays to this list. TODO: Not used.
        'ical' => null, // ical urls sparated by space
        //'size' => 12, // font-size in pt. TODO: Not used.
        'cache' => "86400", // seconds, number + [hdmy]
        'type' => 'event', // [ 'booking', 'event' ]
        'display' => 'year', // [ 'month', 'year' ]
        'description' => 'none', // [ 'mix', 'description', 'summary', 'none' ] / Add description to event. TODO: Documentation.
      );
    }
    return self::$_default_shortcode_params;
  } // default_shortcode_params

  /**
   * Register post type function.
   *
   * @param string $post_type Post Type.
   * @param string $plural Plural Label.
   * @param string $single Single Label.
   * @param string $description Description.
   * @param array  $options Options array.
   *
   * @return bool|string|Icalyearbox_Post_Type
   */
  public function register_post_type($post_type = '', $plural = '', $single = '', $description = '', $options = array()) {

    if (!$post_type || !$plural || !$single) {
      return false;
    }

    $post_type = new Icalyearbox_Post_Type($post_type, $plural, $single, $description, $options);

    return $post_type;
  }

  /**
   * Wrapper function to register a new taxonomy.
   *
   * @param string $taxonomy Taxonomy.
   * @param string $plural Plural Label.
   * @param string $single Single Label.
   * @param array  $post_types Post types to register this taxonomy for.
   * @param array  $taxonomy_args Taxonomy arguments.
   *
   * @return bool|string|Icalyearbox_Taxonomy
   */
  public function register_taxonomy($taxonomy = '', $plural = '', $single = '', $post_types = array(), $taxonomy_args = array()) {

    if (!$taxonomy || !$plural || !$single) {
      return false;
    }

    $taxonomy = new Icalyearbox_Taxonomy($taxonomy, $plural, $single, $post_types, $taxonomy_args);

    return $taxonomy;
  }

  /**
   * Load frontend CSS.
   *
   * @access  public
   * @return void
   * @since   1.0.0
   */
  public function enqueue_styles() {
    wp_register_style(self::$token . '-microtip', esc_url($this->assets_url) . 'css/microtip.min.css', array(), $this->_version);
    wp_enqueue_style(self::$token . '-microtip');
    wp_register_style(self::$token . '-frontend', esc_url($this->assets_url) . 'css/frontend.min.css', array(), $this->_version);
    wp_enqueue_style(self::$token . '-frontend');
    wp_register_style(self::$token . '-style', esc_url($this->assets_url) . 'css/style.min.css', array(), $this->_version);
    wp_enqueue_style(self::$token . '-style');
  } // enqueue_styles

  /**
   * Load frontend Javascript.
   *
   * @access  public
   * @return  void
   * @since   1.0.0
   */
  public function enqueue_scripts() {
    wp_register_script(self::$token . '-frontend', esc_url($this->assets_url) . 'js/frontend' . $this->script_suffix . '.js', array('jquery'), $this->_version, true);
    wp_enqueue_script(self::$token . '-frontend');
    wp_register_script(self::$token . '-html5tooltips', esc_url($this->assets_url) . 'js/html5tooltips.1.7.3' . $this->script_suffix . '.js', array('jquery'), $this->_version, true);
    wp_enqueue_script(self::$token . '-html5tooltips');
    wp_register_script(self::$token . '-script', esc_url($this->assets_url) . 'js/script' . $this->script_suffix . '.js', array('jquery'), $this->_version, true);
    wp_enqueue_script(self::$token . '-script');
  } // enqueue_scripts

  /**
   * Admin enqueue style.
   *
   * @param string $hook Hook parameter.
   *
   * @return void
   */
  public function admin_enqueue_styles($hook = '') {
    wp_register_style(self::$token . '-admin', esc_url($this->assets_url) . 'css/admin.css', array(), $this->_version);
    wp_enqueue_style(self::$token . '-admin');
  } // admin_enqueue_styles

  /**
   * Load admin Javascript.
   *
   * @access  public
   *
   * @param string $hook Hook parameter.
   *
   * @return  void
   * @since   1.0.0
   */
  public function admin_enqueue_scripts($hook = '') {
    wp_register_script(self::$token . '-admin', esc_url($this->assets_url) . 'js/admin' . $this->script_suffix . '.js', array('jquery'), $this->_version, true);
    wp_enqueue_script(self::$token . '-admin');
  } // admin_enqueue_scripts

  /**
   * Load plugin localisation
   *
   * @access  public
   * @return  void
   * @since   1.0.0
   */
  public function load_localisation() {
    load_plugin_textdomain('icalyearbox', false, dirname(plugin_basename($this->file)) . '/languages/');
  } // load_localisation

  /**
   * Load plugin textdomain
   *
   * @access  public
   * @return  void
   * @since   1.0.0
   */
  public function load_plugin_textdomain() {
    $domain = 'icalyearbox';

    $locale = apply_filters('plugin_locale', get_locale(), $domain);

    load_textdomain($domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo');
    load_plugin_textdomain($domain, false, dirname(plugin_basename($this->file)) . '/languages/');
  } // load_plugin_textdomain

  /**
   * Main Icalyearbox Instance
   *
   * Ensures only one instance of Icalyearbox is loaded or can be loaded.
   *
   * @param string $file File instance.
   * @param string $version Version parameter.
   *
   * @return Object Icalyearbox instance
   * @see Icalyearbox()
   * @since 1.0.0
   * @static
   */
  public static function instance($file = '', $version = '1.0.0') {
    if (is_null(self::$_instance)) {
      self::$_instance = new self($file, $version);
    }
    return self::$_instance;
  } // instance

  /**
   * Cloning is forbidden.
   *
   * @since 1.0.0
   */
  public function __clone() {
    _doing_it_wrong(__FUNCTION__, esc_html(__('Cloning of Icalyearbox is forbidden')), esc_attr($this->_version));
  } // __clone

  /**
   * Unserializing instances of this class is forbidden.
   *
   * @since 1.0.0
   */
  public function __wakeup() {
    _doing_it_wrong(__FUNCTION__, esc_html(__('Unserializing instances of Icalyearbox is forbidden')), esc_attr($this->_version));
  } // __wakeup

  /**
   * Installation. Runs on activation.
   *
   * @access  public
   * @return  void
   * @since   1.0.0
   */
  public function install() {
    $this->_log_version_number();
  } // install

  /**
   * Log the plugin version number.
   *
   * @access  public
   * @return  void
   * @since   1.0.0
   */
  private function _log_version_number() { //phpcs:ignore
    update_option(self::$token . '_version', $this->_version);
  } // _log_version_number

  /**
   * Log the plugin version number.
   *
   * @access  public
   * @return  String
   * @since   1.0.0
   */
  public static function icalyearbox_func($atts = array(), $content = null) {
    self::_init_directories();
    self::_init_log();
    //----------    
    if (function_exists('shortcode_atts')) {
      $atts = shortcode_atts(self::default_shortcode_params(), $atts);
    }
    self::write_log($atts);
    $eval = Icalyearbox_Parser::parse($atts, $content, $evaluate_stack, self::$token);
    return $eval;
  } // icalyearbox_func
} // class Icalyearbox

/*
.transparent-bg{
background: rgba(255, 165, 0, 0.73);
}*/

/**
 * Register shortcode.
 */
if (function_exists('add_shortcode')) {
  add_shortcode('icalyearbox', array('Icalyearbox', 'icalyearbox_func'));
}