const { ref } = require("vue");
const { decodeHtmlEntities } = require("../helpers/htmlUtils.js");

const DEFAULT_PAGE_SIZE = 48;

/**
 * Filter value types
 */
const FILTER_TYPES = {
  STRING: "string",
  ARRAY: "array",
  BOOLEAN: "boolean",
};

/**
 * useListData Composable
 * Shared logic for list components with pagination, filtering, and API fetching
 *
 * @param {Object} config - Configuration object
 * @param {string} config.actionName - API action name (e.g., 'get-platforms', 'get-concepts')
 * @param {string} config.dataKey - Key in API response containing items (e.g., 'platforms', 'concepts')
 * @param {string} config.filterEventName - Event name to listen for filter changes
 * @param {Object} config.filterConfig - Filter configuration object defining filter keys, query params, and types
 * @param {Object} config.paginationConfig - Pagination configuration object defining param names and response format
 * @param {string} config.defaultSort - Default sort value (optional, can be set in filterConfig instead)
 * @param {boolean} config.hasPagination - Whether to include pagination support (default: true)
 */
function useListData(config) {
  const {
    actionName,
    dataKey,
    filterEventName,
    filterConfig,
    paginationConfig,
    defaultSort, // Optional override for sort default
    hasPagination = true,
  } = config;

  // Override default sort from parameter if provided
  if (defaultSort && filterConfig && filterConfig.sort) {
    filterConfig.sort.default = defaultSort;
  }

  // State
  const items = ref([]);
  const loading = ref(false);
  const totalCount = ref(0);
  const currentPage = ref(1);
  const totalPages = ref(1);
  const itemsPerPage = ref(DEFAULT_PAGE_SIZE);

  /**
   * Get default value for a filter
   */
  const getFilterDefault = (filterName) => {
    const config = filterConfig[filterName];
    if (!config) return "";
    return config.default;
  };

  /**
   * Parse filter value from event based on type
   */
  const parseFilterValue = (value, type) => {
    switch (type) {
      case FILTER_TYPES.ARRAY:
        if (Array.isArray(value)) return value;
        if (value === null || value === undefined) return [];
        return [value];
      case FILTER_TYPES.BOOLEAN:
        return Boolean(value);
      case FILTER_TYPES.STRING:
      default:
        return value !== null && value !== undefined ? String(value) : "";
    }
  };

  /**
   * Extract filters from event.detail using filterConfig
   */
  const extractFiltersFromEvent = (eventDetail) => {
    const filters = {};

    for (const [filterName, config] of Object.entries(filterConfig)) {
      const eventKey = config.eventKey || filterName;
      if (eventDetail[eventKey]) {
        const rawValue = eventDetail[eventKey];
        filters[filterName] = parseFilterValue(rawValue, config.type);
      }
    }

    return filters;
  };

  /**
   * Extract filters from URL parameters using filterConfig
   */
  const extractFiltersFromUrl = () => {
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    const filters = {};

    for (const [filterName, config] of Object.entries(filterConfig)) {
      const queryParam = config.queryParam;

      switch (config.type) {
        case FILTER_TYPES.ARRAY:
          // Handle array parameters (e.g., game_title[])
          filters[filterName] = params.getAll(queryParam) || [];
          break;
        case FILTER_TYPES.BOOLEAN:
          filters[filterName] =
            params.get(queryParam) === (config.booleanValue || "1");
          break;
        case FILTER_TYPES.STRING:
        default:
          filters[filterName] = params.get(queryParam) || config.default || "";
      }
    }

    return filters;
  };

  /**
   * Build query string for API request or URL
   *
   * @param {Object} filters - Filter values
   * @param {number} pageNum - Page number
   * @param {number} pageSize - Items per page
   * @param {Object} options - Options object
   * @param {boolean} options.includeAction - Include action parameter (default: false)
   * @param {boolean} options.omitDefaultPagination - Omit page=1 and default page size (default: false)
   * @param {boolean} options.withPrefix - Include '?' prefix (default: false)
   * @returns {string} Query string
   */
  const buildQueryString = (
    filters = {},
    pageNum = 1,
    pageSize = DEFAULT_PAGE_SIZE,
    options = {},
  ) => {
    const {
      includeAction = false,
      omitDefaultPagination = false,
      withPrefix = false,
    } = options;

    const queryParts = [];

    // Add action parameter if requested
    if (includeAction) {
      queryParts.push(`action=${actionName}`);
    }

    // Add filter parameters based on filterConfig
    for (const [filterName, config] of Object.entries(filterConfig)) {
      const value = filters[filterName];
      const defaultValue = config.default;

      // Check conditional inclusion
      if (config.conditionalOn && !config.conditionalOn(filters)) {
        continue;
      }

      // Handle different types
      switch (config.type) {
        case FILTER_TYPES.ARRAY:
          if (Array.isArray(value) && value.length > 0) {
            value.forEach((item) => {
              queryParts.push(
                `${config.queryParam}=${encodeURIComponent(item)}`,
              );
            });
          }
          break;

        case FILTER_TYPES.BOOLEAN:
          if (value === true) {
            queryParts.push(
              `${config.queryParam}=${config.booleanValue || "1"}`,
            );
          }
          break;

        case FILTER_TYPES.STRING:
        default:
          if (value && (!config.omitIfDefault || value !== defaultValue)) {
            queryParts.push(
              `${config.queryParam}=${encodeURIComponent(value)}`,
            );
          }
          break;
      }
    }

    // Add pagination parameters
    if (hasPagination) {
      if (omitDefaultPagination) {
        // Omit page=1 and default page size from URL
        if (pageNum > 1) {
          queryParts.push(`${paginationConfig.pageParam}=${pageNum}`);
        }
        if (pageSize !== DEFAULT_PAGE_SIZE) {
          queryParts.push(`${paginationConfig.pageSizeParam}=${pageSize}`);
        }
      } else {
        // Always include pagination params (for API calls)
        queryParts.push(`${paginationConfig.pageParam}=${pageNum}`);
        queryParts.push(`${paginationConfig.pageSizeParam}=${pageSize}`);
      }
    }

    const queryString = queryParts.join("&");
    return withPrefix
      ? queryString.length > 0
        ? `?${queryString}`
        : ""
      : queryString;
  };

  /**
   * Parse pagination from API response
   */
  const parsePaginationResponse = (data) => {
    if (paginationConfig.responseFormat === "nested") {
      const paginationData = data[paginationConfig.responseKey] || {};
      return {
        totalCount: paginationData.totalItems || 0,
        currentPage: paginationData.currentPage || 1,
        totalPages: paginationData.totalPages || 1,
        itemsPerPage: paginationData.itemsPerPage || DEFAULT_PAGE_SIZE,
      };
    }

    // Flat response format
    return {
      totalCount: data.totalCount || 0,
      currentPage: data.currentPage || 1,
      totalPages: data.totalPages || 1,
      itemsPerPage: data.pageSize || DEFAULT_PAGE_SIZE,
    };
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
      const queryString = buildQueryString(filters, pageNum, pageSize, {
        includeAction: true,
      });
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
          const pagination = parsePaginationResponse(data);
          totalCount.value = pagination.totalCount;
          currentPage.value = pagination.currentPage;
          totalPages.value = pagination.totalPages;
          itemsPerPage.value = pagination.itemsPerPage;
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
    const filters = extractFiltersFromEvent(event.detail);
    const pageNum = event.detail.page || 1;

    fetchData(filters, pageNum, itemsPerPage.value);
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
    const filters = extractFiltersFromUrl();

    // Update URL
    const url = new URL(window.location.href);
    const queryString = buildQueryString(filters, pageNum, pageSize, {
      omitDefaultPagination: true,
      withPrefix: true,
    });
    window.history.pushState({}, "", `${url.pathname}${queryString}`);

    // Fetch new page
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
      // For games-style pagination info
      paginationInfo,
    } = props;

    try {
      const decoded = decodeHtmlEntities(initialData);
      items.value = JSON.parse(decoded);

      if (hasPagination) {
        // Handle paginationInfo (games style) or flat props (concepts/platforms style)
        if (paginationInfo) {
          const decodedPagination = decodeHtmlEntities(paginationInfo);
          const parsedPagination = JSON.parse(decodedPagination);
          totalCount.value = parsedPagination.totalItems || 0;
          currentPage.value = parsedPagination.currentPage || 1;
          totalPages.value = parsedPagination.totalPages || 1;
          itemsPerPage.value =
            parsedPagination.itemsPerPage || DEFAULT_PAGE_SIZE;
        } else {
          totalCount.value = parseInt(propTotalCount) || 0;
          currentPage.value = parseInt(propCurrentPage) || 1;
          totalPages.value = parseInt(propTotalPages) || 1;
          itemsPerPage.value = parseInt(propPageSize) || DEFAULT_PAGE_SIZE;
        }
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
    extractFiltersFromUrl,

    // Helpers for components
    getFilterDefault,

    // Constants
    DEFAULT_PAGE_SIZE,
  };
}

module.exports = {
  useListData,
  DEFAULT_PAGE_SIZE,
  FILTER_TYPES,
};
