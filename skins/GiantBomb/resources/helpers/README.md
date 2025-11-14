# JavaScript Helpers

This directory contains reusable JavaScript helper modules for Vue components.

## countryFlags.js

Provides country/region to country code mappings and flag image URLs.

### Functions

#### `getCountryCode(countryName)`

Get the ISO 3166-1 alpha-2 country code for a country or region name.

**Parameters:**
- `countryName` (string) - The country or region name

**Returns:** `string` - Two-letter country code or empty string if not found

**Example:**
```javascript
const { getCountryCode } = require('../helpers/countryFlags.js');

console.log(getCountryCode('United States')); // 'us'
console.log(getCountryCode('Japan'));         // 'jp'
console.log(getCountryCode('Australia'));     // 'au'
console.log(getCountryCode('Unknown'));       // ''
```

#### `getFlagUrl(countryCode, size)`

Get a flag image URL from flagcdn.com.

**Parameters:**
- `countryCode` (string) - Two-letter ISO country code
- `size` (string, optional) - Size variant: 'w20', 'w40', 'w80', 'w160', 'w320', 'w640', 'w1280', 'w2560'. Default: 'w20'

**Returns:** `string` - URL to flag image PNG

**Example:**
```javascript
const { getFlagUrl } = require('../helpers/countryFlags.js');

console.log(getFlagUrl('us', 'w20'));  // 'https://flagcdn.com/w20/us.png'
console.log(getFlagUrl('jp', 'w40'));  // 'https://flagcdn.com/w40/jp.png'
```

#### `getAllCountryCodes()`

Get all available country code mappings.

**Returns:** `Object` - Object mapping country names to country codes

**Example:**
```javascript
const { getAllCountryCodes } = require('../helpers/countryFlags.js');

const codes = getAllCountryCodes();
console.log(codes['United States']); // 'us'
```

#### `hasCountryCode(countryName)`

Check if a country has a code mapping.

**Parameters:**
- `countryName` (string) - The country or region name

**Returns:** `boolean` - True if country code exists

**Example:**
```javascript
const { hasCountryCode } = require('../helpers/countryFlags.js');

console.log(hasCountryCode('Japan'));    // true
console.log(hasCountryCode('Unknown'));  // false
```

### Usage in Vue Components

```vue
<template>
  <div>
    <img 
      v-if="hasCountryCode(region)"
      :src="getFlagUrl(getCountryCode(region), 'w20')"
      :alt="region"
      :title="region"
      class="flag-icon"
    />
    <span v-else>{{ region }}</span>
  </div>
</template>

<script>
const { getCountryCode, getFlagUrl, hasCountryCode } = require('../helpers/countryFlags.js');

module.exports = {
  setup() {
    return {
      getCountryCode,
      getFlagUrl,
      hasCountryCode,
    };
  },
};
</script>

<style>
.flag-icon {
  height: 14px;
  width: auto;
  vertical-align: middle;
  border-radius: 2px;
}
</style>
```

### Data Source

Country code mappings are stored in `resources/data/countryFlags.json` using ISO 3166-1 alpha-2 country codes.

Flag images are loaded from [flagcdn.com](https://flagcdn.com), which provides free, high-quality SVG and PNG flags with various size options.

### Adding New Countries

To add a new country flag:

1. Edit `resources/data/countryFlags.json`
2. Add the country name as the key and the ISO 3166-1 alpha-2 country code as the value
3. No code changes needed - the helper will automatically use the updated data

**Example:**
```json
{
  "United States": "us",
  "Japan": "jp",
  "Your New Country": "xx"
}
```

Find country codes at: https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2

### See Also

- `components/ReleaseList.vue` - Uses country flags for release regions
- `data/countryFlags.json` - Country code mappings

