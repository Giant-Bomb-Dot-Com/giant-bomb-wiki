/**
 * Formats a list of platforms into a string of the first 3 platforms and the number of remaining platforms
 *
 * @param {Array} platforms - The platforms to format
 * @returns {string} The formatted string
 */
function formatPlatforms(platforms) {
  if (!platforms || platforms.length === 0) return "";

  // Remove any platforms with empty or undefined abbrev values
  platforms = platforms.filter((p) => p.abbrev && p.abbrev.trim() !== "");

  if (platforms.length === 0) return "";

  const displayCount = 3;
  const shown = platforms
    .slice(0, displayCount)
    .map((p) => p.abbrev)
    .join(", ");
  const remaining = platforms.length - displayCount;

  if (remaining > 0) {
    return `${shown} +${remaining} more`;
  }
  return shown;
}

module.exports = {
  formatPlatforms,
};
