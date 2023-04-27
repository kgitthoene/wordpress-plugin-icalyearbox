# Icalyearbox - Create calandars with ICS/ICAL data
Contributors: kgitthoene
Author URI: https://github.com/kgitthoene/
Plugin URI: https://github.com/kgitthoene/wordpress-plugin-icalyearbox/
Tags: ics ical yearbox calendar year month booking
Donate link: 
Requires at least: 4.9.0
Tested up to: 6.2
Stable tag: 6.2
Requires PHP: 7.0
License: MIT
License URI: https://github.com/kgitthoene/wordpress-plugin-icalyearbox/blob/master/LICENSE

You can create calendars, filled with in-page or external ICS/ICAL data, as year overview or as separate months.

## Description

This software is a plugin for [WordPress](https://wordpress.org/).
You can create calendars as year overview or as separate months.
These calendars are filled with in-page or external ICS/ICAL data.

Icalyearbox is used as a [Shortcode](https://wordpress.com/support/wordpress-editor/blocks/shortcode-block/) in your Wordpress content:

**Full Syntax**: `[icalyearbox OPTIONS]ICAL-DATA[/icalyearbox]`

`ICAL-DATA` may be empty: `[icalyearbox OPTIONS][/icalyearbox]`

**Short, handy, Syntax**: `[icalyearbox OPTIONS]`

Full documentation, all options, full examples: [https://github.com/kgitthoene/wordpress-plugin-icalyearbox](https://github.com/kgitthoene/wordpress-plugin-icalyearbox)

### Examples

First example: Official holydays in North Rhine-Westphalia, Germany, 2023. \
Month stripe style. Option: `display="year"`

![First example with month stripes.](https://raw.githubusercontent.com/kgitthoene/wordpress-plugin-icalyearbox/master/readme/2023-feiertage-nrw.png)

Second Example: Booking calendar with months. \
Display months in a grid. Option: `display="month"` \
Booking style, i.e. half days on first and last day of a period. Option: `type="booking"`

![Second example with separate months.](https://raw.githubusercontent.com/kgitthoene/wordpress-plugin-icalyearbox/master/readme/booking-cal-month-grid.png)

### Links

* [Documentation](https://github.com/kgitthoene/wordpress-plugin-icalyearbox/)
* [Support forum/Report bugs](https://github.com/kgitthoene/wordpress-plugin-icalyearbox/issues)

## Installation

1. Extract the zipped file and upload the folder `icalyearbox` to to `/wp-content/plugins/` directory.
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

1. ![First example with month stripes.](https://raw.githubusercontent.com/kgitthoene/wordpress-plugin-icalyearbox/master/readme/2023-feiertage-nrw.png)
2. ![Second example with separate months.](https://raw.githubusercontent.com/kgitthoene/wordpress-plugin-icalyearbox/master/readme/booking-cal-month-grid.png)

## Changelog

### 1.0
* Initial release.
