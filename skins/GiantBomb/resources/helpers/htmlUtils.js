/**
 * HTML utility functions
 */

/**
 * Decode HTML entities in a string
 * @param {string} text - Text with HTML entities to decode
 * @returns {string} - Decoded text
 */
function decodeHtmlEntities(text) {
  const textarea = document.createElement("textarea");
  textarea.innerHTML = text;
  return textarea.value;
}

module.exports = {
  decodeHtmlEntities,
};
