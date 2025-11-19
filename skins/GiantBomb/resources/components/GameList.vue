<template>
  <main class="games-main">
    <div v-if="loading" class="games-loading">
      <div class="loading-spinner"></div>
      <p>Loading games...</p>
    </div>

    <div v-else-if="filteredGames.length > 0">
      <div class="game-grid">
        <a
          v-for="(game, index) in filteredGames"
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
    </div>

    <div v-else class="no-games">
      <p>No games found matching your filters.</p>
      <p class="no-games-hint">Try adjusting your filters or clearing them.</p>
    </div>
  </main>
</template>

<script>
const { ref, computed, toRefs, onMounted, onUnmounted } = require("vue");

/**
 * GameList Component
 * Displays games and handles filtering
 */
module.exports = exports = {
  name: "GameList",
  props: {
    initialData: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    const { initialData } = toRefs(props);
    const allGames = ref([]);
    const loading = ref(false);
    const currentFilters = ref({
      search: "",
      platform: "",
      sort: "title-asc",
    });

    // Helper function to decode HTML entities
    const decodeHtmlEntities = (text) => {
      const textarea = document.createElement("textarea");
      textarea.innerHTML = text;
      return textarea.value;
    };

    // Compute filtered and sorted games based on current filters
    const filteredGames = computed(() => {
      let games = [...allGames.value];

      // Filter by search query
      if (currentFilters.value.search) {
        const searchLower = currentFilters.value.search.toLowerCase();
        games = games.filter((game) =>
          game.title.toLowerCase().includes(searchLower),
        );
      }

      // Filter by platform
      if (currentFilters.value.platform) {
        games = games.filter((game) => {
          if (!game.platforms || game.platforms.length === 0) return false;
          return game.platforms.some((p) =>
            p.includes(currentFilters.value.platform),
          );
        });
      }

      // Sort games
      const sortBy = currentFilters.value.sort || "title-asc";
      games.sort((a, b) => {
        switch (sortBy) {
          case "title-asc":
            return a.title.localeCompare(b.title);
          case "title-desc":
            return b.title.localeCompare(a.title);
          case "date-desc":
            // Newest first - treat empty dates as oldest
            if (!a.date && !b.date) return 0;
            if (!a.date) return 1;
            if (!b.date) return -1;
            return b.date.localeCompare(a.date);
          case "date-asc":
            // Oldest first - treat empty dates as newest
            if (!a.date && !b.date) return 0;
            if (!a.date) return -1;
            if (!b.date) return 1;
            return a.date.localeCompare(b.date);
          default:
            return 0;
        }
      });

      return games;
    });

    const handleFilterChange = (event) => {
      const { search, platform, sort } = event.detail;
      currentFilters.value = { search, platform, sort };
    };

    onMounted(() => {
      // Parse initial server-rendered data
      try {
        const decoded = decodeHtmlEntities(initialData.value);
        allGames.value = JSON.parse(decoded);
      } catch (e) {
        console.error("Failed to parse initial data:", e);
        allGames.value = [];
      }

      // Read initial filters from URL
      const urlParams = new URLSearchParams(window.location.search);
      currentFilters.value = {
        search: urlParams.get("search") || "",
        platform: urlParams.get("platform") || "",
        sort: urlParams.get("sort") || "title-asc",
      };

      // Listen for filter changes
      window.addEventListener("games-filter-changed", handleFilterChange);
    });

    onUnmounted(() => {
      window.removeEventListener("games-filter-changed", handleFilterChange);
    });

    return {
      filteredGames,
      loading,
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
