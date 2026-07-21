/*
 * Dependency-free searchable dropdown.
 * Upgrades every <select class="searchable"> (or any <select> inside a
 * .form-control) into a type-to-filter combobox. Works fully offline — no
 * external library, no internet — so it runs on any customer server.
 */
(function () {
  "use strict";

  function enhance(select) {
    if (select.dataset.enhanced === "1") return;
    if (select.multiple) return; // multi-selects keep native UI
    select.dataset.enhanced = "1";

    var wrap = document.createElement("div");
    wrap.className = "ss-wrap";

    var input = document.createElement("input");
    input.type = "text";
    input.className = "form-control ss-input";
    input.setAttribute("autocomplete", "off");
    input.placeholder = "Type to search…";

    var list = document.createElement("div");
    list.className = "ss-list";
    list.style.display = "none";

    select.style.display = "none";
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(input);
    wrap.appendChild(list);
    wrap.appendChild(select);

    function currentText() {
      var o = select.options[select.selectedIndex];
      return o ? o.textContent.trim() : "";
    }
    function setFromSelected() {
      input.value = currentText();
    }
    setFromSelected();

    function build(filter) {
      list.innerHTML = "";
      var f = (filter || "").toLowerCase();
      var count = 0;
      Array.prototype.forEach.call(select.options, function (opt) {
        var label = opt.textContent.trim();
        if (f && label.toLowerCase().indexOf(f) === -1) return;
        var item = document.createElement("div");
        item.className = "ss-item";
        if (opt.selected) item.classList.add("ss-selected");
        item.textContent = label || "—";
        item.addEventListener("mousedown", function (e) {
          e.preventDefault();
          select.value = opt.value;
          select.dispatchEvent(new Event("change", { bubbles: true }));
          setFromSelected();
          close();
        });
        list.appendChild(item);
        count++;
      });
      if (count === 0) {
        var none = document.createElement("div");
        none.className = "ss-item ss-none";
        none.textContent = "No matches";
        list.appendChild(none);
      }
    }

    function open() {
      build(input.value === currentText() ? "" : input.value);
      list.style.display = "block";
    }
    function close() {
      list.style.display = "none";
      if (!currentText()) input.value = "";
      else setFromSelected();
    }

    input.addEventListener("focus", function () {
      input.select();
      open();
    });
    input.addEventListener("input", function () {
      build(input.value);
      list.style.display = "block";
    });
    input.addEventListener("keydown", function (e) {
      var items = list.querySelectorAll(".ss-item:not(.ss-none)");
      var active = list.querySelector(".ss-active");
      var idx = Array.prototype.indexOf.call(items, active);
      if (e.key === "ArrowDown") {
        e.preventDefault();
        if (list.style.display === "none") open();
        var n = items[Math.min(idx + 1, items.length - 1)] || items[0];
        if (active) active.classList.remove("ss-active");
        if (n) n.classList.add("ss-active");
        if (n) n.scrollIntoView({ block: "nearest" });
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        var p = items[Math.max(idx - 1, 0)];
        if (active) active.classList.remove("ss-active");
        if (p) p.classList.add("ss-active");
        if (p) p.scrollIntoView({ block: "nearest" });
      } else if (e.key === "Enter") {
        if (list.style.display !== "none" && active) {
          e.preventDefault();
          active.dispatchEvent(new Event("mousedown"));
        }
      } else if (e.key === "Escape") {
        close();
      }
    });
    input.addEventListener("blur", function () {
      setTimeout(close, 150);
    });
  }

  function init(root) {
    var selects = (root || document).querySelectorAll(
      "select.searchable, form.stack select"
    );
    Array.prototype.forEach.call(selects, enhance);
  }

  if (document.readyState !== "loading") init();
  else document.addEventListener("DOMContentLoaded", function () { init(); });

  window.SearchableSelect = { init: init };
})();
