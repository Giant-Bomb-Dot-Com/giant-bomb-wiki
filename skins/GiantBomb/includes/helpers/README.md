# Helper Functions

This directory contains reusable helper functions for the GiantBomb skin.

## PlatformHelper.php

Provides utility functions for looking up platform data from Semantic MediaWiki with caching support.

### Functions

#### `loadPlatformMappings()`

Loads all platform name to abbreviation mappings from SMW with 24-hour caching.

**Returns:** `array` - Mapping of platform names to their abbreviations

**Example:**

```php
require_once __DIR__ . '/helpers/PlatformHelper.php';

$platforms = loadPlatformMappings();
echo $platforms['Platforms/PlayStation 5']; // "PS5"
echo $platforms['PlayStation 5']; // "PS5" (works with or without namespace)
```

#### `getPlatformAbbreviation($platformName)`

Convenience function to get a single platform's abbreviation.

**Parameters:**

- `$platformName` (string) - The platform name (with or without "Platforms/" prefix)

**Returns:** `string` - The platform abbreviation, or basename if not found

**Example:**

```php
require_once __DIR__ . '/helpers/PlatformHelper.php';

echo getPlatformAbbreviation('Platforms/PlayStation 5'); // "PS5"
echo getPlatformAbbreviation('Xbox Series X'); // "XBSX"
echo getPlatformAbbreviation('Unknown Platform'); // "Unknown Platform" (fallback)
```

#### `getPlatformData($platformName)`

Get full platform data (extensible for future properties).

**Parameters:**

- `$platformName` (string) - The platform name

**Returns:** `array|null` - Platform data array or null if not found

**Example:**

```php
require_once __DIR__ . '/helpers/PlatformHelper.php';

$data = getPlatformData('PlayStation 5');
// Returns: ['name' => 'PlayStation 5', 'abbreviation' => 'PS5']
```

### Caching

All functions use MediaWiki's WANObjectCache with a 24-hour TTL (Time To Live). This means:

- First request: Queries database and caches result
- Subsequent requests: Returns cached data instantly
- After 24 hours: Cache expires and is rebuilt on next request

#### Manual Cache Invalidation

To force a cache refresh (e.g., after adding new platforms), update the cache version in `PlatformHelper.php`:

```php
// Change v1 to v2
$cacheKey = $cache->makeKey('platforms', 'abbreviations', 'v2');
```

### Usage in Views

To use in a view template:

```php
<?php
// At the top of your view file
require_once __DIR__ . '/../helpers/PlatformHelper.php';

// Load all mappings once
$platformMappings = loadPlatformMappings();

// Use in your code
foreach ($games as $game) {
    $abbrev = $platformMappings[$game['platform']] ?? basename($game['platform']);
    // ...
}

// Or use the convenience function
$abbrev = getPlatformAbbreviation($platformName);
```

#### `getAllPlatforms()`

Get all platforms formatted for dropdown/select use with 24-hour caching.

**Returns:** `array` - Array of platform objects with 'name', 'displayName', and 'abbreviation' keys

**Example:**

```php
require_once __DIR__ . '/helpers/PlatformHelper.php';

$platforms = getAllPlatforms();
foreach ($platforms as $platform) {
    echo $platform['displayName']; // "PlayStation 5"
    echo $platform['name']; // "PlayStation 5" (clean name without namespace)
    echo $platform['abbreviation']; // "PS5"
}
```

### See Also

- `views/new-releases-page.php` - Example implementation using platform lookups
- `ReleasesHelper.php` - Uses platform lookups for release data

---

## ReleasesHelper.php

Provides utility functions for querying and formatting release data from Semantic MediaWiki. This helper consolidates release logic used by both the new releases page view and the AJAX API endpoint.

### Functions

#### `formatReleaseDate($rawDate, $timestamp, $dateType)`

Formats a release date based on its specificity level.

**Parameters:**

- `$rawDate` (string) - The raw date from SMW (e.g., "1/1986", "10/2003", "12/31/2024")
- `$timestamp` (int) - The Unix timestamp of the date
- `$dateType` (string) - The date type: "Year", "Month", "Quarter", "Full", or "None"

