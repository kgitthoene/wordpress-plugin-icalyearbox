/**
 * Wordpress Plugin YetAnotherWPICALCalendar (Javascript Component)
 *
 * @license MIT https://en.wikipedia.org/wiki/MIT_License
 * @author  Kai Thoene <k.git.thoene@gmx.net>
 */
if (window.jQuery) {

  class JSHelper {
    static getKeyByValue(obj_p, val_p) {
      return Object.keys(obj_p).find(key => obj_p[key] === val_p);
    }  // getKeyByValue
  }  // class JSHelper

  globalThis[Symbol.for('yetanotherwpicalcalendar_storage')] = (function () {
    /*
     * Example:
    
        return AJAXHelper.post('/ajax', {
          cmd: 'ping',
          uuid: '(my-uuid)'
        }, ajax_callback);
    
        function ajax_callback() {
          var build_number, cmd, data, e;
          if (this.readyState === 4 && this.status === 200) {
            console.log("[ajax_callback] THIS=" + this + " READYSTATE=" + this.readyState + " STATUS=" + this.status + " RESPONSETEXT=" + this.responseText);
            try {
              data = JSON.parse(this.responseText);
            } catch (_error) {
              const now = new Date();
              const daytime = sprintf("%02d:%02d:%02d.%03d", now.getHours(), now.getMinutes(), now.getSeconds(), now.getMilliseconds());
              const msg = sprintf("%s [CLICK] JAVASCRIPT-EXCEPTION='%s'", daytime, _error);
              return console.error(msg);
            }
          } else {
            const now = new Date();
            const daytime = sprintf("%02d:%02d:%02d.%03d", now.getHours(), now.getMinutes(), now.getSeconds(), now.getMilliseconds());
            const msg = sprintf("%s [ajax_callback] NOT-READY! STATE=%f STATUS=%f", daytime, this.readyState, this.status);
            return console.error(msg);
          }
        };
     */
    class AJAXHelper {
      static debug = false;
      static retry_time = 10000;

      static daytime() {
        const now = new Date();
        return ("0" + now.getHours() + ":").slice(-3) + ("0" + now.getMinutes() + ":").slice(-3) + ("0" + now.getSeconds() + ":").slice(-3);
      }

      static daytime_ms() {
        const now = new Date();
        return ("0" + now.getHours() + ":").slice(-3) + ("0" + now.getMinutes() + ":").slice(-3) + ("0" + now.getSeconds() + ":").slice(-3) + ("00" + now.getMilliseconds()).slice(-3);
      }

      static datedaytime_fn() {
        const now = new Date();
        return ("000" + now.getFullYear()).slice(-4) + ("0" + (now.getMonth() + 1)).slice(-2) + ("0" + now.getDate()).slice(-2) + "." + ("0" + now.getHours()).slice(-2) + ("0" + now.getMinutes()).slice(-2) + ("0" + now.getSeconds()).slice(-2);
      }

      static get(retries_p) {
        if ((retries_p != parseInt(retries_p, 10)) || (retries_p < 0)) {
          retries_p = 10;
        }
        var xmlhttp = false;
        for (var i = 0; i < AJAXHelper.XMLHttpFactories.length; i++) {
          try {
            xmlhttp = AJAXHelper.XMLHttpFactories[i]();
          }
          catch (e) {
            continue;
          }
          break;
        }
        xmlhttp.retries = retries_p;
        return xmlhttp;
      }  // get

      static post(uri_p, hash_p, callback_p) {
        const async = true;
        var data = null;
        if ((typeof (hash_p) == 'object') && (hash_p !== undefined)) {
          data = JSON.stringify(hash_p);
        }
        var method = 'POST';
        this.async = async;
        if (AJAXHelper.debug) { console.log(this.daytime_ms() + " [AJAXHelper.post] METHOD='" + method + "' URI='" + uri_p + "' DATA='" + data + "'"); }
        var xhttp = AJAXHelper.get();
        if (!xhttp) return;
        xhttp.open(method, uri_p, async);
        //xhttp.setRequestHeader('Content-Type', 'application/json; encoding=UTF-8');
        xhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; encoding=UTF-8');
        if (AJAXHelper.debug) { console.log(this.daytime_ms() + " [AJAXHelper.post] 'Content-Type', 'application/x-www-form-urlencoded;'"); }
        if (async) { xhttp.timeout = 10000; }
        xhttp.ontimeout = function () { if (AJAXHelper.debug) { console.error(AJAXHelper.daytime_ms() + " [AJAXHelper.post::TIMEOUT](URI='" + uri_p + "', (" + (typeof callback_p) + "))"); } };
        xhttp.onreadystatechange = callback_p;
        xhttp.onerror = function () {
          if (AJAXHelper.debug || true) { console.error(AJAXHelper.daytime_ms() + " [AJAXHelper.post::ERROR](URI='" + uri_p + "', (" + (typeof callback_p) + ")) RETRIES-LEFT=" + this.retries); }
          if (this.retries > 0) {
            setTimeout(function () {
              if (AJAXHelper.debug) { console.log(AJAXHelper.daytime_ms() + " [AJAXHelper.post] RESEND RETRIES-LEFT=" + this.retries + " URI='" + uri_p + "' DATA='" + data + "' ASYNC=" + async.toString()); }
              --this.retries;
              this.open(method, uri_p, async);
              this.send(data);
            }.bind(this), AJAXHelper.retry_time);
          }
          else {
            if (AJAXHelper.debug) { console.error(AJAXHelper.daytime_ms() + " [AJAXHelper.post::ERROR](URI='" + uri_p + "', (" + (typeof callback_p) + ")) GIVE UP|"); }
          }
        };
        xhttp.async = async;
        xhttp.data = data;
        xhttp.send(xhttp.data);
        return xhttp;
      }  // post

      static stringify(obj_p) {
        var result;
        var cache = [];
        result = JSON.stringify(obj_p, function (key, value) {
          if (typeof value === 'object' && value !== null) {
            if (cache.indexOf(value) !== -1) {
              // Circular reference found, discard key
              return;
            }
            // Store value in our collection
            cache.push(value);
          }
          return value;
        });
        cache = null;  // Enable garbage collection
        return result;
      }  // stringify

      static s4() {
        return Math.floor((1 + Math.random()) * 0x10000)
          .toString(16)
          .substring(1);
      }  // s4

      static guid4() {
        return (AJAXHelper.s4() + AJAXHelper.s4() + '-' + AJAXHelper.s4() + '-' + AJAXHelper.s4() + '-' + AJAXHelper.s4() + '-' + AJAXHelper.s4() + AJAXHelper.s4() + AJAXHelper.s4());
      }  // guid4

      static uid128() {
        return (AJAXHelper.s4() + AJAXHelper.s4());
      } // guid4
    }  // class AJAXHelper
    AJAXHelper.XMLHttpFactories = [
      function () { return new XMLHttpRequest() },
      function () { return new ActiveXObject("Msxml2.XMLHTTP") },
      function () { return new ActiveXObject("Msxml3.XMLHTTP") },
      function () { return new ActiveXObject("Microsoft.XMLHTTP") }
    ];
    AJAXHelper.debug = true;
    //----------

    var defaults = {
      'debug': false, // false = no debug on console
      'is_enabled': true,
      'token': 'yetanotherwpicalcalendar'
    };

    var resize_timeout = null;

    function resize() {
      try {
        html5tooltips.refresh();
      }
      catch (e) { console.error("plugin:" + _g.defaults.token + ":EXCEPTION: " + e); }
    }  // resize

    function ajax_callback() {
      if (this.readyState === 4) {
        if (this.status === 200) {
          console.log("[ajax_callback] THIS=" + this + " READYSTATE=" + this.readyState + " STATUS=" + this.status + " RESPONSETEXT='" + this.responseText + "'");
          try {
            //data = JSON.parse(this.responseText);
          } catch (e) {
            return console.error("[ajax_callback] EXCEPTION='" + e + "'");
          }
        } else {
          return console.error("[ajax_callback] NOT-READY! STATE=" + this.readyState + " STATUS=" + this.status);
        }
      }
    } // ajax_callback

    /**
     * sends a request to the specified url from a form. this will change the window location.
     * @param {string} path the path to send the post request to
     * @param {object} params the parameters to add to the url
     * @param {string} [method=post] the method to use on the form
     */
    function _post(path, params, method = 'post') {
      // The rest of this code assumes you are not using a library.
      // It can be made less verbose if you use one.
      const form = document.createElement('form');
      form.method = method;
      form.action = path;
      for (const key in params) {
        if (params.hasOwnProperty(key)) {
          const hiddenField = document.createElement('input');
          hiddenField.type = 'hidden';
          hiddenField.name = key;
          hiddenField.value = params[key];
          form.appendChild(hiddenField);
        }
      }
      document.body.appendChild(form);
      form.submit();
    }

    function post_cb(jqXHR, status, data = {}, error_thrown = '') {
      console.log("[POST-CB] STATUS='" + status + "' DATA='" + JSON.stringify(data) + " ERROR-THROWN='" + error_thrown + "'");
    } // post_cb

    function post_cb_success(data, status, jqXHR) {
      post_cb(jqXHR, status, data);
    }  // post_cb_success

    function post_cb_error(jqXHR, status, error_thrown) {
      post_cb(jqXHR, status, {}, error_thrown);
    }  // post_cb_error

    function post(uri, object) {
      jQuery(function ($) {
        $.ajax({
          url: uri,
          data: object,
          method: 'POST',
          success: post_cb_success,
          error: post_cb_error,
        });
      });
    }  // post

    /*
    function vanilla_javascript_wp_post(uri, object, callback) {
      var request = new XMLHttpRequest();
      request.open('POST', uri, true);
      request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;');
      request.onreadystatechange = callback;
      request.onload = function () {
        if (this.status >= 200 && this.status < 400) {
          // If successful
          console.log(this.response);
        } else {
          // If fail
          console.log(this.response);
        }
      };
      request.onerror = function () {
        // Connection error
      };
      var url_encoded_data = '';
      let object_size = object.length;
      var nr = 1;
      for (var key in object) {
        url_encoded_data += ((nr > 1) ? '&' : '') + encodeURIComponent(key) + '=' + encodeURIComponent(object[key]);
        nr++;
      }
      request.send(url_encoded_data);
    }  // vanilla_javascript_wp_post
    */

    function annotate(id = '', day = '') {
      console.log(defaults.token + "::annotate ID='" + id + "' DAY='" + day + "'");
      let obj = { action: 'yetanotherwpicalcalendar_add_annotation', id: id, day: day, uml: 'äöüß&=' };
      post('/wp-admin/admin-ajax.php', obj);
      //vanilla_post('/wp-admin/admin-ajax.php', obj, ajax_callback);
    } // annotate

    return {
      // exported variables:
      defaults: defaults,
      resize_timeout: resize_timeout,
      // exported functions:
      resize: resize,
      annotate: annotate,
    };
  })();

  addEventListener("DOMContentLoaded", (event) => {
    (function ($) {
      var _g = globalThis[Symbol.for('yetanotherwpicalcalendar_storage')];
      if (_g.defaults.is_enabled) {

        yetanotherwpicalcalendar_annotate = _g.annotate;

        $(window).resize(function () {
          if (_g.resize_timeout != null) { clearTimeout(_g.resize_timeout); }
          _g.resize_timeout = setTimeout(_g.resize, 100);
        });

        _g.resize();
      }
    })(jQuery);
  });
} else {
  console.error("plugin:yetanotherwpicalcalendar:ERROR: jQuery is undefined!");
}
