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
          ".gb-game-hero-cover, .gb-character-hero-cover, .gb-franchise-hero-cover",
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
          ".gb-game-hero, .gb-character-hero, .gb-franchise-hero",
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
    ];

    const targetLinks = document.querySelectorAll(
      ".gb-game-details a, .gb-character-details a, .gb-franchise-details a, .gb-sidebar-related-content a, .gb-accordion-content a, .gb-game-hero-platforms a, .gb-game-hero-platform a, .gb-franchise-game-title a",
    );

    for (const link of targetLinks) {
      let text = link.textContent;
      for (const prefix of prefixes) {
        if (text.startsWith(prefix)) {
          text = text.replace(prefix, "");
          break;
        }
      }
      link.textContent = text.replace(/_/g, " ");
    }

    for (const span of document.querySelectorAll(".gb-game-hero-platform")) {
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

  const init = () => {
    initHeroImages();
    stripPrefixesFromLinks();
    initSidebarTabs();
    initAccordions();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
