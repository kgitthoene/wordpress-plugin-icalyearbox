<?php

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Wordpress Plugin YetAnotherICALCalendar (PHP Component)
 *
 * @license MIT https://en.wikipedia.org/wiki/MIT_License
 * @author  Kai Thoene <k.git.thoene@gmx.net>
 */


require_once 'class-yetanothericalcalendar-logger.php';
require_once 'lib/class-yetanothericalcalendar-helper.php';
require_once 'lib/UUID.php';
require_once 'lib/SleekDB/Store.php';


/**
 * Main plugin class file.
 *
 * @package WordPress Plugin YetAnotherICALCalendar
 */

/*
if (!defined('ABSPATH')) {
exit;
}
*/

/**
 * Main plugin class.
 */
class YetAnotherICALCalendar {
  /**
   * The single instance of YetAnotherICALCalendar.
   *
   * @var     object
   * @access  private
   * @since   1.0.0
   */
  private static $_instance = null;

  /**
   * Default shortcode parameters.
   * 
   * @var     object
   * @access  private
   * @since   1.0.0
   */
  private static $_default_yetanothericalcalendar_params = null;
  private static $_default_yetanothericalcalendar_annotation_params = null;

  /**
   * SleekDB Annotations Store Variables.
   */
  private static $_db_annotations_store = null;
  private static $_db_query_builder = null;

  public static $uuid = '';

  /**
   * Local instance of YetAnotherICALCalendar_Admin_API
   *
   * @var YetAnotherICALCalendar_Admin_API|null
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

  /**
   * Constructor funtion.
   *
   * @param string $file File constructor.
   * @param string $version Plugin version.
   */
  public function __construct($file = '', $version = '1.0.0') {
    $this->_version = $version;

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
      $this->admin = new YetAnotherICALCalendar_Admin_API();
    }

    // Handle localisation.
    $this->load_plugin_textdomain();
    add_action('init', array($this, 'load_localisation'), 0);

    // Create AJAX POST handler action.
    YAICALHelper::write_log(sprintf("Register POST handler."));
    add_action('wp_ajax_yetanothericalcalendar_add_annotation', array($this, 'handle_annotation_add'));
    add_action('wp_ajax_nopriv_yetanothericalcalendar_add_annotation', array($this, 'handle_annotation_add'));
    add_action('wp_ajax_yetanothericalcalendar_get_annotations', array($this, 'handle_annotation_get_annotations'));
    add_action('wp_ajax_nopriv_yetanothericalcalendar_get_annotations', array($this, 'handle_annotation_get_annotations'));

