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

//include 'class-icaleasyreader.php';
include 'class-ics-parser-event.php';
include 'class-ics-parser-ical.php';
//include 'Idearia-Logger.php';


/**
 * Parser plugin class file.
 *
 * @package WordPress Plugin Recursive Shortcode/Includes
 */

class Icalyearbox_Datespan {
  private $_from;
  private $_to;
  private $_description;

  public function __construct($from, $to, $description = "") {
    if (is_a($from, 'DateTime') and is_a($to, 'DateTime')) {
      $this->_description = $description;
      if ($from <= $to) {
        $this->_from = $from;
        $this->_to = $to;
      } else {
        $this->_from = $to;
        $this->_to = $from;
      }
    } else {
      throw new ErrorException("Parameters must be type DateTime!", 0, E_ERROR, __FILE__, __LINE__);
    }
  } // __construct

  public function connects_to($other_span) {
    if (is_a($other_span, 'Icalyearbox_Datespan')) {
      $day_before_this_span = DateTime::createFromFormat('Ymd', $this->_from->format('Ymd'))->sub(new DateInterval('P1D'));
      $day_after_this_span = DateTime::createFromFormat('Ymd', $this->_to->format('Ymd'))->add(new DateInterval('P1D'));
      Icalyearbox_Parser::write_log(sprintf("[CONNECTED? OTHER(%s-%s)-THIS(%s-%s) before/after=(%s-%s)]",
        $other_span->_from->format('Ymd'), $other_span->_to->format('Ymd'),
        $this->_from->format('Ymd'), $this->_to->format('Ymd'),
        $day_before_this_span->format('Ymd'), $day_after_this_span->format('Ymd')));
      if ((($other_span->_from >= $day_before_this_span) and ($other_span->_from <= $day_after_this_span))
        or (($other_span->_to >= $day_before_this_span) and ($other_span->_to <= $day_after_this_span))) {
        Icalyearbox_Parser::write_log(sprintf("-- CONNECTED"));
        return true;
      }
      return false;
    } else {
      throw new ErrorException("Parameter must be type Icalyearbox_Datespan!", 0, E_ERROR, __FILE__, __LINE__);
    }
  } // connects_to

  public function add($other_span) {
    if (is_a($other_span, 'Icalyearbox_Datespan')) {
      // Add this span to this, if they are connecting.
      if ($this->connects_to($other_span)) {
        if ($other_span->_to > $this->_to) {
          $this->_to = $other_span->_to;
        }
        if ($other_span->_from < $this->_from) {
          $this->_from = $other_span->_from;
        }
        return true;
      }
    } else {
      throw new ErrorException("Parameter must be type Icalyearbox_Datespan!", 0, E_ERROR, __FILE__, __LINE__);
    }
    return false;
  } // add

  public function position($dt) {
    if (is_a($dt, 'DateTime')) {
      if ($dt == $this->_to) {
        return Icalyearbox_Datespans::IS_END;
      }
      if ($dt == $this->_from) {
        return Icalyearbox_Datespans::IS_START;
      }
      if (($dt > $this->_from) and ($dt < $this->_to)) {
        return Icalyearbox_Datespans::IS_OCCUPIED;
      }
    } else {
      throw new ErrorException("Parameter must be type DateTime!", 0, E_ERROR, __FILE__, __LINE__);
    }
    return Icalyearbox_Datespans::IS_OUTSIDE;
  } // add

  public function description() {
    return $this->_description;
  } // description

  public function inspect() {
    $value = sprintf("Icalyearbox_Datespan(_from = '%s', _to = '%s')", $this->_from->format('Ymd'), $this->_to->format('Ymd'));
    return $value;
  }
} // class Icalyearbox_Datespan

class Icalyearbox_Datespans {
  public const IS_OUTSIDE = -1;
  public const IS_START = 0;
  public const IS_OCCUPIED = 1;
  public const IS_FREE = 2;
  public const IS_END = 3;

  private $_raw_spans;
  private $_spans;

  public function __construct() {
    $this->_raw_spans = array();
    $this->_spans = array();
  } // __construct

