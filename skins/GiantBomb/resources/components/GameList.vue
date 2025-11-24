<template>
  <main class="games-main">
    <div v-if="loading" class="item-loading">
      <div class="loading-spinner"></div>
      <p>Loading games...</p>
    </div>

    <div v-else-if="games.length > 0">
      <div class="game-grid">
        <a
          v-for="(game, index) in games"
          :key="index"
          :href="game.url"
          class="game-card-link"
        >
          <div class="game-card">
            <div class="game-image">
              <img
                v-if="game.img"
                :src="game.img"
                :alt="game.title"
                loading="lazy"
              />
              <div v-else class="game-image-placeholder">
                <span class="game-placeholder-icon">🎮</span>
              </div>
            </div>
            <div class="game-info">
              <h3 class="game-title">{{ game.title }}</h3>
              <p v-if="game.date" class="game-date">{{ game.date }}</p>
              <div
                v-if="game.platforms && game.platforms.length > 0"
                class="item-platforms"
              >
                <span
                  v-for="(platform, idx) in game.platforms.slice(0, 3)"
                  :key="idx"
                  class="platform-badge"
                  :title="platform"
                >
                  {{ platform }}
                </span>
                <span
                  v-if="game.platforms.length > 3"
                  class="platform-badge platform-badge-more"
                  :title="game.platforms.slice(3).join(', ')"
                >
                  +{{ game.platforms.length - 3 }}
                </span>
              </div>
              <p v-if="game.desc" class="game-desc">{{ game.desc }}</p>
            </div>
          </div>
        </a>
      </div>

      <!-- Async Pagination Controls -->
      <div class="pagination" v-if="paginationData.totalPages > 1">
        <div class="pagination-info">
          Showing {{ paginationData.startItem }}-{{ paginationData.endItem }} of
          {{ paginationData.totalItems }} items
        </div>

        <div class="pagination-controls">
          <button
            @click.prevent="goToPage(1)"
            :class="[
              'pagination-btn',
              'pagination-first',
              { disabled: paginationData.currentPage === 1 },
            ]"
            :disabled="paginationData.currentPage === 1"
            aria-label="First page"
          >
            ««
          </button>

          <button
            @click.prevent="goToPage(paginationData.currentPage - 1)"
            :class="[
              'pagination-btn',
              'pagination-prev',
              { disabled: paginationData.currentPage === 1 },
            ]"
            :disabled="paginationData.currentPage === 1"
            aria-label="Previous page"
          >
            ‹
          </button>

          <button
            v-for="page in visiblePages"
            :key="page"
            @click.prevent="goToPage(page)"
            :class="[
              'pagination-btn',
              'pagination-page',
              { active: page === paginationData.currentPage },
            ]"
            :aria-label="`Page ${page}`"
            :aria-current="
              page === paginationData.currentPage ? 'page' : undefined
            "
          >
            {{ page }}
          </button>

          <button
            @click.prevent="goToPage(paginationData.currentPage + 1)"
            :class="[
              'pagination-btn',
              'pagination-next',
              {
                disabled:
                  paginationData.currentPage === paginationData.totalPages,
              },
            ]"
            :disabled="
              paginationData.currentPage === paginationData.totalPages
            "
            aria-label="Next page"
          >
            ›
          </button>

          <button
            @click.prevent="goToPage(paginationData.totalPages)"
            :class="[
              'pagination-btn',
              'pagination-last',
              {
                disabled:
                  paginationData.currentPage === paginationData.totalPages,
              },
            ]"
            :disabled="
              paginationData.currentPage === paginationData.totalPages
            "
            aria-label="Last page"
          >
            »»
          </button>
        </div>

        <div class="pagination-size">
          <label for="items-per-page-games">Items per page:</label>
          <select
            id="items-per-page-games"
            :value="paginationData.itemsPerPage"
            @change="changeItemsPerPage"
            class="pagination-select"
          >
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="75">75</option>
            <option :value="100">100</option>
          </select>
        </div>
      </div>
    </div>

    <div v-else class="empty-state">
      <p>No games found matching your filters.</p>
      <p class="empty-state-hint">Try adjusting your filters or clearing them.</p>
    </div>
  </main>
