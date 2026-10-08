"use strict";
/*
 * Progressive enhancement only. Every page is complete without this file:
 * it adds theme switching, site search (Persian and Arabic aware), card filtering,
 * copy buttons, a highlighted table of contents and a language hint on the
 * language chooser page.
 */
(function () {
  var root = document.documentElement;
  var i18n = {};
  try { i18n = JSON.parse(root.getAttribute("data-i18n") || "{}"); } catch (e) { i18n = {}; }
  function t(key, fallback) { return i18n[key] || fallback; }
  function fmt(text, a, b) { return String(text).replace("%d", a).replace("%d", b); }

  function safeGet(key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } }
  function safeSet(key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* storage may be blocked */ } }

  /* ---------- Theme ---------- */
  var themeButton = document.querySelector("[data-theme-toggle]");
  function effectiveTheme() {
    var set = root.getAttribute("data-theme");
    if (set === "light" || set === "dark") return set;
    return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  }
  function paintThemeButton() {
    if (!themeButton) return;
    var next = effectiveTheme() === "dark" ? "light" : "dark";
    themeButton.textContent = next === "dark" ? t("dark", "Dark mode") : t("light", "Light mode");
    themeButton.setAttribute("aria-label", next === "dark" ? t("to_dark", "Switch to dark theme") : t("to_light", "Switch to light theme"));
  }
  if (themeButton) {
    themeButton.hidden = false;
    paintThemeButton();
    themeButton.addEventListener("click", function () {
      var next = effectiveTheme() === "dark" ? "light" : "dark";
      root.setAttribute("data-theme", next);
      safeSet("rtly-docs-theme", next);
      paintThemeButton();
    });
    if (window.matchMedia) {
      var mq = window.matchMedia("(prefers-color-scheme: dark)");
      if (mq.addEventListener) mq.addEventListener("change", paintThemeButton);
    }
  }

  /* ---------- Sidebar: collapse on small screens ---------- */
  var sideDetails = document.querySelector(".sidebar details");
  if (sideDetails && window.matchMedia && window.matchMedia("(max-width: 900px)").matches) {
    sideDetails.removeAttribute("open");
  }

  /* ---------- Search text normalisation (Persian/Arabic aware) ---------- */
  function normalize(s) {
    s = String(s).toLowerCase();
    try { s = s.normalize("NFKC"); } catch (e) { /* old engines */ }
    return s
      .replace(/[ً-ٰٟۖ-ۭـ]/g, "")   /* harakat, dagger alef, Quranic marks, tatweel */
      .replace(/[ىيیئ]/g, "ی")           /* alef maksura / Arabic ya / hamza-ya -> Persian ya */
      .replace(/[كک]/g, "ک")                       /* Arabic kaf -> Persian kaf */
      .replace(/[ۀةہ]/g, "ه")                 /* heh variants -> heh */
      .replace(/[أإٱآ]/g, "ا")           /* hamza/madda alef variants -> alef */
      .replace(/ؤ/g, "و")                               /* hamza on waw -> waw */
      .replace(/‌|‍|‏|‎/g, " ")                /* ZWNJ / ZWJ / marks -> space */
      .replace(/[۰-۹]/g, function (c) { return String(c.charCodeAt(0) - 0x06F0); })
      .replace(/[٠-٩]/g, function (c) { return String(c.charCodeAt(0) - 0x0660); })
      .replace(/\s+/g, " ")
      .trim();
  }

  /* ---------- Site search ---------- */
  var searchHost = document.querySelector("[data-search-host]");
  if (searchHost) {
    var indexUrl = root.getAttribute("data-search-index") || "search-index.js";
    var prepared = null;
    var script = document.createElement("script");
    script.src = indexUrl;
    script.defer = true;
    document.head.appendChild(script);

    var box = document.createElement("div");
    box.className = "search-box";
    box.setAttribute("role", "search");
    var input = document.createElement("input");
    input.type = "search";
    input.placeholder = t("search_placeholder", "Search the guide");
    input.setAttribute("aria-label", t("search_label", "Search the guide"));
    input.setAttribute("autocomplete", "off");
    input.setAttribute("aria-controls", "search-results");
    var results = document.createElement("div");
    results.className = "search-results";
    results.id = "search-results";
    results.hidden = true;
    results.setAttribute("aria-label", t("results", "Search results"));
    box.appendChild(input);
    box.appendChild(results);
    searchHost.appendChild(box);

    var active = -1;
    var links = [];
    var escapeHtml = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]; }); };
    var prepare = function () {
      var data = window.RTLY_DOCS || [];
      if (prepared && prepared.length === data.length) return prepared;
      prepared = data.map(function (entry) {
        return {
          entry: entry,
          t: normalize(entry.t),
          d: normalize(entry.d),
          g: normalize(entry.g || ""),
          h: (entry.h || []).map(function (h) { return { text: h[0], id: h[1], n: normalize(h[0]) }; }),
          x: normalize(entry.x || "")
        };
      });
      return prepared;
    };
    var score = function (item, terms) {
      var total = 0;
      for (var i = 0; i < terms.length; i++) {
        var term = terms[i];
        var s = 0;
        if (item.t.indexOf(term) !== -1) s += 10;
        if (item.h.some(function (h) { return h.n.indexOf(term) !== -1; })) s += 5;
        if (item.d.indexOf(term) !== -1) s += 3;
        if (item.g.indexOf(term) !== -1) s += 2;
        if (item.x.indexOf(term) !== -1) s += 1;
        if (!s) return 0;
        total += s;
      }
      return total;
    };
    var bestHeading = function (item, terms) {
      for (var i = 0; i < item.h.length; i++) {
        var ok = terms.every(function (term) { return item.h[i].n.indexOf(term) !== -1; });
        if (ok) return item.h[i];
      }
      return null;
    };
    var render = function () {
      var query = normalize(input.value);
      links = [];
      active = -1;
      if (!query) { results.hidden = true; results.textContent = ""; return; }
      var data = prepare();
      var terms = query.split(" ");
      results.hidden = false;
      if (!data.length) { results.innerHTML = '<p class="none">' + escapeHtml(t("loading", "The search index is still loading.")) + "</p>"; return; }
      var found = data.map(function (item) { return { item: item, s: score(item, terms) }; })
        .filter(function (r) { return r.s > 0; })
        .sort(function (a, b) { return b.s - a.s; })
        .slice(0, 8);
      if (!found.length) { results.innerHTML = '<p class="none">' + escapeHtml(t("no_results", "No guide matches.")) + "</p>"; return; }
      results.innerHTML = found.map(function (r) {
        var heading = bestHeading(r.item, terms);
        var href = r.item.entry.u + (heading ? "#" + heading.id : "");
        return '<a href="' + escapeHtml(href) + '"><strong>' + escapeHtml(r.item.entry.t) + "</strong>" +
          (heading ? "<em>" + escapeHtml(heading.text) + "</em>" : "") +
          "<span>" + escapeHtml(r.item.entry.d) + "</span></a>";
      }).join("");
      links = Array.prototype.slice.call(results.querySelectorAll("a"));
    };
    var move = function (delta) {
      if (!links.length) return;
      if (links[active]) links[active].classList.remove("is-active");
      active = (active + delta + links.length) % links.length;
      links[active].classList.add("is-active");
      links[active].scrollIntoView({ block: "nearest" });
    };
    input.addEventListener("input", render);
    input.addEventListener("focus", render);
    input.addEventListener("keydown", function (event) {
      if (event.key === "ArrowDown") { event.preventDefault(); move(1); }
      else if (event.key === "ArrowUp") { event.preventDefault(); move(-1); }
      else if (event.key === "Enter" && links[active]) { event.preventDefault(); window.location.href = links[active].href; }
      else if (event.key === "Escape") { results.hidden = true; input.blur(); }
    });
    document.addEventListener("click", function (event) { if (!box.contains(event.target)) results.hidden = true; });
    document.addEventListener("keydown", function (event) {
      var tag = (event.target && event.target.tagName) || "";
      if (event.key === "/" && !event.ctrlKey && !event.metaKey && !event.altKey && tag !== "INPUT" && tag !== "TEXTAREA" && tag !== "SELECT") {
        event.preventDefault();
        input.focus();
      }
    });
  }

  /* ---------- Card filters ---------- */
  document.querySelectorAll("[data-filter]").forEach(function (field) {
    var group = document.getElementById(field.getAttribute("data-filter"));
    if (!group) return;
    var items = Array.prototype.slice.call(group.querySelectorAll("[data-filter-item]"));
    var texts = items.map(function (item) { return normalize(item.textContent); });
    var blocks = Array.prototype.slice.call(group.querySelectorAll("[data-filter-block]"));
    var status = document.getElementById(field.getAttribute("data-status"));
    var empty = document.getElementById(field.getAttribute("data-empty"));
    var template = field.getAttribute("data-status-text") || t("status", "%d of %d guides shown");
    var run = function () {
      var query = normalize(field.value);
      var terms = query ? query.split(" ") : [];
      var visible = 0;
      items.forEach(function (item, i) {
        var match = terms.every(function (term) { return texts[i].indexOf(term) !== -1; });
        item.hidden = !match;
        if (match) visible += 1;
      });
      blocks.forEach(function (block) {
        block.hidden = !block.querySelector("[data-filter-item]:not([hidden])");
      });
      if (status) status.textContent = fmt(template, visible, items.length);
      if (empty) empty.hidden = visible !== 0;
    };
    var wrap = field.closest(".filter");
    if (wrap) wrap.hidden = false;
    field.addEventListener("input", run);
    run();
  });

  /* ---------- Copy buttons ---------- */
  document.querySelectorAll("pre").forEach(function (block) {
    var tools = document.createElement("div");
    tools.className = "code-tools";
    var button = document.createElement("button");
    button.type = "button";
    button.className = "copy-button";
    var label = t("copy", "Copy");
    button.textContent = label;
    button.setAttribute("aria-label", t("copy_label", "Copy the code example above"));
    button.addEventListener("click", function () {
      var done = function (text) {
        button.textContent = text;
        window.setTimeout(function () { button.textContent = label; }, 2200);
      };
      var select = function () {
        var selection = window.getSelection();
        var range = document.createRange();
        range.selectNodeContents(block);
        selection.removeAllRanges();
        selection.addRange(range);
        done(t("copy_failed", "Selected. Press copy."));
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(block.textContent).then(function () { done(t("copied", "Copied")); }, select);
      } else { select(); }
    });
    tools.appendChild(button);
    block.parentNode.insertBefore(tools, block.nextSibling);
  });

  /* ---------- Table of contents highlight ---------- */
  var tocLinks = Array.prototype.slice.call(document.querySelectorAll(".toc a"));
  if (tocLinks.length && "IntersectionObserver" in window) {
    var byId = {};
    tocLinks.forEach(function (link) { byId[link.getAttribute("href").slice(1)] = link; });
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting && byId[entry.target.id]) {
          tocLinks.forEach(function (link) { link.classList.remove("is-current"); });
          byId[entry.target.id].classList.add("is-current");
        }
      });
    }, { rootMargin: "-80px 0px -70% 0px" });
    document.querySelectorAll(".prose h2[id], .prose h3[id]").forEach(function (heading) { observer.observe(heading); });
  }

  /* ---------- Language chooser hint ---------- */
  var cards = document.querySelectorAll("[data-lang-card]");
  if (cards.length) {
    var langs = (navigator.languages && navigator.languages.length ? navigator.languages : [navigator.language || ""]);
    var wanted = null;
    for (var i = 0; i < langs.length && !wanted; i++) {
      var code = String(langs[i]).toLowerCase().slice(0, 2);
      if (code === "fa" || code === "en" || code === "ar") wanted = code;
    }
    if (wanted) {
      cards.forEach(function (card) {
        if (card.getAttribute("data-lang-card") === wanted) {
          card.classList.add("is-suggested");
          var tag = card.querySelector("[data-suggest]");
          if (tag) tag.hidden = false;
        }
      });
    }
  }
})();
