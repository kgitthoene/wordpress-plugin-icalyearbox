<?php
/**
 * Plugin Name: YetAnotherWPICALCalendar
 * Version: 1.0.0
 * Plugin URI: https://github.com/kgitthoene/wordpress-plugin-yet-another-wp-ical-calendar
 * Description: Write shortcuts using other shortcodes.
 * Author: Kai Thoene
 * Author URI: https://github.com/kgitthoene/
 * License: MIT
 * License URI: https://en.wikipedia.org/wiki/MIT_License
 * Requires at least: 4.0
 * Tested up to: 6.2
 *
 * Text Domain: yetanotherwpicalcalendar
 * Domain Path: /languages
 *
 * @package WordPress
 * @author Kai Thoene
 * @since 1.0.0
 */

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

if (!defined('ABSPATH')) {
  exit;
}

// Load plugin class files.
require_once 'includes/class-yetanotherwpicalcalendar.php';
require_once 'includes/class-yetanotherwpicalcalendar-parser.php';
/*
TODO Add settings, if useful.
require_once 'includes/class-yetanotherwpicalcalendar-settings.php';
*/

// Load plugin libraries.
require_once 'includes/lib/class-yetanotherwpicalcalendar-admin-api.php';
require_once 'includes/lib/class-yetanotherwpicalcalendar-post-type.php';
require_once 'includes/lib/class-yetanotherwpicalcalendar-taxonomy.php';

/**
 * Returns the main instance of YetAnotherWPICALCalendar to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object YetAnotherWPICALCalendar
 */
function YetAnotherWPICALCalendar() {
  $instance = YetAnotherWPICALCalendar::instance(__FILE__, '1.0.0');
  /*
  TODO: Add settings, if useful.
  if ( is_null( $instance->settings ) ) {
  $instance->settings = YetAnotherWPICALCalendar_Settings::instance( $instance );
  }
  */
  return $instance;
} // YetAnotherWPICALCalendar

YetAnotherWPICALCalendar();