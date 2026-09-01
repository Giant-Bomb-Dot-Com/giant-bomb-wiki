/**
 * Collapses ToC sections with many subsections down to their header,
 * with a count badge that toggles them open.
 */
(function () {
  var THRESHOLD = 8;

  function label(count, collapsed) {
    return (collapsed ? "▸ " : "▾ ") + count;
  }

  function init() {
    var toc = document.getElementById("toc");
    if (!toc) return;
    // releases sidebar toc collapses every platform, threshold elsewhere
    var collapseAll = !!toc.closest(".gb-releases-data");
    var items = document.querySelectorAll("#toc li.toclevel-1");
    for (var i = 0; i < items.length; i++) {
      var li = items[i];
      var sub = li.querySelector(":scope > ul");
      if (!sub) continue;
      if (!collapseAll && sub.children.length <= THRESHOLD) continue;

      var count = sub.children.length;
      li.classList.add("gb-toc-collapsed");

      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "gb-toc-toggle";
      btn.setAttribute("aria-expanded", "false");
      btn.title = count + " subsections";
      btn.textContent = label(count, true);
      btn.addEventListener("click", function (e) {
        var item = e.target.closest("li.toclevel-1");
        var collapsed = item.classList.toggle("gb-toc-collapsed");
        e.target.setAttribute("aria-expanded", String(!collapsed));
        e.target.textContent = label(
          item.querySelector(":scope > ul").children.length,
          collapsed,
        );
      });

      var link = li.querySelector(":scope > a");
      if (link) link.after(btn);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
