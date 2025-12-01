const { ref } = require("vue");

/**
 * useFilters Composable
 * Shared logic for managing filter state and URL synchronization
 */
function useFilters(eventName, initialFilters = {}) {
  const filters = ref({ ...initialFilters });

  const applyFilters = (customFilters = null) => {
    const activeFilters = customFilters || filters.value;
    const url = new URL(window.location.href);
    const queryParts = [];

    // Build query string from active filters
    Object.entries(activeFilters).forEach(([key, value]) => {
      if (value === null || value === undefined || value === "") return;

      // Handle arrays (e.g., game_title[])
      if (Array.isArray(value)) {
        if (value.length > 0) {
          value.forEach((item) => {
            queryParts.push(`${key}[]=${encodeURIComponent(item)}`);
          });
        }
      }
      // Handle booleans
      else if (typeof value === "boolean") {
        if (value) {
          queryParts.push(`${key}=1`);
        }
      }
      // Handle strings and numbers
      else {
        queryParts.push(`${key}=${encodeURIComponent(value)}`);
      }
    });

    // Build the final URL
    const queryString = queryParts.length > 0 ? `?${queryParts.join("&")}` : "";
    const newUrl = `${url.pathname}${queryString}`;

    // Update URL without reloading
    window.history.pushState({}, "", newUrl);

    // Emit custom event for list components to listen to
    window.dispatchEvent(
      new CustomEvent(eventName, {
        detail: activeFilters,
      }),
    );
  };

  const clearFilters = (defaultFilters = {}) => {
    filters.value = { ...defaultFilters };

    // Update URL without reloading
    const url = new URL(window.location.href);
    window.history.pushState({}, "", url.pathname);

    // Emit event to reload with defaults
    window.dispatchEvent(
      new CustomEvent(eventName, {
        detail: defaultFilters,
      }),
    );
  };

  const loadFiltersFromUrl = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const loadedFilters = {};

    urlParams.forEach((value, key) => {
      // Handle array parameters (e.g., game_title[])
      if (key.endsWith("[]")) {
        const baseKey = key.slice(0, -2);
        if (!loadedFilters[baseKey]) {
          loadedFilters[baseKey] = [];
        }
        loadedFilters[baseKey].push(value);
      }
      // Handle boolean parameters
      else if (value === "1" || value === "0") {
        loadedFilters[key] = value === "1";
      }
      // Handle regular parameters
      else {
        loadedFilters[key] = value;
      }
    });

    return loadedFilters;
  };

  return {
    filters,
    applyFilters,
    clearFilters,
    loadFiltersFromUrl,
  };
}

module.exports = { useFilters };
