/* Legacy inventory UI helpers (no external dependencies) */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    /* ---- quick table filter ---- */
    var qs = document.querySelectorAll('[data-filter-target]');
    for (var i = 0; i < qs.length; i++) {
      (function (input) {
        input.addEventListener('keyup', function () {
          var table = document.querySelector(input.getAttribute('data-filter-target'));
          if (!table) return;
          var term = input.value.toLowerCase();
          var rows = table.tBodies[0] ? table.tBodies[0].rows : [];
          for (var r = 0; r < rows.length; r++) {
            rows[r].style.display =
              rows[r].textContent.toLowerCase().indexOf(term) > -1 ? '' : 'none';
          }
        });
      })(qs[i]);
    }

    /* ---- confirm links ---- */
    var cs = document.querySelectorAll('[data-confirm]');
    for (var c = 0; c < cs.length; c++) {
      cs[c].addEventListener('click', function (ev) {
        if (!confirm(this.getAttribute('data-confirm'))) ev.preventDefault();
      });
    }

    /* ---- dynamic item rows ---- */
/* Legacy inventory UI helpers (no external dependencies) */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    /* ---- quick table filter ---- */
    var qs = document.querySelectorAll('[data-filter-target]');
    for (var i = 0; i < qs.length; i++) {
      (function (input) {
        input.addEventListener('keyup', function () {
          var table = document.querySelector(input.getAttribute('data-filter-target'));
          if (!table) return;
          var term = input.value.toLowerCase();
          var rows = table.tBodies[0] ? table.tBodies[0].rows : [];
          for (var r = 0; r < rows.length; r++) {
            rows[r].style.display =
              rows[r].textContent.toLowerCase().indexOf(term) > -1 ? '' : 'none';
          }
        });
      })(qs[i]);
    }

    /* ---- confirm links ---- */
    var cs = document.querySelectorAll('[data-confirm]');
    for (var c = 0; c < cs.length; c++) {
      cs[c].addEventListener('click', function (ev) {
        if (!confirm(this.getAttribute('data-confirm'))) ev.preventDefault();
      });
    }
  });
})();