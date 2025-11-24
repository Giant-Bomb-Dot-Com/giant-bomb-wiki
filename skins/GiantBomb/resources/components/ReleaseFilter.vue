<template>
  <div class="release-filter">
    <h3 class="filter-title">Filter</h3>

    <div class="filter-group">
      <label for="region-filter" class="filter-label">Region</label>
      <select
        id="region-filter"
        v-model="selectedRegion"
        @change="applyFilters"
        class="filter-select"
      >
        <option value="">All Regions</option>
        <option value="United States">United States</option>
        <option value="United Kingdom">United Kingdom</option>
        <option value="Japan">Japan</option>
        <option value="Australia">Australia</option>
      </select>
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
        <option
          v-for="platform in platforms"
          :key="platform.name"
          :value="platform.name"
        >
          {{ platform.displayName }}
        </option>
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
const { ref, computed, toRefs, onMounted } = require("vue");
const { decodeHtmlEntities } = require("../helpers/htmlUtils.js");

/**
 * ReleaseFilter Component
 * Handles filtering of releases by region and platform
 * Updates URL query parameters and refreshes the page
 */
module.exports = exports = {
  name: "ReleaseFilter",
  props: {
    platformsData: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    const { platformsData } = toRefs(props);
    const platforms = ref([]);
    const selectedRegion = ref("");
    const selectedPlatform = ref("");

    const hasActiveFilters = computed(() => {
      return selectedRegion.value !== "" || selectedPlatform.value !== "";
    });

    const applyFilters = () => {
      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);

      // Update or remove region parameter
      if (selectedRegion.value) {
        params.set("region", selectedRegion.value);
      } else {
        params.delete("region");
      }

      // Update or remove platform parameter
      if (selectedPlatform.value) {
        params.set("platform", selectedPlatform.value);
      } else {
        params.delete("platform");
      }

      // Update URL without reloading (for bookmarking/sharing)
      const newUrl = `${url.pathname}?${params.toString()}`;
      window.history.pushState({}, "", newUrl);

      // Emit event for ReleaseList to listen to
      window.dispatchEvent(
        new CustomEvent("releases-filter-changed", {
          detail: {
            region: selectedRegion.value,
            platform: selectedPlatform.value,
          },
        }),
      );
    };

    const clearFilters = () => {
      selectedRegion.value = "";
      selectedPlatform.value = "";

      // Update URL without reloading
      const url = new URL(window.location.href);
      window.history.pushState({}, "", url.pathname);

      // Emit event to reload all releases
      window.dispatchEvent(
        new CustomEvent("releases-filter-changed", {
          detail: {
            region: "",
            platform: "",
          },
        }),
      );
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
      selectedRegion.value = urlParams.get("region") || "";
      selectedPlatform.value = urlParams.get("platform") || "";
    });

    return {
      platforms,
      selectedRegion,
      selectedPlatform,
      hasActiveFilters,
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
