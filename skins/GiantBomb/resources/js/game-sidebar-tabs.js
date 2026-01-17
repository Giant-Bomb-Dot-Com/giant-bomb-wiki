/**
 * Game Page JavaScript
 *
 * Features:
 * - Hero images: Loads cover and background from embedded JSON
 * - Sidebar tabs: Interactive tabs for Characters/Locations/Concepts/Objects
 * - Prefix stripping: Removes namespace prefixes from link text for cleaner display
 *
 * Why client-side prefix stripping?
 * ---------------------------------
 * SMW stores page names with namespace prefixes (e.g., "Companies/id Software").
 * When rendered via #show with link=all, the link text includes the full path.
 * Server-side alternatives would require:
 *   - Changing data storage (breaking existing imports)
 *   - Complex SMW result templates
 *   - Lua modules (which we're avoiding for simplicity)
 *
 * The JS solution is a simple, fast progressive enhancement that improves
 * display without affecting the underlying data model.
 */
(() => {
  "use strict";

  const GB_IMAGE_BASE = "https://www.giantbomb.com/a/uploads/";

  const initHeroImages = () => {
    const imageDataEl = document.getElementById("imageData");
    if (!imageDataEl) return;

    const jsonStr =
      imageDataEl.getAttribute("data-json") || imageDataEl.textContent;
    if (!jsonStr) return;

    try {
      const imageData = JSON.parse(jsonStr);

      if (imageData.infobox?.file && imageData.infobox?.path) {
        const coverUrl = `${GB_IMAGE_BASE}scale_super/${imageData.infobox.path}${imageData.infobox.file}`;
        const coverContainer = document.querySelector(
          ".gb-game-hero-cover, .gb-character-hero-cover, .gb-franchise-hero-cover, .gb-concept-hero-cover, .gb-object-hero-cover, .gb-location-hero-cover, .gb-platform-hero-cover, .gb-company-hero-cover, .gb-person-hero-cover, .gb-accessory-hero-cover",
        );

        if (coverContainer) {
          let coverImg = coverContainer.querySelector("img");
          if (!coverImg) {
            coverImg = document.createElement("img");
            coverContainer.appendChild(coverImg);
          }
          coverImg.src = coverUrl;
          coverImg.alt =
            document.querySelector(
              ".gb-game-hero-title, .gb-character-hero-title, .gb-franchise-hero-title",
            )?.textContent || "Cover image";
        }
      }

      if (imageData.background?.file && imageData.background?.path) {
        const bgUrl = `${GB_IMAGE_BASE}screen_kubrick_wide/${imageData.background.path}${imageData.background.file}`;
        const heroSection = document.querySelector(
          ".gb-game-hero, .gb-character-hero, .gb-franchise-hero, .gb-concept-hero, .gb-object-hero, .gb-location-hero, .gb-platform-hero, .gb-company-hero, .gb-person-hero, .gb-accessory-hero",
        );

        if (heroSection) {
          heroSection.style.backgroundImage = `url(${bgUrl})`;
        }
      }
    } catch (e) {
      console.error("Failed to parse image data:", e);
    }
  };

  const stripPrefixesFromLinks = () => {
    const prefixes = [
      "Companies/",
      "Platforms/",
      "Genres/",
      "Themes/",
      "Franchises/",
      "Characters/",
      "Concepts/",
      "Locations/",
      "Objects/",
      "People/",
      "Games/",
      "Accessories/",
      "Ratings/",
      "Regions/",
    ];

    // DISPLAYTITLE suffixes to strip (type indicators from page templates)
    const suffixes = [
      " (Game)",
      " (Character)",
      " (Franchise)",
      " (Platform)",
      " (Concept)",
      " (Company)",
      " (Person)",
      " (Location)",
      " (Object)",
      " (Genre)",
      " (Theme)",
      " (Accessory)",
      " (DLC)",
      " (Release)",
      " (Region)",
      " (Rating Board)",
      " (Game Rating)",
      // Legacy suffixes (for cached pages)
      " - Giant Bomb Video Game Wiki",
    ];

    // Target all content areas where links may have DISPLAYTITLE suffixes
    const contentSelectors = [
      // Sidebar details
      ".gb-game-details a",
      ".gb-character-details a",
      ".gb-franchise-details a",
      ".gb-concept-details a",
      ".gb-location-details a",
      ".gb-object-details a",
      ".gb-platform-details a",
      ".gb-company-details a",
      ".gb-person-details a",
      ".gb-accessory-details a",
      ".gb-dlc-details a",
      ".gb-theme-details a",
      ".gb-genre-details a",
      ".gb-region-details a",
      ".gb-release-details a",
      ".gb-gamerating-details a",
      ".gb-ratingboard-details a",
      // Sidebar related content
      ".gb-sidebar-related-content a",
      ".gb-accordion-content a",
      // Hero platforms
      ".gb-game-hero-platforms a",
      ".gb-game-hero-platform a",
      // Franchise games
      ".gb-franchise-game-title a",
      // Wiki content areas (main body text)
      ".gb-game-wiki-content a",
      ".gb-character-wiki-content a",
      ".gb-franchise-wiki-content a",
      ".gb-concept-wiki-content a",
      ".gb-location-wiki-content a",
      ".gb-object-wiki-content a",
      ".gb-platform-wiki-content a",
      ".gb-company-wiki-content a",
      ".gb-person-wiki-content a",
      ".gb-accessory-wiki-content a",
      ".gb-dlc-wiki-content a",
      ".gb-theme-wiki-content a",
      ".gb-genre-wiki-content a",
      ".gb-region-wiki-content a",
      ".gb-release-wiki-content a",
      ".gb-gamerating-wiki-content a",
      ".gb-ratingboard-wiki-content a",
    ];
    const targetLinks = document.querySelectorAll(contentSelectors.join(", "));

    for (const link of targetLinks) {
      let text = link.textContent;
      // Strip prefixes
      for (const prefix of prefixes) {
        if (text.startsWith(prefix)) {
          text = text.replace(prefix, "");
          break;
        }
      }
      // Strip DISPLAYTITLE suffixes
      for (const suffix of suffixes) {
        if (text.endsWith(suffix)) {
          text = text.slice(0, -suffix.length);
          break;
        }
      }
      // Strip SMW printout metadata like "(Has name: ...)"
      text = text.replace(/\s*\(Has\s+\w+:\s*[^)]+\)/g, "");
      link.textContent = text.replace(/_/g, " ");
    }

    for (const span of document.querySelectorAll(
      ".gb-game-hero-platform, .gb-franchise-game-platform",
    )) {
      let text = span.textContent;
      for (const prefix of prefixes) {
        if (text.startsWith(prefix)) {
          text = text.replace(prefix, "");
          break;
        }
      }
      span.textContent = text.replace(/_/g, " ");
    }
  };

  const initSidebarTabs = () => {
    for (const container of document.querySelectorAll(
      ".gb-sidebar-related-tabs",
    )) {
      const tabs = container.querySelectorAll(".gb-sidebar-related-tab");
      const section = container.closest(".gb-sidebar-section");
      if (!section) continue;

      const panels = section
        .querySelector(".gb-sidebar-related-content")
        ?.querySelectorAll(".gb-sidebar-related-list");
      if (!panels) continue;

      for (const tab of tabs) {
        tab.addEventListener("click", () => {
          const targetId = tab.getAttribute("data-target");

          for (const t of tabs) {
            t.classList.remove("gb-sidebar-related-tab--active");
          }
          tab.classList.add("gb-sidebar-related-tab--active");

          for (const panel of panels) {
            panel.classList.toggle(
              "gb-sidebar-related-list--active",
              panel.id === targetId,
            );
          }
        });
      }
    }
  };

  /**
   * Add Games tab to franchise pages
   * MediaWiki strips anchor-only links from wikitext, so we add this via JS
   */
  const initFranchiseGamesTab = () => {
    const tabsNav = document.querySelector(".gb-franchise-tabs-nav");
    if (!tabsNav) return;

    const gamesSection = document.getElementById("Games");
    if (!gamesSection) return;

    // Check if Games tab already exists
    if (tabsNav.querySelector('a[href="#Games"]')) return;

    // Find the Images tab to insert before it
    const imagesTab = Array.from(
      tabsNav.querySelectorAll(".gb-franchise-tabs-tab"),
    ).find(
      (tab) =>
        tab.textContent.includes("Images") ||
        tab.querySelector('a[href*="/Images"]'),
    );

    if (imagesTab) {
      const gamesTab = document.createElement("span");
      gamesTab.className = "gb-franchise-tabs-tab";
      const gamesLink = document.createElement("a");
      gamesLink.href = "#Games";
      gamesLink.textContent = "Games";
      gamesLink.addEventListener("click", (e) => {
        e.preventDefault();
        gamesSection.scrollIntoView({ behavior: "smooth" });
      });
      gamesTab.appendChild(gamesLink);
      tabsNav.insertBefore(gamesTab, imagesTab);
    }
  };

  const initAccordions = () => {
    const isMobile = window.innerWidth <= 900;

    for (const accordion of document.querySelectorAll(".gb-accordion")) {
      const header = accordion.querySelector(".gb-accordion-header");
      if (!header) continue;

      // On mobile, close all by default (remove --open, don't add --active)
      if (isMobile && accordion.classList.contains("gb-accordion--open")) {
        // Keep --open class for CSS but don't activate
      }

      header.addEventListener("click", () => {
        accordion.classList.toggle("gb-accordion--active");
        // On desktop, also toggle --open for non-JS styling
        if (!isMobile) {
          accordion.classList.toggle("gb-accordion--open");
        }
      });
    }
  };

  /**
   * Make the games count in sidebar clickable to scroll to Games section
   */
  const initGamesLink = () => {
    const gamesLink = document.querySelector(".gb-games-link");
    if (!gamesLink) return;

    const targetId = gamesLink.dataset.target;
    if (!targetId) return;

    const targetSection = document.getElementById(targetId);
    if (!targetSection) return;

    gamesLink.addEventListener("click", () => {
      targetSection.scrollIntoView({ behavior: "smooth" });
    });
  };

  const init = () => {
    initHeroImages();
    stripPrefixesFromLinks();
    initSidebarTabs();
    initAccordions();
    initFranchiseGamesTab();
    initGamesLink();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
