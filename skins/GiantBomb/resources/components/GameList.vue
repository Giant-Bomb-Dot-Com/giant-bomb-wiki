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

      <!-- Pagination Component -->
      <Pagination
        v-if="paginationData.totalPages > 1"
        :total-items="paginationData.totalItems"
        :items-per-page="paginationData.itemsPerPage"
        :current-page="paginationData.currentPage"
        @page-change="handlePageChange"
      />
    </div>

    <div v-else class="empty-state">
      <p>No games found matching your filters.</p>
      <p class="empty-state-hint">
        Try adjusting your filters or clearing them.
      </p>
    </div>
  </main>
</template>

<script>
const { ref, toRefs, onMounted, onUnmounted } = require("vue");
const { decodeHtmlEntities } = require("../helpers/htmlUtils.js");
const Pagination = require("./Pagination.vue");

/**
 * GameList Component
 * Displays games and handles async filtering
 */
module.exports = exports = {
  name: "GameList",
  components: {
    Pagination,
  },
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

    // Handle pagination change from Pagination component
    const handlePageChange = ({ page, itemsPerPage }) => {
      // Get current URL params
      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);

      // Preserve existing search, platform, and sort params
      const search = params.get("search") || "";
      const platform = params.get("platform") || "";
      const sort = params.get("sort") || "title-asc";

      // Update page param
      if (page === 1) {
        params.delete("page");
      } else {
        params.set("page", page);
      }

      // Update itemsPerPage param
      if (itemsPerPage !== paginationData.value.itemsPerPage) {
        if (itemsPerPage === 25) {
          params.delete("perPage");
        } else {
          params.set("perPage", itemsPerPage);
        }
        // Reset to page 1 when changing items per page
        params.delete("page");
        page = 1;
      }

      // Update URL in browser
      window.history.pushState({}, "", `${url.pathname}?${params.toString()}`);

      // Update local pagination data immediately
      paginationData.value.itemsPerPage = itemsPerPage;
      paginationData.value.currentPage = page;

      // Fetch games for the new page
      fetchGames(search, platform, sort, page);
    };

    // Fetch games from API
    const fetchGames = async (
      search = "",
      platform = "",
      sort = "title-asc",
      page = 1,
    ) => {
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
          itemsPerPage: parsedPagination.itemsPerPage || 25,
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
      handlePageChange,
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
</style>
