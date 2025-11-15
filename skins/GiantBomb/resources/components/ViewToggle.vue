<template>
  <div class="view-toggle">
    <button
      @click="setView('list')"
      :class="['view-btn', 'view-list', { active: currentView === 'list' }]"
    >
      <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
        <rect y="2" width="16" height="2"></rect>
        <rect y="7" width="16" height="2"></rect>
        <rect y="12" width="16" height="2"></rect>
      </svg>
      List
    </button>
    <button
      @click="setView('grid')"
      :class="['view-btn', 'view-grid', { active: currentView === 'grid' }]"
    >
      <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
        <rect width="7" height="7"></rect>
        <rect x="9" width="7" height="7"></rect>
        <rect y="9" width="7" height="7"></rect>
        <rect x="9" y="9" width="7" height="7"></rect>
      </svg>
      Grid
    </button>
  </div>
</template>

<script>
const { ref, toRefs, onMounted } = require("vue");

/**
 * ViewToggle Component
 * Handles switching between grid and list view
 *
 * @prop {String} targetContainer - CSS selector for the container to toggle views on
 * @prop {String} storageKey - localStorage key to save the view preference (default: 'viewPreference')
 */
module.exports = exports = {
  name: "ViewToggle",
  props: {
    targetContainer: {
      type: String,
      required: true,
    },
    storageKey: {
      type: String,
      default: "viewPreference",
    },
  },
  setup(props) {
    const { targetContainer, storageKey } = toRefs(props);
    const currentView = ref("grid");

    const setView = (view) => {
      currentView.value = view;

      // Find the page container and update its class
      const pageContainer = document.querySelector(targetContainer.value);
      if (pageContainer) {
        pageContainer.classList.remove("view-list", "view-grid");
        pageContainer.classList.add(`view-${view}`);
      }

      // Store preference in localStorage
      try {
        localStorage.setItem(storageKey.value, view);
      } catch (e) {
        console.warn("Could not save view preference:", e);
      }
    };

    onMounted(() => {
      // Load saved preference from localStorage
      let savedView = "grid";
      try {
        const stored = localStorage.getItem(storageKey.value);
        if (stored === "list" || stored === "grid") {
          savedView = stored;
        }
      } catch (e) {
        console.warn("Could not load view preference:", e);
      }

      // Apply the saved view
      currentView.value = savedView;
      const pageContainer = document.querySelector(targetContainer.value);
      if (pageContainer) {
        pageContainer.classList.add(`view-${savedView}`);
      }
    });

    return {
      currentView,
      setView,
    };
  },
};
</script>

<style>
.view-toggle {
  display: flex;
  gap: 10px;
}

.view-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: #2a2a2a;
  border: 1px solid #444;
  color: #ccc;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.2s;
}

.view-btn:hover {
  background: #333;
  border-color: #666;
}

.view-btn.active {
  background: #e63946;
  border-color: #e63946;
  color: white;
}

@media (max-width: 768px) {
  .view-toggle {
    display: none;
  }
}
</style>
