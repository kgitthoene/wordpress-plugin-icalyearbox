/**
 * Wordpress Plugin YetAnotherWPICALCalendar (Javascript Component)
 *
 * @license MIT https://en.wikipedia.org/wiki/MIT_License
 * @author  Kai Thoene <k.git.thoene@gmx.net>
 */
if (window.jQuery) {
  globalThis[Symbol.for('yetanotherwpicalcalendar_storage')] = (function () {
    var defaults = {
      'debug': false, // false = no debug on console
      'is_enabled': true,
      'token': 'yetanotherwpicalcalendar'
    };
    var resize_timeout = null;
    return {
      // exported variables:
      defaults: defaults,
      resize_timeout: resize_timeout
      // exported functions:
    };
  })();

  addEventListener("DOMContentLoaded", (event) => {
    (function ($) {
      var _g = globalThis[Symbol.for('yetanotherwpicalcalendar_storage')];
      if (_g.defaults.is_enabled) {
        function resize() {
          try {
            html5tooltips.refresh();
          }
          catch (e) { console.error("plugin:" + _g.defaults.token + ":EXCEPTION: " + e); }
        }  // resize

        $(window).resize(function () {
          if (_g.resize_timeout != null) { clearTimeout(_g.resize_timeout); }
          _g.resize_timeout = setTimeout(resize, 100);
        });

        resize();
      }
    })(jQuery);
  });
} else {
  console.error("plugin:yetanotherwpicalcalendar:ERROR: jQuery is undefined!");
}
