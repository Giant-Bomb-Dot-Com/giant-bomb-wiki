# Filter Components Refactoring

## Overview

Filter components have been refactored to use modular, reusable components. This reduces code duplication and makes it easier to create new filter components in the future.

## Generic Components

### 1. FilterContainer

A wrapper component that provides consistent layout and styling for filter sections.

**Props:**

- `title` (String): Title displayed at the top (default: "Filter")
- `show-clear-button` (Boolean): Whether to show the clear filters button
- `clear-button-text` (String): Text for the clear button (default: "Clear Filters")

**Events:**

- `@clear`: Emitted when the clear button is clicked

**Usage:**

```vue
<filter-container
  title="Filter"
  :show-clear-button="hasActiveFilters"
  @clear="clearFilters"
>
  <!-- Filter components go here -->
</filter-container>
```

### 2. FilterDropdown

A generic dropdown/select component for filters.

**Props:**

- `id` (String, required): HTML id for the select element
- `label` (String): Label text displayed above the dropdown
- `model-value` (String/Number): The selected value (use with v-model)
- `options` (Array, required): Array of options (strings or objects)
- `placeholder` (String): Placeholder text for empty option
- `value-key` (String): For object arrays, which property to use as value
- `label-key` (String): For object arrays, which property to use as label

**Events:**

- `@update:model-value`: Emitted when selection changes (v-model compatible)

**Usage:**

```vue
<!-- Simple string array -->
<FilterDropdown
  id="letter-filter"
  label="Letter"
  v-model="selectedLetter"
  :options="['A', 'B', 'C']"
  placeholder="All"
/>

<!-- Object array -->
<filter-dropdown
  id="sort-filter"
  label="Sort By"
  v-model="selectedSort"
  :options="[
    { value: 'date', label: 'Date' },
    { value: 'name', label: 'Name' },
  ]"
  value-key="value"
  label-key="label"
></filter-dropdown>
```

### 3. SearchableMultiSelect

A complex component for searchable multi-select with chips/tags display.

**Props:**

- `id` (String, required): HTML id for the input element
- `label` (String): Label text displayed above the input
- `placeholder` (String): Placeholder text for the search input
- `selected-items` (Array): Array of selected items (use with v-model:selected-items)
- `search-results` (Array): Array of search results to display
- `is-searching` (Boolean): Whether a search is in progress
- `has-more-results` (Boolean): Whether there are more results to load
- `is-loading-more` (Boolean): Whether more results are being loaded
- `min-search-length` (Number): Minimum characters before searching (default: 2)
- `debounce-ms` (Number): Debounce delay in milliseconds (default: 400)
- `show-match-all` (Boolean): Whether to show "match all" checkbox
- `require-all` (Boolean): Value for "match all" checkbox (use with v-model:require-all)
- `match-all-text` (String): Text for the "match all" checkbox label
- `load-more-text` (String): Text for the load more button
- `no-results-text` (String): Text shown when no results found
- `item-key` (String): For object arrays, which property to use as unique key
- `item-label` (String): For object arrays, which property to use as display label

**Events:**

- `@update:selected-items`: Emitted when selected items change
- `@update:require-all`: Emitted when "match all" checkbox changes
- `@search`: Emitted when user types in search (debounced)
- `@loadMore`: Emitted when load more button clicked
- `@select`: Emitted when an item is selected
- `@remove`: Emitted when an item is removed

**Slots:**

- `result-item`: Custom template for search result items
- `result-content`: Custom template for the content within a result item

**Usage:**

```vue
<searchable-multi-select
  id="search-filter"
  label="Search Items"
  v-model:selected-items="selectedItems"
  v-model:require-all="requireAll"
  :search-results="searchResults"
  :is-searching="isSearching"
  :has-more-results="hasMoreResults"
  :is-loading-more="isLoadingMore"
  :show-match-all="true"
  placeholder="Search..."
  item-key="id"
  item-label="name"
  @search="handleSearch"
  @load-more="handleLoadMore"
>
  <!-- Custom result template -->
  <template #result-item="{ results, selectItem }">
    <div
      v-for="item in results"
      :key="item.id"
      @mousedown="selectItem(item)"
    >
      {{ item.name }}
    </div>
  </template>
</searchable-multi-select>
```

## Composables

### useFilters(eventName, initialFilters)

Shared logic for managing filter state and URL synchronization.

