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
        <option value="release_date">Release Date</option>
        <option value="alphabetical">Alphabetical</option>
        <option value="last_edited">Last Edited</option>
        <option value="last_created">Last Created</option>
      </select>
    </div>

    <div class="filter-group search-group">
      <label for="search-filter" class="filter-label">Search Game</label>

      <!-- Selected games chips -->
      <div v-if="selectedGames.length > 0" class="selected-games">
        <div
          v-for="game in selectedGames"
          :key="game.searchName"
          class="game-chip"
        >
          <span class="game-chip-title">{{ game.title }}</span>
          <button
            @click="removeGame(game)"
            class="game-chip-remove"
            type="button"
            :title="`Remove ${game.title}`"
          >
            ×
          </button>
        </div>
      </div>

      <input
        id="search-filter"
        v-model="searchText"
        type="text"
        placeholder="Enter game name..."
        class="filter-input"
        @input="onSearchInput"
        @focus="onSearchFocus"
        @blur="onSearchBlur"
      />

      <!-- Match All Games Checkbox -->
      <div v-if="selectedGames.length > 1" class="filter-checkbox-group">
        <label class="filter-checkbox-label">
          <input
            type="checkbox"
            v-model="requireAllGames"
            @change="applyFilters"
            class="filter-checkbox"
          />
          <span>Only return results if linked to all games</span>
        </label>
      </div>

      <div v-if="showSearchResults" class="search-results">
        <div v-if="isSearching" class="search-loading">Searching...</div>

        <div v-else-if="searchResults.length > 0" class="search-results-list">
          <div
            v-for="game in searchResults"
            :key="game.searchName"
            class="search-result-item"
            @mousedown="selectGame(game)"
          >
            <div class="game-image">
              <img
                v-if="game.image"
                :src="game.image"
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

          <button
            v-if="hasMoreResults"
            @mousedown="loadMoreResults"
            class="load-more-btn"
            :disabled="isLoadingMore"
          >
            {{ isLoadingMore ? "Loading..." : "See more results" }}
          </button>
        </div>

        <div v-else-if="searchText.length >= 2" class="search-no-results">
          No games found
        </div>
      </div>
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
const { ref, computed, toRefs, onMounted, watch } = require("vue");

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
    const currentGamesArray = currentGames.value
      ? currentGames.value.split("||")
      : [];
    const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("");
    const selectedLetter = ref("");
    const selectedSort = ref("release_date");
    const selectedGames = ref([]);
    const searchText = ref("");
    const requireAllGames = ref(false);

    // Search state
    const searchResults = ref([]);
    const showSearchResults = ref(false);
    const isSearching = ref(false);
    const hasMoreResults = ref(false);
    const isLoadingMore = ref(false);
    const currentSearchPage = ref(1);
    const totalPages = ref(1);
    let debounceTimer = null;

    const hasActiveFilters = computed(() => {
      return (
        selectedLetter.value !== "" ||
        selectedSort.value !== "release_date" ||
        selectedGames.value.length > 0
      );
    });

    const applyFilters = () => {
      const url = new URL(window.location.href);

      // Build query string manually to preserve [] notation for PHP arrays
      const queryParts = [];

      // Add letter parameter
      if (selectedLetter.value) {
        queryParts.push(`letter=${encodeURIComponent(selectedLetter.value)}`);
      }

      // Add sort parameter
      if (selectedSort.value !== "release_date") {
        queryParts.push(`sort=${encodeURIComponent(selectedSort.value)}`);
      }

      // Add game_title[] parameters (preserve [] for PHP array parsing)
      if (selectedGames.value.length > 0) {
        selectedGames.value.forEach((game) => {
          queryParts.push(
            `game_title[]=${encodeURIComponent(game.searchName)}`,
          );
        });
      }

      // Add require_all_games parameter (only if multiple games selected)
      if (selectedGames.value.length > 1 && requireAllGames.value) {
        queryParts.push(`require_all_games=1`);
      }

      // Build the final URL
      const queryString =
        queryParts.length > 0 ? `?${queryParts.join("&")}` : "";
      const newUrl = `${url.pathname}${queryString}`;

      // Update URL without reloading (for bookmarking/sharing)
      window.history.pushState({}, "", newUrl);

      // Emit event for PlatformList to listen to
      window.dispatchEvent(
        new CustomEvent("platforms-filter-changed", {
          detail: {
            letter: selectedLetter.value,
            sort: selectedSort.value,
            gameTitles: selectedGames.value.map((g) => g.searchName),
            requireAllGames: requireAllGames.value,
            page: 1,
          },
        }),
      );
    };

    const clearFilters = () => {
      selectedLetter.value = "";
      selectedSort.value = "release_date";
      selectedGames.value = [];
      requireAllGames.value = false;

      // Update URL without reloading
      const url = new URL(window.location.href);
      window.history.pushState({}, "", url.pathname);

      // Emit event to reload all platforms
      window.dispatchEvent(
        new CustomEvent("platforms-filter-changed", {
          detail: {
            letter: "",
            sort: "release_date",
            gameTitles: [],
            requireAllGames: false,
            page: 1,
          },
        }),
      );
    };

    // Search games via API
    const searchGames = async (query, page = 1, append = false) => {
      if (query.length < 2) {
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

        const url = new URL(window.location.origin + window.location.pathname);
        url.searchParams.set("action", "get-games");
        url.searchParams.set("name", query);
        url.searchParams.set("page", page);
        url.searchParams.set("returnLimit", "10");

        const response = await fetch(url.toString());
        const data = await response.json();

        if (data.success) {
          if (append) {
            searchResults.value = [...searchResults.value, ...data.games];
          } else {
            searchResults.value = data.games;
          }

          currentSearchPage.value = data.currentPage;
          totalPages.value = data.totalPages;
          hasMoreResults.value = data.hasMore;
        }
      } catch (error) {
        console.error("Error searching games:", error);
      } finally {
        isSearching.value = false;
        isLoadingMore.value = false;
      }
    };

    // Debounced search input handler
    const onSearchInput = () => {
      if (debounceTimer) {
        clearTimeout(debounceTimer);
      }

      if (searchText.value.length < 2) {
        searchResults.value = [];
        showSearchResults.value = false;
        hasMoreResults.value = false;
        return;
      }

      showSearchResults.value = true;
      debounceTimer = setTimeout(() => {
        currentSearchPage.value = 1;
        searchGames(searchText.value, 1, false);
      }, 400);
    };

    const onSearchFocus = () => {
      if (searchText.value.length >= 2 && searchResults.value.length > 0) {
        showSearchResults.value = true;
      }
    };

    const onSearchBlur = () => {
      // Delay to allow click events on results to fire
      setTimeout(() => {
        showSearchResults.value = false;
      }, 200);
    };

    const loadMoreResults = () => {
      const nextPage = currentSearchPage.value + 1;
      searchGames(searchText.value, nextPage, true);
    };

    const selectGame = (game) => {
      // Check if game is already selected
      const alreadySelected = selectedGames.value.some(
        (g) => g.searchName === game.searchName,
      );

      if (!alreadySelected) {
        // Add game to selected games array
        selectedGames.value.push({
          searchName: game.searchName,
          title: game.title,
        });

        // Apply the filters using the standard method
        applyFilters();
      }

      // Close the search dropdown and clear search
      showSearchResults.value = false;
      searchText.value = "";
    };

    const removeGame = (game) => {
      // Remove game from selected games array
      selectedGames.value = selectedGames.value.filter(
        (g) => g.searchName !== game.searchName,
      );

      // Apply the filters to update
      applyFilters();
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
        currentRequireAllGames.value === "true";

      // Read multiple game_title parameters from URL
      const gameTitles = urlParams.getAll("game_title[]");
      if (gameTitles.length > 0) {
        selectedGames.value = gameTitles.map((searchName) => ({
          searchName: searchName,
          title: searchName.replace("Games/", " "),
        }));
      } else {
        // Fallback to props if no game titles in URL
        selectedGames.value = currentGamesArray.map((game) => ({
          searchName: game,
          title: game.replace("Games/", " "),
        }));
      }
    });

    return {
      alphabet,
      selectedLetter,
      selectedSort,
      selectedGames,
      searchText,
      searchResults,
      showSearchResults,
      isSearching,
      hasMoreResults,
      isLoadingMore,
      requireAllGames,
      hasActiveFilters,
      applyFilters,
      clearFilters,
      onSearchInput,
      onSearchFocus,
      onSearchBlur,
      loadMoreResults,
      selectGame,
      removeGame,
      formatPlatforms,
      onImageError,
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

/* Search functionality styles */
.search-group {
  position: relative;
}

/* Selected games chips */
.selected-games {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 10px;
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

.game-chip {
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

.game-chip-title {
  line-height: 1.2;
}

.game-chip-remove {
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

.game-chip-remove:hover {
  color: #fff;
  background: #e63946;
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
  display: flex;
  gap: 12px;
  padding: 10px;
  cursor: pointer;
  border-bottom: 1px solid #2a2a2a;
  transition: background 0.2s;
  align-items: flex-start;
}

.search-result-item:hover {
  background: #2a2a2a;
}

.search-result-item:last-child {
  border-bottom: none;
}

.game-image {
  flex-shrink: 0;
  width: 50px;
  height: 50px;
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
