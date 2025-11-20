<template>
  <main class="games-main">
    <div v-if="loading" class="games-loading">
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
                class="game-platforms"
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

      <!-- Server-side Pagination Controls -->
      <div class="pagination" v-if="paginationData.totalPages > 1">
        <div class="pagination-info">
          Showing {{ paginationData.startItem }}-{{ paginationData.endItem }}
          of {{ paginationData.totalItems }} items
        </div>

        <div class="pagination-controls">
          <a
            :href="buildPageUrl(1)"
            :class="[
              'pagination-btn',
              'pagination-first',
              { disabled: paginationData.currentPage === 1 },
            ]"
            :aria-disabled="paginationData.currentPage === 1"
            aria-label="First page"
          >
            ««
          </a>

          <a
            :href="buildPageUrl(paginationData.currentPage - 1)"
            :class="[
              'pagination-btn',
              'pagination-prev',
              { disabled: paginationData.currentPage === 1 },
            ]"
            :aria-disabled="paginationData.currentPage === 1"
            aria-label="Previous page"
          >
            ‹
          </a>

          <a
            v-for="page in visiblePages"
            :key="page"
            :href="buildPageUrl(page)"
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
          </a>

          <a
            :href="buildPageUrl(paginationData.currentPage + 1)"
            :class="[
              'pagination-btn',
              'pagination-next',
              { disabled: paginationData.currentPage === paginationData.totalPages },
            ]"
            :aria-disabled="
              paginationData.currentPage === paginationData.totalPages
            "
            aria-label="Next page"
          >
            ›
          </a>

          <a
            :href="buildPageUrl(paginationData.totalPages)"
            :class="[
              'pagination-btn',
              'pagination-last',
              { disabled: paginationData.currentPage === paginationData.totalPages },
            ]"
            :aria-disabled="
              paginationData.currentPage === paginationData.totalPages
            "
            aria-label="Last page"
          >
            »»
          </a>
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

    <div v-else class="no-games">
      <p>No games found matching your filters.</p>
      <p class="no-games-hint">Try adjusting your filters or clearing them.</p>
    </div>
  </main>
</template>

<script>
const { ref, computed, toRefs, onMounted } = require("vue");

/**
 * GameList Component
 * Displays games with server-side pagination
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

    // Helper function to decode HTML entities
    const decodeHtmlEntities = (text) => {
      const textarea = document.createElement("textarea");
      textarea.innerHTML = text;
      return textarea.value;
    };

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

    // Build URL for page navigation
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

    // Change items per page (reloads with new perPage parameter)
    const changeItemsPerPage = (event) => {
      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);

      const newPerPage = parseInt(event.target.value);
      if (newPerPage === 25) {
        params.delete("perPage");
      } else {
        params.set("perPage", newPerPage);
      }

      // Reset to page 1 when changing items per page
      params.delete("page");

      window.location.href = `${url.pathname}?${params.toString()}`;
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
            (parsedPagination.currentPage - 1) *
              parsedPagination.itemsPerPage +
            1,
          endItem: Math.min(
            parsedPagination.currentPage * parsedPagination.itemsPerPage,
            parsedPagination.totalItems,
          ),
        };
      } catch (e) {
        console.error("Failed to parse pagination info:", e);
      }
    });

    return {
      games,
      loading,
      paginationData,
      visiblePages,
      buildPageUrl,
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
