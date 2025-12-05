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
          class="search-result-item game-result"
          @mousedown="selectItem(game)"
        >
          <div class="game-image">
            <img
              v-if="game.img"
              :src="game.img"
              :alt="game.title"
              @error="onImageError"
            />
            <div v-else class="game-image-placeholder">
              <span>?</span>
            </div>
          </div>

          <div class="game-info">
            <div class="game-title">{{ game.title }}</div>
            <div class="game-meta">
              <span v-if="game.releaseYear" class="game-year">
                Game {{ game.releaseYear }}
              </span>
              <span
                v-if="game.platforms && game.platforms.length > 0"
                class="game-platforms"
              >
                ({{ formatPlatforms(game.platforms) }})
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

/**
 * PlatformFilter Component
 * Handles filtering of platforms by letter, sorting, and games
 * Note: FilterContainer, FilterDropdown, and SearchableMultiSelect are globally registered
 */
module.exports = exports = defineComponent({
  name: "PlatformFilter",
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
      default: "release_date",
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
      { value: "release_date", label: "Release Date" },
      { value: "alphabetical", label: "Alphabetical" },
      { value: "last_edited", label: "Last Edited" },
      { value: "last_created", label: "Last Created" },
    ];

    // Filter state
    const selectedLetter = ref("");
    const selectedSort = ref("release_date");
    const selectedGames = ref([]);
    const requireAllGames = ref(false);

    // Get current page size from URL
    const url = new URL(window.location.origin + window.location.pathname);
    const pageSize = url.searchParams.get("page_size") || 48;
    // Use filters composable
    const { applyFilters: applyFiltersBase, clearFilters: clearFiltersBase } =
      useFilters("platforms-filter-changed", {
        letter: "",
        sort: "release_date",
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
        selectedSort.value !== "release_date" ||
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
      selectedSort.value = "release_date";
      selectedGames.value = [];
      requireAllGames.value = false;
      clearFiltersBase({
        letter: "",
        sort: "release_date",
        game_title: [],
        require_all_games: false,
        page: 1,
      });
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

    const formatPlatforms = (platforms) => {
      if (!platforms || platforms.length === 0) return "";

      const displayCount = 3;
      const shown = platforms
        .slice(0, displayCount)
        .map((p) => p.abbrev)
        .join(", ");
      const remaining = platforms.length - displayCount;

      if (remaining > 0) {
        return `${shown} +${remaining} more`;
      }
      return shown;
    };

    const onImageError = (e) => {
      e.target.style.display = "none";
      e.target.parentElement.innerHTML =
        '<div class="game-image-placeholder"><span>?</span></div>';
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
      formatPlatforms,
      onImageError,
    };
  },
});
</script>

<style>
/* Game-specific search result styles */
.game-result {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.game-image {
  flex-shrink: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
  border-radius: 4px;
  background: #2a2a2a;
  display: flex;
  align-items: center;
  justify-content: center;
}

.game-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.game-image-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #333;
  color: #666;
  font-size: 1.5rem;
  font-weight: bold;
}

.game-info {
  flex: 1;
  min-width: 0;
}

.game-title {
  color: #fff;
  font-size: 0.95rem;
  font-weight: 600;
  margin-bottom: 4px;
  line-height: 1.3;
}

.game-meta {
  color: #999;
  font-size: 0.8rem;
  line-height: 1.4;
}

.game-year {
  margin-right: 4px;
}

.game-platforms {
  color: #aaa;
}
</style>
