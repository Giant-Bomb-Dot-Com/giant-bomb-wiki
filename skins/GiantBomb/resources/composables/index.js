/**
 * Composables Index
 * Export all composables from a single entry point
 */

const { useFilters } = require("./useFilters.js");
const { useSearch } = require("./useSearch.js");

module.exports = {
  useFilters,
  useSearch,
};
