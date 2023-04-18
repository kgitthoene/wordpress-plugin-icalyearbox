/**
 * Wordpress Plugin icalyearbox (Javascript Component)
 *
 * @license MIT https://en.wikipedia.org/wiki/MIT_License
 * @author  Kai Thoene <k.git.thoene@gmx.net>
 */
if (window.jQuery) {
  globalThis[Symbol.for('imapmarkers_storage')] = (function () {
    var defaults = {
      'debug': true, // false = no debug on console
      'token': 'icalyearbox'
    };

    return {
      // exported variables:
      defaults: defaults,
      // exported functions:
    };
  })();

  var _g = globalThis[Symbol.for('imapmarkers_storage')];

  addEventListener("DOMContentLoaded", (event) => {
    (function ($) {
      if (_g.defaults.debug) {
        console.log("plugin:" + _g.defaults.token + ": HELLO");
        $("table.icalyearbox-tag").each(function () {
          let nr_weekdays = $("div.icalyearbox-tag.wday").length;
          console.log("plugin:" + _g.defaults.token + ": NR OF WEEKDAYS="+nr_weekdays);
          var first_row_max_width = 0;
          $("div.icalyearbox-tag.frow").each(function () {
            first_row_max_width = ($(this).width() > first_row_max_width ? Math.ceil($(this).width()) : first_row_max_width);
          });
          var calculated_table_width = first_row_max_width + 2 + 19 * nr_weekdays;
          console.log("plugin:" + _g.defaults.token + ": 1st ROW MAX WIDTH="+first_row_max_width+" TABLE-WIDTH="+calculated_table_width);
          $(this).attr('width', calculated_table_width);
          //$(this).height(18);
        });
      }

    })(jQuery);
  });
} else {
  console.error("plugin:" + _g.defaults.token + ": jQuery is undefined!");
}
