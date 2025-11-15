/**
 * Country Flags Helper
 * Provides country code mappings for flag images
 */

const countryCodes = require("../data/countryFlags.json");

/**
 * Get country code for a country/region name (for use with flag CDNs)
 *
 * @param {string} countryName - The country or region name
 * @returns {string} Two-letter country code (ISO 3166-1 alpha-2) or empty string if not found
 *
 * @example
 * getCountryCode('United States') // Returns: 'us'
 * getCountryCode('Japan')         // Returns: 'jp'
 * getCountryCode('Unknown')       // Returns: ''
 */
function getCountryCode(countryName) {
  if (!countryName) {
    return "";
  }
  return countryCodes[countryName.toLowerCase()] || "";
}

/**
 * Get flag image URL from flagcdn.com
 *
 * @param {string} countryCode - Two-letter country code
 * @param {string} size - Size variant: 'w20', 'w40', 'w80', 'w160', 'w320', 'w640', 'w1280', 'w2560'
 * @returns {string} URL to flag image
 *
 * @example
 * getFlagUrl('us', 'w20') // Returns: 'https://flagcdn.com/w20/us.png'
 */
function getFlagUrl(countryCode, size = "w20") {
  if (!countryCode) {
    return "";
  }
  return `https://flagcdn.com/${size}/${countryCode.toLowerCase()}.png`;
}

/**
 * Get all available country codes
 *
 * @returns {Object} Object mapping country names to country codes
 */
function getAllCountryCodes() {
  return countryCodes;
}

/**
 * Check if a country has a code mapping
 *
 * @param {string} countryName - The country or region name
 * @returns {boolean} True if country code exists
 */
function hasCountryCode(countryName) {
  if (!countryName) {
    return false;
  }
  return countryCodes.hasOwnProperty(countryName.toLowerCase());
}

module.exports = {
  getCountryCode,
  getFlagUrl,
  getAllCountryCodes,
  hasCountryCode,
};
