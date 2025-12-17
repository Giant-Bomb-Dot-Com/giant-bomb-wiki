<template>
  <main class="listing-main">
    <div v-if="loading" class="listing-loading">
      <div class="loading-spinner"></div>
      <p>Loading people...</p>
    </div>

    <div v-else-if="items.length > 0">
      <div class="listing-grid">
        <div v-for="(person, index) in items" :key="index" class="listing-card">
          <a :href="person.url" class="listing-card-link">
            <div v-if="person.image" class="listing-card-image">
              <img :src="person.image" :alt="person.caption" loading="lazy" />
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
              <h3 class="listing-card-title">{{ person.title }}</h3>
              <div v-if="person.deck" class="listing-card-deck">
                {{ person.deck }}
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
      <p>No people found for the selected filters.</p>
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
 * PeopleList Component
 * Displays people and handles async filtering and pagination
 */
module.exports = exports = defineComponent({
  name: "PeopleList",
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

    // Filter configuration for people
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
        conditionalOn: (filters) =>
          filters.gameTitles && filters.gameTitles.length > 1,
      },
    };

    // Pagination configuration for people
    const paginationConfig = {
      pageParam: "page",
      pageSizeParam: "page_size",
      responseFormat: "flat",
    };

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
      actionName: "get-people",
      dataKey: "people",
      filterEventName: "people-filter-changed",
      filterConfig,
      paginationConfig,
      defaultSort: "alphabetical",
      hasPagination: true,
    });

    onMounted(() => {
      initializeFromProps({
        initialData: propsRefs.initialData.value,
        totalCount: propsRefs.totalCount.value,
        currentPage: propsRefs.currentPage.value,
        totalPages: propsRefs.totalPages.value,
        pageSize: propsRefs.pageSize.value,
      });
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
</style>
