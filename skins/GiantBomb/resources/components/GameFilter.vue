<template>
  <filter-container
    class="game-filter"
    title="Filter Games"
    :show-clear-button="hasActiveFilters"
    @clear="clearFilters"
  >
    <filter-input
      id="search-filter"
      label="Search"
      v-model="searchQuery"
      @update:model-value="handleSearchInput"
      placeholder="Search games..."
    ></filter-input>

    <filter-dropdown
      id="platform-filter"
      label="Platform"
      v-model="selectedPlatform"
      :options="platforms"
      placeholder="All Platforms"
      value-key="name"
      label-key="displayName"
      @update:model-value="applyFilters"
    ></filter-dropdown>

    <filter-dropdown
      id="sort-filter"
      label="Sort By"
      v-model="selectedSort"
      :options="sortOptions"
      value-key="value"
      label-key="label"
      @update:model-value="applyFilters"
    ></filter-dropdown>
  </filter-container>
</template>

<script>
const {
  defineComponent,
  ref,
  computed,
  toRefs,
  onMounted,
  onUnmounted,
} = require("vue");
const { useFilters } = require("../composables/useFilters.js");
const { decodeHtmlEntities } = require("../helpers/htmlUtils.js");
const FilterContainer = require("./FilterContainer.vue");
const FilterDropdown = require("./FilterDropdown.vue");
const FilterInput = require("./FilterInput.vue");

/**
 * GameFilter Component
 * Handles filtering of games by search query, platform, and sort order
 * Updates URL query parameters and emits filter events
 * Note: FilterContainer, FilterDropdown, and FilterInput are globally registered
 */
module.exports = exports = defineComponent({
  name: "GameFilter",
  components: {
    FilterContainer,
    FilterDropdown,
    FilterInput,
  },
  props: {
    platformsData: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    const { platformsData } = toRefs(props);

    // Filter state
    const platforms = ref([]);
    const searchQuery = ref("");
    const selectedPlatform = ref("");
    const selectedSort = ref("title-asc");

    // Sort options
    const sortOptions = [
      { value: "title-asc", label: "Title (A-Z)" },
      { value: "title-desc", label: "Title (Z-A)" },
      { value: "date-desc", label: "Newest First" },
      { value: "date-asc", label: "Oldest First" },
    ];

    let searchTimeout = null;

    // Use filters composable
    const { applyFilters: applyFiltersBase, clearFilters: clearFiltersBase } =
      useFilters("games-filter-changed", {
        search: "",
        platform: "",
        sort: "title-asc",
        page: 1,
      });

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
      applyFiltersBase({
        search: searchQuery.value,
        platform: selectedPlatform.value,
        sort: selectedSort.value,
        page: 1,
      });
    };

    const clearFilters = () => {
      searchQuery.value = "";
      selectedPlatform.value = "";
      selectedSort.value = "title-asc";

      clearFiltersBase({
        search: "",
        platform: "",
        sort: "title-asc",
        page: 1,
      });
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
      sortOptions,
      hasActiveFilters,
      handleSearchInput,
      applyFilters,
      clearFilters,
    };
  },
});
</script>

<style>
/* All shared filter styles are in filters.css */
/* Component-specific styles only */
</style>