**Returns:** `string` - The formatted date string

**Example:**

```php
require_once __DIR__ . '/helpers/ReleasesHelper.php';

echo formatReleaseDate('12/31/2024', 1735603200, 'Full');    // "December 31, 2024"
echo formatReleaseDate('10/2024', 1727740800, 'Month');      // "October 2024"
echo formatReleaseDate('1/1986', 504921600, 'Year');         // "1986"
echo formatReleaseDate('1/2025', 1704067200, 'Quarter');     // "Q1 2025"
```

#### `groupReleasesByPeriod($releases)`

Groups releases by time period based on their date specificity.

- **Full dates** → Grouped by week (Sunday-Saturday)
- **Month dates** → Grouped by month
- **Quarter dates** → Grouped by quarter
- **Year dates** → Grouped by year

**Parameters:**

- `$releases` (array) - Array of release data with 'sortTimestamp' and 'dateSpecificity' keys

**Returns:** `array` - Array of grouped releases with 'label', 'releases', and 'sortKey'

**Example:**

```php
require_once __DIR__ . '/helpers/ReleasesHelper.php';

$releases = queryReleasesFromSMW();
$weekGroups = groupReleasesByPeriod($releases);

foreach ($weekGroups as $group) {
    echo $group['label'];           // "December 1, 2024 - December 7, 2024"
    echo count($group['releases']); // 15
}
```

#### `queryReleasesFromSMW($filterRegion = '', $filterPlatform = '')`

Queries release data from Semantic MediaWiki with optional filters.

**Parameters:**

- `$filterRegion` (string, optional) - Filter by region (e.g., "United States", "Japan")
- `$filterPlatform` (string, optional) - Filter by platform name without "Platforms/" prefix

**Returns:** `array` - Array of release data with deduplication

**Example:**

```php
require_once __DIR__ . '/helpers/ReleasesHelper.php';

// Get all releases
$allReleases = queryReleasesFromSMW();

// Get releases for specific region
$usReleases = queryReleasesFromSMW('United States');

// Get releases for specific platform
$ps5Releases = queryReleasesFromSMW('', 'PlayStation 5');

// Get releases with both filters
$usPS5Releases = queryReleasesFromSMW('United States', 'PlayStation 5');

// Each release contains:
foreach ($allReleases as $release) {
    echo $release['title'];                  // Game title
    echo $release['url'];                    // Link to game page
    echo $release['releaseDateFormatted'];   // Formatted date
    echo $release['region'];                 // Region (if set)
    // $release['platforms'] - Array of platform data
    // $release['image'] - Cover image URL
}
```

### Dependencies

ReleasesHelper automatically loads PlatformHelper for platform abbreviation lookups.

### Deduplication

The `queryReleasesFromSMW()` function automatically deduplicates releases based on:

- Game title
- Release date
- Region
- Platforms

This prevents the same release from appearing multiple times.

### Usage in Views and API

**In a view file:**

```php
<?php
require_once __DIR__ . '/../helpers/ReleasesHelper.php';

$filterRegion = $request->getText('region', '');
$filterPlatform = $request->getText('platform', '');

$releases = queryReleasesFromSMW($filterRegion, $filterPlatform);
$weekGroups = groupReleasesByPeriod($releases);

// Pass to template
$data = ['weekGroups' => $weekGroups];
```

**In an API endpoint:**

```php
<?php
require_once __DIR__ . '/../helpers/ReleasesHelper.php';

$releases = queryReleasesFromSMW(
    $request->getText('region', ''),
    $request->getText('platform', '')
);
$weekGroups = groupReleasesByPeriod($releases);

header('Content-Type: application/json');
echo json_encode(['weekGroups' => $weekGroups]);
```

### See Also

- `views/new-releases-page.php` - Example view implementation
- `api/releases-api.php` - Example API endpoint implementation
- `PlatformHelper.php` - Used for platform abbreviations
