# wordpress-plugin-yet-another-wp-ical-calendar

This software is a plugin for [Wordpress](https://wordpress.org/).
You can create calendars as year overview or as separate months.

First example: Official holydays in North Rhine-Westphalia, Germany, 2023. \
Month stripe style. Option: ```display="year"```

![Holydays, NRW, Germany, 2023](readme/2023-feiertage-nrw.png)

Sourcecode in Wordpress:
```
[yetanotherwpicalcalendar year="2023" months="all" ical="https://www.ferienwiki.de/exports/feiertage/2023/de/nordrhein-westfalen" type="event" display="year" description="mix" cache="1y"]
```

Second Example: Booking calendar with months. \
Display months in a grid. Option: ```display="month"``` \
Booking style, i.e. half days on first and last day of a period. Option: ```type="booking"```


![Holydays, NRW, Germany, 2023](readme/booking-cal-month-grid.png)

Sourcecode in Wordpress:
```
[yetanotherwpicalcalendar year="ical" months="now+ical" ical="PRIVATE-URL" type="booking" display="month" cache="1d"]
```

The ```"PRIVATE-URL"```, in this example, is realy not literally written as ```PRIVATE-URL```. In real it is a valid URL ```https://...```

## Usage and Syntax

YetAnotherWPICALCalendar is used as a [Shortcode](https://wordpress.com/support/wordpress-editor/blocks/shortcode-block/) in your Wordpress content:

**Full Syntax**: ```[yetanotherwpicalcalendar OPTIONS]ICAL-DATA[/yetanotherwpicalcalendar]```

```ICAL-DATA``` may be empty: ```[yetanotherwpicalcalendar OPTIONS][/yetanotherwpicalcalendar]```

**Short, handy, Syntax**: ```[yetanotherwpicalcalendar OPTIONS]```

### Full Syntax with ICAL Data

Place your ICAL data in-page.

Example (Source code in Wordpress):

```
[yetanotherwpicalcalendar year="ical" months="ical-1+1" ical="" type="event" display="year"]
BEGIN:VCALENDAR
VERSION:2.0
PRODID:by-hand
CALSCALE:GREGORIAN
METHOD:PUBLISH
BEGIN:VEVENT
DTSTAMP:20230420T190655Z
UID:66ec16fe-69ea-4c74-a263-906001bf8a01
DTSTART;VALUE=DATE:20230527
DESCRIPTION:Sample Day
END:VEVENT
END:VCALENDAR
[/yetanotherwpicalcalendar]
```

### Full Syntax versus Short and Multiple Calendars

*Be prepared!*
If you use more than one **yetanotherwpicalcalendar** on one page, don't mix up the short and the full syntax, because the first opening shortcode ```[yetanotherwpicalcalendar OPTIONS]``` eats up all page content until the next closing tag ```[/yetanotherwpicalcalendar]```.

Rules for multiple calendars on one page:
  * Use only the short form.
  * Xor use only the full form. You may let ```ICAL-DATA``` empty: ```[yetanotherwpicalcalendar OPTIONS][/yetanotherwpicalcalendar]```

### Options

**<u>display</u>**: (string) Allowed values: ```"year"``` and ```"month"```.
Default: ```"year"```

  * ```display="year"``` creates a calendar in month stripe style. See first example above.
  * ```display="month"``` creates a calendar in month grid style. See second example above.

Example: ```display="month"```

**<u>type</u>**: (string) Allowed values: ```"event"``` and ```"booking"```.
Default: ```"event"```

  * ```type="event"``` Creates for all days a full background image. See first example.
  * ```type="booking"``` Creates for the first and last day in a period a half background image. Inbetween a full background image. See second example.

Example: ```type="booking"```

**<u>ical</u>**: (string) Allowed values: Loadable, space separated, URIs leading to ICAL data.
Default: ```""``` (No external resources.)

Multiple ICAL resources are merged (additive).

Example: ```ical="https://www.ferienwiki.de/exports/feiertage/2023/de/nordrhein-westfalen"```


**<u>year</u>**: (string) Allowed values:
  * The keyword ```"now"``` for the current year.
  * The keyword ```"ical"``` for all years in the ICAL data.
  * The keyword ```"NUMBER"``` for the year represented by the number.
  * The keyword ```"now+NUMBER``` for all years from the current year to the current year plus ```NUMBER``` years (inclusive).
  * The keyword ```"now-NUMBER``` for all years from the current year minus ```NUMBER``` years (inclusive) to the current year.
  * The keyword ```"now-NUMBER1+NUMBER2``` for all years from the current year minus ```NUMBER1``` years (inclusive) to the current year plus ```NUMBER2``` years (inclusive).
  * The keyword ```"ical+NUMBER``` for all years in the ICAL data and ```NUMBER``` years after the ICAL data. (inclusive).
  * The keyword ```"ical-NUMBER``` for all years in the ICAL data and ```NUMBER``` years before the ICAL data. (inclusive).
  * The keyword ```"ical-NUMBER1+NUMBER2``` for the first year in the ICAL data minus ```NUMBER1``` years (inclusive) to the last ICAL year plus ```NUMBER2``` years (inclusive).
  * The keyword ```"NUMBER+NUMBER1``` for the year represented by ```NUMBER``` plus ```NUMBER1``` years (inclusive).
  * The keyword ```"NUMBER-NUMBER1``` for the year represented by ```NUMBER``` minus ```NUMBER1``` years (inclusive).
  * The keyword ```"NUMBER-NUMBER1+NUMBER2``` for all years from the year represented by ```NUMBER``` minus ```NUMBER1``` years (inclusive) to the year represented by ```NUMBER``` plus ```NUMBER2``` years (inclusive).
  * A period of years: ```"FROM--TO"```. ```FROM``` and ```TO``` may be a year (number) or the keyword ```now```.
  * A list of comma separated years. You may include the keyword ```now```, for the current year, in this list.

Default value: ```"now"```

List of years to display.

Examples:
  * ```year="2025"```
  * ```year="now+3"```
  * ```year="1980--now"```
  * ```year="2010,2020,now"```
  * ```year="2011,2013,2015,2017"```
  * ```year="ical-1"```
  * ```year="now-1+2"```

**<u>months</u>**: (string) Allowed values:
  * The keyword ```"all"``` for all months in the year.
  * The keyword ```"now"``` for the current month.
  * The keyword ```"ical"``` for all months in the ICAL data.
  * The keyword ```"NUMBER"``` for the month repesented by the number.
  * The keyword ```"now+NUMBER``` for all months from the current to the current month plus ```NUMBER``` months (inclusive).
  * The keyword ```"now-NUMBER``` for all months from the current minus ```NUMBER``` months (inclusive).
  * The keyword ```"now-NUMBER1+NUMBER2``` for all months from the current minus ```NUMBER1``` months (inclusive) to the current month plus ```NUMBER2``` months (inclusive).
  * The keyword ```"ical+NUMBER``` for all months from earliest ICAL month to the last ICAL month plus ```NUMBER``` months (inclusive).
  * The keyword ```"ical-NUMBER``` for all months from earliest ICAL month minus ```NUMBER``` months (inclusive) up to the latest ICAL month.
  * The keyword ```"ical-NUMBER1+NUMBER2``` for all months from earliest ICAL month minus ```NUMBER1``` months (inclusive) (inclusive) up to the last ICAL month plus ```NUMBER2``` months (inclusive).
  * The keyword ```"now+ical``` for all months from the current (inclusive) to the last month from the ICAL data (inclusive).
  * The keyword ```"now-ical``` for the first month from the ICAL data (inclusive) up to the the current month (inclusive).
  * A list of comma separated months. You may include the keyword ```now```, for the current month, in this list.

Default value: ```"all"```

List of month to display. \
Month numbers start from 1 (January) to 12 (Dezember).

If you add or subtract months from another month (plus / minus number syntax) the result is limited to the year.
I.e. with this you can't go beyond January or Dezember of the same year.

Examples:
  * ```month="now"```
  * ```month="now+3"```
  * ```month="1,2,3,4"```
  * ```month="12"```
  * ```month="ical"```
  * ```month="ical-1+1"```
  * ```month="now+ical"```

**<u>description</u>**: (string) Allowed values: ```"none"```, ```"description"```, ```"summary"``` and ```"mix"```.
Default: ```"none"```

  * ```description="none"``` Create no hover tooltips for events.
  * ```description="description"``` Take the events description for the tooltip. Technically is this the ```DESCRIPTION``` field from the ICAL entry.
  * ```description="summary"``` Take the events summary for the tooltip. Technically is this the ```SUMMARY``` field from the ICAL entry.
  * ```description="mix"``` Take the description for the tooltip. If the description is empty, then take the summary for the tooltip.

If there is a description for an event, the day number is decorated (dotted underline).
If you hover over these the description is shown.

Example: ```description="mix"```

**<u>cache</u>**: (string) Allowed values: A positive number of seconds. Alternatively a combination of a number and the abbrevation for hour: ```h```, month: ```m```, day: ```d``` or year: ```y```.
Default: ```"86400"``` (This is one day.) 

The cache value defines the age of the cached ICAL data. After this time the ICAL data is reloaded.

Example: ```cache="1y"```

**<u>align</u>**: (string) Allowed values: ```"center"```, ```"left"``` and ```"right"```.
Default: ```"center"```

  * ```align="center"``` Centers the output in the page.
  * ```align="left"``` Aligns the output to the left.
  * ```align="right"``` Aligns the output to the right.

Example: ```align="left"```

## Installation

Install the plugin using the **Plugin Manager** or download the ZIP-file below, which points to latest version of the plugin.
See also: [How to Install a WordPress Plugin - Step by Step for Beginners](https://www.wpbeginner.com/beginners-guide/step-by-step-guide-to-install-a-wordpress-plugin-for-beginners/)


### Manual Installation

Download: [https://github.com/kgitthoene/wordpress-plugin-yet-another-wp-ical-calendar/zipball/master/](https://github.com/kgitthoene/wordpress-plugin-yet-another-wp-ical-calendar/zipball/master/)

Extract the zip file and rename the extracted folder to ```yetanotherwpicalcalendar```.
Place this folder in ```WORDPRESS-SERVER-ROOT/wp-content/plugins/```

Activate the plugin via Wordpress Dashboard.

Please refer to [https://wordpress.com/support/plugins/install-a-plugin/](https://wordpress.com/support/plugins/install-a-plugin/) for additional info on how to install plugins in Wordpress.

## Used Software and Attribution

The design was insprired by the [yearbox Plugin](https://www.dokuwiki.org/plugin:yearbox) for [DokuWiki](https://www.dokuwiki.org/).
This plugin is based on [ytiurin/html5tooltipsjs](https://github.com/ytiurin/html5tooltipsjs) (Javascript / CSS Tooltips), [u01jmg3/ics-parser](https://github.com/u01jmg3/ics-parser) (ICS / ICAL Parser), [Idearia/php-logger](https://github.com/Idearia/php-logger) (Logging, Debugging).

## License

[MIT](https://github.com/kgitthoene/dokuwiki-plugin-imapmarkers/blob/master/LICENSE.md)
