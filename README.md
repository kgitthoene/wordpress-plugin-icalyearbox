# wordpress-plugin-icalyearbox

This software is a plugin for [Wordpress](https://wordpress.org/).
You can make calendars as year overview or as separate months.

Example: Official holydays in North Rhine-Westphalia, Germany, 2023.

![Holydays, NRW, Germany, 2023](https://raw.githubusercontent.com/kgitthoene/wordpress-plugin-icalyearbox/master/readme/2023-feiertage-nrw.png?token=GHSAT0AAAAAACBWZNHKLWOC2UWXKLYFGZDIZCCXEVQ)

Sourcecode in Wordpress:
```
[icalyearbox year="2023" months="all" ical="https://www.ferienwiki.de/exports/feiertage/2023/de/nordrhein-westfalen" type="event" display="year" description="mix" cache="1y"]
```

## Usage and Syntax

Simply add a [Shortcode](https://wordpress.com/support/wordpress-editor/blocks/shortcode-block/) to your Wordpress content.

The calendar shortcode starts with ```[icalyearbox ``` and ends with ```]```.
Enclosed in this are the options to controll your calendar.

Full Syntax: ```[icalyearbox OPTIONS]```

### Options

**display**: (string) Allowed values: ```"year"``` and ```"month"```.
Default: ```"year"```

  * ```display="year"``` creates a calendar in month stripe style. See example above.
  * ```display="month"``` creates a calendar in month grid style. See second example above.

Example: ```display="month"```

**type**: (string) Allowed values: ```"event"``` and ```"booking"```.
Default: ```"event"```

  * ```type="event"``` Creates for all days a full background image. See first example.
  * ```type="booking"``` Creates for the first and last day in a period a half background image. Inbetween a full background image. See second example.

Example: ```type="booking"```

**description**: (string) Allowed values: ```"none"```, ```"description"```, ```"summary"``` and ```"mix"```.
Default: ```"none"```

  * ```description="none"``` Create no hover tooltips for events.
  * ```description="description"``` Take the events description for the tooltip. Technically is this the ```DESCRIPTION``` field from the ICAL entry.
  * ```description="summary"``` Take the events summary for the tooltip. Technically is this the ```SUMMARY``` field from the ICAL entry.
  * ```description="mix"``` Take the description for the tooltip. If the description is empty, then take the summary for the tooltip.

Example: ```description="mix"```

**cache**: (string) Allowed values: A positive number of seconds. Alternatively a combination of a number and the abbrevation for hour: ```h```, month: ```m```, day: ```d``` or year: ```y```.
Default: ```"86400"``` (This is one day.) 

The cache value defines the age of the cached ICAL data. After this time the ICAL data is reloaded.

Example: ```cache="1y"```

**ical**: (string) Allowed values: Loadable, space separated, URIs leading to ICAL data.
Default: ```""``` (No external resources.)

Example: ```ical="https://www.ferienwiki.de/exports/feiertage/2023/de/nordrhein-westfalen"```

**align**: (string) Allowed values: ```"center"```, ```"left"``` and ```"right"```.
Default: ```"center"```

  * ```align="center"``` Centers the output in the page.
  * ```align="left"``` Aligns the output to the left.
  * ```align="right"``` Aligns the output to the right.

Example: ```align="left"```

**year**: (string) Allowed values:
  * The keyword ```"now"``` for the current year.
  * The keyword ```"ical"``` for all years in the ICAL data.
  * The keyword ```"now+NUMBER``` for all years from the current year to the current year plus ```NUMBER``` years (inclusive).
  * The keyword ```"now-NUMBER``` for all years from the current year minus ```NUMBER``` (inclusive) to the current year.
  * The keyword ```"now-NUMBER1+NUMBER2``` for all years from the current year minus ```NUMBER1``` years (inclusive) to the current year plus ```NUMBER2``` years (inclusive).
  * The keyword ```"ical+NUMBER``` for all years in the ICAL data and ```NUMBER``` years after the ICAL data. (inclusive).
  * The keyword ```"ical-NUMBER``` for all years in the ICAL data and ```NUMBER``` years before the ICAL data. (inclusive).
  * The keyword ```"ical-NUMBER1+NUMBER2``` for the first year in the ICAL data minus ```NUMBER1``` years (inclusive) to the last ICAL year plus ```NUMBER2``` years (inclusive).
  * A period of years: ```"FROM--TO"```. ```FROM``` and ```TO``` may be a year (number) or the keyword ```now```.
  * A list of comma separated list of years or a single year number. You may include the keyword ```now```, for the current year, in this list.

List of displayed years.

Default value: ```"now"```

Examples:
  * ```year="now+3"```
  * ```year="1980--now"```
  * ```year="2010,2020,now"```
  * ```year="2011,2013,2015,2017"```
  * ```year="ical-1"```
  * ```year="now-1+2"```

**months**: (string) Allowed values:
  * The keyword ```"all"``` for all months in the year.
  * The keyword ```"now"``` for the current month.
  * The keyword ```"ical"``` for all months in the ICAL data.
  * The keyword ```"now+NUMBER``` for all months from the current to the current month plus ```NUMBER``` months (inclusive).
  * The keyword ```"now-NUMBER``` for all months from the current minus ```NUMBER``` months (inclusive).
  * The keyword ```"now-NUMBER1+NUMBER2``` for all months from the current minus ```NUMBER1``` months (inclusive) to the current month plus ```NUMBER2``` months (inclusive).
  * The keyword ```"now+ical``` for all months from the current to the last month from the ICAL data (inclusive).
  * The keyword ```"now-ical``` for the first month from the ICAL data (inclusive) up to the the current month (inclusive).
  * A list of comma separated months or a single month number. You may include the keyword ```now```, for the current month, in this list.

List of displayed months.

Default value: ```"all"```

Examples:
  * ```month="now"```
  * ```month="now+3"```
  * ```month="1,2,3,4"```
  * ```month="12"```
  * ```month="ical"```
  * ```month="now+ical"```

### Create a Calendar in Month Stripe Style


There are these configuration options:

**"marker"**: (string) ```"internal"``` -- Use the plugins-internal marker. This is the default, so no need to write this, if you are happy with the internal marker.

**"marker"**: (string) ```"LINK"``` -- Internal or external link to an image.

Example: ```"marker": "imapmarkers:marker.002.png"```

Example: ```"marker": "https://upload.wikimedia.org/wikipedia/commons/f/f2/678111-map-marker-512.png"```

**"marker-width"**: (positive number) -- Set the markers width to this value.

Example: ```"marker-width": 20```

**"marker-height"**: (positive number) -- Set the markers height to this value.

Example: ```"marker-height": 32```

**"marker-color"**: (string) ```"HTML-COLOR"``` -- Set the internal markers color to this value.

Example: ```"marker-color": "#FDEB00"```

**"clicked-reference-css"**: (JSON) ```JSON-OBJECT-WITH-CSS-DEFINITIONS```  -- The CSS definitions are applied to a reference, if you click the reference.

Example: ```"clicked-reference-css": { "font-weight": "bold", "color": "red" }```

**"area-fillColor"**: (string) "HTML-COLOR-HEXADECIMAL" -- Set the color of the hoverd area. Don't use a `#' before the hex-code.

Example: ```"area-fillColor": "ff0000"```

**"area-fillOpacity"**: (float) ```OPACITY-PERCENT``` -- Set the opacity of the hovered area.
The value must between 0 and 1.
```1``` is full opacity.
```0``` is no opacity, i.e. the hovered area is not shown.

Example: ```"area-fillOpacity": 0.3```

Complete Configuration-Example (Place this **inside** your map definition!):

```
{{cfg>}}
  {
    "marker-color": "red",
    "clicked-reference-css": { "font-weight": "bold", "color": "red" },
    "area-fillColor": "ff0000",
    "area-fillOpacity": 0.2
  }
{{<cfg}}
```

### Create References

References are spans, i.e. a piece of inline text.
And if you click a reference, the marker is shown in the middle of the **area** with the same **identifier**.

References can be places everywhere in the page.

**Reference**: ```{{imapmloc>IDENTIFIER|TEXT}}```

```IDENTIFIER``` refers to the **areas** identifier.
You may define multiple references for one identifier.

```TEXT``` is the text shown in the page.

Example: ```{{imapmloc>CON1|Asia}}``` -- Refers to the area with ```CON1``` as identifier.

## Installation

Install the plugin using the [Plugin Manager](https://www.dokuwiki.org/plugin:extension) or the download URL above, which points to latest version of the plugin.


### Manual Installation

Download: [https://github.com/kgitthoene/dokuwiki-plugin-imapmarkers/zipball/master/](https://github.com/kgitthoene/dokuwiki-plugin-imapmarkers/zipball/master/)

Extract the zip file and rename the extracted folder to ```imapmarkers```.
Place this folder in ```DOKUWIKI-SERVER-ROOT/lib/plugins/```

Please refer to [http://www.dokuwiki.org/extensions](http://www.dokuwiki.org/extensions) for additional info
on how to install extensions in DokuWiki.

## Used Software and Attribution

The design was insprired by the [yearbox Plugin](https://www.dokuwiki.org/plugin:yearbox) for [DokuWiki](https://www.dokuwiki.org/).
This plugin is based on [ytiurin/html5tooltipsjs](https://github.com/ytiurin/html5tooltipsjs) (Javascript / CSS Tooltips) and [u01jmg3/ics-parser](https://github.com/u01jmg3/ics-parser) (ICS / ICAL Parser).

## License

[MIT](https://github.com/kgitthoene/dokuwiki-plugin-imapmarkers/blob/master/LICENSE.md)
