<template>
  <div class="platform-filter">
    <h3 class="filter-title">Filter</h3>

    <div class="filter-group">
      <label for="letter-filter" class="filter-label">Letter</label>
      <select
        id="letter-filter"
        v-model="selectedLetter"
        @change="applyFilters"
        class="filter-select"
      >
        <option value="">All</option>
        <option value="#">#</option>
        <option v-for="letter in alphabet" :key="letter" :value="letter">
          {{ letter }}
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
        <option value="alphabetical">Alphabetical</option>
        <option value="release_date">Release Date</option>
      </select>
    </div>

    <div class="filter-group">
      <label for="search-filter" class="filter-label">Search Game</label>
      <input
        id="search-filter"
        v-model="searchText"
        type="text"
        placeholder="Enter game name..."
        class="filter-input"
        disabled
        title="Coming soon"
      />
      <small class="filter-note">Search functionality coming soon</small>
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
const { ref, computed, toRefs, onMounted } = require("vue");

/**
 * PlatformFilter Component
 * Handles filtering of platforms by letter and sorting
 * Updates URL query parameters and refreshes the page
 */
module.exports = exports = {
  name: "PlatformFilter",
  props: {
    currentLetter: {
      type: String,
      default: "",
    },
    currentSort: {
      type: String,
      default: "alphabetical",
    },
  },
  setup(props) {
    const { currentLetter, currentSort } = toRefs(props);
    const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("");
    const selectedLetter = ref("");
    const selectedSort = ref("alphabetical");
    const searchText = ref("");

    const hasActiveFilters = computed(() => {
      return (
        selectedLetter.value !== "" || selectedSort.value !== "alphabetical"
      );
    });

    const applyFilters = () => {
      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);

      // Update or remove letter parameter
      if (selectedLetter.value) {
        params.set("letter", selectedLetter.value);
      } else {
        params.delete("letter");
      }

      // Update or remove sort parameter
      if (selectedSort.value !== "alphabetical") {
        params.set("sort", selectedSort.value);
      } else {
        params.delete("sort");
      }

      // Reset to page 1 when filters change
      params.delete("page");

      // Update URL without reloading (for bookmarking/sharing)
      const newUrl = `${url.pathname}?${params.toString()}`;
      window.history.pushState({}, "", newUrl);

      // Emit event for PlatformList to listen to
      window.dispatchEvent(
        new CustomEvent("platforms-filter-changed", {
          detail: {
            letter: selectedLetter.value,
            sort: selectedSort.value,
            page: 1,
          },
        }),
      );
    };

    const clearFilters = () => {
      selectedLetter.value = "";
      selectedSort.value = "alphabetical";

      // Update URL without reloading
      const url = new URL(window.location.href);
      window.history.pushState({}, "", url.pathname);

      // Emit event to reload all platforms
      window.dispatchEvent(
        new CustomEvent("platforms-filter-changed", {
          detail: {
            letter: "",
            sort: "alphabetical",
            page: 1,
          },
        }),
      );
    };

    onMounted(() => {
      // Read current filter values from props or URL
      const urlParams = new URLSearchParams(window.location.search);
      selectedLetter.value = urlParams.get("letter") || currentLetter.value;
      selectedSort.value = urlParams.get("sort") || currentSort.value;
    });

    return {
      alphabet,
      selectedLetter,
      selectedSort,
      searchText,
      hasActiveFilters,
      applyFilters,
      clearFilters,
    };
  },
};
</script>

<style>
.platform-filter {
  background: #2a2a2a;
  border: 1px solid #444;
  border-radius: 8px;
  padding: 20px;
}

.filter-title {
  margin: 0 0 20px 0;
  font-size: 1.2rem;
  color: #fff;
  border-bottom: 2px solid #444;
  padding-bottom: 10px;
}

.filter-group {
  margin-bottom: 20px;
}

.filter-label {
  display: block;
  margin-bottom: 8px;
  color: #ccc;
  font-size: 0.9rem;
  font-weight: 600;
}

.filter-select,
.filter-input {
  width: 100%;
  padding: 10px;
  background: #1a1a1a;
  border: 1px solid #444;
  border-radius: 4px;
  color: #fff;
  font-size: 0.95rem;
  cursor: pointer;
  transition: border-color 0.2s;
}

.filter-input {
  cursor: text;
}

.filter-input:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.filter-select:hover,
.filter-input:hover:not(:disabled) {
  border-color: #666;
}

.filter-select:focus,
.filter-input:focus {
  outline: none;
  border-color: #e63946;
}

.filter-note {
  display: block;
  margin-top: 5px;
  font-size: 0.75rem;
  color: #888;
  font-style: italic;
}

.clear-filters-btn {
  width: 100%;
  padding: 10px;
  background: #444;
  border: 1px solid #666;
  border-radius: 4px;
  color: #fff;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 10px;
}

.clear-filters-btn:hover {
  background: #555;
  border-color: #888;
}

.clear-filters-btn:active {
  background: #333;
}
</style>
