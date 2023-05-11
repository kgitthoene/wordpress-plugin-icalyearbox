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