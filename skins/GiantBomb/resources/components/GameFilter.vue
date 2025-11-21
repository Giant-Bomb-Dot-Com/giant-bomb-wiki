<template>
  <div class="game-filter">
    <h3 class="filter-title">Filter Games</h3>

    <div class="filter-group">
      <label for="search-filter" class="filter-label">Search</label>
      <input
        id="search-filter"
        v-model="searchQuery"
        @input="handleSearchInput"
        type="text"
        placeholder="Search games..."
        class="filter-input"
      />
    </div>

    <div class="filter-group">
      <label for="platform-filter" class="filter-label">Platform</label>
      <select
        id="platform-filter"
        v-model="selectedPlatform"
        @change="applyFilters"
        class="filter-select"
      >
        <option value="">All Platforms</option>
        <option v-for="platform in platforms" :key="platform" :value="platform">
          {{ platform }}
        </option>
      </select>
    </div>

    <div class="filter-group">
      <label for="sort-filter" class="filter-label">Sort By</label>
      <select
        id="sort-filter"
        v-model="selectedSort"
        @change="applyFilters"
        class="filter-select"
      >
        <option value="title-asc">Title (A-Z)</option>
        <option value="title-desc">Title (Z-A)</option>
        <option value="date-desc">Newest First</option>
        <option value="date-asc">Oldest First</option>
      </select>
    </div>

    <button
      v-if="hasActiveFilters"
      @click="clearFilters"
      class="clear-filters-btn"
    >
      Clear Filters
    </button>
  </div>
</template>

<script>
const { ref, computed, toRefs, onMounted, onUnmounted } = require("vue");

/**
 * GameFilter Component
 * Handles filtering of games by search query, platform, and sort order
 * Updates URL query parameters and emits filter events
 */
module.exports = exports = {
  name: "GameFilter",
  props: {
    platformsData: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    const { platformsData } = toRefs(props);
    const platforms = ref([]);
    const searchQuery = ref("");
    const selectedPlatform = ref("");
    const selectedSort = ref("title-asc");
    let searchTimeout = null;

    const hasActiveFilters = computed(() => {
      return (
        searchQuery.value !== "" ||
        selectedPlatform.value !== "" ||
        selectedSort.value !== "title-asc"
      );
    });

    const handleSearchInput = () => {
      // Clear existing timeout
      if (searchTimeout) {
        clearTimeout(searchTimeout);
      }

      // Wait 800ms after user stops typing before applying filters
      searchTimeout = setTimeout(() => {
        applyFilters();
      }, 800);
    };

    const applyFilters = () => {
      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);

      // Update or remove search parameter
      if (searchQuery.value) {
        params.set("search", searchQuery.value);
      } else {
        params.delete("search");
      }

      // Update or remove platform parameter
      if (selectedPlatform.value) {
        params.set("platform", selectedPlatform.value);
      } else {
        params.delete("platform");
      }

      // Update or remove sort parameter
      if (selectedSort.value && selectedSort.value !== "title-asc") {
        params.set("sort", selectedSort.value);
      } else {
        params.delete("sort");
      }

      // Reset to page 1 when filters change
      params.delete("page");

      // Update URL without reloading (for bookmarking/sharing)
      const newUrl = `${url.pathname}?${params.toString()}`;
      window.history.pushState({}, "", newUrl);

      // Emit event for GameList to listen to
      window.dispatchEvent(
        new CustomEvent("games-filter-changed", {
          detail: {
            search: searchQuery.value,
            platform: selectedPlatform.value,
            sort: selectedSort.value,
            page: 1
          },
        }),
      );
    };

    const clearFilters = () => {
      searchQuery.value = "";
      selectedPlatform.value = "";
      selectedSort.value = "title-asc";

      // Update URL without reloading
      const url = new URL(window.location.href);
      window.history.pushState({}, "", url.pathname);

      // Emit event to reload all games
      window.dispatchEvent(
        new CustomEvent("games-filter-changed", {
          detail: {
            search: "",
            platform: "",
            sort: "title-asc",
            page: 1
          },
        }),
      );
    };

    // Helper function to decode HTML entities
    const decodeHtmlEntities = (text) => {
      const textarea = document.createElement("textarea");
      textarea.innerHTML = text;
      return textarea.value;
    };

    onMounted(() => {
      // Parse platforms data
      try {
        const decodedJson = decodeHtmlEntities(platformsData.value);
        platforms.value = JSON.parse(decodedJson);
      } catch (e) {
        console.error("Failed to parse platforms data:", e);
        platforms.value = [];
      }

      // Read current filter values from URL
      const urlParams = new URLSearchParams(window.location.search);
      searchQuery.value = urlParams.get("search") || "";
      selectedPlatform.value = urlParams.get("platform") || "";
      selectedSort.value = urlParams.get("sort") || "title-asc";
    });

    onUnmounted(() => {
      // Clear any pending search timeout
      if (searchTimeout) {
        clearTimeout(searchTimeout);
      }
    });

    return {
      platforms,
      searchQuery,
      selectedPlatform,
      selectedSort,
      hasActiveFilters,
      handleSearchInput,
      applyFilters,
      clearFilters,
    };
  },
};
</script>

<style>
/* All shared filter styles are in filters.css */
/* Component-specific styles only */
</style>
