<template>
  <main class="listing-main">
    <div v-if="loading" class="listing-loading">
      <div class="loading-spinner"></div>
      <p>Loading concepts...</p>
    </div>

    <div v-else-if="concepts.length > 0">
      <div class="listing-grid">
        <div
          v-for="(concept, index) in concepts"
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
const { defineComponent, ref, toRefs, onMounted, onUnmounted } = require("vue");
const Pagination = require("./Pagination.vue");
const DEFAULT_PAGE_SIZE = 48;

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
    const { initialData, totalCount, currentPage, totalPages, pageSize } =
      toRefs(props);
    const concepts = ref([]);
    const loading = ref(false);
    const pageCount = ref(parseInt(totalCount.value) || 0);
    const page = ref(parseInt(currentPage.value) || 1);
    const pages = ref(parseInt(totalPages.value) || 1);
    const itemsPerPage = ref(parseInt(pageSize.value) || DEFAULT_PAGE_SIZE);

    // Helper function to decode HTML entities
    const { decodeHtmlEntities } = require("../helpers/htmlUtils.js");

    const fetchConcepts = async (
      letter = "",
      sort = "release_date",
      gameTitles = [],
      requireAllGames = false,
      pageNum = 1,
      pageSize = DEFAULT_PAGE_SIZE,
    ) => {
      loading.value = true;

      try {
        // Build query string manually to preserve [] notation for PHP arrays
        const queryParts = [];
        queryParts.push("action=get-concepts");

        if (letter) {
          queryParts.push(`letter=${encodeURIComponent(letter)}`);
        }
        if (sort !== "release_date") {
          queryParts.push(`sort=${encodeURIComponent(sort)}`);
        }
        if (gameTitles && gameTitles.length > 0) {
          gameTitles.forEach((gameTitle) => {
            queryParts.push(`game_title[]=${encodeURIComponent(gameTitle)}`);
          });
        }
        if (gameTitles && gameTitles.length > 1 && requireAllGames) {
          queryParts.push(`require_all_games=1`);
        }
        queryParts.push(`page=${pageNum}`);
        queryParts.push(`page_size=${pageSize}`);

        const url = `${window.location.pathname}?${queryParts.join("&")}`;

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
          concepts.value = data.concepts || [];
          pageCount.value = data.totalCount || 0;
          page.value = data.currentPage || 1;
          pages.value = data.totalPages || 1;
          itemsPerPage.value = data.pageSize || DEFAULT_PAGE_SIZE;
        } else {
          console.error("API returned error:", data);
          concepts.value = [];
        }
      } catch (error) {
        console.error("Failed to fetch concepts:", error);
        // Keep existing data on error
      } finally {
        loading.value = false;
      }
    };

    const handleFilterChange = (event) => {
      const {
        letter,
        sort,
        game_title: gameTitles,
        require_all_games: requireAllGames,
        page: pageNum,
      } = event.detail;
      fetchConcepts(
        letter,
        sort,
        gameTitles,
        requireAllGames || false,
        pageNum || 1,
        itemsPerPage.value,
      );
    };

    const handlePageChange = (event) => {
      const { page, itemsPerPage } = event;
      goToPage(page, itemsPerPage);
    };

    const goToPage = (pageNum, pageSize) => {
      if (pageNum < 1 || pageNum > pages.value) {
        return;
      }

      // Get current filters from URL
      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);
      const letter = params.get("letter") || "";
      const sort = params.get("sort") || "release_date";
      const gameTitles = params.getAll("game_title[]") || [];
      const requireAllGames = params.get("require_all_games") === "1";

      // Build query string manually to preserve [] notation
      const queryParts = [];
      if (letter) {
        queryParts.push(`letter=${encodeURIComponent(letter)}`);
      }
      if (sort !== "release_date") {
        queryParts.push(`sort=${encodeURIComponent(sort)}`);
      }
      if (gameTitles.length > 0) {
        gameTitles.forEach((gameTitle) => {
          queryParts.push(`game_title[]=${encodeURIComponent(gameTitle)}`);
        });
      }
      if (gameTitles.length > 1 && requireAllGames) {
        queryParts.push(`require_all_games=1`);
      }
      queryParts.push(`page=${pageNum}`);
      queryParts.push(`page_size=${pageSize}`);

      const queryString =
        queryParts.length > 0 ? `?${queryParts.join("&")}` : "";
      const newUrl = `${url.pathname}${queryString}`;
      window.history.pushState({}, "", newUrl);

      // Fetch new page
      fetchConcepts(
        letter,
        sort,
        gameTitles,
        requireAllGames,
        pageNum,
        pageSize,
      );

      // Scroll to top
      window.scrollTo({ top: 0, behavior: "smooth" });
    };

    onMounted(() => {
      // Parse initial server-rendered data
      try {
        const decoded = decodeHtmlEntities(initialData.value);
        concepts.value = JSON.parse(decoded);
        pageCount.value = parseInt(totalCount.value) || 0;
        page.value = parseInt(currentPage.value) || 1;
        pages.value = parseInt(totalPages.value) || 1;
        itemsPerPage.value = parseInt(pageSize.value) || DEFAULT_PAGE_SIZE;
      } catch (e) {
        console.error("Failed to parse initial data:", e);
        concepts.value = [];
      }

      // Listen for filter changes
      window.addEventListener("concepts-filter-changed", handleFilterChange);
    });

    onUnmounted(() => {
      window.removeEventListener("concepts-filter-changed", handleFilterChange);
    });

    return {
      concepts,
      loading,
      totalCount: pageCount,
      currentPage: page,
      totalPages: pages,
      goToPage,
      handlePageChange,
    };
  },
});
</script>

<style>
/* All shared styles are now in listingPage.css */
/* Component-specific styles only below if needed */
</style>
