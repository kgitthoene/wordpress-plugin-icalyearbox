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
}  // YAICALHelper