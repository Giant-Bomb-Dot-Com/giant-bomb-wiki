<template>
  <main class="listing-main">
    <div v-if="loading" class="listing-loading">
      <div class="loading-spinner"></div>
      <p>Loading games...</p>
    </div>

    <div v-else-if="items.length > 0">
      <div class="listing-grid">
        <div v-for="(game, index) in items" :key="index" class="listing-card">
          <a :href="game.url" class="listing-card-link">
            <div v-if="game.img" class="listing-card-image">
              <img :src="game.img" :alt="game.title" loading="lazy" />
            </div>
            <div
              v-else
              class="listing-card-image listing-card-image-placeholder"
            >
              <img
                src="https://www.giantbomb.com/a/uploads/original/11/110673/3026329-gb_default-16_9.png"
                alt="Giant Bomb Default Image"
                loading="lazy"
              />
            </div>

            <div class="listing-card-info">
              <h3 class="listing-card-title">{{ game.title }}</h3>
              <div v-if="game.date" class="listing-card-meta">
                {{ game.date }}
              </div>
              <div v-if="game.desc" class="listing-card-deck">
                {{ game.desc }}
              </div>
              <div
                v-if="game.platforms && game.platforms.length > 0"
                class="platform-badges"
              >
                <span
                  v-for="(platform, idx) in game.platforms.slice(0, 3)"
                  :key="idx"
                  class="platform-badge"
                  :title="getPlatformTitle(platform)"
                >
                  {{ getPlatformAbbrev(platform) }}
                </span>
                <span
                  v-if="game.platforms.length > 3"
                  class="platform-badge platform-badge-more"
                  :title="getRemainingPlatformsTitles(game.platforms)"
                >
                  +{{ game.platforms.length - 3 }}
                </span>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- Pagination Component -->
      <pagination
        :total-items="totalCount"
        :items-per-page="itemsPerPage"
        :current-page="currentPage"
        @page-change="handlePageChange"
        :items-per-page-options="[24, 48, 72, 96]"
      />
    </div>

    <div v-else class="listing-empty">
      <p>No games found matching your filters.</p>
      <p class="listing-empty-hint">
        Try adjusting your filters or clearing them.
      </p>
    </div>
  </main>
</template>

<script>
const { defineComponent, toRefs, onMounted, onUnmounted } = require("vue");
const Pagination = require("./Pagination.vue");
const { useListData, FILTER_TYPES } = require("../composables/useListData.js");

/**
 * GameList Component
 * Displays games and handles async filtering
 */
module.exports = exports = defineComponent({
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
    const propsRefs = toRefs(props);

    // Filter configuration for games
    const filterConfig = {
      search: {
        queryParam: "search",
        type: FILTER_TYPES.STRING,
        default: "",
        omitIfDefault: true,
      },
      platform: {
        queryParam: "platform",
        type: FILTER_TYPES.STRING,
        default: "",
        omitIfDefault: true,
      },
      sort: {
        queryParam: "sort",
        type: FILTER_TYPES.STRING,
        default: "title-asc",
        omitIfDefault: true,
      },
    };

    // Pagination configuration for games
    const paginationConfig = {
      pageParam: "page",
      pageSizeParam: "perPage",
      responseFormat: "nested",
      responseKey: "pagination",
    };

    // Use the shared list data composable with games configuration
    const {
      items,
      loading,
      totalCount,
      currentPage,
      totalPages,
      itemsPerPage,
      handlePageChange,
      initializeFromProps,
      setupFilterListener,
      teardownFilterListener,
    } = useListData({
      actionName: "get-games",
      dataKey: "games",
      filterEventName: "games-filter-changed",
      filterConfig,
      paginationConfig,
      hasPagination: true,
    });

    onMounted(() => {
      // Initialize from server-rendered props
      initializeFromProps({
        initialData: propsRefs.initialData.value,
        paginationInfo: propsRefs.paginationInfo.value,
      });

      // Setup filter event listener
      setupFilterListener();
    });

    onUnmounted(() => {
      teardownFilterListener();
    });

    // Helper functions for platform display
    const getPlatformTitle = (platform) => {
      // Handle both object format (new API) and string format (legacy)
      return typeof platform === "object" && platform.title
        ? platform.title
        : platform;
    };

    const getPlatformAbbrev = (platform) => {
      // Handle both object format (new API) and string format (legacy)
      return typeof platform === "object" && platform.abbrev
        ? platform.abbrev
        : platform;
    };

    const getRemainingPlatformsTitles = (platforms) => {
      // Get titles of platforms after the first 3
      return platforms
        .slice(3)
        .map((p) => getPlatformTitle(p))
        .join(", ");
    };

    return {
      items,
      loading,
      totalCount,
      currentPage,
      totalPages,
      itemsPerPage,
      handlePageChange,
      getPlatformTitle,
      getPlatformAbbrev,
      getRemainingPlatformsTitles,
    };
  },
});
</script>

<style>
/* All shared styles are now in listingPage.css */
/* Component-specific styles only below */
.listing-empty-hint {
  font-size: 0.95rem;
  color: #666;
  margin-top: 0.5rem;
}
</style>
