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


require_once 'lib/class-ics-parser-event.php';
require_once 'lib/class-ics-parser-ical.php';
require_once 'class-yetanothericalcalendar-datespans.php';


/**
 * Parser plugin class.
 */
class YetAnotherICALCalendar_Parser {
  /**
   * SleekDB Annotations Store Variables.
   */
  private static $_db_annotations_rw_store = null;

  private static $_cache_reload_timeout = 86400; // [s] -- 86400 = one day

  private static $_session_removal_timeout = 86400; // [s] -- 86400 = one day / For annotation sessions in database.

  private static $_shortcut_src = '';
  private static $_content_src = '';

  /**
   * Render an error message as output (HTML).
   *
   * @access  private
   * @return  String HTML output.
   * @since   1.0.0
   */
  private static function _error($msg) {
    YAICALHelper::write_log($msg, true, basename(debug_backtrace()[1]['file']), debug_backtrace()[1]['function'], intval(debug_backtrace()[0]['line']));
    $src = '';
    if (!empty(self::$_shortcut_src)) {
      $src = '<br />' . self::$_shortcut_src;
    }
    return YAICALHelper::get_html_error_msg($msg . $src);
  } // _error

  private static function _clean_annotation_rw() {
    $nr_deletes = 0;
    if (is_null(self::$_db_annotations_rw_store)) {
      self::$_db_annotations_rw_store = new \SleekDB\Store("annotation_rw", YAICALHelper::get_my_database_directory(), ['timeout' => false]);
    }
    // Fetch data.
    $db_anno_rw = self::$_db_annotations_rw_store->createQueryBuilder()
      ->getQuery()
      ->fetch();
    // Collect objects to remove:
    $a_removeal_list = [];
    foreach($db_anno_rw as $row) {
      $dt_now = new DateTime();
      $dt_set = DateTime::createFromFormat('Y-m-d?H:i:sT', $row['set']); // 2023-05-05T15:37:08+02:00
      $delta = YAICALHelper::datetime_delta($dt_set, $dt_now);
      if($delta > self::$_session_removal_timeout) {
        array_push($a_removeal_list, $row['_id']);
      }
    }
    // Remove objects:
    foreach($a_removeal_list as $db_id) {
      self::$_db_annotations_rw_store->deleteBy([['_id', '=', $db_id]]);
      $nr_deletes++;
    }
    return $nr_deletes;
} // _clean_annotation_rw

  public static function set_annotation_rw($pid, $id, $uuid, $is_read, $is_write) {
    if ((!empty($pid)) and (!empty($id)) and (!empty($uuid))) {
      if (is_null(self::$_db_annotations_rw_store)) {
        self::$_db_annotations_rw_store = new \SleekDB\Store("annotation_rw", YAICALHelper::get_my_database_directory(), ['timeout' => false]);
      }
      // Remove before save.
      $dt_now = new DateTime();
      $dt_now_s = $dt_now->format('c');
      self::$_db_annotations_rw_store->deleteBy([['pid', '=', $pid], ['id', '=', $id], ['uuid', '=', $uuid]]);
      self::$_db_annotations_rw_store->insert(['pid' => $pid, 'id' => $id, 'uuid' => $uuid, 'read' => ($is_read ? 1 : 0), 'write' => ($is_write ? 1 : 0), 'set' => $dt_now_s]);
      self::_clean_annotation_rw();
    }
  } // set_annotation_rw

