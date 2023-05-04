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


class YAICALHelper {
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
    if (!isset($_COOKIE[$name])) {
      // UUID / SEE:https://gist.github.com/dahnielson/508447
      $uuid = UUID::v4();
      setcookie($name, $uuid, 0);
      return $uuid;
    } else {
      return $_COOKIE[$name];
    }
  } // get_or_set_session_cookie
} // YAICALHelper