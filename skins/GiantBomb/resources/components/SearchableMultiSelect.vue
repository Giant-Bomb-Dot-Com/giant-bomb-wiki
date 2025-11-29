<template>
  <div class="filter-group search-group">
    <label v-if="label" :for="id" class="filter-label">{{ label }}</label>

    <!-- Selected items chips -->
    <div v-if="selectedItems.length > 0" class="selected-items">
      <div
        v-for="item in selectedItems"
        :key="getItemKey(item)"
        class="item-chip"
      >
        <span class="item-chip-title">{{ getItemLabel(item) }}</span>
        <button
          @click="removeItem(item)"
          class="item-chip-remove"
          type="button"
          :title="`Remove ${getItemLabel(item)}`"
        >
          ×
        </button>
      </div>
    </div>

    <input
      :id="id"
      v-model="searchText"
      type="text"
      :placeholder="placeholder"
      class="filter-input"
      @input="onSearchInput"
      @focus="onSearchFocus"
      @blur="onSearchBlur"
    />

    <!-- Match All Checkbox (shown when multiple items selected) -->
    <div
      v-if="showMatchAll && selectedItems.length > 1"
      class="filter-checkbox-group"
    >
      <label class="filter-checkbox-label">
        <input
          type="checkbox"
          :checked="requireAll"
          @change="$emit('update:requireAll', $event.target.checked)"
          class="filter-checkbox"
        />
        <span>{{ matchAllText }}</span>
      </label>
    </div>

    <div v-if="showSearchResults" class="search-results">
      <div v-if="isSearching" class="search-loading">Searching...</div>

      <div v-else-if="searchResults.length > 0" class="search-results-list">
        <slot
          name="result-item"
          :results="searchResults"
          :select-item="selectItem"
        >
          <!-- Default result item rendering -->
          <div
            v-for="item in searchResults"
            :key="getItemKey(item)"
            class="search-result-item"
            @mousedown="selectItem(item)"
          >
            <slot name="result-content" :item="item">
              {{ getItemLabel(item) }}
            </slot>
          </div>
        </slot>

        <button
          v-if="hasMoreResults"
          @mousedown="loadMore"
          class="load-more-btn"
          :disabled="isLoadingMore"
        >
          {{ isLoadingMore ? "Loading..." : loadMoreText }}
        </button>
      </div>

      <div
        v-else-if="searchText.length >= minSearchLength"
        class="search-no-results"
      >
        {{ noResultsText }}
      </div>
    </div>
  </div>
</template>

<script>
const { defineComponent, ref, computed, toRefs } = require("vue");

/**
 * SearchableMultiSelect Component
 * Generic component for searchable multi-select with chips
 */
const component = defineComponent({
  name: "SearchableMultiSelect",
  props: {
    id: {
      type: String,
      required: true,
    },
    label: {
      type: String,
      default: "",
    },
    placeholder: {
      type: String,
      default: "Search...",
    },
    selectedItems: {
      type: Array,
      default: () => [],
    },
    searchResults: {
      type: Array,
      default: () => [],
    },
    isSearching: {
      type: Boolean,
      default: false,
    },
    hasMoreResults: {
      type: Boolean,
      default: false,
    },
    isLoadingMore: {
      type: Boolean,
      default: false,
    },
    minSearchLength: {
      type: Number,
      default: 2,
    },
    debounceMs: {
      type: Number,
      default: 400,
    },
    showMatchAll: {
      type: Boolean,
      default: false,
    },
    requireAll: {
      type: Boolean,
      default: false,
    },
    matchAllText: {
      type: String,
      default: "Match all selected items",
    },
    loadMoreText: {
      type: String,
      default: "See more results",
    },
    noResultsText: {
      type: String,
      default: "No results found",
    },
    // For object arrays: specify which property to use as key
    itemKey: {
      type: String,
      default: null,
    },
    // For object arrays: specify which property to use as label
    itemLabel: {
      type: String,
      default: null,
    },
  },
  emits: [
    "update:selectedItems",
    "update:requireAll",
    "search",
    "loadMore",
    "select",
    "remove",
  ],
  setup(props, { emit }) {
    const searchText = ref("");
    const showSearchResults = ref(false);
    let debounceTimer = null;

    const getItemKey = (item) => {
      if (typeof item === "object" && props.itemKey) {
        return item[props.itemKey];
      }
      return item;
    };

    const getItemLabel = (item) => {
      if (typeof item === "object" && props.itemLabel) {
        return item[props.itemLabel];
      }
      return item;
    };

    const onSearchInput = () => {
      if (debounceTimer) {
        clearTimeout(debounceTimer);
      }

      if (searchText.value.length < props.minSearchLength) {
        showSearchResults.value = false;
        return;
      }

      showSearchResults.value = true;
      debounceTimer = setTimeout(() => {
        emit("search", searchText.value);
      }, props.debounceMs);
    };

    const onSearchFocus = () => {
      if (
        searchText.value.length >= props.minSearchLength &&
        props.searchResults.length > 0
      ) {
        showSearchResults.value = true;
      }
    };

    const onSearchBlur = () => {
      // Delay to allow click events on results to fire
      setTimeout(() => {
        showSearchResults.value = false;
      }, 200);
    };

    const selectItem = (item) => {
      // Check if item is already selected
      const alreadySelected = props.selectedItems.some(
        (i) => getItemKey(i) === getItemKey(item),
      );

      if (!alreadySelected) {
        const updatedItems = [...props.selectedItems, item];
        emit("update:selectedItems", updatedItems);
        emit("select", item);
      }

      // Close the search dropdown and clear search
      showSearchResults.value = false;
      searchText.value = "";
    };

    const removeItem = (item) => {
      const updatedItems = props.selectedItems.filter(
        (i) => getItemKey(i) !== getItemKey(item),
      );
      emit("update:selectedItems", updatedItems);
      emit("remove", item);
    };

    const loadMore = () => {
      emit("loadMore");
    };

    return {
      searchText,
      showSearchResults,
      getItemKey,
      getItemLabel,
      onSearchInput,
      onSearchFocus,
      onSearchBlur,
      selectItem,
      removeItem,
      loadMore,
    };
  },
});