    // Set session cookie.
    add_action('init', function () {
      YAICALHelper::write_log(sprintf("get_or_set_session_cookie"));
      $uuid = YAICALHelper::get_or_set_session_cookie('wordpress-yetanothericalcalendar_seesion_cookie');
      YAICALHelper::write_log(sprintf("UUID='%s'", $uuid));
      self::$uuid = $uuid;
    });
  } // __construct

  /**
   * Setup and return class default shortcode parameters.
   *
   * @return Array Associative Array with default parameters.
   */
  private static function default_yetanothericalcalendar_params() {
    if (self::$_default_yetanothericalcalendar_params === null) {
      self::$_default_yetanothericalcalendar_params = array(
        'id' => '', // ID of this calendar.
        'year' => "now", // List of years to show.
        //'recent' => 21, // Display this days before today. TODO: Not used.
        'months' => "all", // List of months to print.
        'align' => "center", // Alignment of output in page.
        //'weekdays' => "all", // Limit weekdays to this list. TODO: Not used.
        'ical' => null, // ical urls sparated by space
        //'size' => 12, // font-size in pt. TODO: Not used.
        'cache' => "86400", // seconds, number + [hdmy]
        'type' => 'event', // [ 'booking-split', 'booking', 'event' ]
        'display' => 'year', // [ 'month', 'year' ]
        'description' => 'none', // [ 'mix', 'description', 'summary', 'none' ] / Add description to event. TODO: Documentation.
        'read' => '+', // Comma separated list of roles: E.g.: 'administrator,editor,author,contributor,subscriber' / Empty = No read access. '*' = Read access for all roles. '+' = Read access for everybody.
        'write' => '*', // Comma separated list of roles: E.g.: 'administrator,editor,author,contributor,subscriber' / Empty = No write access. '*' = Write access for all roles. '+' = Write access for everybody.
      );
    }
    return self::$_default_yetanothericalcalendar_params;
  } // default_yetanothericalcalendar_params

  private static function default_yetanothericalcalendar_annatation_params() {
    if (self::$_default_yetanothericalcalendar_annotation_params === null) {
      self::$_default_yetanothericalcalendar_annotation_params = array(
        'id' => '', // ID of a calendar.
        'read' => '+', // Comma separated list of roles: E.g.: 'administrator,editor,author,contributor,subscriber' / Empty = No read access. '*' = Read access for all roles. '+' = Read access for everybody.
        'write' => '*', // Comma separated list of roles: E.g.: 'administrator,editor,author,contributor,subscriber' / Empty = No write access. '*' = Write access for all roles. '+' = Write access for everybody.
      );
    }
    return self::$_default_yetanothericalcalendar_annotation_params;
  } // default_yetanothericalcalendar_annatation_params

  /**
   * Register post type function.
   *
   * @param string $post_type Post Type.
   * @param string $plural Plural Label.
   * @param string $single Single Label.
   * @param string $description Description.
   * @param array  $options Options array.
   *
   * @return bool|string|YetAnotherICALCalendar_Post_Type
   */
  public function register_post_type($post_type = '', $plural = '', $single = '', $description = '', $options = array()) {

    if (!$post_type || !$plural || !$single) {
      return false;
    }

    $post_type = new YetAnotherICALCalendar_Post_Type($post_type, $plural, $single, $description, $options);

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
   * @return bool|string|YetAnotherICALCalendar_Taxonomy
   */
  public function register_taxonomy($taxonomy = '', $plural = '', $single = '', $post_types = array(), $taxonomy_args = array()) {

    if (!$taxonomy || !$plural || !$single) {
      return false;
    }

    $taxonomy = new YetAnotherICALCalendar_Taxonomy($taxonomy, $plural, $single, $post_types, $taxonomy_args);

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
    wp_register_style(YAICALHelper::get_token() . '-frontend', esc_url($this->assets_url) . 'css/frontend.min.css', array(), $this->_version);
    wp_enqueue_style(YAICALHelper::get_token() . '-frontend');
    // SEE:https://tingle.robinparisi.com/
    wp_register_style(YAICALHelper::get_token() . '-tingle', esc_url($this->assets_url) . 'css/tingle.min.css', array(), $this->_version);
    wp_enqueue_style(YAICALHelper::get_token() . '-tingle');
    wp_register_style(YAICALHelper::get_token() . '-style', esc_url($this->assets_url) . 'css/style.min.css', array(), $this->_version);
    wp_enqueue_style(YAICALHelper::get_token() . '-style');
  } // enqueue_styles

  /**
   * Load frontend Javascript.
   *
   * @access  public
   * @return  void
   * @since   1.0.0
   */
  public function enqueue_scripts() {
    wp_register_script(YAICALHelper::get_token() . '-frontend', esc_url($this->assets_url) . 'js/frontend' . $this->script_suffix . '.js', array('jquery'), $this->_version, true);
    wp_enqueue_script(YAICALHelper::get_token() . '-frontend');
    wp_register_script(YAICALHelper::get_token() . '-html5tooltips', esc_url($this->assets_url) . 'js/html5tooltips.1.7.3' . $this->script_suffix . '.js', array('jquery'), $this->_version, true);
    wp_enqueue_script(YAICALHelper::get_token() . '-html5tooltips');
    wp_register_script(YAICALHelper::get_token() . '-tingle', esc_url($this->assets_url) . 'js/tingle' . $this->script_suffix . '.js', array('jquery'), $this->_version, true);
    wp_enqueue_script(YAICALHelper::get_token() . '-tingle');
    wp_register_script(YAICALHelper::get_token() . '-script', esc_url($this->assets_url) . 'js/script' . $this->script_suffix . '.js', array('jquery'), $this->_version, true);
    wp_enqueue_script(YAICALHelper::get_token() . '-script');
  } // enqueue_scripts

  /**
   * Admin enqueue style.
   *
   * @param string $hook Hook parameter.
   *
   * @return void
   */
  public function admin_enqueue_styles($hook = '') {
    wp_register_style(YAICALHelper::get_token() . '-admin', esc_url($this->assets_url) . 'css/admin.css', array(), $this->_version);
    wp_enqueue_style(YAICALHelper::get_token() . '-admin');
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
    wp_register_script(YAICALHelper::get_token() . '-admin', esc_url($this->assets_url) . 'js/admin' . $this->script_suffix . '.js', array('jquery'), $this->_version, true);
    wp_enqueue_script(YAICALHelper::get_token() . '-admin');
  } // admin_enqueue_scripts

  /**
   * Load plugin localisation
   *
   * @access  public
   * @return  void
   * @since   1.0.0
   */
  public function load_localisation() {
    load_plugin_textdomain('yetanothericalcalendar', false, dirname(plugin_basename($this->file)) . '/languages/');
  } // load_localisation

  /**
   * Load plugin textdomain
   *
   * @access  public
   * @return  void
   * @since   1.0.0
   */
  public function load_plugin_textdomain() {
    $domain = 'yetanothericalcalendar';

    $locale = apply_filters('plugin_locale', get_locale(), $domain);

    load_textdomain($domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo');
    load_plugin_textdomain($domain, false, dirname(plugin_basename($this->file)) . '/languages/');
  } // load_plugin_textdomain

  /**
   * Main YetAnotherICALCalendar Instance
   *
   * Ensures only one instance of YetAnotherICALCalendar is loaded or can be loaded.
   *
   * @param string $file File instance.
   * @param string $version Version parameter.
   *
   * @return Object YetAnotherICALCalendar instance
   * @see YetAnotherICALCalendar()
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
    _doing_it_wrong(__FUNCTION__, esc_html(__('Cloning of YetAnotherICALCalendar is forbidden')), esc_attr($this->_version));
  } // __clone

  /**
   * Unserializing instances of this class is forbidden.
   *
   * @since 1.0.0
   */
  public function __wakeup() {
    _doing_it_wrong(__FUNCTION__, esc_html(__('Unserializing instances of YetAnotherICALCalendar is forbidden')), esc_attr($this->_version));
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
    update_option(YAICALHelper::get_token() . '_version', $this->_version);
  } // _log_version_number

  /**
   * Log the plugin version number.
   *
   * @access  public
   * @return  String
   * @since   1.0.0
   */
  public static function yetanothericalcalendar_func($atts = array(), $content = null) {
    //----------    
    if (function_exists('shortcode_atts')) {
      $atts = shortcode_atts(self::default_yetanothericalcalendar_params(), $atts);
    }
    YAICALHelper::write_log($atts);
    YAICALHelper::write_log(sprintf("UUID='%s'", self::$uuid));
    $eval = YetAnotherICALCalendar_Parser::parse(self::$uuid, $atts, $content, $evaluate_stack, YAICALHelper::get_token());
    return $eval;
  } // yetanothericalcalendar_func

  public static function yetanothericalcalendar_annotation_func($atts = array(), $content = null) {
    //----------    
    if (function_exists('shortcode_atts')) {
      $atts = shortcode_atts(self::default_yetanothericalcalendar_annatation_params(), $atts);
    }
    YAICALHelper::write_log($atts);
    YAICALHelper::write_log(sprintf("UUID='%s'", self::$uuid));
    $eval = YetAnotherICALCalendar_Parser::parse_annotation(self::$uuid, $atts, $content, $evaluate_stack, YAICALHelper::get_token());
    return $eval;
  } // yetanothericalcalendar_annotation_func

  public static function handle_annotation_add() {
    //status_header(200);
    //request handlers should exit() when they complete their task
    foreach ($_POST as $key => $value) {
      $key = sanitize_text_field(strval($key));
      $value = sanitize_text_field(strval($value));
      YAICALHelper::write_log(sprintf("POST[%s]='%s'", $key, $value));
    }
    $pid = '';
    $id = '(unset)';
    $day = '(unset)';
    $is_all_keys_found = true;
    foreach (['pid', 'id', 'day'] as $key) {
      if (!array_key_exists($key, $_POST)) {
        $is_all_keys_found = false;
        break;
      } else {
        $value = sanitize_text_field(strval($_POST[$key]));
        YAICALHelper::write_log(sprintf("POST['%s']='%s'", $key, $value));
        if (empty($_POST[$key])) {
          YAICALHelper::write_log(sprintf("POST['%s']='%s' IS EMPTY!", $key, $value));
          $is_all_keys_found = false;
          break;
        }
      }
    }
    if ($is_all_keys_found) {
      $pid = sanitize_text_field($_POST['pid']);
      $id = sanitize_text_field($_POST['id']);
      $day = sanitize_text_field($_POST['day']);
      $uuid = YAICALHelper::get_session_cookie('wordpress-yetanothericalcalendar_seesion_cookie');
      if (!empty($uuid)) {
        try {
          $a_annotation_rw = YetAnotherICALCalendar_Parser::get_annotation_rw($pid, $id, $uuid);
          YAICALHelper::write_log(sprintf("UUID=%s PID=%s ID='%s' R/W=%s/%s ROLES='%s'",
            $uuid,
            $pid,
            $id,
            YAICALHelper::booltostr($a_annotation_rw['read']),
            YAICALHelper::booltostr($a_annotation_rw['write']),
            implode(', ', YAICALHelper::get_current_user_roles())));
          if ($a_annotation_rw['write']) {
            $note = array_key_exists('note', $_POST) ? sanitize_textarea_field($_POST['note']) : '';
            if (is_null(self::$_db_annotations_store)) {
              self::$_db_annotations_store = new \SleekDB\Store("annotations", YAICALHelper::get_my_database_directory());
              self::$_db_query_builder = self::$_db_annotations_store->createQueryBuilder();
            }
            // Remove before save.
            self::$_db_annotations_store->deleteBy([['id', '=', $id], ['day', '=', $day]]);
            if (!empty($note)) {
              // Save if not empty.
              self::$_db_annotations_store->insert(['id' => $id, 'day' => $day, 'note' => $note]);
            }
            wp_send_json(['status' => 'OK', 'id' => $id, 'day' => $day]);
          } else {
            wp_send_json(['status' => 'FAIL', 'id' => $id, 'day' => $day, 'error' => 'No write permission!']);
          }
        } catch (Exception $e) {
          YAICALHelper::write_log(sprintf("EXCEPTION='%s'", $e->getMessage()));
          wp_send_json(['status' => 'FAIL', 'id' => $id, 'day' => $day, 'error' => esc_html($e->getMessage())]);
        }
      } else {
        wp_send_json(['status' => 'FAIL', 'id' => $id, 'day' => $day, 'error' => 'Session cookie not set!']);
      }
    } else {
      wp_send_json(['status' => 'FAIL', 'id' => $id, 'day' => $day, 'error' => 'Invalid request parameter!']);
    }
    //----------
    wp_die();
  } // handle_annotation_add

  public static function handle_annotation_get_annotations() {
    //status_header(200);
    //request handlers should exit() when they complete their task
    $id = null;
    $pid = null;
    foreach ($_POST as $key => $value) {
      $key = sanitize_text_field(strval($key));
      $value = sanitize_text_field(strval($value));
      YAICALHelper::write_log(sprintf("POST['%s']='%s'", $key, $value));
      if ($key == 'id') {
        $id = $value;
      }
      if ($key == 'pid') {
        $pid = $value;
      }
    }
    $a_annotations = array();
    $doc = '';
    $uuid = YAICALHelper::get_session_cookie('wordpress-yetanothericalcalendar_seesion_cookie');
    if (!empty($uuid)) {
      if (!empty($id) and !empty($pid)) {
        try {
          $a_annotation_rw = YetAnotherICALCalendar_Parser::get_annotation_rw($pid, $id, $uuid);
          YAICALHelper::write_log(sprintf("PID=%s ID='%s' R/W=%s/%s ROLES='%s'",
            $pid,
            $id,
            YAICALHelper::booltostr($a_annotation_rw['read']),
            YAICALHelper::booltostr($a_annotation_rw['write']),
            implode(', ', YAICALHelper::get_current_user_roles())));
          if ($a_annotation_rw['read']) {
            if (is_null(self::$_db_annotations_store)) {
              self::$_db_annotations_store = new \SleekDB\Store("annotations", YAICALHelper::get_my_database_directory());
              self::$_db_query_builder = self::$_db_annotations_store->createQueryBuilder();
            }
            $db_anno = self::$_db_query_builder
              ->where(['id', '=', $id])
              ->orderBy(['day' => 'asc'])
              ->getQuery()
              ->fetch();
            $a_annotations = $db_anno;
            if (empty($a_annotations)) {
              $doc = '<div style="border-left:10px solid rgb(0,212,255); padding:3px 6px 3px 6px; font-size:80%; line-height:14px;">Für den Kalender mit ID="' . $id . '" wurden keine Anmerkungen gefunden.</div>';
            } else {
              //
              //----------
              // Render annotations:
              $is_write = $a_annotation_rw['write'];
              $doc = '<table><tbody>';
              foreach ($db_anno as $anno) {
                $doc .= '<tr>';
                $wastebasket = $is_write
                  ? sprintf('<td><img class="wastebasket" src="%s" onclick="yetanothericalcalendar_del_annotation(\'%s\', \'%s\', \'%s\'); return false"></td>',
                    plugins_url('/assets/img/wastebasket.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'), esc_html($pid), esc_html($anno['id']), esc_html($anno['day']))
                  : '';
                $click_to_edit = $is_write
                  ? sprintf(' onclick="yetanothericalcalendar_annotate(\'%s\', \'%s\', \'%s\'); return false"', esc_html($pid), esc_html($anno['id']), esc_html($anno['day']))
                  : '';
                $click_to_edit_class = $is_write ? ' class="pointer"' : '';
                $dt = YAICALHelper::strtodatetime($anno['day']);
                $day_display = $dt->format("j.n.Y");
                $day_weekday = __($dt->format("D"));
                $doc .= sprintf('%s<td style="text-align:right;"%s%s>%s.</td><td style="text-align:right;"%s%s>%s:</td><td%s%s>%s</td>',
                  $wastebasket,
                  $click_to_edit_class, $click_to_edit, $day_weekday,
                  $click_to_edit_class, $click_to_edit, $day_display,
                  $click_to_edit_class, $click_to_edit, esc_html(str_replace("\n", ' / ', trim($anno['note']))));
                $doc .= '</tr>';
              }
              $doc .= '</tbody></table>';
            }
          } else {
            $doc = '<div style="border-left:10px solid red; padding:3px 6px 3px 6px; font-size:80%; line-height:14px;">Keine Leseerlaubnis für die Notizen!</div>';
          }
          wp_send_json(['status' => 'OK', 'id' => $id, 'annotations' => $a_annotations, 'doc' => $doc]);
        } catch (Exception $e) {
          YAICALHelper::write_log(sprintf("EXCEPTION='%s'", $e->getMessage()));
          wp_send_json(['status' => 'FAIL',
            'id' => $id,
            'annotations' => $a_annotations,
            'doc' => YAICALHelper::get_html_error_msg($e->getMessage())]);
        }
      } else {
        wp_send_json(['status' => 'FAIL',
          'id' => '',
          'annotations' => $a_annotations,
          'doc' => YAICALHelper::get_html_error_msg('Invalid request parameter!')]);
      }
    } else {
      wp_send_json(['status' => 'FAIL',
        'id' => (empty($id) ? '' : $id),
        'annotations' => $a_annotations,
        'doc' => YAICALHelper::get_html_error_msg('Session cookie not set!')]);
    }
    //----------
    wp_die();
  } // handle_annotation_get_annotations
} // class YetAnotherICALCalendar

/*
.transparent-bg{
background: rgba(255, 165, 0, 0.73);
}*/

/**
 * Register shortcode.
 */
if (function_exists('add_shortcode')) {
  add_shortcode('yetanothericalcalendar', array('YetAnotherICALCalendar', 'yetanothericalcalendar_func'));
  add_shortcode('yetanothericalcalendar-annotation', array('YetAnotherICALCalendar', 'yetanothericalcalendar_annotation_func'));
}