<template>
  <div class="filter-container">
    <h3 v-if="title" class="filter-title">{{ title }}</h3>
    <slot></slot>
    <button
      v-if="showClearButton"
      @click="$emit('clear')"
      class="clear-filters-btn"
    >
      {{ clearButtonText }}
    </button>
  </div>
</template>

<script>
/**
 * FilterContainer Component
 * Generic wrapper component for filter layouts
 */
const { defineComponent } = require("vue");
const component = defineComponent({
  name: "FilterContainer",
  props: {
    title: {
      type: String,
      default: "Filter",
    },
    showClearButton: {
      type: Boolean,
      default: false,
    },
    clearButtonText: {
      type: String,
      default: "Clear Filters",
    },
  },
  emits: ["clear"],
});

module.exports = exports = component;
exports.default = component;
</script>

<style>
.filter-container {
  background: #2a2a2a;
  border: 1px solid #444;
  border-radius: 8px;
  padding: 20px;
}

.filter-title {
  margin: 0 0 20px 0;
  font-size: 1.2rem;
  color: #fff;
  border-bottom: 2px solid #444;
  padding-bottom: 10px;
}

.clear-filters-btn {
  width: 100%;
  padding: 10px;
  background: #444;
  border: 1px solid #666;
  border-radius: 4px;
  color: #fff;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 10px;
}

.clear-filters-btn:hover {
  background: #555;
  border-color: #888;
}

.clear-filters-btn:active {
  background: #333;
}
</style>