**Parameters:**

- `eventName` (String): Name of the custom event to dispatch
- `initialFilters` (Object): Default filter values

**Returns:**

- `filters` (Ref): Reactive filter state object
- `applyFilters(customFilters)`: Function to apply filters and update URL
- `clearFilters(defaultFilters)`: Function to clear filters
- `loadFiltersFromUrl()`: Function to load filters from URL parameters

**Usage:**

```javascript
const { applyFilters, clearFilters } = useFilters("my-filter-changed", {
  search: "",
  sort: "date",
});

// Apply filters
applyFilters({
  search: "test",
  sort: "name",
});

// Clear filters
clearFilters({ search: "", sort: "date" });
```

### useSearch(searchFunction, options)

Shared logic for search functionality with debouncing and pagination.

**Parameters:**

- `searchFunction` (Function): Async function that performs the search
  - Should accept `(query, page)` and return `{ success, items, currentPage, totalPages, hasMore }`
- `options` (Object):
  - `debounceMs` (Number): Debounce delay (default: 400)
  - `minSearchLength` (Number): Minimum search length (default: 2)

**Returns:**

- `searchResults` (Ref): Array of search results
- `isSearching` (Ref): Whether a search is in progress
- `hasMoreResults` (Ref): Whether there are more results to load
- `isLoadingMore` (Ref): Whether more results are being loaded
- `currentSearchPage` (Ref): Current page number
- `totalPages` (Ref): Total number of pages
- `search(query, page, append)`: Function to perform search
- `debouncedSearch(query, page)`: Debounced search function
- `loadMore(query)`: Function to load next page
- `reset()`: Function to reset search state

**Usage:**

```javascript
const searchApi = async (query, page) => {
  const response = await fetch(`/api/search?q=${query}&page=${page}`);
  const data = await response.json();
  return {
    success: data.success,
    items: data.results,
    currentPage: data.page,
    totalPages: data.total_pages,
    hasMore: data.has_more,
  };
};

const {
  searchResults,
  isSearching,
  hasMoreResults,
  debouncedSearch,
  loadMore,
} = useSearch(searchApi);

// Perform a search
debouncedSearch("search term");

// Load more results
loadMore("search term");
```

## Benefits

1. **Reduced Code Duplication**: Common functionality is now in reusable components
2. **Easier Maintenance**: Changes to filter UI/behavior can be made in one place
3. **Consistent UI**: All filters will have the same look and feel
4. **Faster Development**: New filter components can be created quickly using generic components
5. **Better Separation of Concerns**: Filter logic is separated from presentation
6. **Type Safety**: Generic components handle different data types (strings, objects, arrays)

## Migration Notes

The refactored PlatformFilter and ReleaseFilter components maintain the same external API (props, events) so no changes are needed to the PHP templates or parent components.

## Creating New Filter Components

To create a new filter component:

1. Use `FilterContainer` as the wrapper
2. Add `FilterDropdown` components for select/dropdown filters
3. Use `SearchableMultiSelect` for searchable multi-select with chips
4. Use `useFilters` composable for filter state management
5. Use `useSearch` composable for search functionality

Example:

```vue
<template>
  <filter-container
    title="My Filters"
    :show-clear-button="hasActiveFilters"
    @clear="clearFilters"
  >
    <filter-dropdown
      id="category-filter"
      label="Category"
      v-model="selectedCategory"
      :options="categories"
      @update:model-value="onFilterChange"
    ></filter-dropdown>
  </filter-container>
</template>

<script>
const { ref, computed } = require("vue");
const FilterContainer = require("./FilterContainer.vue");
const FilterDropdown = require("./FilterDropdown.vue");
const { useFilters } = require("../composables/useFilters.js");

module.exports = exports = {
  name: "MyFilter",
  components: { FilterContainer, FilterDropdown },
  setup() {
    const selectedCategory = ref("");
    const categories = ["A", "B", "C"];

    const { applyFilters, clearFilters } = useFilters("my-filter-changed", {
      category: "",
    });

    const hasActiveFilters = computed(() => selectedCategory.value !== "");

    const onFilterChange = () => {
      applyFilters({ category: selectedCategory.value });
    };

    return {
      selectedCategory,
      categories,
      hasActiveFilters,
      onFilterChange,
      clearFilters,
    };
  },
};
</script>
```
