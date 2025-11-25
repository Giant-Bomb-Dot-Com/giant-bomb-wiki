/**
 * Loads and initializes the external Giant Bomb header component
 */
(function () {
  "use strict";

  function hideEmptyContainer() {
    const container = document.getElementById("gb-header");
    if (container) {
      container.style.display = "none";
    }
  }

  // Get header assets URL from MediaWiki config (set via environment variable)
  const baseUrl = mw.config.get("wgHeaderAssetsUrl");

  // If no header URL is configured, hide the empty container
  if (!baseUrl) {
    hideEmptyContainer();
    return;
  }
  // Load the external header CSS
  const link = document.createElement("link");
  link.rel = "stylesheet";
  link.href = baseUrl + "/api/public/header-assets?format=css";
  document.head.appendChild(link);

  // Load the external header script
  const script = document.createElement("script");
  script.src = baseUrl + "/api/public/header-assets?format=js";
  // script.async = true;

  script.onload = function () {
    // Wait for DOM to be ready and header script to be available
    if (typeof GiantBombHeader !== "undefined" && GiantBombHeader.render) {
      GiantBombHeader.render("gb-header");
    } else {
      console.error("GiantBombHeader not available after script load");
      hideEmptyContainer();
    }
  };

  script.onerror = function () {
    console.error("Failed to load Giant Bomb header script");
    hideEmptyContainer();
  };

  document.head.appendChild(script);
})();