module.exports = exports = component;
exports.default = component;
</script>

<style>
.search-group {
  position: relative;
}

/* Selected items chips */
.selected-items {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 10px;
}

.item-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px 6px 12px;
  background: #1a1a1a;
  border: 1px solid #444;
  border-radius: 4px;
  font-size: 0.85rem;
  color: #fff;
}

.item-chip-title {
  line-height: 1.2;
}

.item-chip-remove {
  background: none;
  border: none;
  color: #999;
  font-size: 1.4rem;
  line-height: 1;
  cursor: pointer;
  padding: 0;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 2px;
  transition: all 0.2s;
}

.item-chip-remove:hover {
  color: #fff;
  background: #e63946;
}

.filter-input {
  width: 100%;
  padding: 10px;
  background: #1a1a1a;
  border: 1px solid #444;
  border-radius: 4px;
  color: #fff;
  font-size: 0.95rem;
  cursor: text;
  transition: border-color 0.2s;
}

.filter-input:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.filter-input:hover:not(:disabled) {
  border-color: #666;
}

.filter-input:focus {
  outline: none;
  border-color: #e63946;
}

/* Filter checkbox styling */
.filter-checkbox-group {
  margin-top: 10px;
}

.filter-checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #ccc;
  font-size: 0.85rem;
  cursor: pointer;
  user-select: none;
}

.filter-checkbox {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: #e63946;
}

.search-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #1a1a1a;
  border: 1px solid #e63946;
  border-top: none;
  border-radius: 0 0 4px 4px;
  max-height: 400px;
  overflow-y: auto;
  z-index: 1000;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
}

.search-loading,
.search-no-results {
  padding: 20px;
  text-align: center;
  color: #888;
  font-size: 0.9rem;
}

.search-results-list {
  padding: 0;
}

.search-result-item {
  padding: 10px;
  cursor: pointer;
  border-bottom: 1px solid #2a2a2a;
  transition: background 0.2s;
  color: #fff;
}

.search-result-item:hover {
  background: #2a2a2a;
}

.search-result-item:last-child {
  border-bottom: none;
}

.load-more-btn {
  width: 100%;
  padding: 12px;
  background: #2a2a2a;
  border: none;
  border-top: 1px solid #444;
  color: #fff;
  font-size: 0.85rem;
  cursor: pointer;
  transition: background 0.2s;
  font-weight: 600;
}

.load-more-btn:hover:not(:disabled) {
  background: #333;
}

.load-more-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Scrollbar styling for search results */
.search-results::-webkit-scrollbar {
  width: 8px;
}

.search-results::-webkit-scrollbar-track {
  background: #1a1a1a;
}

.search-results::-webkit-scrollbar-thumb {
  background: #444;
  border-radius: 4px;
}

.search-results::-webkit-scrollbar-thumb:hover {
  background: #555;
}
</style>
