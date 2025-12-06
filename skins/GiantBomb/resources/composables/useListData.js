const { ref, onMounted, onUnmounted } = require("vue");
const { decodeHtmlEntities } = require("../helpers/htmlUtils.js");

const DEFAULT_PAGE_SIZE = 48;

/**
 * useListData Composable
 * Shared logic for list components with pagination, filtering, and API fetching
 *
 * @param {Object} config - Configuration object
 * @param {string} config.actionName - API action name (e.g., 'get-platforms', 'get-concepts')
 * @param {string} config.dataKey - Key in API response containing items (e.g., 'platforms', 'concepts')
 * @param {string} config.filterEventName - Event name to listen for filter changes
 * @param {string} config.defaultSort - Default sort value (e.g., 'release_date', 'alphabetical')
 * @param {boolean} config.hasPagination - Whether to include pagination support (default: true)
 */
function useListData(config) {
  const {
    actionName,
    dataKey,
    filterEventName,
    defaultSort = "alphabetical",
    hasPagination = true,
  } = config;

  // State
  const items = ref([]);
  const loading = ref(false);
  const totalCount = ref(0);
  const currentPage = ref(1);
  const totalPages = ref(1);
  const itemsPerPage = ref(DEFAULT_PAGE_SIZE);

  /**
   * Build query string for API request
   */
  const buildQueryString = (
    filters = {},
    pageNum = 1,
    pageSize = DEFAULT_PAGE_SIZE,
  ) => {
    const queryParts = [];
    queryParts.push(`action=${actionName}`);

    const {
      letter = "",
      sort = defaultSort,
      gameTitles = [],
      requireAllGames = false,
    } = filters;

    if (letter) {
      queryParts.push(`letter=${encodeURIComponent(letter)}`);
    }
    if (sort !== defaultSort) {
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

    if (hasPagination) {
      queryParts.push(`page=${pageNum}`);
      queryParts.push(`page_size=${pageSize}`);
    }

    return queryParts.join("&");
  };

  /**
   * Fetch data from the API
   */
  const fetchData = async (
    filters = {},
    pageNum = 1,
    pageSize = DEFAULT_PAGE_SIZE,
  ) => {
    loading.value = true;

    try {
      const queryString = buildQueryString(filters, pageNum, pageSize);
      const url = `${window.location.pathname}?${queryString}`;

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
        items.value = data[dataKey] || [];
        if (hasPagination) {
          totalCount.value = data.totalCount || 0;
          currentPage.value = data.currentPage || 1;
          totalPages.value = data.totalPages || 1;
          itemsPerPage.value = data.pageSize || DEFAULT_PAGE_SIZE;
        }
      } else {
        console.error("API returned error:", data);
        items.value = [];
      }
    } catch (error) {
      console.error(`Failed to fetch ${dataKey}:`, error);
      // Keep existing data on error
    } finally {
      loading.value = false;
    }
  };

  /**
   * Handle filter change events from filter components
   */
  const handleFilterChange = (event) => {
    const {
      letter,
      sort,
      game_title: gameTitles,
      require_all_games: requireAllGames,
      page: pageNum,
    } = event.detail;

    const filters = {
      letter,
      sort,
      gameTitles,
      requireAllGames: requireAllGames || false,
    };

    fetchData(filters, pageNum || 1, itemsPerPage.value);
  };

  /**
   * Handle page change from pagination component
   */
  const handlePageChange = (event) => {
    const { page, itemsPerPage: newItemsPerPage } = event;
    goToPage(page, newItemsPerPage);
  };

  /**
   * Navigate to a specific page
   */
  const goToPage = (pageNum, pageSize) => {
    if (pageNum < 1 || pageNum > totalPages.value) {
      return;
    }

    // Get current filters from URL
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    const letter = params.get("letter") || "";
    const sort = params.get("sort") || defaultSort;
    const gameTitles = params.getAll("game_title[]") || [];
    const requireAllGames = params.get("require_all_games") === "1";

    // Build query string manually to preserve [] notation
    const queryParts = [];
    if (letter) {
      queryParts.push(`letter=${encodeURIComponent(letter)}`);
    }
    if (sort !== defaultSort) {
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

    const queryString = queryParts.length > 0 ? `?${queryParts.join("&")}` : "";
    const newUrl = `${url.pathname}${queryString}`;
    window.history.pushState({}, "", newUrl);

    // Fetch new page
    const filters = {
      letter,
      sort,
      gameTitles,
      requireAllGames,
    };
    fetchData(filters, pageNum, pageSize);

    // Scroll to top
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  /**
   * Initialize list data from server-rendered props
   */
  const initializeFromProps = (props) => {
    const {
      initialData,
      totalCount: propTotalCount,
      currentPage: propCurrentPage,
      totalPages: propTotalPages,
      pageSize: propPageSize,
    } = props;

    try {
      const decoded = decodeHtmlEntities(initialData);
      items.value = JSON.parse(decoded);

      if (hasPagination) {
        totalCount.value = parseInt(propTotalCount) || 0;
        currentPage.value = parseInt(propCurrentPage) || 1;
        totalPages.value = parseInt(propTotalPages) || 1;
        itemsPerPage.value = parseInt(propPageSize) || DEFAULT_PAGE_SIZE;
      }
    } catch (e) {
      console.error("Failed to parse initial data:", e);
      items.value = [];
    }
  };

  /**
   * Setup and teardown filter event listeners
   */
  const setupFilterListener = () => {
    window.addEventListener(filterEventName, handleFilterChange);
  };

  const teardownFilterListener = () => {
    window.removeEventListener(filterEventName, handleFilterChange);
  };

  return {
    // State
    items,
    loading,
    totalCount,
    currentPage,
    totalPages,
    itemsPerPage,

    // Methods
    fetchData,
    handleFilterChange,
    handlePageChange,
    goToPage,
    initializeFromProps,
    setupFilterListener,
    teardownFilterListener,

    // Constants
    DEFAULT_PAGE_SIZE,
  };
}

module.exports = { useListData, DEFAULT_PAGE_SIZE };

