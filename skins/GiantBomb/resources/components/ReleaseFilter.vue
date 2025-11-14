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
    const selectedRegion = ref('');
    const selectedPlatform = ref('');

    const hasActiveFilters = computed(() => {
      return selectedRegion.value !== '' || selectedPlatform.value !== '';
    });

    const applyFilters = () => {
      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);
      
      // Update or remove region parameter
      if (selectedRegion.value) {
        params.set('region', selectedRegion.value);
      } else {
        params.delete('region');
      }
      
      // Update or remove platform parameter
      if (selectedPlatform.value) {
        params.set('platform', selectedPlatform.value);
      } else {
        params.delete('platform');
      }
      
      // Reload page with new parameters
      const newUrl = `${url.pathname}?${params.toString()}`;
      window.location.href = newUrl;
    };

    const clearFilters = () => {
      selectedRegion.value = '';
      selectedPlatform.value = '';
      
      // Reload page without filter parameters
      const url = new URL(window.location.href);
      window.location.href = url.pathname;
    };

    // Helper function to decode HTML entities
    const decodeHtmlEntities = (text) => {
      const textarea = document.createElement('textarea');
      textarea.innerHTML = text;
      return textarea.value;
    };

    onMounted(() => {
      // Parse platforms data
      try {
        const decodedJson = decodeHtmlEntities(platformsData.value);
        platforms.value = JSON.parse(decodedJson);
        console.log('Loaded platforms:', platforms.value.length);
      } catch (e) {
        console.error('Failed to parse platforms data:', e);
        console.error('Data received:', platformsData.value);
        platforms.value = [];
      }

      // Read current filter values from URL
      const urlParams = new URLSearchParams(window.location.search);
      selectedRegion.value = urlParams.get('region') || '';
      selectedPlatform.value = urlParams.get('platform') || '';
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
.release-filter {
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

.filter-select {
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

.filter-select:hover {
  border-color: #666;
}

.filter-select:focus {
  outline: none;
  border-color: #e63946;
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

