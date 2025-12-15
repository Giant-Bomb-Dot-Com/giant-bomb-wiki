/**
 * Loads and initializes the external Giant Bomb header component
 */
(function () {
  "use strict";

  const STORAGE_KEY = "gbHeaderAssets";
  const SIX_HOURS = 6 * 60 * 60 * 1000;
  const MAX_RENDER_ATTEMPTS = 20;

  function hideEmptyContainer() {
    const container = document.getElementById("gb-header");
    if (container) {
      container.style.display = "none";
    }
  }

  function addConnectionHints(href) {
    const head = document.head || document.getElementsByTagName("head")[0];
    if (!head) {
      return;
    }
    ["dns-prefetch", "preconnect"].forEach((rel) => {
      if (head.querySelector(`link[rel="${rel}"][href="${href}"]`)) {
        return;
      }
      const link = document.createElement("link");
      link.rel = rel;
      link.href = href;
      if (rel === "preconnect") {
        link.crossOrigin = "anonymous";
      }
      head.appendChild(link);
    });
  }

  function storageAvailable() {
    try {
      const testKey = "__gb_header_storage_test__";
      window.sessionStorage.setItem(testKey, "1");
      window.sessionStorage.removeItem(testKey);
      return true;
    } catch (err) {
      return false;
    }
  }

  const supportsStorage = storageAvailable();

  function loadFromStorage(options) {
    const allowStale = options && options.allowStale;
    if (!supportsStorage) {
      return null;
    }
    try {
      const raw = window.sessionStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return null;
      }
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== "object") {
        return null;
      }
      if (!parsed.version || parsed.version !== options.version) {
        return null;
      }
      if (!parsed.timestamp) {
        return null;
      }
      const age = Date.now() - parsed.timestamp;
      if (!allowStale && age > SIX_HOURS) {
        return null;
      }
      return parsed;
    } catch (err) {
      return null;
    }
  }

  function saveToStorage(data) {
    if (!supportsStorage) {
      return;
    }
    try {
      window.sessionStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
          css: data.css || "",
          js: data.js || "",
          timestamp: Date.now(),
          version: data.version,
        }),
      );
    } catch (err) {
      // Ignore storage failures
    }
  }

  function injectCss(css) {
    if (!css) {
      return;
    }
    const existing = document.querySelector("style[data-gb-header-css]");
    if (existing) {
      existing.remove();
    }
    const style = document.createElement("style");
    style.type = "text/css";
    style.dataset.gbHeaderCss = "true";
    style.appendChild(document.createTextNode(css));
    (document.head || document.documentElement).appendChild(style);
  }

  function injectJs(js) {
    if (!js) {
      return;
    }
    const existing = document.querySelector("script[data-gb-header-js]");
    if (existing) {
      existing.remove();
    }
    const script = document.createElement("script");
    script.type = "text/javascript";
    script.dataset.gbHeaderJs = "true";
    script.text = js;
    (document.head || document.documentElement).appendChild(script);
  }

  function renderHeader(attempts) {
    const remaining =
      typeof attempts === "number" ? attempts : MAX_RENDER_ATTEMPTS;
    if (
      typeof window.GiantBombHeader !== "undefined" &&
      window.GiantBombHeader &&
      typeof window.GiantBombHeader.render === "function"
    ) {
      try {
        window.GiantBombHeader.render("gb-header");
      } catch (err) {
        console.error("Failed to render Giant Bomb header", err);
        hideEmptyContainer();
      }
      return;
    }
    if (remaining <= 0) {
      console.error("GiantBombHeader render function not available");
      hideEmptyContainer();
      return;
    }
    window.setTimeout(function () {
      renderHeader(remaining - 1);
    }, 50);
  }

  function applyAssets(assets) {
    if (!assets) {
      hideEmptyContainer();
      return;
    }
    injectCss(assets.css);
    injectJs(assets.js);
    // Rendering requires the script to be evaluated; scheduling on the next frame gives the browser time to execute it.
    window.requestAnimationFrame(function () {
      renderHeader();
    });
  }

  async function fetchAssets(cssUrl, jsUrl) {
    const responses = await Promise.all([
      fetch(cssUrl, { credentials: "omit" }),
      fetch(jsUrl, { credentials: "omit" }),
    ]);
    const [cssResp, jsResp] = responses;
    if (!cssResp.ok || !jsResp.ok) {
      throw new Error("Failed to fetch header assets");
    }
    const [css, js] = await Promise.all([cssResp.text(), jsResp.text()]);
    return { css, js };
  }

  function refreshCache(cssUrl, jsUrl, version) {
    fetchAssets(cssUrl, jsUrl)
      .then(function (fetched) {
        saveToStorage({
          css: fetched.css,
          js: fetched.js,
          version: version,
        });
      })
      .catch(function (err) {
        // Swallow background refresh errors
        console.debug("Background refresh of header assets failed", err);
      });
  }

  // Get header assets URL from MediaWiki config (set via environment variable)
  const configuredBaseUrl = mw.config.get("wgHeaderAssetsUrl");

  // If no header URL is configured, hide the empty container
  if (!configuredBaseUrl) {
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
  script.async = true;

  script.onload = function () {
    // Wait for DOM to be ready and header script to be available
    if (typeof GiantBombHeader !== "undefined" && GiantBombHeader.render) {
      GiantBombHeader.render("gb-header");
    } else {
      console.error("GiantBombHeader not available after script load");
      hideEmptyContainer();
    }
  };

  const baseUrl = configuredBaseUrl.replace(/\/+$/, "");
  const cssUrl = baseUrl + "/api/public/header-assets?format=css";
  const jsUrl = baseUrl + "/api/public/header-assets?format=js";
  const cacheVersion = baseUrl;

  addConnectionHints(baseUrl);

  const freshCached = loadFromStorage({ version: cacheVersion });
  if (freshCached) {
    applyAssets(freshCached);
    // If the cache is more than half-way to expiring, refresh in the background.
    if (Date.now() - freshCached.timestamp > SIX_HOURS / 2) {
      refreshCache(cssUrl, jsUrl, cacheVersion);
    }
    return;
  }

  fetchAssets(cssUrl, jsUrl)
    .then(function (assets) {
      const payload = {
        css: assets.css,
        js: assets.js,
        version: cacheVersion,
      };
      saveToStorage(payload);
      applyAssets(payload);
    })
    .catch(function (err) {
      console.error("Failed to load Giant Bomb header assets", err);
      const stale = loadFromStorage({
        version: cacheVersion,
        allowStale: true,
      });
      if (stale) {
        console.warn("Using stale cached Giant Bomb header assets");
        applyAssets(stale);
        return;
      }
      hideEmptyContainer();
    });
})();