  public function add($span) {
    if (is_a($span, 'Icalyearbox_Datespan')) {
      array_push($this->_raw_spans, $span);
      $b_is_found = false;
      foreach ($this->_spans as $saved_span) {
        if ($saved_span->add($span)) {
          $b_is_found = true;
          break;
        }
      }
      if (!$b_is_found) {
        array_push($this->_spans, $span);
      }
    } else {
      throw new ErrorException("Parameter must be type Icalyearbox_Datespan!", 0, E_ERROR, __FILE__, __LINE__);
    }
  } // function add

  public function position($dt) {
    if (is_a($dt, 'DateTime')) {
      foreach ($this->_spans as $span) {
        $pos = $span->position($dt);
        switch ($pos) {
          case self::IS_START:
          case self::IS_END:
          case self::IS_OCCUPIED:
            return $pos;
        }
      }
      return self::IS_FREE;
    } else {
      throw new ErrorException("Parameter must be type DateTime!", 0, E_ERROR, __FILE__, __LINE__);
    }
  }

  public function description($dt) {
    $rv = array();
    if (is_a($dt, 'DateTime')) {
      foreach ($this->_raw_spans as $span) {
        $pos = $span->position($dt);
        switch ($pos) {
          case self::IS_START:
          case self::IS_END:
          case self::IS_OCCUPIED:
            if (!empty($span->description())) {
              array_push($rv, $span->description());
            }
        }
      }
    } else {
      throw new ErrorException("Parameter must be type DateTime!", 0, E_ERROR, __FILE__, __LINE__);
    }
    return array_unique($rv);
  }

