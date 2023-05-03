/**
 * Wordpress Plugin YetAnotherWPICALCalendar (Javascript Component)
 *
 * @license MIT https://en.wikipedia.org/wiki/MIT_License
 * @author  Kai Thoene <k.git.thoene@gmx.net>
 */
if (window.jQuery) {
  //
  //----------
  // Storage for Annotations.
  if (typeof globalThis[Symbol.for('yetanotherwpicalcalendar_annotation_storage')] === 'undefined') {
    globalThis[Symbol.for('yetanotherwpicalcalendar_annotation_storage')] = (function () {
      var modals = {};
      var data = {};
      var post_state = {};
      var abort = {};
      var save = {};
      var annotations = {};

      function format_day(d) {
        var year = d.substr(0, 4);
        var month = parseInt(d.substr(4, 2));
        var day = parseInt(d.substr(6, 2));
        return day + '.' + month + '.' + year;
      }  // format_day

      return {
        modals: modals,
        data: data,
        post_state: post_state,
        abort: abort,
        save: save,
        annotations: annotations,
        //
        format_day: format_day,
      };
    })();
  }
  //
  //----------
  // Storage for Calendar Stuff.
  if (typeof globalThis[Symbol.for('yetanotherwpicalcalendar_storage')] === 'undefined') {
    globalThis[Symbol.for('yetanotherwpicalcalendar_storage')] = (function () {
      var defaults = {
        'debug': false, // false = no debug on console
        'is_enabled': true,
        'token': 'yetanotherwpicalcalendar'
      };
      var resize_timeout = null;
      var AJAX = {
        RUNNING: 0,
        FAIL: 1,
        DONE: 2,
        FINAL: 4,
      };

      function is_empty(s = '') {
        return (!s || s.length === 0);
      } // is_empty

      function is_null(v = null) {
        return (v == null);
      } // is_null

      function resize() {
        try {
          html5tooltips.refresh();
        }
        catch (e) { console.error("plugin:" + _g.defaults.token + ":EXCEPTION: " + e); }
      }  // resize

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

      function post(uri, object, cb_success = null, cb_error = null) {
        jQuery(function ($) {
          return $.ajax({
            url: uri,
            data: object,
            method: 'POST',
            success: (is_null(cb_success) ? post_cb_success : cb_success),
            error: (is_null(cb_error) ? post_cb_error : cb_error),
          });
        });
      }  // post

      //
      //----------
      // Load Annotations from Database:
      function load_annotations(id_selector = "") {
        var _ga = globalThis[Symbol.for('yetanotherwpicalcalendar_annotation_storage')];
        jQuery(function ($) {
          $(id_selector + ".yetanotherwpicalcalendar-annotation").each(function (idx) {
            let id = $(this).attr('id');
            //console.log("Try to load annotation from ID='" + id + "'");
            if (is_empty(id)) {
              $(this).html('<div style="border-left:4px solid red;">&nbsp;No ID for annotations given!</div>');
            } else {
              let obj = { action: 'yetanotherwpicalcalendar_get_annotations', id: id };
              $.ajax({
                url: '/wp-admin/admin-ajax.php',
                data: obj,
                method: 'POST'
              })
                .done(function (data, status, jqXHR) {
                  //console.log("[" + idx + ":LOAD DONE] ID='" + data.id + "' DATA='"+JSON.stringify(data)+"'");
                  var b_is_ok = false;
                  if (status == 'success') {
                    let rc = data.status;
                    b_is_ok = (rc == 'OK');
                  }
                  if (b_is_ok) {
                    let id = data.id;
                    let a_an = data.annotations;
                    $(this).html(data.doc);
                    _ga.annotations[id] = a_an;
                  }
                }.bind(this))
                .fail(function (jqXHR, status, error_thrown) {
                  let id = $(this).attr('id');
                  $(this).html('<div style="unicode-bidi:embed; font-family:monospace; font-size:12px; font-weight:normal; color:black; background-color:#FFAA4D; border-left:12px solid red; padding:3px 6px 3px 6px;">'
                    + 'Plugin YetAnotherWPICALCalendar::ERROR -- Cannot get annotations! STATUS="' + status + '" ERROR="' + error_thrown + '"'
                    + '<br />[yetanotherwpicalcalendar-annotation id="' + id + '"]'
                    + '"</div>');
                }.bind(this));
            }
          });
        });
      }  // load_annotations

      function annotate(id = '', day = '') {
        //console.log(defaults.token + "::annotate ID='" + id + "' DAY='" + day + "'");
        jQuery(function ($) {
          var _ga = globalThis[Symbol.for('yetanotherwpicalcalendar_annotation_storage')];
          //console.log("GA ->:");
          //console.log(_ga);
          modal = _ga.modals['annotation'];
          // Find existing annotation:
          note = '';
          if (id in _ga.annotations) {
            for (var i = 0; i < _ga.annotations[id].length; i++) {
              anno = _ga.annotations[id][i];
              if (anno.day == day) {
                note = anno.note;
                break;
              }
            }
          }
          //
          _ga.data['annotation'] = { id: id, day: day, note: note };
          _ga.post_state['annotation'] = null;
          modal.open();
          setTimeout(function () {
            //console.log("FOCUS");
            $("#annotation-note").focus();
          }, 100);
        });
      } // annotate

      function ajax_write_annotation() {
        var _ga = globalThis[Symbol.for('yetanotherwpicalcalendar_annotation_storage')];
        jQuery(function ($) {
          let data = _ga.data['annotation'];
          let obj = { action: 'yetanotherwpicalcalendar_add_annotation', id: data.id, day: data.day, note: data.note };
          //
          //----------
          // Watch for result:
          _ga.post_state['annotation'] = AJAX.RUNNING;
          let resultInterval = setInterval(function () {
            if ((_ga.post_state['annotation'] != null) && (_ga.post_state['annotation'] != AJAX.RUNNING)) {
              //console.log("POST-STATE=" + _ga.post_state['annotation'] + " ID=" + this.id);
              switch (_ga.post_state['annotation']) {
                case 1:
                  clearInterval(resultInterval);
                  $("#" + this.id + "-cal-msg").html('<div style="unicode-bidi:embed; font-family:monospace; font-size:12px; font-weight:normal; color:black; background-color:#FFAA4D; border-left:12px solid red; padding:3px 6px 3px 6px;">'
                    + 'Plugin YetAnotherWPICALCalendar::ERROR -- Cannot write annotation! DAY="' + this.day + '"'
                    + '<br />[yetanotherwpicalcalendar-annotation id="' + this.id + '"]'
                    + '"</div>');
                  $("#" + this.id + "-cal-msg").css('display', 'block');
                  break;
                case 2:
                  clearInterval(resultInterval);
                  load_annotations("#" + this.id);
                  break;
              }
            }
          }.bind(data), 100);
          //
          $.ajax({
            url: '/wp-admin/admin-ajax.php',
            data: obj,
            method: 'POST'
          })
            .done(function (data, status, jqXHR) {
              //console.log("[ajax_write_annotation:DONE] ID='" + this.id + "' DATA='" + JSON.stringify(data) + "'");
              var b_is_ok = false;
              if (status == 'success') {
                let rc = data['status'];
                b_is_ok = (rc == 'OK');
              }
              _ga.post_state['annotation'] = AJAX.DONE;
            }.bind(data))
            .fail(function (jqXHR, status, error_thrown) {
              //$('#annotation-msg').text('[FAIL] Press Abbruch');
              //console.log("[ajax_write_annotation:FAIL] ID='" + this.id + "'");
              _ga.post_state['annotation'] = AJAX.FAIL;
            }.bind(data));
        });
      }  // ajax_write_annotation

      function delete_annotation(id, day) {
        var _ga = globalThis[Symbol.for('yetanotherwpicalcalendar_annotation_storage')];
        //console.log("[delete_annotation:ENTRY] ID='" + id + "' DAY='" + day + "'");
        _ga.data['annotation'].id = id;
        _ga.data['annotation'].day = day;
        _ga.data['annotation'].note = '';
        ajax_write_annotation();
      }  // delete_annotation

      return {
        // exported variables:
        defaults: defaults,
        resize_timeout: resize_timeout,
        AJAX: AJAX,
        // exported functions:
        is_empty: is_empty,
        is_null: is_null,
        resize: resize,
        post_cb: post_cb,
        post: post,
        load_annotations: load_annotations,
        annotate: annotate,
        ajax_write_annotation: ajax_write_annotation,
        delete_annotation: delete_annotation,
      };
    })();
  }

  addEventListener("DOMContentLoaded", (event) => {
    (function ($) {
      var _g = globalThis[Symbol.for('yetanotherwpicalcalendar_storage')];
      var _ga = globalThis[Symbol.for('yetanotherwpicalcalendar_annotation_storage')];
      if (_g.defaults.is_enabled) {

        yetanotherwpicalcalendar_annotate = _g.annotate;
        yetanotherwpicalcalendar_del_annotation = _g.delete_annotation

        $(window).resize(function () {
          if (_g.resize_timeout != null) { clearTimeout(_g.resize_timeout); }
          _g.resize_timeout = setTimeout(_g.resize, 100);
        });

        _g.resize();
        _g.load_annotations();

        //----------
        // Create modal dialog:
        _ga.modals['annotation'] = new tingle.modal({  // Tingle. SEE:https://tingle.robinparisi.com/
          footer: true,
          stickyFooter: false,
          closeMethods: ['overlay', 'button', 'escape'],
          closeLabel: "Close",
          cssClass: [],
          beforeOpen: function () {
            _ga.abort['annotation'] = false;
            _ga.save['annotation'] = false;
            $('#annotation-day').text(_ga.format_day(_ga.data['annotation'].day));
            $('#annotation-note').val(_ga.data['annotation'].note);
            $('#annotation-msg').css('display', 'none');
          },
          onOpen: function () {
          },
          onClose: function () {
            //console.log('modal closed');
            if (_ga.post_state['annotation'] == null) {
              _ga.post_state['annotation'] = _g.AJAX.FINAL;
            }
          },
          beforeClose: function () {
            if (_ga.abort['annotation']) {
              _ga.abort['annotation'] = false;
            }
            if (_ga.save['annotation']) {
              _ga.save['annotation'] = false;
              _ga.data['annotation'].note = $('#annotation-note').val();
              _g.ajax_write_annotation();
              // e.g. save content before closing the modal
            }
            return true;
          }
        });
        _ga.data['annotation'] = {};
        _ga.post_state['annotation'] = null;
        // set content
        _ga.modals['annotation'].setContent('<div class="yetanotherwpicalcalendar-annotation-modal">'
          + '<div><strong style="border-bottom:1px dotted gray;">Notiz bearbeiten</strong></div>'
          + '<form action="#">'
          + '<div id="annotation-calendar-id" style="display:none;"></div>'
          + 'Vom <span id="annotation-day"></span>:<br />'
          + '<div class="grow-wrap"><textarea id="annotation-note" onInput="this.parentNode.dataset.replicatedValue = this.value"></textarea></div>'
          + '</form>'
          + '<div id="annotation-msg" style="display:none;><div class="loader"></div></div>'
          + '</div>');
        _ga.modals['annotation'].addFooterBtn('Speichern', 'tingle-btn tingle-btn--primary tingle-btn--pull-right', function () {
          _ga = globalThis[Symbol.for('yetanotherwpicalcalendar_annotation_storage')];
          $('#annotation-msg').css('display', 'block');
          // here goes some logic
          _ga.save['annotation'] = true;
          _ga.modals['annotation'].close();
        });
        _ga.modals['annotation'].addFooterBtn('Abbruch', 'tingle-btn tingle-btn--pull-right', function () {
          _ga = globalThis[Symbol.for('yetanotherwpicalcalendar_annotation_storage')];
          // here goes some logic
          _ga.abort['annotation'] = true;
          _ga.modals['annotation'].close();
        });

      }
    })(jQuery);
  });
} else {
  console.error("plugin:yetanotherwpicalcalendar:ERROR: jQuery is undefined!");
}