</template>

<script>
const { ref, computed, toRefs, onMounted, onUnmounted } = require("vue");
const { decodeHtmlEntities } = require("../helpers/htmlUtils.js");

/**
 * GameList Component
 * Displays games and handles async filtering
 */
module.exports = exports = {
  name: "GameList",
  props: {
    initialData: {
      type: String,
      required: true,
    },
    paginationInfo: {
      type: String,
      required: false,
      default: "{}",
    },
  },
  setup(props) {
    const { initialData, paginationInfo } = toRefs(props);
    const games = ref([]);
    const loading = ref(false);
    const paginationData = ref({
      currentPage: 1,
      totalPages: 1,
      itemsPerPage: 25,
      totalItems: 0,
      startItem: 0,
      endItem: 0,
    });

    // Calculate visible pages for pagination
    const visiblePages = computed(() => {
      const total = paginationData.value.totalPages;
      const current = paginationData.value.currentPage;
      const maxVisible = 5;

      if (total <= maxVisible) {
        return Array.from({ length: total }, (_, i) => i + 1);
      }

      const halfVisible = Math.floor(maxVisible / 2);
      let start = Math.max(1, current - halfVisible);
      let end = Math.min(total, start + maxVisible - 1);

      if (end - start < maxVisible - 1) {
        start = Math.max(1, end - maxVisible + 1);
      }

      return Array.from({ length: end - start + 1 }, (_, i) => start + i);
    });

    // Navigate to a specific page
    const goToPage = (page) => {
      if (page < 1 || page > paginationData.value.totalPages) {
        return;
      }

      // Update URL
      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);

      if (page === 1) {
        params.delete("page");
      } else {
        params.set("page", page);
      }

      window.history.pushState({}, "", `${url.pathname}?${params.toString()}`);

      // Fetch games for the new page
      const search = params.get("search") || "";
      const platform = params.get("platform") || "";
      const sort = params.get("sort") || "title-asc";
      fetchGames(search, platform, sort, page);
    };

    // Build URL for page navigation (kept for compatibility)
    const buildPageUrl = (page) => {
      if (page < 1 || page > paginationData.value.totalPages) {
        return "#";
      }

      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);

      if (page === 1) {
        params.delete("page");
      } else {
        params.set("page", page);
      }

      return `${url.pathname}?${params.toString()}`;
    };

    // Fetch games from API
    const fetchGames = async (search = "", platform = "", sort = "title-asc", page = 1) => {
      loading.value = true;

      try {
        // Build API URL
        const params = new URLSearchParams();
        params.set("action", "get-games");
        if (search) params.set("search", search);
        if (platform) params.set("platform", platform);
        if (sort && sort !== "title-asc") params.set("sort", sort);
        if (page > 1) params.set("page", page);
        params.set("perPage", paginationData.value.itemsPerPage);

        const url = `${window.location.pathname}?${params.toString()}`;

        const response = await fetch(url, {
          method: "GET",
          credentials: "same-origin",
          headers: {
            Accept: "application/json",
          },
        });

        if (!response.ok) {
          const text = await response.text();
          console.error("Response body:", text);
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
          games.value = data.games || [];
          paginationData.value = data.pagination || {
            currentPage: 1,
            totalPages: 1,
            itemsPerPage: 25,
            totalItems: 0,
            startItem: 0,
            endItem: 0,
          };
        } else {
          console.error("API returned error:", data);
          games.value = [];
        }
      } catch (error) {
        console.error("Failed to fetch games:", error);
        // Keep existing data on error
      } finally {
        loading.value = false;
      }
    };

    // Handle filter change events
    const handleFilterChange = (event) => {
      const { search, platform, sort, page } = event.detail;
      fetchGames(search, platform, sort, page);
    };

    // Change items per page (now uses async fetch)
    const changeItemsPerPage = (event) => {
      const newPerPage = parseInt(event.target.value);
      paginationData.value.itemsPerPage = newPerPage;

      // Update URL
      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);

      if (newPerPage === 25) {
        params.delete("perPage");
      } else {
        params.set("perPage", newPerPage);
      }
      params.delete("page");

      window.history.pushState({}, "", `${url.pathname}?${params.toString()}`);

      // Fetch with new page size
      const search = params.get("search") || "";
      const platform = params.get("platform") || "";
      const sort = params.get("sort") || "title-asc";
      fetchGames(search, platform, sort, 1);
    };

    onMounted(() => {
      // Parse initial server-rendered data
      try {
        const decoded = decodeHtmlEntities(initialData.value);
        games.value = JSON.parse(decoded);
      } catch (e) {
        console.error("Failed to parse initial data:", e);
        games.value = [];
      }

      // Parse pagination info
      try {
        const decodedPagination = decodeHtmlEntities(paginationInfo.value);
        const parsedPagination = JSON.parse(decodedPagination);
        paginationData.value = {
          currentPage: parsedPagination.currentPage || 1,
          totalPages: parsedPagination.totalPages || 1,
          itemsPerPage: parsedPagination.itemsPerPage || 20,
          totalItems: parsedPagination.totalItems || 0,
          startItem:
            (parsedPagination.currentPage - 1) * parsedPagination.itemsPerPage +
            1,
          endItem: Math.min(
            parsedPagination.currentPage * parsedPagination.itemsPerPage,
            parsedPagination.totalItems,
          ),
        };
      } catch (e) {
        console.error("Failed to parse pagination info:", e);
      }

      // Listen for filter changes
      window.addEventListener("games-filter-changed", handleFilterChange);
    });

    onUnmounted(() => {
      window.removeEventListener("games-filter-changed", handleFilterChange);
    });

    return {
      games,
      loading,
      paginationData,
      visiblePages,
      buildPageUrl,
      goToPage,
      changeItemsPerPage,
    };
  },
};
</script>

