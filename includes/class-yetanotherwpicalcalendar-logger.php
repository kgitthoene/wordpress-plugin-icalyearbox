<?php

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Wordpress Plugin YetAnotherWPICALCalendar (PHP Component)
 *
 * @license MIT https://en.wikipedia.org/wiki/MIT_License
 * @author  Kai Thoene <k.git.thoene@gmx.net>
 */

include 'Idearia-Logger.php';


/**
 * Logger wrapper class.
 */
class YetAnotherWPICALCalendar_Logger extends Idearia\Logger {
  public static $log_file_maxsize = 1048576; // 1024 * 1024 = 1MB
  public static $log_file_maxfiles = 10;

  private static function _recursive_log_file_rename_and_removal($fn, $nr = 0) {
    $this_fn = ($nr == 0 ? $fn : ($fn . '.' . strval($nr)));
    $next_fn = $fn . '.' . strval($nr + 1);
    if (($nr + 1) > self::$log_file_maxfiles) {
      if (file_exists($this_fn)) {
        unlink($this_fn);
      }
    } else {
      if (file_exists($next_fn)) {
        self::_recursive_log_file_rename_and_removal($fn, $nr + 1);
      }
      if ($nr != 0) {
        rename($this_fn, $next_fn);
      }
      if ($nr == 0) {
        copy($this_fn, $next_fn);
        if (key_exists(parent::$log_file_path, parent::$output_streams)) {
          ftruncate(parent::$output_streams[parent::$log_file_path], 0);
        }
      }
    }
  } // _recursive_log_file_rename_and_removal

  private static function _check_log_files() {
    if (parent::$write_log) {
      $log_fn = parent::$log_file_path;
      if (file_exists($log_fn)) {
        $log_size = filesize($log_fn);
        if ($log_size > self::$log_file_maxsize) {
          self::_recursive_log_file_rename_and_removal($log_fn);
        }
      }
    }
  } // _check_log_files

  public static function debug($message, $name = '') {
    self::_check_log_files();
    return parent::debug($message, $name);
  } // debug

  public static function info($message, $name = '') {
    self::_check_log_files();
    return parent::info($message, $name);
  } // info

  public static function warning($message, $name = '') {
    self::_check_log_files();
    return parent::warning($message, $name);
  } // warning

  public static function error($message, $name = '') {
    self::_check_log_files();
    return parent::error($message, $name);
  } // error
} // class YetAnotherWPICALCalendar_Logger
