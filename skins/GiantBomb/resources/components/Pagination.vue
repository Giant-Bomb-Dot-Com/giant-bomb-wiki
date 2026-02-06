<template>
  <div class="pagination">
    <div class="pagination-info">
      Showing {{ startItem }}-{{ endItem }} of {{ totalItems }} items
    </div>

    <div class="pagination-controls">
      <button
        @click="goToPage(1)"
        :disabled="currentPage === 1"
        class="pagination-btn pagination-first"
        aria-label="First page"
      >
        ««
      </button>

      <button
        @click="goToPage(currentPage - 1)"
        :disabled="currentPage === 1"
        class="pagination-btn pagination-prev"
        aria-label="Previous page"
      >
        ‹
      </button>

      <button
        v-for="page in visiblePages"
        :key="page"
        @click="goToPage(page)"
        :class="[
          'pagination-btn',
          'pagination-page',
          { active: page === currentPage },
        ]"
        :aria-label="`Page ${page}`"
        :aria-current="page === currentPage ? 'page' : undefined"
      >
        {{ page }}
      </button>

      <button
        @click="goToPage(currentPage + 1)"
        :disabled="currentPage === totalPages"
        class="pagination-btn pagination-next"
        aria-label="Next page"
      >
        ›
      </button>

      <button
        @click="goToPage(totalPages)"
        :disabled="currentPage === totalPages"
        class="pagination-btn pagination-last"
        aria-label="Last page"
      >
        »»
      </button>
    </div>

    <div class="pagination-size">
      <label for="items-per-page">Items per page:</label>
      <select
        id="items-per-page"
        v-model="itemsPerPageLocal"
        @change="changeItemsPerPage"
        class="pagination-select"
      >
        <option
          v-for="option in itemsPerPageOptions"
          :key="option"
          :value="option"
        >
          {{ option }}
        </option>
      </select>
    </div>
  </div>
</template>

<script>
const {
  ref,
  computed,
  watch,
  toRefs,
  defineComponent,
  onMounted,
} = require("vue");

/**
 * Pagination Component
 * Reusable pagination component for lists
 *
 * Props:
 * - totalItems: Total number of items
 * - itemsPerPage: Number of items to display per page (default: 20)
 * - currentPage: Current page number (default: 1)
 * - maxVisiblePages: Maximum number of page buttons to show (default: 5)
 *
 * Events:
 * - pageChange: Emitted when page changes { page, itemsPerPage }
 */
module.exports = exports = defineComponent({
  name: "Pagination",
  props: {
    totalItems: {
      type: Number,
      required: true,
    },
    itemsPerPage: {
      type: Number,
      default: 25,
    },
    currentPage: {
      type: Number,
      default: 1,
    },
    maxVisiblePages: {
      type: Number,
      default: 5,
    },
    itemsPerPageOptions: {
      type: Array,
      default: () => [25, 50, 75, 100],
    },
  },
  emits: ["pageChange"],
  setup(props, { emit }) {
    const { totalItems, itemsPerPage, currentPage, maxVisiblePages } =
      toRefs(props);
    const itemsPerPageLocal = ref(itemsPerPage.value);
    const currentPageLocal = ref(currentPage.value);

    // Calculate total pages
    const totalPages = computed(() => {
      return Math.max(1, Math.ceil(totalItems.value / itemsPerPageLocal.value));
    });

    // Calculate start and end item numbers for display
    const startItem = computed(() => {
      if (totalItems.value === 0) return 0;
      return (currentPageLocal.value - 1) * itemsPerPageLocal.value + 1;
    });

    const endItem = computed(() => {
      const end = currentPageLocal.value * itemsPerPageLocal.value;
      return Math.min(end, totalItems.value);
    });

    // Calculate which page numbers to show
    const visiblePages = computed(() => {
      const total = totalPages.value;
      const current = currentPageLocal.value;
      const maxVisible = maxVisiblePages.value;

      if (total <= maxVisible) {
        // Show all pages if total is less than max
        return Array.from({ length: total }, (_, i) => i + 1);
      }

      const halfVisible = Math.floor(maxVisible / 2);
      let start = Math.max(1, current - halfVisible);
      let end = Math.min(total, start + maxVisible - 1);

      // Adjust start if we're near the end
      if (end - start < maxVisible - 1) {
        start = Math.max(1, end - maxVisible + 1);
      }

      return Array.from({ length: end - start + 1 }, (_, i) => start + i);
    });

    const goToPage = (page) => {
      if (page < 1 || page > totalPages.value) return;
      currentPageLocal.value = page;
      emit("pageChange", {
        page: currentPageLocal.value,
        itemsPerPage: itemsPerPageLocal.value,
      });
    };

    const changeItemsPerPage = () => {
      // Reset to page 1 when changing items per page
      currentPageLocal.value = 1;
      emit("pageChange", {
        page: currentPageLocal.value,
        itemsPerPage: itemsPerPageLocal.value,
      });
    };

    onMounted(() => {
      // Read current values from url
      const urlParams = new URLSearchParams(window.location.search);
      itemsPerPageLocal.value =
        parseInt(urlParams.get("page_size")) || itemsPerPage.value;
      currentPageLocal.value =
        parseInt(urlParams.get("page")) || currentPage.value;
    });

    // Watch for prop changes from parent
    watch(currentPage, (newPage) => {
      currentPageLocal.value = newPage;
    });

    watch(itemsPerPage, (newItemsPerPage) => {
      itemsPerPageLocal.value = newItemsPerPage;
    });

    return {
      currentPageLocal,
      itemsPerPageLocal,
      totalPages,
      startItem,
      endItem,
      visiblePages,
      goToPage,
      changeItemsPerPage,
    };
  },
});
</script>

<style>
.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 0;
  gap: 20px;
  flex-wrap: wrap;
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
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pagination-btn:hover:not(:disabled) {
  background: #3a3a3a;
  border-color: #b05f1c;
  color: #fff;
}

.pagination-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.pagination-btn.active {
  background: #b05f1c;
  border-color: #b05f1c;
  color: #fff;
  font-weight: 600;
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
  border-color: #b05f1c;
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

  /* Hide some page numbers on mobile */
  .pagination-page:not(.active):nth-child(n + 6) {
    display: none;
  }
}
</style>
