/*
 * Cascading dropdowns, dependency-free.
 * A <select data-depends="client"> shows only the options whose data-partner
 * matches the value currently chosen in the field with id "id_client".
 * When the source changes, non-matching options are hidden and a hidden
 * selection is cleared. Works offline; degrades to showing all options if JS
 * is disabled.
 */
(function () {
  "use strict";

  function apply(dependent) {
    var sourceName = dependent.dataset.depends;
    var source = document.getElementById("id_" + sourceName);
    if (!source) return;

    function refresh() {
      var val = source.value;
      var current = dependent.value;
      var currentStillValid = !current;
      Array.prototype.forEach.call(dependent.options, function (opt) {
        if (!opt.value) {
          opt.hidden = false;
          return; // keep the blank "---------" option
        }
        var owner = opt.getAttribute("data-partner");
        var show = !val || owner === val;
        opt.hidden = !show;
        opt.disabled = !show;
        if (show && opt.value === current) currentStillValid = true;
      });
      if (!currentStillValid) dependent.value = "";
    }

    source.addEventListener("change", refresh);
    refresh();
  }

  function init(root) {
    var deps = (root || document).querySelectorAll("select[data-depends]");
    Array.prototype.forEach.call(deps, apply);
  }

  if (document.readyState !== "loading") init();
  else document.addEventListener("DOMContentLoaded", function () { init(); });

  window.DependentSelect = { init: init };
})();