  public function inspect() {
    $value = 'Icalyearbox_Datespans(_spans = [';
    $nr_spans = count($this->_spans);
    $nr = 0;
    foreach ($this->_spans as $span) {
      $value .= $span->inspect();
      $nr++;
      if ($nr < $nr_spans) {
        $value .= ', ';
      }
    }
    $value .= '])';
    return $value;
  } // inspect
} // class Icalyearbox_Datespans

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
  private static $_enable_debugging = true; //TODO //phpcs:ignore
  private static $_log_initialized = false;
  private static $_log_class = null;

  private static $_directories_initialized = false;
  public static $token = 'icalyearbox';
  private static $_my_plugin_directory = null;
  private static $_my_log_directory = null;
  private static $_my_cache_directory = null;

  private static $_cache_reload_timeout = 86400; // [s] -- 86400 = one day

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
   * Render an error message as output (HTML).
   *
   * @access  private
   * @return  String HTML output.
   * @since   1.0.0
   */
  private static function _error($msg, $sc = NULL, $sc_pos = NULL, $content = NULL) {
    if ($sc != NULL and $sc_pos != NULL and $content != NULL) {
      $cn = substr($content, 0, $sc_pos) . '<span style="background-color:#AA000F; color:white;">' . substr($content, $sc_pos, strlen($sc)) . '</span>' . substr($content, $sc_pos + strlen($sc));
    } else {
      $cn = NULL;
    }
    return
      '<div style="unicode-bidi: embed; font-family: monospace; font-size:12px; color:black; background-color:#E0E0E0;">' .
      '[icalyearbox]:ERROR -- ' . $msg . ($sc_pos === NULL ? '' : ' POSITION=' . $sc_pos) . ($sc === NULL ? '' : ' SHORTCODE="' . $sc . '"') . "\n" .
      ($cn === NULL ? '' : 'CONTENT="' . $cn . '"') .
      '</div>';
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

  /**
   * Get name and state (OPEN, CLOSE) of a shortcode from shortcode content.
   *
   * @access  private
   * @return  Array With elements 0 => (true, false) - Is OPEN tag. 1 => String Tag name.
   * @since   1.0.0
   */
  private static function _get_shortcode_tag($shortcode_content) {
    if (preg_match('|^\s*/\s*(\S+)|', $shortcode_content, $matches)) {
      return array(false, $matches[1]);
    }
    if (preg_match('/^\s*(\S+)/', $shortcode_content, $matches)) {
      return array(true, $matches[1]);
    }
    return NULL;
  } // _get_shortcode_tag

  /**
   * Render calendar as years.
   *
   * @access  public
   * @return  String
   * @since   1.0.0
   */
  private static function _render_as_years($align, $type, $description, $b_use_ical_years, $b_use_ical_months, $a_years, $a_ical_years, $a_months, $a_ical_year_months, $ical_spans, $a_months_names, $a_months_abr, $a_wdays_first_chracter) {
    $day_now = DateTime::createFromFormat('Ymd', sprintf("%04d%02d%02d", intval(date('Y')), intval(date('m')), intval(date('d'))));
    $doc = "";
    // Calc start week day and width for all years:
    $calendar_starts_with_wday = 8;
    $calendar_width = 0;
    foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
      // Determine witch month has the „earliest“ weekday.
      foreach (($b_use_ical_months ? $a_ical_year_months[$year] : $a_months) as $month) {
        $month_first_day = DateTime::createFromFormat('Ymd', sprintf("%04d%02d01", $year, $month));
        $wday = $month_first_day->format('w');
        $wday = ($wday == 0 ? 7 : $wday);
        $calendar_starts_with_wday = ($wday < $calendar_starts_with_wday ? $wday : $calendar_starts_with_wday);
      }
      // Determine the „width“ of the calendar.
      foreach (($b_use_ical_months ? $a_ical_year_months[$year] : $a_months) as $month) {
        $month_first_day = DateTime::createFromFormat('Ymd', sprintf("%04d%02d01", $year, $month));
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
      self::write_log(sprintf("RENDER YEAR=%d", $year));
      self::write_log(sprintf("%04d: STARTWDAY=%d WIDTH=%d DIR='%s'", $year, $calendar_starts_with_wday, $calendar_width, self::$_my_plugin_directory));
      //
      $hint_top_space = ($description ? ' style="padding-top:15px;"' : '');
      $doc .= sprintf('<div class="icalyearbox-reset-this"><div class="icalyearbox icalyearbox-tag"%s year="%d"><table class="icalyearbox-tag yr-table%s" width="%dpx"><tbody>',
        $hint_top_space, $year, ($align == '' ? '' : (' ' . $align)), $approximated_table_width_in_pixels) . PHP_EOL;
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
        $month_first_day = DateTime::createFromFormat('Ymd', sprintf("%04d%02d01", $year, $month));
        $month_starts_with_wday = $month_first_day->format('w');
        $month_starts_with_wday = ($month_starts_with_wday == 0 ? 7 : $month_starts_with_wday);
        $nr_mdays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        // Month name:
        $doc .= sprintf('<tr class="icalyearbox-tag"><th><div class="icalyearbox-tag cellc frow%s"><span class="mo-span">%s</span></div></th>',
          (($nr_month_counter % 2) == 1 ? ' alt' : ''),
          __($a_months_abr[$month - 1], 'icalyearbox')) . PHP_EOL;
        self::write_log(sprintf("%04d%02d: CALSTARTWDAY=%d MONTHSTARTWDAY=%d", $year, $month, $calendar_starts_with_wday, $month_starts_with_wday));
        for ($i = 0; $i < $calendar_width; $i++) {
          $wday = (($calendar_starts_with_wday + $i) % 7);
          $wday = ($wday == 0 ? 7 : $wday);
          $month_day = $i - ($month_starts_with_wday - $calendar_starts_with_wday) + 1;
          if (($month_day >= 1) and ($month_day <= $nr_mdays)) {
            $dt_this_date = DateTime::createFromFormat('Ymd', sprintf("%04d%02d%02d", $year, $month, $month_day));
            $pos = $ical_spans->position($dt_this_date);
            $td_backgroud_image_style = '';
            if ($type == "event") {
              switch ($pos) {
                case Icalyearbox_Datespans::IS_START:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
                  break;
                case Icalyearbox_Datespans::IS_END:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
                  break;
                case Icalyearbox_Datespans::IS_OCCUPIED:
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
                case Icalyearbox_Datespans::IS_OCCUPIED:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
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
            if ($type == "event") {
              $a_desc = $ical_spans->description($dt_this_date);
              $desc = (count($a_desc) ? ': ' . implode(', ', $a_desc) : '');
            }
            $desc = '';
            if ($description) {
              $a_desc = $ical_spans->description($dt_this_date);
              $desc = (count($a_desc) ? implode(', ', $a_desc) : '');
            }
            if(empty($desc)) {
              $mo_day_span = sprintf('<span class="icalyearbox-tag">%02d</span>', $month_day);
            } else {
              //SEE:https://github.com/chinchang/hint.css
              $mo_day_span = sprintf('<span class="hint--top icalyearbox-tag icalyearbox-hint" aria-label="%s">%02d</span>', esc_html($desc), $month_day);
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
    $day_now = DateTime::createFromFormat('Ymd', sprintf("%04d%02d%02d", intval(date('Y')), intval(date('m')), intval(date('d'))));
    $doc = "";
    // Calc start week day and width for all years:
    $calendar_height = 4;
    foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
      // Determine the „height“ of the calendar.
      foreach (($b_use_ical_months ? $a_ical_year_months[$year] : $a_months) as $month) {
        $month_first_day = DateTime::createFromFormat('Ymd', sprintf("%04d%02d01", $year, $month));
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
        $month_first_day = DateTime::createFromFormat('Ymd', sprintf("%04d%02d01", $year, $month));
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
                $dt_this_date = DateTime::createFromFormat('Ymd', sprintf("%04d%02d%02d", $year, $month, $month_day));
                $pos = $ical_spans->position($dt_this_date);
                $td_backgroud_image_style = '';
                if ($type == "event") {
                  switch ($pos) {
                    case Icalyearbox_Datespans::IS_START:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
                      break;
                    case Icalyearbox_Datespans::IS_END:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
                      break;
                    case Icalyearbox_Datespans::IS_OCCUPIED:
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
                    case Icalyearbox_Datespans::IS_OCCUPIED:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', self::$_my_plugin_directory . '/index.php'));
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
                if ($description) {
                  $a_desc = $ical_spans->description($dt_this_date);
                  $desc = (count($a_desc) ? implode(', ', $a_desc) : '');
                }
                if(empty($desc)) {
                  $mo_day_span = sprintf('<span class="icalyearbox-tag">%02d</span>', $month_day);
                } else {
                  //SEE:https://github.com/chinchang/hint.css
                  $mo_day_span = sprintf('<span class="hint--top icalyearbox-tag icalyearbox-hint" aria-label="%s">%02d</span>', esc_html($desc), $month_day);
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
    $pattern_open = '/(' . $atts['open'] . ')/';
    $pattern_close = '/(' . $atts['close'] . ')/';
    self::write_log("CONTENT='" . $content . "'");
    self::write_log("OPEN='" . $pattern_open . "'");
    self::write_log("CLOSE='" . $pattern_close . "'");
    //
    //----------
    // Check syntax for tags.
    //
    //
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
        self::write_log(sprintf("CACHE-MATCHES=%d", count($matches)));
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
        self::write_log(sprintf("ICAL-URL-MATCHES=%d", count($matches)));
        self::write_log($matches);
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
          $response = wp_remote_get($ical_url, ['timeout' => 10]);
          if (is_array($response) && !is_wp_error($response)) {
            // Write to cahce:
            $my_cache_file = fopen($cache_fn, "w");
            fwrite($my_cache_file, wp_remote_retrieve_body($response));
            fclose($my_cache_file);
          } else {
            // TODO make error message
          }
        }
        // Analyse ical data.
        if (file_exists($cache_fn)) {
          //$ical = new icalyearbox_iCalEasyReader();
          $ical = new Ical\ICal($cache_fn, array('defaultTimeZone' => 'Europe/Berlin'));
          $ical_lines = $ical->events();
          self::write_log(sprintf("ICAL-SIZE=%d", $ical->eventCount));
          if ($ical->eventCount > 0) {
            foreach ($ical_lines as $ical_event_key => $ical_event) {
              array_push($a_ical_events, $ical_event);
              self::write_log(sprintf("[%s] ICAL-DTSTART=%s DTEND=%s", $ical_event_key, $ical_event->dtstart, $ical_event->dtend));
            }
          }
        }
      } catch (Exception $e) {
        self::write_log(sprintf("ERROR='%s'", strval($e)));
      }
    }
    //
    //----------
    // Get years and months per year from ical events.
    foreach ($a_ical_events as $ical_event) {
      $dt_start = $ical_event->dtstart;
      $dt_end = $ical_event->dtend;
      $dt_description = (!empty($ical_event->description) ? $ical_event->description : (!empty($ical_event->summary) ? $ical_event->summary : ''));
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
      // Collect ical spans:
      self::write_log(sprintf("DTSTART='%s' DTEND='%s'", strval($dt_start), strval($dt_end)));
      $from = DateTime::createFromFormat('Ymd', substr($dt_start, 0, 8));
      $to = DateTime::createFromFormat('Ymd', substr($dt_end, 0, 8));
      $ical_spans->add(new Icalyearbox_Datespan($from, $to, $dt_description));
    }
    //----------
    // Make contious list of months in a year:
    foreach ($a_ical_year_months as $year => $a_months) {
      $min = min($a_months);
      $max = max($a_months);
      $a_cont_months = array();
      for ($m = $min; $m <= $max; $m++) {
        array_push($a_cont_months, $m);
      }
      $a_ical_year_months[$year] = $a_cont_months;
    }
    //
    //----------
    // Collect all years.
    $a_years = array();
    //self::write_log($atts);
    if (array_key_exists('year', $atts)) {
      $atts_year = trim($atts['year']);
      self::write_log(sprintf("PARAM YEAR='%s'", $atts_year));
      if (preg_match("/^((?i)NOW|(?i)ICAL|[1-9][0-9]*)(\+[1-9][0-9]*){0,1}(-[1-9][0-9]*){0,1}$/", $atts_year, $matches)
        or preg_match("/^((?i)NOW|(?i)ICAL|[1-9][0-9]*)(\-[1-9][0-9]*){0,1}(\+[1-9][0-9]*){0,1}$/", $atts_year, $matches)) {
        self::write_log($matches);
        if (strtolower($matches[1]) == 'ical') {
          $b_use_ical_years = true;
          switch (count($matches)) {
            case 2:
              // Detected the word "ical":
              break;
            case 3:
              // Detected one plus or minus.
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
              // Detected two plus or minus values.
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
        } else {
          self::_set_year($matches[1], $base_year);
          switch (count($matches)) {
            case 2:
              // Detected the word "now":
              array_push($a_years, $base_year);
              break;
            case 3:
              // Detected one plus or minus.
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
        // year interval = from,to
        if (preg_match("/^((?i)NOW|\d+)\s*,\s*((?i)NOW|\d+)$/", $atts_year, $matches)) {
          // year range = two values (inclusive):
          if (count($matches) == 3) {
            if (self::_set_year($matches[1], $from) and self::_set_year($matches[2], $to)) {
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
    }
    //
    //----------
    // Collect all months.
    $a_months = array();
    //self::write_log($atts);
    if (array_key_exists('months', $atts)) {
      $atts_months = trim($atts['months']);
      self::write_log(sprintf("PARAM MONTHS='%s'", $atts_months));
      if (preg_match("/^((?i)NOW|(?i)ALL|(?i)ICAL|[1-9][0-9]*)(\+[1-9][0-9]*){0,1}(-[1-9][0-9]*){0,1}$/", $atts_months, $matches)
        or preg_match("/^((?i)NOW|(?i)ALL|(?i)ICAL|[1-9][0-9]*)(\-[1-9][0-9]*){0,1}(\+[1-9][0-9]*){0,1}$/", $atts_months, $matches)) {
        if (strtolower($matches[1]) == 'ical') {
          $b_use_ical_months = true;
        } else {
          self::write_log($matches);
          if (self::_set_month($matches[1], $base_month)) {
            switch (count($matches)) {
              case 2:
                // Detected the word "all" or "now":
                if ($base_month == -1) {
                  array_push($a_months, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);
                } else {
                  array_push($a_months, $base_month);
                }
                break;
              case 3:
                // Detected one plus or minus.
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
          } else {
            // TODO Errounous month!
          }
        }
      } else {
        // list of months = a,b,c[...]
        if (preg_match("/^((?i)NOW|[\d,\s]+)+$/", $atts_months, $matches)) {
          self::write_log("LIST OF MONTHS");
          self::write_log($matches);
          if (count($matches) == 2) {
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
          if (preg_match("/^((?i)NOW([+-])(?i)ICAL)$/", $atts_months, $matches)) {
            self::write_log(sprintf("NOW[+-]ICAL: ICAL-YEARS & ICAL-MONTHS: USE-ICAL-YEARS=%s", self::_booltostr($b_use_ical_years)));
            self::write_log($a_ical_years);
            self::write_log(sprintf("NOW[+-]ICAL: YEARS & MONTHS:"));
            self::write_log($a_years);
            self::write_log($matches);
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
          } else {
            self::write_log("No MONTH matches!"); // TODO
          }
        }
      }
    }
    self::write_log(sprintf("ICAL-YEARS & ICAL-MONTHS:"));
    self::write_log($a_ical_years);
    self::write_log($a_ical_year_months);
    //
    //----------
    // Add ical months for orhant years:
    if ($b_use_ical_years) {
      foreach ($a_ical_years as $year) {
        if (!array_key_exists($year, $a_ical_year_months)) {
          $a_ical_year_months[$year] = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);
        }
      }
    }
    //
    // Get alignment.
    $align = (in_array($atts['align'], ['left', 'center', 'right']) ? $atts['align'] : 'center');
    //
    // Get calendar type.
    $type = (in_array($atts['type'], ['booking', 'event']) ? $atts['type'] : 'event');
    //
    // Get calendar display type.
    $display = (in_array($atts['display'], ['month', 'year']) ? $atts['display'] : 'year');
    //
    // Get description flag.
    $description = (in_array($atts['description'], [false, true]) ? $atts['description'] : false);
    //
    self::write_log(sprintf("ICAL-YEARS & ICAL-MONTHS:"));
    self::write_log($a_ical_years);
    self::write_log($a_ical_year_months);
    self::write_log(sprintf("YEARS & MONTHS:"));
    self::write_log($a_years);
    self::write_log($a_months);
    //
    //----------
    //
    self::write_log(sprintf("B_USE_ICAL_YEARS=%s B_USE_ICAL_MONTHS=%s", self::_booltostr($b_use_ical_years), self::_booltostr($b_use_ical_months)));
    self::write_log($ical_spans->inspect());
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
    self::write_log($a_wdays_first_chracter);
    //
    //----------
    // Render calender.
    //
    $doc = ($display == 'year'
      ? self::_render_as_years($align, $type, $description, $b_use_ical_years, $b_use_ical_months, $a_years, $a_ical_years, $a_months, $a_ical_year_months, $ical_spans, $a_months_names, $a_months_abr, $a_wdays_first_chracter)
      : self::_render_as_months($align, $type, $description, $b_use_ical_years, $b_use_ical_months, $a_years, $a_ical_years, $a_months, $a_ical_year_months, $ical_spans, $a_months_names, $a_months_abr, $a_wdays_first_chracter)
    );
    return $doc;
    //
    //----------
    $offset = 0;
    $match = NULL;
    $a_pos = array();
    $pos = NULL;
    while (preg_match($pattern_open, $content, $matches, PREG_OFFSET_CAPTURE, $offset)) {
      $match = $matches[0][0];
      $pos = $matches[0][1];
      self::write_log('POS=' . $pos);
      // Find the closing brace.
      $offset = $pos + strlen($match);
      if (preg_match($pattern_close, $content, $matches, PREG_OFFSET_CAPTURE, $offset)) {
        $match_close = $matches[0][0];
        $pos_close = $matches[0][1];
        $shortcode_content = substr($content, $pos + strlen($match), $pos_close - $pos - strlen($match));
        $shortcode_complete = $match . $shortcode_content . $match_close;
        $a_tag = self::_get_shortcode_tag($shortcode_content);
        if ($a_tag === NULL) {
          return self::_error("Cannot find shortcode tag inside shortcode!", $shortcode_complete, $pos, $content);
        }
        $tag_open = $a_tag[0];
        $tag_name = $a_tag[1];
        array_push($a_pos, array(
          'position' => $pos,
          'shortcode' => $shortcode_complete,
          'content' => $shortcode_content,
          'tag' => $tag_name,
          'open' => $tag_open
        ));
        self::write_log('POS_CLOSE=' . $pos_close);
        self::write_log('SHORTCODE=' . $shortcode_complete);
        self::write_log('CONTENT=' . $shortcode_content);
        self::write_log('TAG=' . $tag_name . ' OPEN=' . ($tag_open ? 'yes' : 'no'));
      } else {
        return self::_error("Cannot find closing brace for shortcode!", $match, $pos, $content);
      }
    }
    //
    //----------
    // Find the last opening shortcode.
    //
    $index = count($a_pos) - 1;
    while ($index >= 0) {
      $open = $a_pos[$index]['open'];
      if ($open) {
        break;
      }
      $index--;
    }
    $last_open_index = $index;
    //
    if ($last_open_index >= 0) {
      self::write_log('LAST-OPEN-INDEX=' . $last_open_index);
      $pos = $a_pos[$last_open_index]['position'];
      $shortcode_complete = $a_pos[$last_open_index]['shortcode'];
      $shortcode_content = $a_pos[$last_open_index]['content'];
      $tag_name = $a_pos[$last_open_index]['tag'];
      $tag_open = $a_pos[$last_open_index]['open'];
      //
      //----------
      // Check if we have a closing tag.
      // This must be the next shortcode or it's not existing.
      //
      $has_closing_tag = false;
      if ($last_open_index < (count($a_pos) - 1)) {
        if (!$a_pos[$last_open_index + 1]['open'] and ($a_pos[$last_open_index + 1]['tag'] == $tag_name)) {
          // We have a closing tag.
          $has_closing_tag = true;
        }
      }
      //
      //----------
      // Process the last found shortcode.
      //
      if (!$has_closing_tag) {
        //
        //----------
        // This tag has no closing tag.
        // 
        $content_before = ($pos == 0 ? '' : substr($content, 0, $pos));
        $content_after = substr($content, $pos + strlen($shortcode_complete));
        // Evaluate shortcode.
        $to_eval = $shortcode_complete;
        $to_eval = preg_replace($pattern_open, '[', $to_eval);
        $to_eval = preg_replace($pattern_close, ']', $to_eval);
        self::write_log('HAS-NO-CLOSING:EVAL[' . $last_open_index . '] ->|' . $content_before . '|' . $to_eval . '|' . $content_after . '|');
        if (function_exists('do_shortcode')) {
          if ($atts['deconstruct']) {
            $eval = str_repeat(" ", strlen($shortcode_complete));
          } else {
            $eval = do_shortcode($to_eval);
          }
        } else {
          $eval = '';
        }
        if ($to_eval == $eval) {
          return self::_error("Unknown shortcode!", $shortcode_complete, $pos, $content);
        }
        if ($atts['deconstruct']) {
          $eval = str_repeat(" ", strlen($shortcode_complete));
          if ($evaluate_stack !== NULL) {
            array_push($evaluate_stack, array($pos, $pos + strlen($shortcode_complete)));
          }
        }
        if ($last_open_index == 0) {
          return $content_before . $eval . $content_after;
        } else {
          return self::parse($atts, $content_before . $eval . $content_after, $evaluate_stack);
        }
      } else {
        //
        //----------
        // We have a closing tag.
        //
        $next_pos = $a_pos[$last_open_index + 1]['position'];
        $next_shortcode_complete = $a_pos[$last_open_index + 1]['shortcode'];
        $shortcode_complete = substr($content, $pos, $next_pos + strlen($next_shortcode_complete) - $pos);
        self::write_log('HAS-CLOSING: SHORTCODE="' . $shortcode_complete . '"');
        //
        $content_before = ($pos == 0 ? '' : substr($content, 0, $pos));
        $content_after = substr($content, $pos + strlen($shortcode_complete));
        // Evaluate shortcode.
        $to_eval = $shortcode_complete;
        $to_eval = preg_replace($pattern_open, '[', $to_eval);
        $to_eval = preg_replace($pattern_close, ']', $to_eval);
        self::write_log('HAS-CLOSING:EVAL[' . $last_open_index . '] ->|' . $content_before . '|' . $to_eval . '|' . $content_after . '|');
        if (function_exists('do_shortcode')) {
          if ($atts['deconstruct']) {
            $eval = str_repeat(" ", strlen($shortcode_complete));
          } else {
            $eval = do_shortcode($to_eval);
          }
        } else {
          $eval = '';
        }
        if ($to_eval == $eval) {
          return self::_error("Unknown shortcode!", $shortcode_complete, $pos, $content);
        }
        if ($atts['deconstruct']) {
          $eval = str_repeat(" ", strlen($shortcode_complete));
          if ($evaluate_stack !== NULL) {
            array_push($evaluate_stack, array($pos, $pos + strlen($shortcode_complete)));
          }
        }
        if ($last_open_index == 0) {
          return $content_before . $eval . $content_after;
        } else {
          return self::parse($atts, $content_before . $eval . $content_after, $evaluate_stack);
        }
      }
    }
    return $content;
  } // parse

} // class Icalyearbox_Parser
