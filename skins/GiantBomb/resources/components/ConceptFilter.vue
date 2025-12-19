<template>
  <filter-container
    title="Filter"
    :show-clear-button="hasActiveFilters"
    @clear="clearFilters"
  >
    <filter-dropdown
      id="letter-filter"
      label="Letter"
      v-model="selectedLetter"
      :options="letterOptions"
      placeholder="All"
      @update:model-value="onFilterChange"
    ></filter-dropdown>

    <filter-dropdown
      id="sort-filter"
      label="Sort By"
      v-model="selectedSort"
      :options="sortOptions"
      value-key="value"
      label-key="label"
      @update:model-value="onFilterChange"
    ></filter-dropdown>

    <searchable-multi-select
      id="search-filter"
      label="Has Games"
      v-model:selected-items="selectedGames"
      v-model:require-all="requireAllGames"
      :search-results="searchResults"
      :is-searching="isSearching"
      :has-more-results="hasMoreResults"
      :is-loading-more="isLoadingMore"
      :show-match-all="true"
      match-all-text="Only return results if linked to all games"
      placeholder="Enter game name..."
      item-key="searchName"
      item-label="title"
      @search="handleSearch"
      @load-more="handleLoadMore"
      @select="onFilterChange"
      @remove="onFilterChange"
    >
      <template #result-item="{ results, selectItem }">
        <div
          v-for="game in results"
          :key="game.searchName"
          class="search-result-item filter-search-game"
          @mousedown="selectItem(game)"
        >
          <div class="filter-search-game__image">
            <img
              v-if="game.img"
              :src="game.img"
              :alt="game.title"
              @error="onImageError"
            />
            <div v-else class="filter-search-game__image-placeholder">
              <span>?</span>
            </div>
          </div>

          <div class="filter-search-game__info">
            <div class="filter-search-game__title">{{ game.title }}</div>
            <div class="filter-search-game__meta">
              <span v-if="game.releaseYear" class="filter-search-game__year">
                Game {{ game.releaseYear }}
              </span>
              <span
                v-if="game.platforms && game.platforms.length > 0"
                class="filter-search-game__platforms"
              >
                ({{ formatPlatformsProxy(game.platforms) }})
              </span>
            </div>
          </div>
        </div>
      </template>
    </searchable-multi-select>
  </filter-container>
</template>

<script>
const {
  defineComponent,
  ref,
  computed,
  toRefs,
  onMounted,
  watch,
} = require("vue");
const { useFilters } = require("../composables/useFilters.js");
const { useSearch } = require("../composables/useSearch.js");
const FilterContainer = require("./FilterContainer.vue");
const FilterDropdown = require("./FilterDropdown.vue");
const SearchableMultiSelect = require("./SearchableMultiSelect.vue");
const { formatPlatforms } = require("../helpers/displayUtils.js");

/**
 * ConceptFilter Component
 * Handles filtering of concepts by letter, sorting, and games
 * Note: FilterContainer, FilterDropdown, and SearchableMultiSelect are globally registered
 */
