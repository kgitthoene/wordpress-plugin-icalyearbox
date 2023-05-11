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


class YAICALHelper {
  /**
   * The debug trigger.
   */
  private static $_enable_debugging = true; // false = Log only error messages.
  private static $_log_initialized = false;
  private static $_log_class = null;

  private static $_directories_initialized = false;
  public static $token = 'yetanothericalcalendar';
  public static $token_annotation = 'yetanothericalcalendar-annotation';
  private static $_my_plugin_directory = null;
  private static $_my_log_directory = null;
  private static $_my_cache_directory = null;
  private static $_my_database_directory = null;

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
      // Create database directory.
      self::$_my_database_directory = self::$_my_plugin_directory . '/db';
      if (!is_dir(self::$_my_database_directory)) {
        mkdir(self::$_my_database_directory, 0777, true);
      }
      self::$_directories_initialized = true;
    }
  } // _init_directories

  private static function _init_log() {
    if (!self::$_log_initialized) {
      if (!self::$_directories_initialized) {
        self::_init_directories();
      }
      if (class_exists('YetAnotherICALCalendar_Logger')) {
        self::$_log_class = 'YetAnotherICALCalendar_Logger';
        self::$_log_class::$log_level = 'debug';
        self::$_log_class::$write_log = true;
        self::$_log_class::$log_dir = self::$_my_log_directory;
        self::$_log_class::$log_file_name = self::$token;
        self::$_log_class::$log_file_extension = 'log';
        self::$_log_class::$print_log = false;
      }
      self::$_log_initialized = true;
    }
  } // _init_log

  public static function init() {
    self::_init_directories();
    self::_init_log();
  } // init

  public static function get_my_plugin_directory() {
    self::init();
    return self::$_my_plugin_directory;
  } // get_my_plugin_directory

  public static function get_my_cache_directory() {
    self::init();
    return self::$_my_cache_directory;
  } // get_my_cache_directory

  public static function get_my_database_directory() {
    self::init();
    return self::$_my_database_directory;
  } // get_my_database_directory

  public static function write_log($log = NULL, $is_error = false, $bn = '', $func = '', $line = -1) {
    if (self::$_enable_debugging or $is_error) {
      self::init();
      $bn = (empty($bn) ? basename(debug_backtrace()[1]['file']) : $bn);
      $func = (empty($func) ? debug_backtrace()[1]['function'] : $func);
      $line = ($line == -1 ? intval(debug_backtrace()[0]['line']) : $line);
      $msg = sprintf('[%s:%d:%s] %s', $bn, $line, $func, ((is_array($log) || is_object($log)) ? print_r($log, true) : $log));
      if (is_null(self::$_log_class)) {
        error_log($msg . PHP_EOL);
      } else {
        if ($is_error) {
          self::$_log_class::error($msg);
        } else {
          self::$_log_class::debug($msg);
        }
      }
    }
  } // write_log

  public static function get_token() {
    return self::$token;
  } // get_token

  public static function get_token_annotation() {
    return self::$token_annotation;
  } // get_token_annotation

  public static function swap_values(&$x, &$y) {
    $tmp = $x;
    $x = $y;
    $y = $tmp;
  } // swap_values

  public static function booltostr($b) {
    return $b ? 'true' : 'false';
  } // booltostr

  public static function strtodatetime($ymd) {
    $dt = DateTime::createFromFormat('Ymd His e', $ymd . '000000 UTC');
    return $dt;
  } // strtodatetime

  public static function getav($a, $k) {
    return array_key_exists($k, $a) ? trim(strval($a[$k])) : '';
  } // getav

  public static function purecontent($c) {
    $c = preg_replace('/<br\s\/>/i', "\n", $c);
    $c = preg_replace('/<p>([^<]*?)<\/p>/i', "$1\n", $c);
    return trim($c);
  } // purecontent

  public static function datetime_delta($dt1, $dt2) { // Seconds.
    if (is_a($dt1, 'DateTime') and is_a($dt2, 'DateTime')) {
      $i = $dt1->diff($dt2, true);
      return (intval($i->format('%r%a')) * 86400 + $i->h * 3600 + $i->m * 60 + $i->s);
    } else {
      throw new ErrorException("Parameters must be type DateTime!", 0, E_ERROR, __FILE__, __LINE__);
    }
  } // purecontent

  public static function get_current_user_roles() {
    if (is_user_logged_in()) {
      $user = wp_get_current_user();
      return (array) $user->roles;
    }
    return [];
  } // get_current_user_roles

  public static function is_access($access) {
    $roles = self::get_current_user_roles();
    $is_acc = false;
    if ($access == '+') {
      $is_acc = true;
    } else {
      if (!empty($roles)) {
        if ($access == '*') {
          $is_acc = true;
        } else {
          $roles_acc = explode(',', $access);
          foreach ($roles as $role) {
            foreach ($roles_acc as $role_acc) {
              if ($role == trim($role_acc)) {
                $is_acc = true;
                break;
              }
            }
            if ($is_acc) {
              break;
            }
          }
        }
      }
    }
    return $is_acc;
  } // is_access

  public static function get_or_set_session_cookie($name) {
    if (isset($_COOKIE[$name])) {
      return sanitize_key($_COOKIE[$name]);
    } else {
      try {
        // UUID / SEE:https://gist.github.com/dahnielson/508447
        $uuid = UUID::v4();
        setcookie($name, $uuid, 0);
        return $uuid;
      } catch (Exception $e) {
        return null;
      }
    }
  } // get_or_set_session_cookie

  public static function get_session_cookie($name) {
    if (isset($_COOKIE[$name])) {
      return sanitize_key($_COOKIE[$name]);
    } else {
      return null;
    }
  } // get_or_set_session_cookie

  public static function get_html_error_msg($msg) {
    return '<div style="unicode-bidi: embed; font-family: monospace; font-size:12px; font-weight:normal; color:black; background-color:#FFAA4D; border-left:12px solid red; padding:3px 6px 3px 6px;">' .
      'Plugin YetAnotherICALCalendar::ERROR -- ' . esc_html($msg) . '</div>';
  }
} // YAICALHelper