# Yet Another ICAL Calendar - Create calendars with ICAL data.
Contributors: kgitthoene
Author URI: https://github.com/kgitthoene/
Plugin URI: https://github.com/kgitthoene/wordpress-plugin-yetanothericalcalendar/
Tags: ics ical yearbox calendar year month booking
Donate link: 
Requires at least: 4.9.0
Tested up to: 6.2
Stable tag: 6.2
Requires PHP: 7.0
License: X11
License URI: https://github.com/kgitthoene/wordpress-plugin-yetanothericalcalendar/blob/master/LICENSE

You can create calendars, filled with in-page or external ICAL data, as month stripes (year overview) or as separate months.

You can create an annotation for every day. Please see the [Full Documentation](https://github.com/kgitthoene/wordpress-plugin-yetanothericalcalendar/).

## Description

This software is a plugin for [WordPress](https://wordpress.org/).
You can create calendars as year overview or as separate months.
These calendars are filled with in-page or external ICAL data.

YetAnotherICALCalendar is used as a [Shortcode](https://wordpress.com/support/wordpress-editor/blocks/shortcode-block/) in your Wordpress content:

**Full Syntax**: `[yetanothericalcalendar OPTIONS]ICAL-DATA[/yetanothericalcalendar]`

`ICAL-DATA` may be empty: `[yetanothericalcalendar OPTIONS][/yetanothericalcalendar]`

**Short, handy, Syntax**: `[yetanothericalcalendar OPTIONS]`

Full documentation, all options, full examples: [https://github.com/kgitthoene/wordpress-plugin-yetanothericalcalendar](https://github.com/kgitthoene/wordpress-plugin-yetanothericalcalendar)

### Examples

First example: Official holydays in North Rhine-Westphalia, Germany, 2023. \
Month stripe style. Option: `display="year"`

![First example with month stripes.](https://raw.githubusercontent.com/kgitthoene/wordpress-plugin-yetanothericalcalendar/master/readme/2023-feiertage-nrw.png)

Second Example: Booking calendar with months. \
Display months in a grid. Option: `display="month"` \
Booking style, i.e. half days on first and last day of a period. Option: `type="booking"`

![Second example with separate months.](https://raw.githubusercontent.com/kgitthoene/wordpress-plugin-yetanothericalcalendar/master/readme/booking-cal-month-grid.png)

### Links

* [Full Documentation](https://github.com/kgitthoene/wordpress-plugin-yetanothericalcalendar/)
* [Support forum/Report bugs](https://github.com/kgitthoene/wordpress-plugin-yetanothericalcalendar/issues)

## Installation

1. Extract the zipped file and upload the folder `yetanothericalcalendar` to to `/wp-content/plugins/` directory.
1. Activate the plugin through the `Plugins` menu in WordPress.

## Frequently Asked Questions

### If I make an event, DTEND is missing in the calendar!

Then you possibly use DTSTART and DTEND in DATE-format: `YYYYMMDD`.
DTEND must be in the future, compared to DTSTART.

See: [RFC 5545](https://www.rfc-editor.org/rfc/rfc5545#section-3.6.1): „The "DTEND" property for a "VEVENT" calendar component specifies the **non-inclusive** end of the event.“

So set DTEND to the next day after the event.

### One day events

See: [RFC 5545](https://www.rfc-editor.org/rfc/rfc5545#section-3.6.1): „For cases where a "VEVENT" calendar component specifies a "DTSTART" property with a DATE value type but no "DTEND" nor "DURATION" property, the event's duration is taken to be one day.“

Simply omit DTEND and DURATION.

## Screenshots

1. ![First example with month stripes.](https://raw.githubusercontent.com/kgitthoene/wordpress-plugin-yetanothericalcalendar/master/readme/2023-feiertage-nrw.png)
2. ![Second example with separate months.](https://raw.githubusercontent.com/kgitthoene/wordpress-plugin-yetanothericalcalendar/master/readme/booking-cal-month-grid.png)

## Changelog

### 1.9.0
* Annotation release.

### 1.0.0
* Initial release.

## Cookies

To ensure communication with the AJAX interface of this plugin, which has unprivileged ports, we use a session cookie to identify a browser session.
The name of this cookie is: ```wordpress-yetanothericalcalendar_seesion_cookie```.
It will be deleted when the browser is closed.

The reading and writing rights for annotations and calendars are thus implemented.

## Used Software and Attribution

The design was insprired by the [yearbox Plugin](https://www.dokuwiki.org/plugin:yearbox) for [DokuWiki](https://www.dokuwiki.org/).
This plugin is based on [ytiurin/html5tooltipsjs](https://github.com/ytiurin/html5tooltipsjs) (Javascript / CSS Tooltips), [u01jmg3/ics-parser](https://github.com/u01jmg3/ics-parser) (ICS / ICAL Parser), [Idearia/php-logger](https://github.com/Idearia/php-logger) (Logging, Debugging), [Tingle](https://tingle.robinparisi.com/) (Modal dialogs written in pure JavaScript), [SleekDB](https://sleekdb.github.io/) (NoSQL Database), [UUID](https://www.php.net/manual/en/function.uniqid.php#94959) (RFC 4211 COMPLIANT Universally Unique IDentifiers).

## License

X11 License (aka. MIT License)

Copyright (c) 2023 Kai Thoene

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.