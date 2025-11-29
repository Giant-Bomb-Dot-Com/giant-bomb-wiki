const { ref } = require("vue");

/**
 * useSearch Composable
 * Shared logic for search functionality with debouncing and pagination
 */
function useSearch(
  searchFunction,
  { debounceMs = 400, minSearchLength = 2 } = {},
) {
  const searchResults = ref([]);
  const isSearching = ref(false);
  const hasMoreResults = ref(false);
  const isLoadingMore = ref(false);
  const currentSearchPage = ref(1);
  const totalPages = ref(1);
  let debounceTimer = null;

  const search = async (query, page = 1, append = false) => {
    if (query.length < minSearchLength) {
      searchResults.value = [];
      hasMoreResults.value = false;
      return;
    }

    try {
      if (append) {
        isLoadingMore.value = true;
      } else {
        isSearching.value = true;
      }

      const result = await searchFunction(query, page);

      if (result.success) {
        if (append) {
          searchResults.value = [...searchResults.value, ...result.items];
        } else {
          searchResults.value = result.items;
        }

        currentSearchPage.value = result.currentPage || page;
        totalPages.value = result.totalPages || 1;
        hasMoreResults.value = result.hasMore || false;
      }
    } catch (error) {
      console.error("Error searching:", error);
    } finally {
      isSearching.value = false;
      isLoadingMore.value = false;
    }
  };

  const debouncedSearch = (query, page = 1) => {
    if (debounceTimer) {
      clearTimeout(debounceTimer);
    }

    if (query.length < minSearchLength) {
      searchResults.value = [];
      hasMoreResults.value = false;
      return;
    }

    debounceTimer = setTimeout(() => {
      currentSearchPage.value = 1;
      search(query, page, false);
    }, debounceMs);
  };

  const loadMore = (query) => {
    const nextPage = currentSearchPage.value + 1;
    search(query, nextPage, true);
  };

  const reset = () => {
    searchResults.value = [];
    hasMoreResults.value = false;
    isSearching.value = false;
    isLoadingMore.value = false;
    currentSearchPage.value = 1;
    totalPages.value = 1;
  };

  return {
    searchResults,
    isSearching,
    hasMoreResults,
    isLoadingMore,
    currentSearchPage,
    totalPages,
    search,
    debouncedSearch,
    loadMore,
    reset,
  };
}

module.exports = { useSearch };

