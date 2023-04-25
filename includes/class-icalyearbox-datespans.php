<?php

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Wordpress Plugin icalyearbox (PHP Component)
 *
 * @license MIT https://en.wikipedia.org/wiki/MIT_License
 * @author  Kai Thoene <k.git.thoene@gmx.net>
 */


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
      if ((($other_span->_from >= $this->_from) and ($other_span->_from <= $this->_to))
        or (($other_span->_to >= $this->_from) and ($other_span->_to <= $this->_to))) {
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

  public function position($dt, $type = 'event') {
    if (is_a($dt, 'DateTime')) {
      if ($type = 'booking') {
        if (($dt == $this->_from) and ($dt == $this->_to)) {
          return Icalyearbox_Datespans::IS_IN_SPAN;
        }
        if ($dt == $this->_to) {
          return Icalyearbox_Datespans::IS_END;
        }
        if ($dt == $this->_from) {
          return Icalyearbox_Datespans::IS_START;
        }
        if (($dt > $this->_from) and ($dt < $this->_to)) {
          return Icalyearbox_Datespans::IS_IN_SPAN;
        }
      } else {
        if (($dt >= $this->_from) and ($dt <= $this->_to)) {
          return Icalyearbox_Datespans::IS_IN_SPAN;
        }
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
    $value = sprintf("Icalyearbox_Datespan(_from = '%s', _to = '%s', _description='%s')", $this->_from->format('c'), $this->_to->format('c'), $this->_description);
    return $value;
  }
} // class Icalyearbox_Datespan


class Icalyearbox_Datespans {
  public const IS_OUTSIDE = -1;
  public const IS_START = 0;
  public const IS_IN_SPAN = 1;
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

  public function position($dt, $type = 'event') {
    if (is_a($dt, 'DateTime')) {
      $search_counter = 0;
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
