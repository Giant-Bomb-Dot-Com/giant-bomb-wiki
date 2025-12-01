<template>
  <filter-container
    title="Filter"
    :show-clear-button="hasActiveFilters"
    @clear="clearFilters"
  >
    <filter-dropdown
      id="region-filter"
      label="Region"
      v-model="selectedRegion"
      :options="regionOptions"
      placeholder="All Regions"
      @update:model-value="onFilterChange"
    ></filter-dropdown>

    <filter-dropdown
      id="platform-filter"
      label="Platform"
      v-model="selectedPlatform"
      :options="platforms"
      placeholder="All Platforms"
      value-key="name"
      label-key="displayName"
      @update:model-value="onFilterChange"
    ></filter-dropdown>
  </filter-container>
</template>

<script>
const { defineComponent, ref, computed, toRefs, onMounted } = require("vue");
const { useFilters } = require("../composables/useFilters.js");
const { decodeHtmlEntities } = require("../helpers/htmlUtils.js");
const FilterContainer = require("./FilterContainer.vue");
const FilterDropdown = require("./FilterDropdown.vue");

/**
 * ReleaseFilter Component
 * Handles filtering of releases by region and platform
 * Note: FilterContainer and FilterDropdown are globally registered
 */
module.exports = exports = defineComponent({
  name: "ReleaseFilter",
  components: {
    FilterContainer,
    FilterDropdown,
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
    const selectedRegion = ref("");
    const selectedPlatform = ref("");

    // Region options
    const regionOptions = [
      "United States",
      "United Kingdom",
      "Japan",
      "Australia",
    ];

    // Use filters composable
    const { applyFilters: applyFiltersBase, clearFilters: clearFiltersBase } =
      useFilters("releases-filter-changed", {
        region: "",
        platform: "",
      });

    const hasActiveFilters = computed(() => {
      return selectedRegion.value !== "" || selectedPlatform.value !== "";
    });

    const onFilterChange = () => {
      applyFiltersBase({
        region: selectedRegion.value,
        platform: selectedPlatform.value,
      });
    };

    const clearFilters = () => {
      selectedRegion.value = "";
      selectedPlatform.value = "";
      clearFiltersBase({
        region: "",
        platform: "",
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
      selectedRegion.value = urlParams.get("region") || "";
      selectedPlatform.value = urlParams.get("platform") || "";
    });

    return {
      platforms,
      regionOptions,
      selectedRegion,
      selectedPlatform,
      hasActiveFilters,
      onFilterChange,
      clearFilters,
    };
  },
});
</script>