<style>
/* Shared grid/list styles are in itemGrid.css */

/* Component-specific styles */
.games-count {
  font-size: 0.9rem;
  color: #999;
  margin: 0;
}

.game-image-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
}

.game-placeholder-icon {
  font-size: 4rem;
  opacity: 0.3;
}

/* Pagination styles */
.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 30px 0 20px 0;
  gap: 20px;
  flex-wrap: wrap;
  border-top: 1px solid #333;
  margin-top: 30px;
}

.pagination-info {
  font-size: 0.9rem;
  color: #999;
}

.pagination-controls {
  display: flex;
  gap: 5px;
  align-items: center;
}

.pagination-btn {
  min-width: 36px;
  height: 36px;
  padding: 8px 12px;
  background: #2a2a2a;
  border: 1px solid #444;
  border-radius: 4px;
  color: #ccc;
  font-size: 0.9rem;
  text-decoration: none;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pagination-btn:hover:not(.disabled) {
  background: #3a3a3a;
  border-color: #e63946;
  color: #fff;
}

.pagination-btn.disabled {
  opacity: 0.3;
  cursor: not-allowed;
  pointer-events: none;
}

.pagination-btn.active {
  background: #e63946;
  border-color: #e63946;
  color: #fff;
  font-weight: 600;
  pointer-events: none;
}

.pagination-first,
.pagination-last {
  font-weight: bold;
}

.pagination-prev,
.pagination-next {
  font-size: 1.2rem;
  font-weight: bold;
}

.pagination-size {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.9rem;
  color: #999;
}

.pagination-select {
  padding: 6px 10px;
  background: #2a2a2a;
  border: 1px solid #444;
  border-radius: 4px;
  color: #ccc;
  font-size: 0.9rem;
  cursor: pointer;
}

.pagination-select:hover {
  border-color: #e63946;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .pagination {
    flex-direction: column;
    align-items: stretch;
    gap: 15px;
  }

  .pagination-info {
    text-align: center;
  }

  .pagination-controls {
    justify-content: center;
    flex-wrap: wrap;
  }

  .pagination-size {
    justify-content: center;
  }

  .pagination-btn {
    min-width: 32px;
    height: 32px;
    padding: 6px 10px;
    font-size: 0.85rem;
  }
}
</style>
