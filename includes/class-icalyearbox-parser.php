<?php

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Wordpress Plugin Icalyearbox (PHP Component)
 *
 * @license MIT https://en.wikipedia.org/wiki/MIT_License
 * @author  Kai Thoene <k.git.thoene@gmx.net>
 */


include 'class-ics-parser-event.php';
include 'class-ics-parser-ical.php';
include 'class-icalyearbox-datespans.php';


/**
 * Parser plugin class.
 */
class Icalyearbox_Parser {
  /**
   * The debug trigger.
   *
   * @var     object
   * @access  private
   * @since   1.0.0
   */
  private static $_enable_debugging = true; // false = Log only error messages.
  private static $_log_initialized = false;
  private static $_log_class = null;

  private static $_directories_initialized = false;
  public static $token = 'icalyearbox';
  private static $_my_plugin_directory = null;
  private static $_my_log_directory = null;
  private static $_my_cache_directory = null;

  private static $_cache_reload_timeout = 86400; // [s] -- 86400 = one day

  private static $_shortcut_src = '';
  private static $_content_src = '';

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
  } // _init_directories

  private static function _init_log() {
    if (!self::$_log_initialized) {
      if (!self::$_directories_initialized) {
        self::_init_directories();
      }
      if (class_exists('Icalyearbox_Logger')) {
        self::$_log_class = 'Icalyearbox_Logger';
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

  public static function write_log($log = NULL, $is_error = false, $bn = '', $func = '', $line = -1) {
    if (self::$_enable_debugging or $is_error) {
      self::_init_directories();
      self::_init_log();
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

  /**
   * Render an error message as output (HTML).
   *
   * @access  private
   * @return  String HTML output.
   * @since   1.0.0
   */
  private static function _error($msg) {
    self::write_log($msg, true, basename(debug_backtrace()[1]['file']), debug_backtrace()[1]['function'], intval(debug_backtrace()[0]['line']));
    $src = '';
    if (!empty(self::$_shortcut_src)) {
      $src = '<br />' . self::$_shortcut_src;
    }
    $rv = '<div style="unicode-bidi: embed; font-family: monospace; font-size:12px; font-weight:normal; color:black; background-color:#FFAA4D; border-left:12px solid red; padding:3px 6px 3px 6px;">' .
      'Plugin Icalyearbox::ERROR -- ' . esc_html($msg) . $src . '</div>';
    return $rv;
  } // _error

  private static function _set_year($match, &$year) {
    if (strtolower($match) == "now") {
      $year = intval(intval(date('Y')));
      return true;
    } else {
      if (preg_match("/^[+-]{0,1}[1-9]\d*$/", $match, $matches)) {
        $year = intval($match);
        return true;
      }
    }
    $year = null;
    return false;
  } // _set_year

  private static function _set_month($match, &$month) {
    if (strtolower($match) == "all") {
      $month = -1;
      return true;
    } else {
      if (strtolower($match) == "now") {
        $month = intval(intval(date('m')));
        return true;
      } else {
        if ((intval($match) >= 1) and (intval($match) <= 12)) {
          $month = intval($match);
          return true;
        }
      }
    }
    $month = null;
    return false;
  } // _set_month

  private static function _swap_values(&$x, &$y) {
    $tmp = $x;
    $x = $y;
    $y = $tmp;
  } // _swap_values

  private static function _booltostr($b) {
    return $b ? 'true' : 'false';
  } // _booltostr

  private static function _strtodatetime($ymd) {
    $dt = DateTime::createFromFormat('Ymd His e', $ymd . '000000 UTC');
    return $dt;
  } // _strtodatetime

  private static function _getav($a, $k) {
    return array_key_exists($k, $a) ? trim(strval($a[$k])) : '';
  } // _getav

  private static function _purecontent($c) {
    $c = preg_replace('/<br\s\/>/i', "\n", $c);
    $c = preg_replace('/<p>([^<]*?)<\/p>/i', "$1\n", $c);
    return trim($c);
  } // _purecontent

  private static function _add_ical_events_to_ical_spans($ical_url, $description, $ical_lines, &$a_ical_events, &$ical_spans) {
    foreach ($ical_lines as $ical_event_key => $ical_event) {
      if (preg_match('/^(\d{8})/', $ical_event->dtstart, $matches)) {
        $dt_start = $matches[1];
        $b_exclude_dtend = false;
        if (preg_match('/^\d{8}$/', $ical_event->dtstart) and preg_match('/^\d{8}$/', $ical_event->dtend)) {
          // In date format dtend is not inclusive!
          // SEE:https://www.rfc-editor.org/rfc/rfc5545#section-3.6.1
          // CITE:„The "DTEND" property for a "VEVENT" calendar component specifies the non-inclusive end of the event.“
          $b_exclude_dtend = true;
          // Exception for ...
          if (preg_match('/^http[s]{0,1}:\/\/(www\.){0,1}fewo-direkt\.de\/icalendar\//', $ical_url)) {
            $b_exclude_dtend = false;
          }
        }
        $dt_end = substr(strval($ical_event->dtend), 0, 8);
        $dt_end = (empty($dt_end) ? $dt_start : $dt_end);
        if($dt_start == $dt_end) {
          $b_exclude_dtend = false;
        }
        if (empty($dt_start) or empty($dt_end)) {
          self::write_log(sprintf("WRONG VEVENT! DTSTART='%s' DTEND='%s'", strval($dt_start), strval($dt_end)));
        } else {
          array_push($a_ical_events, array('EVENT' => $ical_event, 'DTSTART' => $dt_start, 'DTEND' => $dt_end));
          switch ($description) {
            case 'none':
              $dt_description = '';
            case 'description':
              $dt_description = $ical_event->description;
              break;
            case 'summary':
              $dt_description = $ical_event->summary;
              break;
            case 'mix':
              $dt_description = (!empty($ical_event->description) ? $ical_event->description : (!empty($ical_event->summary) ? $ical_event->summary : ''));
              break;
          }
          $from = self::_strtodatetime($dt_start);
          $to = self::_strtodatetime($dt_end);
          if ($b_exclude_dtend) {
            $to = $to->modify('-1 day');
          }
          $span = new Icalyearbox_Datespan($from, $to, $dt_description);
          $ical_spans->add($span);
          self::write_log(sprintf("[%s] FROM=%s TO=%s SPAN='%s'", $ical_event_key, $from->format('c'), $to->format('c'), $span->inspect()));
        }
      } else {
        self::write_log(sprintf("WRONG VEVENT! DTSTART='%s' EMPTY OR WRONG FORMAT!", strval($dt_start)));
      }
    }
  } // _add_ical_events_to_ical_spans
  /**
   * Render calendar as years.
   *
   * @access  public
   * @return  String
   * @since   1.0.0
   */
  private static function _render_as_years($align, $type, $description, $b_use_ical_years, $b_use_ical_months, $a_years, $a_ical_years, $a_months, $a_ical_year_months, $ical_spans, $a_months_names, $a_months_abr, $a_wdays_first_chracter) {
    $day_now = self::_strtodatetime(sprintf("%04d%02d%02d", intval(date('Y')), intval(date('m')), intval(date('d'))));
    $doc = "";
    // Calc start week day and width for all years:
    $calendar_starts_with_wday = 8;
    $calendar_width = 0;
    foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
      // Determine witch month has the „earliest“ weekday.
      foreach (($b_use_ical_months ? $a_ical_year_months[$year] : $a_months) as $month) {
        $month_first_day = self::_strtodatetime(sprintf("%04d%02d01", $year, $month));
        $wday = $month_first_day->format('w');
        $wday = ($wday == 0 ? 7 : $wday);
        $calendar_starts_with_wday = ($wday < $calendar_starts_with_wday ? $wday : $calendar_starts_with_wday);
      }
      // Determine the „width“ of the calendar.
      foreach (($b_use_ical_months ? $a_ical_year_months[$year] : $a_months) as $month) {
        $month_first_day = self::_strtodatetime(sprintf("%04d%02d01", $year, $month));
        $wday = $month_first_day->format('w');
        $wday = ($wday == 0 ? 7 : $wday);
        $nr_mdays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $mwidth = $nr_mdays + ($wday - $calendar_starts_with_wday);
        $calendar_width = ($mwidth > $calendar_width ? $mwidth : $calendar_width);
      }
    }
    $approximated_table_width_in_pixels = 50 + 19 * $calendar_width;
    //
    foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
      self::write_log(sprintf("RENDER YEAR=%d TYPE='%s'", $year, $type));
      self::write_log(sprintf("%04d: STARTWDAY=%d WIDTH=%d DIR='%s'", $year, $calendar_starts_with_wday, $calendar_width, self::$_my_plugin_directory));
      //
      $doc .= sprintf('<div class="icalyearbox-reset-this"><div class="icalyearbox icalyearbox-tag" year="%d"><table class="icalyearbox-tag yr-table%s" width="%dpx"><tbody>',
        $year, ($align == '' ? '' : (' ' . $align)), $approximated_table_width_in_pixels) . PHP_EOL;
      // Table header:
      $doc .= sprintf('<tr class="icalyearbox-tag yr-header"><th class="icalyearbox-tag"><div class="icalyearbox-tag cellc plain frow"><span class="icalyearbox-tag yr-span">%04d</span></div></th>', $year) . PHP_EOL;
      for ($i = 0; $i < $calendar_width; $i++) {
        $offset = ($i % 7);
        $offset = ($offset == 0 ? 7 : $offset);
        $wday_index = ($offset + $calendar_starts_with_wday - 1) % 7;
        $wday_class = '';
        if ($wday_index >= 5) {
          $wday_class = ' wkend';
        }
        $doc .= sprintf('<th class="icalyearbox-tag"><div class="icalyearbox-tag cellc square wday%s">%s</div></th>', $wday_class, $a_wdays_first_chracter[$wday_index]) . PHP_EOL;
      }
      $doc .= '</tr>' . PHP_EOL;
      // Table body (months):
      $nr_month_counter = 0;
      foreach (($b_use_ical_months ? $a_ical_year_months[$year] : $a_months) as $month) {
        $month_first_day = self::_strtodatetime(sprintf("%04d%02d01", $year, $month));
        $month_starts_with_wday = $month_first_day->format('w');
        $month_starts_with_wday = ($month_starts_with_wday == 0 ? 7 : $month_starts_with_wday);
        $nr_mdays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        // Month name:
        $doc .= sprintf('<tr class="icalyearbox-tag"><th><div class="icalyearbox-tag cellc frow%s"><span class="mo-span">%s</span></div></th>',
          (($nr_month_counter % 2) == 1 ? ' alt' : ''),
          __($a_months_abr[$month - 1], 'icalyearbox')) . PHP_EOL;
        //self::write_log(sprintf("%04d%02d: CALSTARTWDAY=%d MONTHSTARTWDAY=%d", $year, $month, $calendar_starts_with_wday, $month_starts_with_wday));
        for ($i = 0; $i < $calendar_width; $i++) {
          $wday = (($calendar_starts_with_wday + $i) % 7);
          $wday = ($wday == 0 ? 7 : $wday);
          $month_day = $i - ($month_starts_with_wday - $calendar_starts_with_wday) + 1;
          if (($month_day >= 1) and ($month_day <= $nr_mdays)) {
            $dt_this_date = self::_strtodatetime(sprintf("%04d%02d%02d", $year, $month, $month_day));
            $pos = $ical_spans->position($dt_this_date, $type);
            //self::write_log(sprintf("%04d%02d%02d: DATE='%s' POS=%d", $year, $month, $month_day, $dt_this_date->format('c'), $pos));
            $td_backgroud_image_style = '';
            if ($type == "event") {
              switch ($pos) {
                case Icalyearbox_Datespans::IS_START:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
                  break;
                case Icalyearbox_Datespans::IS_END:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
                  break;
                case Icalyearbox_Datespans::IS_IN_SPAN:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
                  break;
                case Icalyearbox_Datespans::IS_SPLIT:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
                  break;
                case Icalyearbox_Datespans::IS_FREE:
                  break;
              }
            } else {
              switch ($pos) {
                case Icalyearbox_Datespans::IS_START:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/start-background.svg', self::$_my_plugin_directory . '/index.php'));
                  break;
                case Icalyearbox_Datespans::IS_END:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/end-background.svg', self::$_my_plugin_directory . '/index.php'));
                  break;
                case Icalyearbox_Datespans::IS_IN_SPAN:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
                  break;
                case Icalyearbox_Datespans::IS_SPLIT:
                  self::write_log(sprintf("SPLIT: %04d%02d%02d", $year, $month, $month_day));
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/split-background.svg', self::$_my_plugin_directory . '/index.php'));
                  break;
                case Icalyearbox_Datespans::IS_FREE:
                  break;
              }
            }
            /*
            if ($month_day == 1) {
            self::write_log(sprintf("%04d%02d01: WDAY=%d", $year, $month, $wday));
            }
            */
            $a_wday_classes = array();
            if ($wday >= 6) {
              array_push($a_wday_classes, "wkend");
            }
            if ($dt_this_date == $day_now) {
              array_push($a_wday_classes, "today");
            }
            $wday_class = "";
            if (count($a_wday_classes)) {
              $wday_class = sprintf(' %s', implode(' ', $a_wday_classes));
            }
            $desc = '';
            if ($type == "event") {
              $a_desc = $ical_spans->description($dt_this_date);
              $desc = (count($a_desc) ? ': ' . implode(', ', $a_desc) : '');
            }
            $desc = '';
            if ($description != 'none') {
              $a_desc = $ical_spans->description($dt_this_date);
              $desc = (count($a_desc) ? implode(', ', $a_desc) : '');
            }
            if (empty($desc)) {
              $mo_day_span = sprintf('<span class="icalyearbox-tag">%02d</span>', $month_day);
            } else {
              //SEE:https://github.com/ytiurin/html5tooltipsjs
              $mo_day_span = sprintf('<span class="icalyearbox-tag icalyearbox-hint" data-tooltip="%s">%02d</span>', esc_html($desc), $month_day);
            }
            $doc .= sprintf('<td class="icalyearbox-tag"><div class="icalyearbox-tag cellc square%s"%s>%s</div></td>',
              $wday_class, $td_backgroud_image_style, $mo_day_span) . PHP_EOL;
            /*
            $doc .= sprintf('<td class="icalyearbox-tag"><div class="icalyearbox-tag cellc square%s"%s><a href="#" class="icalyearbox-tag link" title="%02d.%02d.%04d%s" rel="nofollow">%02d</a></div></td>',
            $wday_class, $td_backgroud_image_style, $month_day, $month, $year, $desc, $month_day) . PHP_EOL;
            */
          } else {
            $doc .= sprintf('<td class="icalyearbox-tag"><div class="icalyearbox-tag cellc square blank">&nbsp;</div></td>') . PHP_EOL;
          }
        }
        $doc .= '</tr>' . PHP_EOL;
        $nr_month_counter++;
      }
      $doc .= '</tbody></table></div></div>' . PHP_EOL;
    }
    return $doc;
  } // _render_as_years

  /**
   * Render calendar as months.
   *
   * @access  public
   * @return  String
   * @since   1.0.0
   */
  private static function _render_as_months($align, $type, $description, $b_use_ical_years, $b_use_ical_months, $a_years, $a_ical_years, $a_months, $a_ical_year_months, $ical_spans, $a_months_names, $a_months_abr, $a_wdays_first_chracter) {
    $day_now = self::_strtodatetime(sprintf("%04d%02d%02d", intval(date('Y')), intval(date('m')), intval(date('d'))));
    $doc = "";
    // Calc start week day and width for all years:
    $calendar_height = 4;
    foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
      // Determine the „height“ of the calendar.
      foreach (($b_use_ical_months ? $a_ical_year_months[$year] : $a_months) as $month) {
        $month_first_day = self::_strtodatetime(sprintf("%04d%02d01", $year, $month));
        $wday = $month_first_day->format('w');
        $wday = ($wday == 0 ? 7 : $wday);
        $nr_mdays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $mheight = ceil(($nr_mdays + $wday - 1) / 7.0);
        $calendar_height = ($mheight > $calendar_height ? $mheight : $calendar_height);
      }
    }
    $approximated_table_width_in_pixels = 19 * 7;
    //
    $doc .= sprintf('<div class="icalyearbox-reset-this"><div class="icalyearbox icalyearbox-tag mo-grid">') . PHP_EOL;
    foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
      self::write_log(sprintf("RENDER AS MONTHS YEAR=%d", $year));
      self::write_log(sprintf("%04d: WIDTH=%d HEIGHT=%d DIR='%s' DESCRIPTION=%s", $year, 7, $calendar_height, self::$_my_plugin_directory, self::_booltostr($description)));
      $nr_month_counter = 0;
      foreach (($b_use_ical_months ? $a_ical_year_months[$year] : $a_months) as $month) {
        //
        // Table body (months):
        $doc .= sprintf('<div class="icalyearbox-tag mo-column"><table class="icalyearbox-tag mo-table%s" width="%dpx" year-mo="%04d%02d"><tbody>',
          ($align == '' ? '' : (' ' . $align)), $approximated_table_width_in_pixels, $year, $month) . PHP_EOL;
        // Table header:
        $doc .= sprintf('<tr class="icalyearbox-tag"><th class="icalyearbox-tag" colspan="7"><div class="icalyearbox-tag cellc plain"><span class="icalyearbox-tag yr-span">%s&nbsp;&nbsp;%04d</span></div></th></tr>',
          __($a_months_names[$month - 1], 'icalyearbox'), $year) . PHP_EOL;
        $doc .= sprintf('<tr class="icalyearbox-tag mo-header">') . PHP_EOL;
        for ($i = 0; $i < 7; $i++) {
          $wday_class = '';
          if ($i >= 5) {
            $wday_class = ' wkend';
          }
          $doc .= sprintf('<th class="icalyearbox-tag"><div class="icalyearbox-tag cellc square wday%s">%s</div></th>', $wday_class, $a_wdays_first_chracter[$i]) . PHP_EOL;
        }
        $doc .= '</tr>' . PHP_EOL;
        $month_first_day = self::_strtodatetime(sprintf("%04d%02d01", $year, $month));
        $month_starts_with_wday = $month_first_day->format('w');
        $month_starts_with_wday = ($month_starts_with_wday == 0 ? 7 : $month_starts_with_wday);
        $nr_mdays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $month_index = 0;
        $month_day = 1;
        $month_height = ceil(($nr_mdays + $month_starts_with_wday - 1) / 7.0);
        for ($y = 1; $y <= $calendar_height; $y++) {
          // Render week.
          $doc .= sprintf('<tr class="icalyearbox-tag">') . PHP_EOL;
          for ($i = 0; $i < 7; $i++) {
            if ($y > $month_height) {
              $doc .= sprintf('<td class="icalyearbox-tag hidden"><div class="icalyearbox-tag cellc square">&nbsp;</div></td>') . PHP_EOL;
            } else {
              $month_index++;
              $wday = $i + 1;
              if (($month_index < $month_starts_with_wday) or ($month_day > $nr_mdays)) {
                $doc .= sprintf('<td class="icalyearbox-tag"><div class="icalyearbox-tag cellc square blank">&nbsp;</div></td>') . PHP_EOL;
              } else {
                $dt_this_date = self::_strtodatetime(sprintf("%04d%02d%02d", $year, $month, $month_day));
                $pos = $ical_spans->position($dt_this_date, $type);
                $td_backgroud_image_style = '';
                if ($type == "event") {
                  switch ($pos) {
                    case Icalyearbox_Datespans::IS_START:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
                      break;
                    case Icalyearbox_Datespans::IS_END:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
                      break;
                    case Icalyearbox_Datespans::IS_IN_SPAN:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
                      break;
                    case Icalyearbox_Datespans::IS_FREE:
                      break;
                  }
                } else {
                  switch ($pos) {
                    case Icalyearbox_Datespans::IS_START:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/start-background.svg', self::$_my_plugin_directory . '/index.php'));
                      break;
                    case Icalyearbox_Datespans::IS_END:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/end-background.svg', self::$_my_plugin_directory . '/index.php'));
                      break;
                    case Icalyearbox_Datespans::IS_IN_SPAN:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
                      break;
                    case Icalyearbox_Datespans::IS_SPLIT:
                      self::write_log(sprintf("SPLIT! DATE='%s'", $dt_this_date->format('c')));
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/split-background.svg', self::$_my_plugin_directory . '/index.php'));
                      break;
                    case Icalyearbox_Datespans::IS_FREE:
                      break;
                  }
                }
                if ($month_day == 1) {
                  self::write_log(sprintf("%04d%02d01: WDAY=%d", $year, $month, $wday));
                }
                $a_wday_classes = array();
                if ($wday >= 6) {
                  array_push($a_wday_classes, "wkend");
                }
                if ($dt_this_date == $day_now) {
                  array_push($a_wday_classes, "today");
                }
                $wday_class = "";
                if (count($a_wday_classes)) {
                  $wday_class = sprintf(' %s', implode(' ', $a_wday_classes));
                }
                $desc = '';
                if ($description != 'none') {
                  $a_desc = $ical_spans->description($dt_this_date);
                  $desc = (count($a_desc) ? implode(', ', $a_desc) : '');
                }
                if (empty($desc)) {
                  $mo_day_span = sprintf('<span class="icalyearbox-tag">%02d</span>', $month_day);
                } else {
                  //SEE:https://github.com/ytiurin/html5tooltipsjs
                  $mo_day_span = sprintf('<span class="icalyearbox-tag icalyearbox-hint" data-tooltip="%s">%02d</span>', esc_html($desc), $month_day);
                }
                $doc .= sprintf('<td class="icalyearbox-tag"><div class="icalyearbox-tag cellc square%s"%s>%s</div></td>',
                  $wday_class, $td_backgroud_image_style, $mo_day_span) . PHP_EOL;
                /*
                $doc .= sprintf('<td class="icalyearbox-tag"><div class="icalyearbox-tag cellc square%s"%s><a href="#" class="icalyearbox-tag link" title="%02d.%02d.%04d%s" rel="nofollow">%02d</a></div></td>',
                $wday_class, $td_backgroud_image_style, $month_day, $month, $year, $desc, $month_day) . PHP_EOL;
                */
                $month_day++;
              }
            }
          }
          $doc .= sprintf('</tr">') . PHP_EOL;
        }
        $nr_month_counter++;
        $doc .= '</tbody></table></div>' . PHP_EOL;
      } // months loop
    } // years loop
    $doc .= '</div></div>' . PHP_EOL;
    return $doc;
  } // _render_as_months

  /**
   * Parse strings with shortcodes.
   *
   * @access  public
   * @return  String
   * @since   1.0.0
   */
  public static function parse($atts, $content, &$evaluate_stack = NULL, $token = 'icalyearbox') {
    date_default_timezone_set("Europe/Berlin");
    //----------
    self::_init_directories();
    self::_init_log(self::$_my_log_directory);
    //----------
    self::$_content_src = $content;
    $content = self::_purecontent($content);
    $has_content = !empty($content);
    self::write_log("CONTENT='" . $content . "'");
    //
    //----------
    // Construct original shortcut source code.
    $shortcut_src = '[' . self::$token;
    $a_atts_keys = array_keys($atts);
    foreach ($a_atts_keys as $key) {
      $shortcut_src .= ' ' . $key . '="' . $atts[$key] . '"';
    }
    $shortcut_src .= ']';
    if (!empty($content)) {
      $shortcut_src .= "<br />" . self::$_content_src;
      $shortcut_src .= '[/' . self::$token . ']';
    }
    self::$_shortcut_src = $shortcut_src;
    self::write_log(sprintf(""));
    //
    //----------
    // Get alignment.
    $align = (in_array($atts['align'], ['left', 'center', 'right']) ? $atts['align'] : 'center');
    //
    // Get calendar type.
    $type = (in_array($atts['type'], ['booking-split', 'booking', 'event']) ? $atts['type'] : 'event');
    //
    // Get calendar display type.
    $display = (in_array($atts['display'], ['month', 'year']) ? $atts['display'] : 'year');
    //
    // Get description flag.
    $description = (in_array($atts['description'], [false, true]) ? $atts['description'] : false);
    //
    //----------
    // Load ical(s).
    //
    $b_use_ical_years = false;
    $b_use_ical_months = false;
    $a_ical_events = array();
    $a_ical_years = array();
    $a_ical_year_months = array();
    $ical_spans = new Icalyearbox_Datespans();
    $cache_reload_timeout = 86400; // 1 day
    //
    //----------
    // Get cache timeout.
    if (array_key_exists('cache', $atts)) {
      $atts_cache = trim($atts['cache']);
      if (preg_match("/^([1-9]\d*)((?i)[hdmy])$/", $atts_cache, $matches)) {
        //self::write_log(sprintf("CACHE-MATCHES=%d", count($matches)));
        if (count($matches) == 3) {
          $multiplier = intval($matches[1]);
          $time_period = 86400;
          switch (strtolower($matches[2])) {
            case 'h':
              $time_period = 3600;
              break;
            case 'm':
              $time_period = 86400 * 30.436875;
              break;
            case 'y':
              $time_period = 86400 * 365.2425;
              break;
          }
          $cache_reload_timeout = intval(floor($multiplier * $time_period));
        }
      } else {
        if (preg_match("/^([1-9]\d*|0)$/", $atts_cache, $matches)) {
          if (count($matches) == 2) {
            $cache_reload_timeout = intval($matches[1]);
          }
        }
      }
    }
    self::write_log(sprintf("CAHCE-RELOAD-TIMEOUT=%d", $cache_reload_timeout));
    //
    //----------
    // Get ical urls:
    $a_ical_urls = array();
    if (array_key_exists('ical', $atts)) {
      $atts_ical = trim($atts['ical']);
      self::write_log(sprintf("PARAM ICAL='%s'", $atts_ical));
      if (preg_match("/^(\S+(\s+))*(\S+)$/", $atts_ical, $matches)) {
        //self::write_log(sprintf("ICAL-URL-MATCHES=%d", count($matches)));
        //self::write_log($matches);
        if (count($matches) == 4) {
          $ical_list_string = preg_replace('/\s+/', ' ', $matches[0]);
          $a_ical_url_list = explode(' ', $ical_list_string);
          foreach ($a_ical_url_list as $ical_url) {
            if (!in_array($ical_url, $a_ical_urls)) {
              array_push($a_ical_urls, $ical_url);
            }
          }
        }
      }
    }
    //
    //----------
    // Fetch ical urls:
    foreach ($a_ical_urls as $ical_url) {
      $file_id = hash("sha256", $ical_url);
      self::write_log(sprintf("URL='%s' HASH='%s'", $ical_url, $file_id));
      $cache_fn = self::$_my_cache_directory . '/' . $file_id;
      try {
        $has_reload = true;
        if (file_exists($cache_fn)) {
          // Calc time difference.
          $t_cache_file_diff = time() - filemtime($cache_fn);
          if ($t_cache_file_diff < $cache_reload_timeout) {
            $has_reload = false;
          }
          self::write_log(sprintf("T-CACHE-DIFF=%d HAS-RELOAD=%s", $t_cache_file_diff, self::_booltostr($has_reload)));
        }
        // Load external resource.
        if ($has_reload) {
          $response = wp_remote_get($ical_url, ['timeout' => 30]);

          if (is_array($response) && (!is_wp_error($response))) {
            $response_code = intval(wp_remote_retrieve_response_code($response));
            self::write_log(sprintf("HTTP-RESPONSE-CODE=%d", $response_code));
            if ($response_code == 200) {
              // Write to cahce:
              $my_cache_file = fopen($cache_fn, "w");
              fwrite($my_cache_file, wp_remote_retrieve_body($response));
              fclose($my_cache_file);
            } else {
              return self::_error(sprintf("Cannot load URL! URL=\"%s\" RESPONSE-CODE=\"%s\"", $ical_url, strval(wp_remote_retrieve_response_code($response))));
            }
          } else {
            return self::_error(sprintf("Cannot load URL! URL=\"%s\"", $ical_url));
          }
        }
        // Analyse ical data.
        if (file_exists($cache_fn)) {
          //$ical = new icalyearbox_iCalEasyReader();
          //SEE:https://github.com/u01jmg3/ics-parser
          $ical = new Ical\ICal($cache_fn, array(
            'defaultTimeZone' => 'Europe/Berlin',
            'defaultWeekStart' => 'MO',
            'skipRecurrence' => false,
            'defaultSpan' => 5));
          self::write_log(sprintf("ICAL-SIZE=%d", $ical->eventCount));
          if ($ical->eventCount > 0) {
            self::_add_ical_events_to_ical_spans($ical_url, $description, $ical->events(), $a_ical_events, $ical_spans);
          }
        }
      } catch (Exception $e) {
        $msg = sprintf("ERROR='%s'", strval($e));
        self::write_log($msg);
        return self::_error($msg);
      }
    }
    //
    //----------
    // Analyse ICAL content:
    if ($has_content) {
      //$ical = new icalyearbox_iCalEasyReader();
      //SEE:https://github.com/u01jmg3/ics-parser
      $ical = new Ical\ICal($content, array(
        'defaultTimeZone' => 'Europe/Berlin',
        'defaultWeekStart' => 'MO',
        'skipRecurrence' => false,
        'defaultSpan' => 5));
      self::write_log(sprintf("CONTENT-ICAL-SIZE=%d", $ical->eventCount));
      if ($ical->eventCount > 0) {
        self::_add_ical_events_to_ical_spans('', $description, $ical->events(), $a_ical_events, $ical_spans);
      }
    }
    //
    //----------
    // Get years and months per year from ical events.
    foreach ($a_ical_events as $ical_event_key => $a_ical_event) {
      $ical_event = $a_ical_event['EVENT'];
      $dt_start = $a_ical_event['DTSTART'];
      $dt_end = $a_ical_event['DTEND'];
      // Collect ical spans:
      //self::write_log(sprintf("[%s] GET YEARS/MONTHS ICAL-DTSTART='%s' DTEND='%s'", $ical_event_key, $dt_start, $dt_end));
      foreach ([$dt_start, $dt_end] as $dt) {
        if (preg_match("/^(\d{4})(\d{2})(\d{2})/", $dt, $matches)) {
          if (count($matches) == 4) {
            $year = intval($matches[1]);
            $month = intval($matches[2]);
            if (!in_array($year, $a_ical_years)) {
              array_push($a_ical_years, $year);
            }
            if (!array_key_exists($year, $a_ical_year_months)) {
              $a_ical_year_months[$year] = array();
            }
            if (!in_array($month, $a_ical_year_months[$year])) {
              array_push($a_ical_year_months[$year], $month);
            }
          }
        }
      }
    }
    //----------
    // Make continuous list of months in a year:
    foreach ($a_ical_year_months as $year => $a_i_months) {
      $min = min($a_i_months);
      $max = max($a_i_months);
      $a_cont_months = array();
      for ($m = $min; $m <= $max; $m++) {
        array_push($a_cont_months, $m);
      }
      $a_ical_year_months[$year] = $a_cont_months;
    }
    //
    //----------
    // Collect all years.
    $b_years_syntax_is_correct = false;
    $a_years = array();
    //self::write_log($atts);
    if (array_key_exists('year', $atts)) {
      $atts_year = trim($atts['year']);
      //self::write_log(sprintf("PARAM YEAR='%s'", $atts_year));
      if (preg_match("/^((?i)NOW|(?i)ICAL|[1-9][0-9]*)(\+[1-9][0-9]*){0,1}(-[1-9][0-9]*){0,1}$/", $atts_year, $matches)
        or preg_match("/^((?i)NOW|(?i)ICAL|[1-9][0-9]*)(\-[1-9][0-9]*){0,1}(\+[1-9][0-9]*){0,1}$/", $atts_year, $matches)) {
        //self::write_log($matches);
        if (strtolower($matches[1]) == 'ical') {
          $b_use_ical_years = true;
          switch (count($matches)) {
            case 2:
              // Detected the word "ical":
              $b_years_syntax_is_correct = true;
              break;
            case 3:
              // Detected ONE plus or minus.
              $b_years_syntax_is_correct = true;
              $offset = intval($matches[2]);
              if ($offset < 0) {
                $base_year = min($a_ical_years);
              } else {
                $base_year = max($a_ical_years);
              }
              $from = $base_year;
              $to = $base_year + $offset;
              if ($from > $to) {
                self::_swap_values($from, $to);
              }
              for ($year = $from; $year <= $to; $year++) {
                if (!in_array($year, $a_ical_years)) {
                  array_push($a_ical_years, $year);
                }
              }
              sort($a_ical_years);
              break;
            case 4:
              // Detected TWO plus or minus values.
              $b_years_syntax_is_correct = true;
              $offset1 = intval($matches[2]);
              $offset2 = intval($matches[3]);
              if ($offset1 < 0) {
                $base_year1 = min($a_ical_years);
              } else {
                $base_year1 = max($a_ical_years);
              }
              if ($offset2 < 0) {
                $base_year2 = min($a_ical_years);
              } else {
                $base_year2 = max($a_ical_years);
              }
              $from = $base_year1 + $offset1;
              $to = $base_year2 + $offset2;
              if ($from > $to) {
                self::_swap_values($from, $to);
              }
              for ($year = $from; $year <= $to; $year++) {
                if (!in_array($year, $a_ical_years)) {
                  array_push($a_ical_years, $year);
                }
              }
              sort($a_ical_years);
              break;
          }
        } else { // Base year is "now" or NUMBER:
          self::_set_year($matches[1], $base_year);
          switch (count($matches)) {
            case 2:
              // Detected the word "now" or NUMBER:
              $b_years_syntax_is_correct = true;
              array_push($a_years, $base_year);
              break;
            case 3:
              // Detected one plus or minus.
              $b_years_syntax_is_correct = true;
              $offset = intval($matches[2]);
              $from = $base_year;
              $to = $base_year + $offset;
              if ($from > $to) {
                self::_swap_values($from, $to);
              }
              for ($year = $from; $year <= $to; $year++) {
                array_push($a_years, $year);
              }
              break;
            case 4:
              // Detected two plus or minus values.
              $b_years_syntax_is_correct = true;
              $offset1 = intval($matches[2]);
              $offset2 = intval($matches[3]);
              $from = $base_year + $offset1;
              $to = $base_year + $offset2;
              if ($from > $to) {
                self::_swap_values($from, $to);
              }
              for ($year = $from; $year <= $to; $year++) {
                array_push($a_years, $year);
              }
              break;
          }
        }
      } else {
        // year interval = from--to
        if (preg_match("/^((?i)NOW|\d+)\s*--\s*((?i)NOW|\d+)$/", $atts_year, $matches)) {
          // year range = two values (inclusive):
          if (count($matches) == 3) {
            if (self::_set_year($matches[1], $from) and self::_set_year($matches[2], $to)) {
              $b_years_syntax_is_correct = true;
              if ($from > $to) {
                self::_swap_values($from, $to);
              }
              for ($year = $from; $year <= $to; $year++) {
                array_push($a_years, $year);
              }
            }
          }
        } else {
          // year list = a,b,c[...]
          if (preg_match("/^(((?i)NOW|[\d,\s]+)+)$/", $atts_year, $matches)) {
            if (count($matches) == 3) {
              $b_years_syntax_is_correct = true;
              $years_list_string = preg_replace('/\s+/', '', $matches[1]);
              $a_years_list = explode(',', $years_list_string);
              foreach ($a_years_list as $year_raw) {
                if (self::_set_year($year_raw, $year)) {
                  array_push($a_years, $year);
                }
              }
            }
          }
        }
      }
      if (!$b_years_syntax_is_correct) {
        return self::_error(sprintf("The syntax of YEAR is incorrect. YEAR=\"%s\"", $atts_year));
      }
    }
    //
    //----------
    // Collect all months.
    $b_months_syntax_is_correct = false;
    $a_months = array();
    //self::write_log($atts);
    if (array_key_exists('months', $atts)) {
      $atts_months = trim($atts['months']);
      self::write_log(sprintf("PARAM MONTHS='%s'", $atts_months));
      if (preg_match("/^((?i)NOW|(?i)ALL|(?i)ICAL|[1-9][0-9]*)(\+[1-9][0-9]*){0,1}(-[1-9][0-9]*){0,1}$/", $atts_months, $matches)
        or preg_match("/^((?i)NOW|(?i)ALL|(?i)ICAL|[1-9][0-9]*)(\-[1-9][0-9]*){0,1}(\+[1-9][0-9]*){0,1}$/", $atts_months, $matches)) {
        if (strtolower($matches[1]) == 'ical') {
          $b_months_syntax_is_correct = true;
          $b_use_ical_months = true;
          //self::write_log($matches);
          switch (count($matches)) {
            case 2:
              // ICAL months, do nothing these are set.
              $b_months_syntax_is_correct = true;
              break;
            case 3:
              // Detected one plus or minus.
              $b_months_syntax_is_correct = true;
              foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
                if (array_key_exists($year, $a_ical_year_months) and (!empty($a_ical_year_months[$year]))) {
                  $offset = intval($matches[2]);
                  if ($offset >= 0) {
                    $from = min($a_ical_year_months[$year]);
                    $to = min([12, max($a_ical_year_months[$year]) + $offset]);
                  } else {
                    $from = max([1, min($a_ical_year_months[$year]) + $offset]);
                    $to = max($a_ical_year_months[$year]);
                  }
                  if ($from > $to) {
                    self::_swap_values($from, $to);
                  }
                  $a_new_months = array();
                  for ($month = $from; $month <= $to; $month++) {
                    array_push($a_new_months, $month);
                  }
                  $a_ical_year_months[$year] = $a_new_months;
                } else {
                  return self::_error(sprintf("No ICAL data found for this year! YEAR=\"%s\"", $year));
                }
              }
              break;
            case 4:
              // Detected two plus or minus values.
              $b_months_syntax_is_correct = true;
              foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
                if (array_key_exists($year, $a_ical_year_months) and (!empty($a_ical_year_months[$year]))) {
                  $offset1 = intval($matches[2]);
                  $offset2 = intval($matches[3]);
                  if ($offset1 > $offset2) {
                    self::_swap_values($offset1, $offset2);
                  }
                  $from = max([1, min($a_ical_year_months[$year]) + $offset1]);
                  $to = min([12, max($a_ical_year_months[$year]) + $offset2]);
                  $a_new_months = array();
                  for ($month = $from; $month <= $to; $month++) {
                    array_push($a_new_months, $month);
                  }
                  $a_ical_year_months[$year] = $a_new_months;
                } else {
                  return self::_error(sprintf("No ICAL data found for this year! YEAR=\"%s\"", $year));
                }
              }
              break;
          }
        } else {
          //self::write_log($matches);
          if (self::_set_month($matches[1], $base_month)) {
            switch (count($matches)) {
              case 2:
                // Detected the word "all" or "now":
                $b_months_syntax_is_correct = true;
                if ($base_month == -1) {
                  array_push($a_months, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);
                } else {
                  array_push($a_months, $base_month);
                }
                break;
              case 3:
                // Detected one plus or minus.
                $b_months_syntax_is_correct = true;
                if ($base_month == -1) {
                  array_push($a_months, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);
                } else {
                  $offset = intval($matches[2]);
                  $from = $base_month;
                  $to = $base_month + $offset;
                  if ($from > $to) {
                    self::_swap_values($from, $to);
                  }
                  $to = ($to < 1 ? 1 : $to);
                  $to = ($to > 12 ? 12 : $to);
                  for ($month = $from; $month <= $to; $month++) {
                    array_push($a_months, $month);
                  }
                }
                break;
              case 4:
                // Detected two plus or minus values.
                $b_months_syntax_is_correct = true;
                if ($base_month == -1) {
                  array_push($a_months, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);
                } else {
                  $offset1 = intval($matches[2]);
                  $offset2 = intval($matches[3]);
                  $from = $base_month + $offset1;
                  $to = $base_month + $offset2;
                  if ($from > $to) {
                    self::_swap_values($from, $to);
                  }
                  $from = ($from < 1 ? 1 : $from);
                  $from = ($from > 12 ? 12 : $from);
                  $to = ($to < 1 ? 1 : $to);
                  $to = ($to > 12 ? 12 : $to);
                  for ($month = $from; $month <= $to; $month++) {
                    array_push($a_months, $month);
                  }
                }
                break;
            }
          }
        }
      } else {
        // list of months = a,b,c[...]
        if (preg_match("/^((?i)NOW|[\d,\s]+)+$/", $atts_months, $matches)) {
          //self::write_log("LIST OF MONTHS");
          //self::write_log($matches);
          if (count($matches) == 2) {
            $b_months_syntax_is_correct = true;
            $months_list_string = preg_replace('/\s+/', '', $matches[0]);
            $a_months_list = explode(',', $months_list_string);
            foreach ($a_months_list as $month_raw) {
              if (self::_set_month($month_raw, $month)) {
                array_push($a_months, $month);
              }
            }
          }
        } else {
          // now[+-]ical
          if (preg_match("/^\s*((?i)NOW([+-])(?i)ICAL)\s*$/", $atts_months, $matches)) {
            $b_months_syntax_is_correct = true;
            /*
            self::write_log(sprintf("NOW[+-]ICAL: ICAL-YEARS & ICAL-MONTHS: USE-ICAL-YEARS=%s", self::_booltostr($b_use_ical_years)));
            self::write_log($a_ical_years);
            self::write_log(sprintf("NOW[+-]ICAL: YEARS & MONTHS:"));
            self::write_log($a_years);
            self::write_log($matches);
            */
            $operator = $matches[2];
            $year_now = intval(date('Y'));
            $month_now = intval(date('m'));
            $b_is_ok = false;
            // Correct months in current year:
            foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
              if ($year == $year_now) {
                if (!array_key_exists($year, $a_ical_year_months)) {
                  $a_ical_year_months[$year] = array();
                }
                if ($operator == '+') {
                  $max_ical_month = ((in_array($year + 1, ($b_use_ical_years ? $a_ical_years : $a_years))) ? 12 : max($a_ical_year_months[$year]));
                  if ($max_ical_month >= $month_now) {
                    $new_month_list = array();
                    for ($m = $month_now; $m <= $max_ical_month; $m++) {
                      array_push($new_month_list, $m);
                    }
                    $a_ical_year_months[$year] = $new_month_list;
                    $b_use_ical_months = true;
                  }
                } else {
                  $min_ical_month = ((array_key_exists($year - 1, ($b_use_ical_years ? $a_ical_years : $a_years))) ? 1 : min($a_ical_year_months[$year]));
                  if ($min_ical_month <= $month_now) {
                    $new_month_list = array();
                    for ($m = $min_ical_month; $m <= $month_now; $m++) {
                      array_push($new_month_list, $m);
                    }
                    $a_ical_year_months[$year] = $new_month_list;
                    $b_use_ical_months = true;
                  }
                }
              }
            }
            if ($b_use_ical_years) {
              // Correct years, if we are in ical mode:
              $a_keys_to_remove = array();
              if ($operator == '+') {
                // Remove all years before the current year:
                foreach ($a_ical_years as $key => $year) {
                  if ($year < $year_now) {
                    array_push($a_keys_to_remove, $key);
                  }
                }
              } else {
                // Remove all years before the current year:
                foreach ($a_ical_years as $key => $year) {
                  if ($year > $year_now) {
                    array_push($a_keys_to_remove, $key);
                  }
                }
              }
              while (($key = array_pop($a_keys_to_remove)) != null) {
                unset($a_ical_years[$key]);
              }
            }
          }
        }
      }
      if (!$b_months_syntax_is_correct) {
        $msg = esc_html(sprintf("The syntax of YEAR is incorrect. YEAR=\"%s\"", $atts_year));
        return self::_error($msg);
      }
    }
    //
    //----------
    // Add ical months for orphaned years:
    if ($b_use_ical_years) {
      foreach ($a_ical_years as $year) {
        if (!array_key_exists($year, $a_ical_year_months)) {
          $a_ical_year_months[$year] = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);
        }
      }
    }
    //
    // Check for empty datasets:
    if ($b_use_ical_years) {
      if (empty($a_ical_years)) {
        return self::_error(sprintf("No ICAL data found for YEAR definition. YEAR=\"%s\"", self::_getav($atts, 'year')));
      }
    } else {
      if (empty($a_years)) {
        return self::_error(sprintf("No years found for YEAR definition. YEAR=\"%s\"", self::_getav($atts, 'year')));
      }
    }
    if ($b_use_ical_months) {
      if (empty($a_ical_year_months)) {
        return self::_error(sprintf("No ICAL data found for MONTHS definition. MONTHS=\"%s\"", self::_getav($atts, 'months')));
      } else {
        foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
          if ((!array_key_exists($year, $a_ical_year_months)) or empty($a_ical_year_months[$year])) {
            return self::_error(sprintf("No ICAL data found for this year. YEAR=\"%s\"", self::_getav($atts, 'year')));
          }
        }
      }
    } else {
      if (empty($a_months)) {
        return self::_error(sprintf("No months found for MONTHS definition. MONTHS=\"%s\"", self::_getav($atts, 'months')));
      }
    }
    //
    //----------
    // Make continous months, if $b_use_ical_months == true
    if ($b_use_ical_months) {
      $a_disp_years = ($b_use_ical_years ? $a_ical_years : $a_years);
      foreach ($a_disp_years as $year) {
        $from_month = min($a_ical_year_months[$year]);
        $to_month = max($a_ical_year_months[$year]);
        $previous_year = $year - 1;
        if (in_array($previous_year, $a_disp_years)) {
          $from_month = 1;
        }
        $next_year = $year + 1;
        if (in_array($next_year, $a_disp_years)) {
          $to_month = 12;
        }
        if (($from_month != min($a_ical_year_months[$year])) or ($to_month != max($a_ical_year_months[$year]))) {
          $a_new_months = array();
          for ($i = $from_month; $i <= $to_month; $i++) {
            array_push($a_new_months, $i);
          }
          $a_ical_year_months[$year] = $a_new_months;
        }
      }
    }
    //
    //----------
    //
    self::write_log(sprintf("B_USE_ICAL_YEARS=%s B_USE_ICAL_MONTHS=%s", self::_booltostr($b_use_ical_years), self::_booltostr($b_use_ical_months)));
    /*
    self::write_log(sprintf("ICAL-YEARS & ICAL-MONTHS:"));
    self::write_log($a_ical_years);
    self::write_log($a_ical_year_months);
    self::write_log(sprintf("YEARS & MONTHS:"));
    self::write_log($a_years);
    self::write_log($a_months);
    */
    //
    //----------
    // Get first character for each day in a week.
    $a_wdays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
    $a_months_names = ["MONTH-January", "MONTH-February", "MONTH-March", "MONTH-April", "MONTH-May", "MONTH-June", "MONTH-July", "MONTH-August", "MONTH-September", "MONTH-October", "MONTH-November", "MONTH-December"];
    $a_months_abr = ["Jan", "Feb", "Mar", "Apr", "May", "June", "July", "Aug", "Sept", "Oct", "Nov", "Dez"];
    $a_wdays_first_chracter = array();
    foreach ($a_wdays as $wday) {
      array_push($a_wdays_first_chracter, mb_substr(__($wday, 'icalyearbox'), 0, 1));
    }
    //self::write_log($a_wdays_first_chracter);
    //
    //----------
    // Render calender.
    $doc = ($display == 'year'
      ? self::_render_as_years($align, $type, $description, $b_use_ical_years, $b_use_ical_months, $a_years, $a_ical_years, $a_months, $a_ical_year_months, $ical_spans, $a_months_names, $a_months_abr, $a_wdays_first_chracter)
      : self::_render_as_months($align, $type, $description, $b_use_ical_years, $b_use_ical_months, $a_years, $a_ical_years, $a_months, $a_ical_year_months, $ical_spans, $a_months_names, $a_months_abr, $a_wdays_first_chracter)
    );
    return $doc;
  } // parse
} // class Icalyearbox_Parser