module.exports = exports = defineComponent({
  name: "ConceptFilter",
  components: {
    FilterContainer,
    FilterDropdown,
    SearchableMultiSelect,
  },
  props: {
    currentLetter: {
      type: String,
      default: "",
    },
    currentSort: {
      type: String,
      default: "",
    },
    currentRequireAllGames: {
      type: Boolean,
      default: false,
    },
    currentGames: {
      type: String,
      default: "",
    },
  },
  setup(props) {
    const { currentLetter, currentSort, currentGames, currentRequireAllGames } =
      toRefs(props);

    // Letter options
    const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("");
    const letterOptions = ["#", ...alphabet];

    // Sort options
    const sortOptions = [
      { value: "", label: "Default" },
      { value: "alphabetical", label: "Alphabetical" },
      { value: "last_edited", label: "Last Edited" },
      { value: "last_created", label: "Last Created" },
    ];

    // Filter state
    const selectedLetter = ref("");
    const selectedSort = ref("");
    const selectedGames = ref([]);
    const requireAllGames = ref(false);

    // Get current page size from URL
    const url = new URL(window.location.origin + window.location.pathname);
    const pageSize = url.searchParams.get("page_size") || 48;
    // Use filters composable
    const { applyFilters: applyFiltersBase, clearFilters: clearFiltersBase } =
      useFilters("concepts-filter-changed", {
        letter: "",
        sort: "",
        game_title: [],
        require_all_games: false,
        page: 1,
        page_size: pageSize,
      });

    // Search games function
    const searchGamesApi = async (query, page) => {
      const url = new URL(window.location.origin + window.location.pathname);
      url.searchParams.set("action", "get-games");
      url.searchParams.set("search", query);
      url.searchParams.set("page", page);
      url.searchParams.set("perPage", "10");

      const response = await fetch(url.toString());
      const data = await response.json();

      return {
        success: data.success,
        items: data.games || [],
        currentPage: data.pagination.currentPage,
        totalPages: data.pagination.totalPages,
        hasMore: data.pagination.totalPages > data.pagination.currentPage,
      };
    };

    // Use search composable
    const {
      searchResults,
      isSearching,
      hasMoreResults,
      isLoadingMore,
      debouncedSearch,
      loadMore,
    } = useSearch(searchGamesApi);

    const hasActiveFilters = computed(() => {
      return (
        selectedLetter.value !== "" ||
        selectedSort.value !== "" ||
        selectedGames.value.length > 0
      );
    });

    const onFilterChange = () => {
      const gameTitles = selectedGames.value.map((g) => g.searchName);
      applyFiltersBase({
        letter: selectedLetter.value,
        sort: selectedSort.value,
        game_title: gameTitles,
        require_all_games:
          selectedGames.value.length > 1 && requireAllGames.value,
        page: 1,
      });
    };

    const clearFilters = () => {
      selectedLetter.value = "";
      selectedSort.value = "";
      selectedGames.value = [];
      requireAllGames.value = false;
      clearFiltersBase({
        letter: "",
        sort: "",
        game_title: [],
        require_all_games: false,
        page: 1,
      });
    };

    const formatPlatformsProxy = (platforms) => {
      return formatPlatforms(platforms);
    };

    const lastSearchQuery = ref("");

    const handleSearch = (query) => {
      lastSearchQuery.value = query;
      debouncedSearch(query);
    };

    const handleLoadMore = () => {
      if (lastSearchQuery.value) {
        loadMore(lastSearchQuery.value);
      }
    };

    const onImageError = (e) => {
      e.target.style.display = "none";
      e.target.parentElement.innerHTML =
        '<div class="filter-search-game__image-placeholder"><span>?</span></div>';
    };

    onMounted(() => {
      // Read current filter values from props or URL
      const urlParams = new URLSearchParams(window.location.search);
      selectedLetter.value = urlParams.get("letter") || currentLetter.value;
      selectedSort.value = urlParams.get("sort") || currentSort.value;
      requireAllGames.value =
        urlParams.get("require_all_games") === "1" ||
        currentRequireAllGames.value === true;

      // Read multiple game_title parameters from URL
      const gameTitles = urlParams.getAll("game_title[]");
      if (gameTitles.length > 0) {
        selectedGames.value = gameTitles.map((searchName) => ({
          searchName: searchName,
          title: searchName.replace("Games/", " "),
        }));
      } else if (currentGames.value) {
        // Fallback to props if no game titles in URL
        const currentGamesArray = currentGames.value.split("||");
        selectedGames.value = currentGamesArray.map((game) => ({
          searchName: game,
          title: game.replace("Games/", " "),
        }));
      }
    });

    // Watch for requireAllGames changes to trigger filter update
    watch(requireAllGames, () => {
      if (selectedGames.value.length > 1) {
        onFilterChange();
      }
    });

    return {
      letterOptions,
      sortOptions,
      selectedLetter,
      selectedSort,
      selectedGames,
      requireAllGames,
      searchResults,
      isSearching,
      hasMoreResults,
      isLoadingMore,
      hasActiveFilters,
      onFilterChange,
      clearFilters,
      handleSearch,
      handleLoadMore,
      onImageError,
      formatPlatformsProxy,
    };
  },
});
</script>

<style>
/* Filter search game styles are in listingPage.css (.filter-search-game) */
</style>