  public static function get_annotation_rw($pid, $id, $uuid) {
    if ((!empty($pid)) and (!empty($id)) and (!empty($uuid))) {
      if (is_null(self::$_db_annotations_rw_store)) {
        self::$_db_annotations_rw_store = new \SleekDB\Store("annotation_rw", YAICALHelper::get_my_database_directory(), ['timeout' => false]);
      }
      // Fetch data.
      $db_anno_rw = self::$_db_annotations_rw_store->createQueryBuilder()
        ->where([['pid', '=', $pid], 'AND', ['id', '=', $id], 'AND', ['uuid', '=', $uuid]])
        ->limit(1)
        ->getQuery()
        ->fetch();
      YAICALHelper::write_log(sprintf("#FETCH=%d", count($db_anno_rw)));
      if (count($db_anno_rw) == 1) {
        foreach ($db_anno_rw[0] as $key => $value) {
          YAICALHelper::write_log(sprintf("DB_ANNO_RW['%s'] = '%s'", $key, $value));
        }
        self::_clean_annotation_rw();
        return ['read' => ($db_anno_rw[0]['read'] == '1'), 'write' => ($db_anno_rw[0]['write'] == '1')];
      }
    }
    return ['read' => false, 'write' => false];
  } // get_annotation_rw

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
        if ($dt_start == $dt_end) {
          $b_exclude_dtend = false;
        }
        if (empty($dt_start) or empty($dt_end)) {
          YAICALHelper::write_log(sprintf("WRONG VEVENT! DTSTART='%s' DTEND='%s'", strval($dt_start), strval($dt_end)));
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
          $from = YAICALHelper::strtodatetime($dt_start);
          $to = YAICALHelper::strtodatetime($dt_end);
          if ($b_exclude_dtend) {
            $to = $to->modify('-1 day');
          }
          $span = new YetAnotherICALCalendar_Datespan($from, $to, $dt_description);
          $ical_spans->add($span);
          //YAICALHelper::write_log(sprintf("[%s] FROM=%s TO=%s SPAN='%s'", $ical_event_key, $from->format('c'), $to->format('c'), $span->inspect()));
        }
      } else {
        YAICALHelper::write_log(sprintf("WRONG VEVENT! DTSTART='%s' EMPTY OR WRONG FORMAT!", strval($dt_start)));
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
  private static function _render_as_years($pid, $id, $a_acc, $align, $type, $description, $b_use_ical_years, $b_use_ical_months, $a_years, $a_ical_years, $a_months, $a_ical_year_months, $ical_spans, $a_months_names, $a_months_abr, $a_wdays_first_chracter) {
    $day_now = YAICALHelper::strtodatetime(sprintf("%04d%02d%02d", intval(date('Y')), intval(date('m')), intval(date('d'))));
    $doc = "";
    // Calc start week day and width for all years:
    $calendar_starts_with_wday = 8;
    $calendar_width = 0;
    foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
      // Determine witch month has the „earliest“ weekday.
      foreach (($b_use_ical_months ? $a_ical_year_months[$year] : $a_months) as $month) {
        $month_first_day = YAICALHelper::strtodatetime(sprintf("%04d%02d01", $year, $month));
        $wday = $month_first_day->format('w');
        $wday = ($wday == 0 ? 7 : $wday);
        $calendar_starts_with_wday = ($wday < $calendar_starts_with_wday ? $wday : $calendar_starts_with_wday);
      }
      // Determine the „width“ of the calendar.
      foreach (($b_use_ical_months ? $a_ical_year_months[$year] : $a_months) as $month) {
        $month_first_day = YAICALHelper::strtodatetime(sprintf("%04d%02d01", $year, $month));
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
      //YAICALHelper::write_log(sprintf("RENDER YEAR=%d TYPE='%s'", $year, $type));
      //YAICALHelper::write_log(sprintf("%04d: STARTWDAY=%d WIDTH=%d DIR='%s'", $year, $calendar_starts_with_wday, $calendar_width, YAICALHelper::get_my_plugin_directory()));
      //
      $doc .= sprintf('<div class="yetanothericalcalendar-reset-this"><div class="yetanothericalcalendar" id="%s" year="%d"><div id="%s-cal-msg" style=display:none;"></div><table class="yr-table%s" width="%dpx"><tbody>',
        $id, $id, $year, ($align == '' ? '' : (' ' . $align)), $approximated_table_width_in_pixels) . PHP_EOL;
      // Table header:
      $doc .= sprintf('<tr class="yr-header"><th><div class="cellc plain frow"><span class="yr-span">%04d</span></div></th>', $year) . PHP_EOL;
      for ($i = 0; $i < $calendar_width; $i++) {
        $offset = ($i % 7);
        $offset = ($offset == 0 ? 7 : $offset);
        $wday_index = ($offset + $calendar_starts_with_wday - 1) % 7;
        $wday_class = '';
        if ($wday_index >= 5) {
          $wday_class = ' wkend';
        }
        $doc .= sprintf('<th><div class="cellc square wday%s">%s</div></th>', $wday_class, $a_wdays_first_chracter[$wday_index]) . PHP_EOL;
      }
      $doc .= '</tr>' . PHP_EOL;
      // Table body (months):
      $nr_month_counter = 0;
      foreach (($b_use_ical_months ? $a_ical_year_months[$year] : $a_months) as $month) {
        $month_first_day = YAICALHelper::strtodatetime(sprintf("%04d%02d01", $year, $month));
        $month_starts_with_wday = $month_first_day->format('w');
        $month_starts_with_wday = ($month_starts_with_wday == 0 ? 7 : $month_starts_with_wday);
        $nr_mdays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        // Month name:
        $doc .= sprintf('<tr><th><div class="cellc frow%s"><span class="mo-span">%s</span></div></th>',
          (($nr_month_counter % 2) == 1 ? ' alt' : ''),
          __($a_months_abr[$month - 1], 'yetanothericalcalendar')) . PHP_EOL;
        //YAICALHelper::write_log(sprintf("%04d%02d: CALSTARTWDAY=%d MONTHSTARTWDAY=%d", $year, $month, $calendar_starts_with_wday, $month_starts_with_wday));
        for ($i = 0; $i < $calendar_width; $i++) {
          $wday = (($calendar_starts_with_wday + $i) % 7);
          $wday = ($wday == 0 ? 7 : $wday);
          $month_day = $i - ($month_starts_with_wday - $calendar_starts_with_wday) + 1;
          if (($month_day >= 1) and ($month_day <= $nr_mdays)) {
            $dt_this_date = YAICALHelper::strtodatetime(sprintf("%04d%02d%02d", $year, $month, $month_day));
            $pos = $ical_spans->position($dt_this_date, $type);
            //YAICALHelper::write_log(sprintf("%04d%02d%02d: DATE='%s' POS=%d", $year, $month, $month_day, $dt_this_date->format('c'), $pos));
            $td_backgroud_image_style = '';
            if ($type == "event") {
              switch ($pos) {
                case YetAnotherICALCalendar_Datespans::IS_START:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                  break;
                case YetAnotherICALCalendar_Datespans::IS_END:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                  break;
                case YetAnotherICALCalendar_Datespans::IS_IN_SPAN:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                  break;
                case YetAnotherICALCalendar_Datespans::IS_SPLIT:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                  break;
                case YetAnotherICALCalendar_Datespans::IS_FREE:
                  break;
              }
            } else {
              switch ($pos) {
                case YetAnotherICALCalendar_Datespans::IS_START:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/start-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                  break;
                case YetAnotherICALCalendar_Datespans::IS_END:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/end-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                  break;
                case YetAnotherICALCalendar_Datespans::IS_IN_SPAN:
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                  break;
                case YetAnotherICALCalendar_Datespans::IS_SPLIT:
                  //YAICALHelper::write_log(sprintf("SPLIT: %04d%02d%02d", $year, $month, $month_day));
                  $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/split-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                  break;
                case YetAnotherICALCalendar_Datespans::IS_FREE:
                  break;
              }
            }
            /*
            if ($month_day == 1) {
            YAICALHelper::write_log(sprintf("%04d%02d01: WDAY=%d", $year, $month, $wday));
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
            //----------
            // If we have an calendar ID, then add an onClick-event.
            $onclick_for_day = '';
            $onclick_for_day_class = '';
            if ((!empty($id)) and $a_acc['write']) {
              $onclick_for_day = sprintf(' onclick="yetanothericalcalendar_annotate(\'%s\', \'%s\', \'%04d%02d%02d\'); return false"',
                esc_html($pid), esc_html($id), $year, $month, $month_day);
              $onclick_for_day_class = ' pointer';
            }
            //----------
            if (empty($desc)) {
              $mo_day_span = sprintf('<span class="yetanothericalcalendar-day%s"%s>%02d</span>', $onclick_for_day_class, $onclick_for_day, $month_day);
            } else {
              //SEE:https://github.com/ytiurin/html5tooltipsjs
              $mo_day_span = sprintf('<span class="yetanothericalcalendar-day yetanothericalcalendar-tooltip%s"%s data-tooltip="%s">%02d</span>',
                $onclick_for_day_class, $onclick_for_day, esc_html($desc), $month_day);
            }
            $doc .= sprintf('<td><div class="cellc square%s"%s>%s</div></td>',
              $wday_class, $td_backgroud_image_style, $mo_day_span) . PHP_EOL;
            /*
            $doc .= sprintf('<td><div class="cellc square%s"%s><a href="#" class="link" title="%02d.%02d.%04d%s" rel="nofollow">%02d</a></div></td>',
            $wday_class, $td_backgroud_image_style, $month_day, $month, $year, $desc, $month_day) . PHP_EOL;
            */
          } else {
            $doc .= sprintf('<td><div class="cellc square blank">&nbsp;</div></td>') . PHP_EOL;
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
  private static function _render_as_months($pid, $id, $a_acc, $align, $type, $description, $b_use_ical_years, $b_use_ical_months, $a_years, $a_ical_years, $a_months, $a_ical_year_months, $ical_spans, $a_months_names, $a_months_abr, $a_wdays_first_chracter) {
    $day_now = YAICALHelper::strtodatetime(sprintf("%04d%02d%02d", intval(date('Y')), intval(date('m')), intval(date('d'))));
    $doc = "";
    // Calc start week day and width for all years:
    $calendar_height = 4;
    foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
      // Determine the „height“ of the calendar.
      foreach (($b_use_ical_months ? $a_ical_year_months[$year] : $a_months) as $month) {
        $month_first_day = YAICALHelper::strtodatetime(sprintf("%04d%02d01", $year, $month));
        $wday = $month_first_day->format('w');
        $wday = ($wday == 0 ? 7 : $wday);
        $nr_mdays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $mheight = ceil(($nr_mdays + $wday - 1) / 7.0);
        $calendar_height = ($mheight > $calendar_height ? $mheight : $calendar_height);
      }
    }
    $approximated_table_width_in_pixels = 19 * 7;
    //
    $doc .= sprintf('<div class="yetanothericalcalendar-reset-this"><div id="%s-cal-msg" style=display:none;"></div><div id="%s" class="yetanothericalcalendar mo-grid">', $id, $id) . PHP_EOL;
    foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
      YAICALHelper::write_log(sprintf("RENDER AS MONTHS YEAR=%d", $year));
      YAICALHelper::write_log(sprintf("%04d: WIDTH=%d HEIGHT=%d DIR='%s' DESCRIPTION=%s", $year, 7, $calendar_height, YAICALHelper::get_my_plugin_directory(), YAICALHelper::booltostr($description)));
      $nr_month_counter = 0;
      foreach (($b_use_ical_months ? $a_ical_year_months[$year] : $a_months) as $month) {
        //
        // Table body (months):
        $doc .= sprintf('<div class="mo-column"><table class="mo-table%s" width="%dpx" year-mo="%04d%02d"><tbody>',
          ($align == '' ? '' : (' ' . $align)), $approximated_table_width_in_pixels, $year, $month) . PHP_EOL;
        // Table header:
        $doc .= sprintf('<tr><th colspan="7"><div class="cellc plain"><span class="yr-span">%s&nbsp;&nbsp;%04d</span></div></th></tr>',
          __($a_months_names[$month - 1], 'yetanothericalcalendar'), $year) . PHP_EOL;
        $doc .= sprintf('<tr class="mo-header">') . PHP_EOL;
        for ($i = 0; $i < 7; $i++) {
          $wday_class = '';
          if ($i >= 5) {
            $wday_class = ' wkend';
          }
          $doc .= sprintf('<th><div class="cellc square wday%s">%s</div></th>', $wday_class, $a_wdays_first_chracter[$i]) . PHP_EOL;
        }
        $doc .= '</tr>' . PHP_EOL;
        $month_first_day = YAICALHelper::strtodatetime(sprintf("%04d%02d01", $year, $month));
        $month_starts_with_wday = $month_first_day->format('w');
        $month_starts_with_wday = ($month_starts_with_wday == 0 ? 7 : $month_starts_with_wday);
        $nr_mdays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $month_index = 0;
        $month_day = 1;
        $month_height = ceil(($nr_mdays + $month_starts_with_wday - 1) / 7.0);
        for ($y = 1; $y <= $calendar_height; $y++) {
          // Render week.
          $doc .= sprintf('<tr>') . PHP_EOL;
          for ($i = 0; $i < 7; $i++) {
            if ($y > $month_height) {
              $doc .= sprintf('<td class="hidden"><div class="cellc square">&nbsp;</div></td>') . PHP_EOL;
            } else {
              $month_index++;
              $wday = $i + 1;
              if (($month_index < $month_starts_with_wday) or ($month_day > $nr_mdays)) {
                $doc .= sprintf('<td><div class="cellc square blank">&nbsp;</div></td>') . PHP_EOL;
              } else {
                $dt_this_date = YAICALHelper::strtodatetime(sprintf("%04d%02d%02d", $year, $month, $month_day));
                $pos = $ical_spans->position($dt_this_date, $type);
                $td_backgroud_image_style = '';
                if ($type == "event") {
                  switch ($pos) {
                    case YetAnotherICALCalendar_Datespans::IS_START:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                      break;
                    case YetAnotherICALCalendar_Datespans::IS_END:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                      break;
                    case YetAnotherICALCalendar_Datespans::IS_IN_SPAN:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                      break;
                    case YetAnotherICALCalendar_Datespans::IS_FREE:
                      break;
                  }
                } else {
                  switch ($pos) {
                    case YetAnotherICALCalendar_Datespans::IS_START:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/start-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                      break;
                    case YetAnotherICALCalendar_Datespans::IS_END:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/end-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                      break;
                    case YetAnotherICALCalendar_Datespans::IS_IN_SPAN:
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/occupied-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                      break;
                    case YetAnotherICALCalendar_Datespans::IS_SPLIT:
                      YAICALHelper::write_log(sprintf("SPLIT! DATE='%s'", $dt_this_date->format('c')));
                      $td_backgroud_image_style = sprintf(' style="background-image: url(%s); background-size: cover; background-repeat: no-repeat;"', plugins_url('/assets/img/split-background.svg', YAICALHelper::get_my_plugin_directory() . '/index.php'));
                      break;
                    case YetAnotherICALCalendar_Datespans::IS_FREE:
                      break;
                  }
                }
                if ($month_day == 1) {
                  YAICALHelper::write_log(sprintf("%04d%02d01: WDAY=%d", $year, $month, $wday));
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
                //----------
                // If we have an calendar ID, then add an onClick-event.
                $onclick_for_day = '';
                $onclick_for_day_class = '';
                if ((!empty($id)) and $a_acc['write']) {
                  $onclick_for_day = sprintf(' onclick="yetanothericalcalendar_annotate(\'%s\', \'%s\', \'%04d%02d%02d\'); return false"',
                    esc_html($pid), esc_html($id), $year, $month, $month_day);
                  $onclick_for_day_class = ' pointer';
                }
                //----------
                if (empty($desc)) {
                  $mo_day_span = sprintf('<span class="yetanothericalcalendar-day%s"%s>%02d</span>',
                    $onclick_for_day_class, $onclick_for_day, $month_day);
                } else {
                  //SEE:https://github.com/ytiurin/html5tooltipsjs
                  $mo_day_span = sprintf('<span class="yetanothericalcalendar-day yetanothericalcalendar-tooltip%s"%s data-tooltip="%s">%02d</span>',
                    $onclick_for_day_class, $onclick_for_day, esc_html($desc), $month_day);
                }
                $doc .= sprintf('<td><div class="cellc square%s"%s>%s</div></td>',
                  $wday_class, $td_backgroud_image_style, $mo_day_span) . PHP_EOL;
                /*
                $doc .= sprintf('<td><div class="cellc square%s"%s><a href="#" class="link" title="%02d.%02d.%04d%s" rel="nofollow">%02d</a></div></td>',
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

  private static function _add_month(
    $month,
    $year,
    &$a_years,
    $b_use_ical_months, &$a_ical_year_months, &$a_months) {
    YAICALHelper::write_log(sprintf("_add_month: MONTH=%d YEAR=%d", $month, $year));
    // Add year.
    if (!array_search($year, $a_years)) {
      array_push($a_years, $year);
      if ($b_use_ical_months and (!array_key_exists($year, $a_ical_year_months))) {
        $a_ical_year_months[$year] = array();
      }
    }
    // Add month.
    if ($b_use_ical_months) {
      if (!array_search($month, $a_ical_year_months[$year])) {
        array_push($a_ical_year_months[$year], $month);
        sort($a_ical_year_months[$year], SORT_NUMERIC);
      }
    } else {
      if (!array_search($month, $a_months)) {
        array_push($a_months, $month);
        sort($a_months, SORT_NUMERIC);
      }
    }
  } // _add_month

  private static function _add_months_outside_this_year(
    $nr_months,
    $year,
    &$a_years,
    $b_use_ical_months, &$a_ical_year_months, &$a_months) {
    if ($nr_months > 0) {
      $current_year = $year + 1;
      $current_month = 0;
      for ($i = 1; $i <= $nr_months; $i++) {
        $current_month++;
        if ($current_month == 13) {
          $current_month = 1;
          $current_year++;
        }
        self::_add_month($current_month, $current_year, $a_years, $b_use_ical_months, $a_ical_year_months, $a_months);
      }
    }
    if ($nr_months < 0) {
      $current_year = $year - 1;
      $current_month = 13;
      for ($i = 0; $i > $nr_months; $i--) {
        $current_month--;
        if ($current_month == 0) {
          $current_month = 12;
          $current_year--;
        }
        self::_add_month($current_month, $current_year, $a_years, $b_use_ical_months, $a_ical_year_months, $a_months);
      }
    }
  } // _add_months_outside_this_year

  /**
   * Parse strings with shortcodes.
   *
   * @access  public
   * @return  String
   * @since   1.0.0
   */
  public static function parse($uuid, $atts, $content, &$evaluate_stack = NULL, $token = 'yetanothericalcalendar') {
    //----------
    YAICALHelper::init();
    //----------
    self::$_content_src = $content;
    $content = YAICALHelper::purecontent($content);
    $has_content = !empty($content);
    YAICALHelper::write_log("CONTENT='" . $content . "'");
    //
    //----------
    // Construct original shortcut source code.
    $shortcut_src = '[' . YAICALHelper::get_token();
    $a_atts_keys = array_keys($atts);
    foreach ($a_atts_keys as $key) {
      $shortcut_src .= ' ' . $key . '="' . $atts[$key] . '"';
    }
    $shortcut_src .= ']';
    if (!empty($content)) {
      $shortcut_src .= "<br />" . self::$_content_src;
      $shortcut_src .= '[/' . YAICALHelper::get_token() . ']';
    }
    self::$_shortcut_src = $shortcut_src;
    YAICALHelper::write_log(sprintf(""));
    //
    //----------
    // Get ID.
    $id = YAICALHelper::getav($atts, 'id');
    // Get page/post ID.
    $pid = strval(get_the_ID());
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
    // Get read access.
    $read_acc = $atts['read'];
    //
    //----------
    // Get write access.
    $write_acc = $atts['write'];
    //
    //----------
    // Check, if we have read access.
    $is_read_acc = YAICALHelper::is_access($read_acc);
    // Check, if we have write access.
    $is_write_acc = YAICALHelper::is_access($write_acc);
    $a_acc = ['read' => $is_read_acc, 'write' => $is_write_acc];
    //
    //----------
    // Load ical(s).
    //
    $b_use_ical_years = false;
    $b_use_ical_months = false;
    $a_ical_events = array();
    $a_ical_years = array();
    $a_ical_year_months = array();
    $ical_spans = new YetAnotherICALCalendar_Datespans();
    $cache_reload_timeout = 86400; // 1 day
    //
    //----------
    // Get cache timeout.
    if (array_key_exists('cache', $atts)) {
      $atts_cache = trim($atts['cache']);
      if (preg_match("/^([1-9]\d*)((?i)[hdmy])$/", $atts_cache, $matches)) {
        //YAICALHelper::write_log(sprintf("CACHE-MATCHES=%d", count($matches)));
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
    YAICALHelper::write_log(sprintf("CAHCE-RELOAD-TIMEOUT=%d", $cache_reload_timeout));
    //
    //----------
    // Get ical urls:
    $a_ical_urls = array();
    if (array_key_exists('ical', $atts)) {
      $atts_ical = trim($atts['ical']);
      YAICALHelper::write_log(sprintf("PARAM ICAL='%s'", $atts_ical));
      if (preg_match("/^(\S+(\s+))*(\S+)$/", $atts_ical, $matches)) {
        //YAICALHelper::write_log(sprintf("ICAL-URL-MATCHES=%d", count($matches)));
        //YAICALHelper::write_log($matches);
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
      YAICALHelper::write_log(sprintf("URL='%s' HASH='%s'", $ical_url, $file_id));
      $cache_fn = YAICALHelper::get_my_cache_directory() . '/' . $file_id;
      try {
        $has_reload = true;
        if (file_exists($cache_fn)) {
          // Calc time difference.
          $t_cache_file_diff = time() - filemtime($cache_fn);
          if ($t_cache_file_diff < $cache_reload_timeout) {
            $has_reload = false;
          }
          YAICALHelper::write_log(sprintf("T-CACHE-DIFF=%d HAS-RELOAD=%s", $t_cache_file_diff, YAICALHelper::booltostr($has_reload)));
        }
        // Load external resource.
        if ($has_reload) {
          $response = wp_remote_get($ical_url, ['timeout' => 30]);

          if (is_array($response) && (!is_wp_error($response))) {
            $response_code = intval(wp_remote_retrieve_response_code($response));
            YAICALHelper::write_log(sprintf("HTTP-RESPONSE-CODE=%d", $response_code));
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
          //$ical = new yetanothericalcalendar_iCalEasyReader();
          //SEE:https://github.com/u01jmg3/ics-parser
          $ical = new Ical\ICal($cache_fn, array(
            'defaultTimeZone' => 'Europe/Berlin',
            'defaultWeekStart' => 'MO',
            'skipRecurrence' => false,
            'defaultSpan' => 5));
          YAICALHelper::write_log(sprintf("ICAL-SIZE=%d", $ical->eventCount));
          if ($ical->eventCount > 0) {
            self::_add_ical_events_to_ical_spans($ical_url, $description, $ical->events(), $a_ical_events, $ical_spans);
          }
        }
      } catch (Exception $e) {
        $msg = sprintf("ERROR='%s'", strval($e));
        YAICALHelper::write_log($msg);
        return self::_error($msg);
      }
    }
    //
    //----------
    // Analyse ICAL content:
    if ($has_content) {
      //$ical = new yetanothericalcalendar_iCalEasyReader();
      //SEE:https://github.com/u01jmg3/ics-parser
      $ical = new Ical\ICal($content, array(
        'defaultTimeZone' => 'Europe/Berlin',
        'defaultWeekStart' => 'MO',
        'skipRecurrence' => false,
        'defaultSpan' => 5));
      YAICALHelper::write_log(sprintf("CONTENT-ICAL-SIZE=%d", $ical->eventCount));
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
      //YAICALHelper::write_log(sprintf("[%s] GET YEARS/MONTHS ICAL-DTSTART='%s' DTEND='%s'", $ical_event_key, $dt_start, $dt_end));
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
    //YAICALHelper::write_log($atts);
    if (array_key_exists('year', $atts)) {
      $atts_year = trim($atts['year']);
      //YAICALHelper::write_log(sprintf("PARAM YEAR='%s'", $atts_year));
      if (preg_match("/^((?i)NOW|(?i)ICAL|[1-9][0-9]*)(\+[1-9][0-9]*){0,1}(-[1-9][0-9]*){0,1}$/", $atts_year, $matches)
        or preg_match("/^((?i)NOW|(?i)ICAL|[1-9][0-9]*)(\-[1-9][0-9]*){0,1}(\+[1-9][0-9]*){0,1}$/", $atts_year, $matches)) {
        //YAICALHelper::write_log($matches);
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
                YAICALHelper::swap_values($from, $to);
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
                YAICALHelper::swap_values($from, $to);
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
                YAICALHelper::swap_values($from, $to);
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
                YAICALHelper::swap_values($from, $to);
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
                YAICALHelper::swap_values($from, $to);
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
    //YAICALHelper::write_log($atts);
    if (array_key_exists('months', $atts)) {
      $atts_months = trim($atts['months']);
      YAICALHelper::write_log(sprintf("PARAM MONTHS='%s'", $atts_months));
      if (preg_match("/^((?i)NOW|(?i)ALL|(?i)ICAL|[1-9][0-9]*)(\+[1-9][0-9]*){0,1}(-[1-9][0-9]*){0,1}$/", $atts_months, $matches)
        or preg_match("/^((?i)NOW|(?i)ALL|(?i)ICAL|[1-9][0-9]*)(\-[1-9][0-9]*){0,1}(\+[1-9][0-9]*){0,1}$/", $atts_months, $matches)) {
        if (strtolower($matches[1]) == 'ical') {
          $b_months_syntax_is_correct = true;
          $b_use_ical_months = true;
          //YAICALHelper::write_log($matches);
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
                    $over_months = max([0, max($a_ical_year_months[$year]) + $offset - 12]);
                  } else {
                    $from = max([1, min($a_ical_year_months[$year]) + $offset]);
                    $to = max($a_ical_year_months[$year]);
                    $over_months = min([0, min($a_ical_year_months[$year]) + $offset]);
                  }
                  if ($from > $to) {
                    YAICALHelper::swap_values($from, $to);
                  }
                  $a_new_months = array();
                  for ($month = $from; $month <= $to; $month++) {
                    array_push($a_new_months, $month);
                  }
                  $a_ical_year_months[$year] = $a_new_months;
                  if ($b_use_ical_years) {
                    self::_add_months_outside_this_year($over_months,
                      $year,
                      $a_ical_years,
                      $b_use_ical_months, $a_ical_year_months, $a_months);
                  } else {
                    self::_add_months_outside_this_year($over_months,
                      $year,
                      $a_years,
                      $b_use_ical_months, $a_ical_year_months, $a_months);
                  }
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
                    YAICALHelper::swap_values($offset1, $offset2);
                  }
                  $from = max([1, min($a_ical_year_months[$year]) + $offset1]);
                  $to = min([12, max($a_ical_year_months[$year]) + $offset2]);
                  $over_months1 = min([0, min($a_ical_year_months[$year]) + $offset1]);
                  $over_months2 = max([0, max($a_ical_year_months[$year]) + $offset2 - 12]);
                  $a_new_months = array();
                  for ($month = $from; $month <= $to; $month++) {
                    array_push($a_new_months, $month);
                  }
                  $a_ical_year_months[$year] = $a_new_months;
                  if ($b_use_ical_years) {
                    self::_add_months_outside_this_year($over_months1,
                      $year,
                      $a_ical_years,
                      $b_use_ical_months, $a_ical_year_months, $a_months);
                  } else {
                    self::_add_months_outside_this_year($over_months1,
                      $year,
                      $a_years,
                      $b_use_ical_months, $a_ical_year_months, $a_months);
                  }
                  if ($b_use_ical_years) {
                    self::_add_months_outside_this_year($over_months2,
                      $year,
                      $a_ical_years,
                      $b_use_ical_months, $a_ical_year_months, $a_months);
                  } else {
                    self::_add_months_outside_this_year($over_months2,
                      $year,
                      $a_years,
                      $b_use_ical_months, $a_ical_year_months, $a_months);
                  }
                } else {
                  return self::_error(sprintf("No ICAL data found for this year! YEAR=\"%s\"", $year));
                }
              }
              break;
          }
        } else {
          //YAICALHelper::write_log($matches);
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
                    YAICALHelper::swap_values($from, $to);
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
                    YAICALHelper::swap_values($from, $to);
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
          //YAICALHelper::write_log("LIST OF MONTHS");
          //YAICALHelper::write_log($matches);
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
          if (preg_match("/^\s*((?i)NOW([+-])(?i)ICAL)(([+-])[1-9][0-9]*){0,1}\s*$/", $atts_months, $matches)) {
            $b_months_syntax_is_correct = true;
            /*
            YAICALHelper::write_log(sprintf("NOW[+-]ICAL: ICAL-YEARS & ICAL-MONTHS: USE-ICAL-YEARS=%s", YAICALHelper::booltostr($b_use_ical_years)));
            YAICALHelper::write_log($a_ical_years);
            YAICALHelper::write_log(sprintf("NOW[+-]ICAL: YEARS & MONTHS:"));
            YAICALHelper::write_log($a_years);
            YAICALHelper::write_log($matches);
            */
            $operator = $matches[2];
            $year_now = intval(date('Y'));
            $month_now = intval(date('m'));
            $operator2 = '+';
            $offset = 0;
            YAICALHelper::write_log("NOW+ICAL:");
            YAICALHelper::write_log($matches);
            if (count($matches) == 5) {
              $operator2 = $matches[4];
              $offset = intval($matches[3]);
            }
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
              YAICALHelper::write_log($a_ical_years);
              // Remove all months not in years.
              if ($b_use_ical_months) {
                $a_years_to_remove = [];
                foreach ($a_ical_year_months as $year => $months) {
                  if (!in_array($year, $a_ical_years)) {
                    array_push($a_years_to_remove, $year);
                    YAICALHelper::write_log(sprintf("YEARTOREMOVE=%s", $year));
                  }
                }
                foreach ($a_years_to_remove as $year) {
                  unset($a_ical_year_months[$year]);
                }
              }
            }
            // Add extra months:
            if ($offset != 0) {
              YAICALHelper::write_log(sprintf("OFFSET=%d", $offset));
              if ($offset > 0) {
                $year = max($b_use_ical_years ? $a_ical_years : $a_years);
                YAICALHelper::write_log(sprintf("OFFSET: MAX-YEAR=%d ICAL-MONTHS=%s", $year, YAICALHelper::booltostr($b_use_ical_months)));
                $a_month_array_reference = ($b_use_ical_months ? $a_ical_year_months[$year] : $a_months);
                $max_month = max($a_month_array_reference);
                $over_months = max([0, $max_month + $offset - 12]);
                $to = min([12, $max_month + $offset]);
                YAICALHelper::write_log(sprintf("OFFSET: OVER-MONTHS=%d MAX-MONTH=%d TO=%d", $over_months, $max_month, $to));
                for ($m = $max_month + 1; $m <= $to; $m++) {
                  if (!in_array($m, $a_month_array_reference)) {
                    array_push($a_month_array_reference, $m);
                  }
                }
                sort($a_month_array_reference, SORT_NUMERIC);
              } else {
                $year = min($b_use_ical_years ? $a_ical_years : $a_years);
                YAICALHelper::write_log(sprintf("OFFSET: MIN-YEAR=%d ICAL-MONTHS=%s", $year, YAICALHelper::booltostr($b_use_ical_months)));
                $a_month_array_reference = ($b_use_ical_months ? $a_ical_year_months[$year] : $a_months);
                $min_month = min($a_month_array_reference);
                $over_months = min([0, $min_month + $offset]);
                $from = max([1, $min_month + $offset]);
                YAICALHelper::write_log(sprintf("OFFSET: OVER-MONTHS=%d MIN-MONTH=%d FROM=%d", $over_months, $min_month, $from));
                for ($m = $from; $m < $min_month; $m++) {
                  if (!in_array($m, $a_month_array_reference)) {
                    array_push($a_month_array_reference, $m);
                  }
                }
                sort($a_month_array_reference, SORT_NUMERIC);
              }
              if ($b_use_ical_months) {
                $a_ical_year_months[$year] = $a_month_array_reference;
              } else {
                $a_months = $a_month_array_reference;
              }
              if ($b_use_ical_years) {
                self::_add_months_outside_this_year($over_months,
                  $year,
                  $a_ical_years,
                  $b_use_ical_months, $a_ical_year_months, $a_months);
              } else {
                self::_add_months_outside_this_year($over_months,
                  $year,
                  $a_years,
                  $b_use_ical_months, $a_ical_year_months, $a_months);
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
        return self::_error(sprintf("No ICAL data found for YEAR definition. YEAR=\"%s\"", YAICALHelper::getav($atts, 'year')));
      }
    } else {
      if (empty($a_years)) {
        return self::_error(sprintf("No years found for YEAR definition. YEAR=\"%s\"", YAICALHelper::getav($atts, 'year')));
      }
    }
    if ($b_use_ical_months) {
      if (empty($a_ical_year_months)) {
        return self::_error(sprintf("No ICAL data found for MONTHS definition. MONTHS=\"%s\"", YAICALHelper::getav($atts, 'months')));
      } else {
        foreach (($b_use_ical_years ? $a_ical_years : $a_years) as $year) {
          if ((!array_key_exists($year, $a_ical_year_months)) or empty($a_ical_year_months[$year])) {
            return self::_error(sprintf("No ICAL data found for this year. YEAR=\"%s\"", YAICALHelper::getav($atts, 'year')));
          }
        }
      }
    } else {
      if (empty($a_months)) {
        return self::_error(sprintf("No months found for MONTHS definition. MONTHS=\"%s\"", YAICALHelper::getav($atts, 'months')));
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
    // Sort years:
    if ($b_use_ical_years) {
      sort($a_ical_years, SORT_NUMERIC);
    } else {
      sort($a_years, SORT_NUMERIC);
    }
    //
    //----------
    //
    YAICALHelper::write_log(sprintf("B_USE_ICAL_YEARS=%s B_USE_ICAL_MONTHS=%s", YAICALHelper::booltostr($b_use_ical_years), YAICALHelper::booltostr($b_use_ical_months)));
    if ($b_use_ical_years) {
      YAICALHelper::write_log($a_ical_years);
    } else {
      YAICALHelper::write_log($a_years);
    }
    if ($b_use_ical_months) {
      YAICALHelper::write_log($a_ical_year_months);
    } else {
      YAICALHelper::write_log($a_months);
    }
    //
    //----------
    // Get first character for each day in a week.
    $a_wdays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
    $a_months_names = ["MONTH-January", "MONTH-February", "MONTH-March", "MONTH-April", "MONTH-May", "MONTH-June", "MONTH-July", "MONTH-August", "MONTH-September", "MONTH-October", "MONTH-November", "MONTH-December"];
    $a_months_abr = ["Jan", "Feb", "Mar", "Apr", "May", "June", "July", "Aug", "Sept", "Oct", "Nov", "Dez"];
    $a_wdays_first_chracter = array();
    foreach ($a_wdays as $wday) {
      array_push($a_wdays_first_chracter, mb_substr(__($wday, 'yetanothericalcalendar'), 0, 1));
    }
    //YAICALHelper::write_log($a_wdays_first_chracter);
    //
    //----------
    // Render calender.
    if ($a_acc['read']) {
      $doc = ($display == 'year'
        ? self::_render_as_years($pid, $id, $a_acc, $align, $type, $description, $b_use_ical_years, $b_use_ical_months, $a_years, $a_ical_years, $a_months, $a_ical_year_months, $ical_spans, $a_months_names, $a_months_abr, $a_wdays_first_chracter)
        : self::_render_as_months($pid, $id, $a_acc, $align, $type, $description, $b_use_ical_years, $b_use_ical_months, $a_years, $a_ical_years, $a_months, $a_ical_year_months, $ical_spans, $a_months_names, $a_months_abr, $a_wdays_first_chracter)
      );
    } else {
      $doc = '<div style="border-left:10px solid red; padding:3px 6px 3px 6px; font-size:80%; line-height:14px;">Keine Leseerlaubnis für den Kalender!</div>';
    }
    return $doc;
  } // parse

  public static function parse_annotation($uuid, $atts, $content, &$evaluate_stack = NULL, $token = 'yetanothericalcalendar-annotation') {
    //----------
    YAICALHelper::init();
    //----------
    self::$_content_src = $content;
    $content = YAICALHelper::purecontent($content);
    $has_content = !empty($content);
    YAICALHelper::write_log("CONTENT='" . $content . "'");
    //
    //----------
    // Construct original shortcut source code.
    $shortcut_src = '[' . YAICALHelper::get_token_annotation();
    $a_atts_keys = array_keys($atts);
    foreach ($a_atts_keys as $key) {
      $shortcut_src .= ' ' . $key . '="' . $atts[$key] . '"';
    }
    $shortcut_src .= ']';
    if (!empty($content)) {
      $shortcut_src .= "<br />" . self::$_content_src;
      $shortcut_src .= '[/' . YAICALHelper::get_token_annotation() . ']';
    }
    self::$_shortcut_src = $shortcut_src;
    YAICALHelper::write_log(sprintf(""));
    //
    //----------
    // Get Calendar ID.
    $id = YAICALHelper::getav($atts, 'id');
    // Get page/post ID.
    $pid = strval(get_the_ID());
    // Get session id.
    YAICALHelper::write_log(sprintf("UUID='%s'", $uuid));
    //
    $doc = '';
    if ((!empty($id)) and (!empty($pid)) and (!empty($uuid))) {
      //
      //----------
      // Get read access.
      $read_acc = $atts['read'];
      //
      //----------
      // Get write access.
      $write_acc = $atts['write'];
      //
      //----------
      // Check, if we have read access.
      $is_read_acc = YAICALHelper::is_access($read_acc);
      // Check, if we have write access.
      $is_write_acc = YAICALHelper::is_access($write_acc);
      // Remeber it.
      self::set_annotation_rw($pid, $id, $uuid, $is_read_acc, $is_write_acc);
      //
      //----------
      // Render calender.
      YAICALHelper::write_log(sprintf("parse_annotation: PID=%s ID='%s' READ='%s' WRITE='%s' REQUIRED=%s POST-STATUS='%s'",
        $pid, $id,
        YAICALHelper::booltostr($is_read_acc),
        YAICALHelper::booltostr($is_write_acc),
        YAICALHelper::booltostr(post_password_required()), get_post_status()));
      YAICALHelper::write_log(sprintf("parse_annotation: ROLES='%s'", implode(', ', YAICALHelper::get_current_user_roles())));
      $doc = sprintf('<div class="yetanothericalcalendar-annotation" id="%s" pid="%s"><div class="loader"></div></div>', $id, $pid);
    }
    return $doc;
  } // parse_annotation
} // class YetAnotherICALCalendar_Parser