<template>
  <main class="platforms-main">
    <div v-if="loading" class="platforms-loading">
      <div class="loading-spinner"></div>
      <p>Loading platforms...</p>
    </div>

    <div v-else-if="platforms.length > 0">
      <div class="platforms-grid">
        <div
          v-for="(platform, index) in platforms"
          :key="index"
          class="platform-card"
        >
          <a :href="platform.url" class="platform-card-link">
            <div v-if="platform.image" class="platform-image">
              <img
                :src="platform.image"
                :alt="platform.title"
                loading="lazy"
              />
            </div>
            <div v-else class="platform-image platform-image-placeholder">
              <img
                src="https://www.giantbomb.com/a/uploads/original/11/110673/3026329-gb_default-16_9.png"
                alt="Giant Bomb Default Image"
                loading="lazy"
              />
            </div>

            <div class="platform-info">
              <h3 class="platform-title">{{ platform.title }}</h3>
              <div v-if="platform.deck" class="platform-deck">
                {{ platform.deck }}
              </div>
              <div v-if="platform.releaseDateFormatted" class="platform-date">
                Released: {{ platform.releaseDateFormatted }}
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="pagination">
        <button
          @click="goToPage(currentPage - 1)"
          :disabled="currentPage <= 1"
          class="pagination-btn"
        >
          Previous
        </button>

        <div class="pagination-info">
          Page {{ currentPage }} of {{ totalPages }}
          <span class="pagination-total">({{ totalCount }} total)</span>
        </div>

        <button
          @click="goToPage(currentPage + 1)"
          :disabled="currentPage >= totalPages"
          class="pagination-btn"
        >
          Next
        </button>
      </div>
    </div>

    <div v-else class="no-platforms">
      <p>No platforms found for the selected filters.</p>
    </div>
  </main>
</template>

<script>
const { ref, toRefs, onMounted, onUnmounted } = require("vue");

/**
 * PlatformList Component
 * Displays platforms and handles async filtering and pagination
 */
module.exports = exports = {
  name: "PlatformList",
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
  },
  setup(props) {
    const { initialData, totalCount, currentPage, totalPages } = toRefs(props);
    const platforms = ref([]);
    const loading = ref(false);
    const pageCount = ref(parseInt(totalCount.value) || 0);
    const page = ref(parseInt(currentPage.value) || 1);
    const pages = ref(parseInt(totalPages.value) || 1);

    // Helper function to decode HTML entities
    const decodeHtmlEntities = (text) => {
      const textarea = document.createElement("textarea");
      textarea.innerHTML = text;
      return textarea.value;
    };

    const fetchPlatforms = async (letter = "", sort = "alphabetical", pageNum = 1) => {
      loading.value = true;

      try {
        // Build API URL
        const params = new URLSearchParams();
        params.set("action", "get-platforms");
        if (letter) params.set("letter", letter);
        if (sort !== "alphabetical") params.set("sort", sort);
        params.set("page", pageNum.toString());

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
          platforms.value = data.platforms || [];
          pageCount.value = data.totalCount || 0;
          page.value = data.currentPage || 1;
          pages.value = data.totalPages || 1;
        } else {
          console.error("API returned error:", data);
          platforms.value = [];
        }
      } catch (error) {
        console.error("Failed to fetch platforms:", error);
        // Keep existing data on error
      } finally {
        loading.value = false;
      }
    };

    const handleFilterChange = (event) => {
      const { letter, sort, page: pageNum } = event.detail;
      fetchPlatforms(letter, sort, pageNum || 1);
    };

    const goToPage = (pageNum) => {
      if (pageNum < 1 || pageNum > pages.value) {
        return;
      }

      // Update URL
      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);
      params.set("page", pageNum.toString());
      const newUrl = `${url.pathname}?${params.toString()}`;
      window.history.pushState({}, "", newUrl);

      // Get current filters
      const letter = params.get("letter") || "";
      const sort = params.get("sort") || "alphabetical";

      // Fetch new page
      fetchPlatforms(letter, sort, pageNum);

      // Scroll to top
      window.scrollTo({ top: 0, behavior: "smooth" });
    };

    onMounted(() => {
      // Parse initial server-rendered data
      try {
        const decoded = decodeHtmlEntities(initialData.value);
        platforms.value = JSON.parse(decoded);
        pageCount.value = parseInt(totalCount.value) || 0;
        page.value = parseInt(currentPage.value) || 1;
        pages.value = parseInt(totalPages.value) || 1;
      } catch (e) {
        console.error("Failed to parse initial data:", e);
        platforms.value = [];
      }

      // Listen for filter changes
      window.addEventListener("platforms-filter-changed", handleFilterChange);
    });

    onUnmounted(() => {
      window.removeEventListener("platforms-filter-changed", handleFilterChange);
    });

    return {
      platforms,
      loading,
      totalCount: pageCount,
      currentPage: page,
      totalPages: pages,
      goToPage,
    };
  },
};
</script>

<style>
.platforms-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #999;
}

.loading-spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #333;
  border-top-color: #e63946;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 20px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.platforms-loading p {
  font-size: 1.1rem;
  margin: 0;
}

.no-platforms {
  text-align: center;
  padding: 60px 20px;
  color: #999;
  font-size: 1.2rem;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 20px;
  margin-top: 40px;
  padding: 20px;
}

.pagination-btn {
  padding: 10px 20px;
  background: #2a2a2a;
  border: 1px solid #444;
  border-radius: 4px;
  color: #fff;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.2s;
}

.pagination-btn:hover:not(:disabled) {
  background: #333;
  border-color: #666;
}

.pagination-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pagination-info {
  font-size: 0.95rem;
  color: #ccc;
}

.pagination-total {
  color: #888;
  font-size: 0.85rem;
  margin-left: 5px;
}
</style>

