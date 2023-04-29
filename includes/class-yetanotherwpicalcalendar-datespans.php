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


/**
 * Parser plugin class file.
 *
 * @package WordPress Plugin YetAnotherWPICALCalendar/Includes
 */
class YetAnotherWPICALCalendar_Datespan {
  private $_from;
  private $_to;
  private $_description;

  public function __construct($from, $to, $description = "") {
    if (is_a($from, 'DateTime') and is_a($to, 'DateTime')) {
      $this->_description = $description;
      $this->_from = $from;
      $this->_to = $to;
    } else {
      throw new ErrorException("Parameters must be type DateTime!", 0, E_ERROR, __FILE__, __LINE__);
    }
  } // __construct

  public function connects_to($other_span) {
    if (is_a($other_span, 'YetAnotherWPICALCalendar_Datespan')) {
      if ((($other_span->_from >= $this->_from) and ($other_span->_from <= $this->_to))
        or (($other_span->_to >= $this->_from) and ($other_span->_to <= $this->_to))) {
        return true;
      }
      return false;
    } else {
      throw new ErrorException("Parameter must be type YetAnotherWPICALCalendar_Datespan!", 0, E_ERROR, __FILE__, __LINE__);
    }
  } // connects_to

  public function add($other_span) {
    if (is_a($other_span, 'YetAnotherWPICALCalendar_Datespan')) {
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
      throw new ErrorException("Parameter must be type YetAnotherWPICALCalendar_Datespan!", 0, E_ERROR, __FILE__, __LINE__);
    }
    return false;
  } // add

  public function position($dt, $type = 'event') {
    if (is_a($dt, 'DateTime')) {
      if (($type == 'booking') or (($type == 'booking-split'))) {
        if (($dt == $this->_from) and ($dt == $this->_to)) {
          YetAnotherWPICALCalendar_Parser::write_log(sprintf("position: IN_SPAN TYPE='%s' DAY=%s FROM=%s TO=%s", $type, $dt->format('c'), $this->_from->format('c'), $this->_to->format('c')));
          return YetAnotherWPICALCalendar_Datespans::IS_IN_SPAN;
        }
        if ($dt == $this->_to) {
          YetAnotherWPICALCalendar_Parser::write_log(sprintf("position: IS_END TYPE='%s' DAY=%s FROM=%s TO=%s", $type, $dt->format('c'), $this->_from->format('c'), $this->_to->format('c')));
          return YetAnotherWPICALCalendar_Datespans::IS_END;
        }
        if ($dt == $this->_from) {
          YetAnotherWPICALCalendar_Parser::write_log(sprintf("position: IS_START TYPE='%s' DAY=%s FROM=%s TO=%s", $type, $dt->format('c'), $this->_from->format('c'), $this->_to->format('c')));
          return YetAnotherWPICALCalendar_Datespans::IS_START;
        }
        if (($dt > $this->_from) and ($dt < $this->_to)) {
          YetAnotherWPICALCalendar_Parser::write_log(sprintf("position: IN_SPAN TYPE='%s' DAY=%s FROM=%s TO=%s", $type, $dt->format('c'), $this->_from->format('c'), $this->_to->format('c')));
          return YetAnotherWPICALCalendar_Datespans::IS_IN_SPAN;
        }
      } else {
        if (($dt >= $this->_from) and ($dt <= $this->_to)) {
          return YetAnotherWPICALCalendar_Datespans::IS_IN_SPAN;
        }
      }
    } else {
      throw new ErrorException("Parameter must be type DateTime!", 0, E_ERROR, __FILE__, __LINE__);
    }
    return YetAnotherWPICALCalendar_Datespans::IS_OUTSIDE;
  } // add

  public function description() {
    return $this->_description;
  } // description

  public function inspect() {
    $value = sprintf("YetAnotherWPICALCalendar_Datespan(_from = '%s', _to = '%s', _description='%s')", $this->_from->format('c'), $this->_to->format('c'), $this->_description);
    return $value;
  }
} // class YetAnotherWPICALCalendar_Datespan


class YetAnotherWPICALCalendar_Datespans {
  public const IS_OUTSIDE = -1;
  public const IS_START = 0;
  public const IS_IN_SPAN = 1;
  public const IS_SPLIT = 2;
  public const IS_FREE = 3;
  public const IS_END = 4;

  private $_raw_spans;
  private $_spans;

  public function __construct() {
    $this->_raw_spans = array();
    $this->_spans = array();
  } // __construct

  public function add($span) {
    if (is_a($span, 'YetAnotherWPICALCalendar_Datespan')) {
      array_push($this->_raw_spans, clone $span);
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
      throw new ErrorException("Parameter must be type YetAnotherWPICALCalendar_Datespan!", 0, E_ERROR, __FILE__, __LINE__);
    }
  } // function add

  public function position($dt, $type = 'event') {
    if (is_a($dt, 'DateTime')) {
      $search_counter = 0;
      if ($type == 'booking-split') {
        $a_positions = array();
        $nr = 0;
        foreach ($this->_raw_spans as $key => $span) {
          $pos = $span->position($dt, $type);
          if ($dt->format('c') == '2023-08-05T00:00:00+00:00') {
            YetAnotherWPICALCalendar_Parser::write_log(sprintf("[%d] POS=%d DAY=%s SPAN='%s'", $key, $pos, $dt->format('c'), $span->inspect()));
          }
          switch ($pos) {
            case self::IS_SPLIT:
              break;
              return $pos;
            case self::IS_START:
            case self::IS_END:
            case self::IS_IN_SPAN:
              if (!in_array($pos, $a_positions)) {
                array_push($a_positions, $pos);
              }
          }
          $nr++;
        }
        if (count($a_positions) > 1) {
          YetAnotherWPICALCalendar_Parser::write_log(sprintf("position: COUNT=%d DAY=%s %d='%s' TYPE='%s'", $nr, $dt->format('c'), count($a_positions), implode(', ', $a_positions), $type));
        }
        if (in_array(self::IS_START, $a_positions) and in_array(self::IS_END, $a_positions)) {
          return self::IS_SPLIT;
        }
        if (in_array(self::IS_IN_SPAN, $a_positions)) {
          return self::IS_IN_SPAN;
        }
        if (in_array(self::IS_START, $a_positions)) {
          return self::IS_START;
        }
        if (in_array(self::IS_END, $a_positions)) {
          return self::IS_END;
        }
        return self::IS_FREE;
      } else {
        if ($type == 'booking') {
          foreach ($this->_spans as $span) {
            $pos = $span->position($dt, $type);
            switch ($pos) {
              case self::IS_START:
              case self::IS_END:
              case self::IS_IN_SPAN:
                return $pos;
            }
          }
        } else {
          foreach ($this->_raw_spans as $span) {
            $search_counter++;
            $pos = $span->position($dt, $type);
            switch ($pos) {
              case self::IS_START:
              case self::IS_END:
              case self::IS_IN_SPAN:
                return $pos;
            }
          }
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
          case self::IS_IN_SPAN:
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
    $value = 'YetAnotherWPICALCalendar_Datespans(_spans = [';
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
} // class YetAnotherWPICALCalendar_Datespans
