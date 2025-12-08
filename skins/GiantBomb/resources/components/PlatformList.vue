<template>
  <main class="listing-main">
    <div v-if="loading" class="listing-loading">
      <div class="loading-spinner"></div>
      <p>Loading platforms...</p>
    </div>

    <div v-else-if="items.length > 0">
      <div class="listing-grid">
        <div
          v-for="(platform, index) in items"
          :key="index"
          class="listing-card"
        >
          <a :href="platform.url" class="listing-card-link">
            <div v-if="platform.image" class="listing-card-image">
              <img :src="platform.image" :alt="platform.title" loading="lazy" />
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
              <h3 class="listing-card-title">{{ platform.title }}</h3>
              <div v-if="platform.deck" class="listing-card-deck">
                {{ platform.deck }}
              </div>
              <div
                v-if="platform.releaseDateFormatted"
                class="listing-card-meta listing-card-date"
              >
                Launched on {{ platform.releaseDateFormatted }}
              </div>
              <div
                v-if="platform.gameCount"
                class="listing-card-meta listing-card-game-count"
              >
                Games: {{ platform.gameCount }}
              </div>
            </div>
          </a>
        </div>
      </div>

      <pagination
        :total-items="totalCount"
        :current-page="currentPage"
        @page-change="handlePageChange"
        :items-per-page-options="[24, 48, 72, 96]"
        :items-per-page="48"
      >
      </pagination>
    </div>

    <div v-else class="listing-empty">
      <p>No platforms found for the selected filters.</p>
    </div>
  </main>
</template>

<script>
const { defineComponent, toRefs, onMounted, onUnmounted } = require("vue");
const Pagination = require("./Pagination.vue");
const {
  useListData,
  DEFAULT_PAGE_SIZE,
  FILTER_TYPES,
} = require("../composables/useListData.js");

/**
 * PlatformList Component
 * Displays platforms and handles async filtering and pagination
 */
module.exports = exports = defineComponent({
  name: "PlatformList",
  components: {
    Pagination,
  },
  props: {
    initialData: {
      type: String,
      required: true,
    },
    totalCount: {
      type: String,
      default: "0",
    },
    currentPage: {
      type: String,
      default: "1",
    },
    totalPages: {
      type: String,
      default: "1",
    },
    pageSize: {
      type: String,
      default: DEFAULT_PAGE_SIZE.toString(),
    },
  },
  setup(props) {
    const propsRefs = toRefs(props);

    // Filter configuration for platforms
    const filterConfig = {
      letter: {
        queryParam: "letter",
        type: FILTER_TYPES.STRING,
        default: "",
        omitIfDefault: true,
      },
      sort: {
        queryParam: "sort",
        type: FILTER_TYPES.STRING,
        default: "release_date",
        omitIfDefault: true,
      },
      gameTitles: {
        eventKey: "game_title",
        queryParam: "game_title[]",
        type: FILTER_TYPES.ARRAY,
        default: [],
      },
      requireAllGames: {
        eventKey: "require_all_games",
        queryParam: "require_all_games",
        type: FILTER_TYPES.BOOLEAN,
        default: false,
        booleanValue: "1",
        // Only include if gameTitles has more than 1 item
        conditionalOn: (filters) =>
          filters.gameTitles && filters.gameTitles.length > 1,
      },
    };

    // Pagination configuration for platforms
    const paginationConfig = {
      pageParam: "page",
      pageSizeParam: "page_size",
      responseFormat: "flat",
    };

    // Use the shared list data composable with platforms configuration
    const {
      items,
      loading,
      totalCount,
      currentPage,
      totalPages,
      handlePageChange,
      initializeFromProps,
      setupFilterListener,
      teardownFilterListener,
    } = useListData({
      actionName: "get-platforms",
      dataKey: "platforms",
      filterEventName: "platforms-filter-changed",
      filterConfig,
      paginationConfig,
      defaultSort: "release_date",
      hasPagination: true,
    });

    onMounted(() => {
      // Initialize from server-rendered props
      initializeFromProps({
        initialData: propsRefs.initialData.value,
        totalCount: propsRefs.totalCount.value,
        currentPage: propsRefs.currentPage.value,
        totalPages: propsRefs.totalPages.value,
        pageSize: propsRefs.pageSize.value,
      });

      // Setup filter event listener
      setupFilterListener();
    });

    onUnmounted(() => {
      teardownFilterListener();
    });

    return {
      items,
      loading,
      totalCount,
      currentPage,
      totalPages,
      handlePageChange,
    };
  },
});
</script>

<style>
/* All shared styles are now in listingPage.css */
/* Component-specific styles only below if needed */
</style>
