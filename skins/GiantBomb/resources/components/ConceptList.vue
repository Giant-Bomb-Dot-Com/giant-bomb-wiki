<template>
  <main class="listing-main">
    <div v-if="loading" class="listing-loading">
      <div class="loading-spinner"></div>
      <p>Loading concepts...</p>
    </div>

    <div v-else-if="items.length > 0">
      <div class="listing-grid">
        <div
          v-for="(concept, index) in items"
          :key="index"
          class="listing-card"
        >
          <a :href="concept.url" class="listing-card-link">
            <div v-if="concept.image" class="listing-card-image">
              <img :src="concept.image" :alt="concept.caption" loading="lazy" />
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
              <h3 class="listing-card-title">{{ concept.title }}</h3>
              <div v-if="concept.deck" class="listing-card-deck">
                {{ concept.deck }}
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
      <p>No concepts found for the selected filters.</p>
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
 * ConceptList Component
 * Displays concepts and handles async filtering and pagination
 */
module.exports = exports = defineComponent({
  name: "ConceptList",
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

    // Filter configuration for concepts
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
        default: "alphabetical",
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

    // Pagination configuration for concepts
    const paginationConfig = {
      pageParam: "page",
      pageSizeParam: "page_size",
      responseFormat: "flat",
    };

    // Use the shared list data composable with concepts configuration
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
      actionName: "get-concepts",
      dataKey: "concepts",
      filterEventName: "concepts-filter-changed",
      filterConfig,
      paginationConfig,
      defaultSort: "alphabetical",
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
