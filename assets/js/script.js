/**
 * Wordpress Plugin icalyearbox (Javascript Component)
 *
 * @license MIT https://en.wikipedia.org/wiki/MIT_License
 * @author  Kai Thoene <k.git.thoene@gmx.net>
 */
if (window.jQuery) {
  globalThis[Symbol.for('icalyearbox_storage')] = (function () {
    var defaults = {
      'debug': false, // false = no debug on console
      'token': 'icalyearbox'
    };
    return {
      // exported variables:
      defaults: defaults,
      // exported functions:
    };
  })();

  addEventListener("DOMContentLoaded", (event) => {
    (function ($) {
      var _g = globalThis[Symbol.for('icalyearbox_storage')];
      try {
        if (_g.defaults.debug) { console.log("plugin:" + _g.defaults.token + ": HELLO"); }
        $("table.icalyearbox-tag").each(function () {
          let nr_weekdays = $(this).find("div.icalyearbox-tag.wday").length;
          if (_g.defaults.debug) { console.log("plugin:" + _g.defaults.token + ": NR OF WEEKDAYS=" + nr_weekdays); }
          var first_row_max_width = 0;
          $(this).find("div.icalyearbox-tag.frow").each(function () {
            let span_width = $(this).find("span").width();
            first_row_max_width = (span_width > first_row_max_width ? Math.ceil(span_width) : first_row_max_width);
          });
          var calculated_table_width = 12 + first_row_max_width + 2 + 19 * nr_weekdays;
          if (_g.defaults.debug) { console.log("plugin:" + _g.defaults.token + ": 1st ROW MAX WIDTH=" + first_row_max_width + " TABLE-WIDTH=" + calculated_table_width); }
          $(this).attr('width', calculated_table_width);
          if ($(this).find("div.icalyearbox-tag.square")) {
            let cell_height = $(this).find("div.icalyearbox-tag.square").width();
            if (_g.defaults.debug) { console.log("plugin:" + _g.defaults.token + ": CELL HEIGHT=" + cell_height); }
            $(this).find(".cellc.icalyearbox-tag").css("height", cell_height + "px")
          }
        });
      }
      catch (e) { console.error("plugin:" + _g.defaults.token + ":EXCEPTION: " + e); }
    })(jQuery);
  });
} else {
  console.error("plugin:icalyearbox:ERROR: jQuery is undefined!");
}