# Helper Functions

This directory contains reusable helper functions for the GiantBomb skin.

## Table of Contents

- [DateHelper.php](#datehelperphp) - Date formatting utilities
- [PlatformHelper.php](#platformhelperphp) - Platform data queries and lookups
- [ReleasesHelper.php](#releaseshelperphp) - Release data queries and grouping
- [Testing](#testing) - Unit test information

---

## DateHelper.php

Provides date formatting utilities used across the skin.

### Functions

#### `formatReleaseDate($rawDate, $timestamp, $dateType)`

Formats a release date based on its specificity level.

**Parameters:**

- `$rawDate` (string) - The raw date from SMW (e.g., "1/1986", "10/2003", "12/31/2024")
- `$timestamp` (int) - The Unix timestamp of the date
- `$dateType` (string) - The date type: "Year", "Month", "Quarter", "Full", or "None"

**Returns:** `string` - The formatted date string

**Date Type Formatting:**

- `Full` → "December 31, 2024" (F j, Y)
- `Month` → "October 2024" (F Y)
- `Quarter` → "Q1 2025" (Q# YYYY)
- `Year` → "2024" (Y)
- `None` → Returns raw date unchanged

**Example:**

```php
require_once __DIR__ . '/helpers/DateHelper.php';

echo formatReleaseDate('12/31/2024', 1735603200, 'Full');    // "December 31, 2024"
echo formatReleaseDate('10/2024', 1727740800, 'Month');      // "October 2024"
echo formatReleaseDate('1/1986', 504921600, 'Year');         // "1986"
echo formatReleaseDate('1/2025', 1704067200, 'Quarter');     // "Q1 2025"
```

---

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

#### `queryPlatformsFromSMW($filterLetter, $filterGameTitles, $sort, $page, $limit, $requireAllGames)`

Queries platforms from Semantic MediaWiki with filtering, sorting, and pagination support.

**Parameters:**

- `$filterLetter` (string, optional) - Filter by first letter (A-Z or # for numbers)
- `$filterGameTitles` (array, optional) - Array of game page names to filter by
- `$sort` (string, optional) - Sort method: 'release_date', 'alphabetical', 'last_edited', 'last_created'. Default: 'release_date'
- `$page` (int, optional) - Page number for pagination. Default: 1
- `$limit` (int, optional) - Results per page. Default: 48
- `$requireAllGames` (bool, optional) - If true, platforms must be linked to ALL specified games (AND logic). If false, ANY game (OR logic). Default: false

**Returns:** `array` - Array with keys:

- `platforms` - Array of platform data
- `totalCount` - Total number of platforms matching filters
- `currentPage` - Current page number
- `totalPages` - Total number of pages

**Example:**

```php
require_once __DIR__ . '/helpers/PlatformHelper.php';

// Get all platforms, sorted by release date
$result = queryPlatformsFromSMW();

// Get platforms starting with 'P', page 2
$result = queryPlatformsFromSMW('P', [], 'alphabetical', 2, 48);

// Get platforms that have either game
$result = queryPlatformsFromSMW('', ['Games/Halo', 'Games/Gears of War'], 'release_date', 1, 48, false);

// Get platforms that have BOTH games
$result = queryPlatformsFromSMW('', ['Games/Call of Duty', 'Games/FIFA'], 'release_date', 1, 48, true);

foreach ($result['platforms'] as $platform) {
    echo $platform['title'];           // "PlayStation 5"
    echo $platform['shortName'];       // "PS5"
    echo $platform['releaseDateFormatted']; // "November 12, 2020"
    echo $platform['gameCount'];       // 450
}
```

#### `getPlatformsForGameFromSMW($gamePageName)`

Get all platforms associated with a specific game.

**Parameters:**

- `$gamePageName` (string) - The game page name (e.g., "Games/Halo Infinite")

**Returns:** `array` - Array of platform names

**Example:**

```php
require_once __DIR__ . '/helpers/PlatformHelper.php';

$platforms = getPlatformsForGameFromSMW('Games/Halo Infinite');
// Returns: ['PlayStation 5', 'Xbox Series X', 'PC']
```

#### `getGameCountForPlatformFromSMW($platformName)`

Get the number of games associated with a platform.

**Parameters:**

- `$platformName` (string) - The platform page name (e.g., "Platforms/PlayStation 5")

**Returns:** `int` - Number of games

**Example:**

```php
require_once __DIR__ . '/helpers/PlatformHelper.php';

$count = getGameCountForPlatformFromSMW('Platforms/PlayStation 5');
echo "PlayStation 5 has $count games";
```

#### `getPlatformCountFromSMW($filterLetter, $filterGameTitles, $requireAllGames)`

Get the total count of platforms matching filters (used for pagination).

**Parameters:**

- `$filterLetter` (string, optional) - Filter by first letter
- `$filterGameTitles` (array, optional) - Array of game page names
- `$requireAllGames` (bool, optional) - AND vs OR logic for game filters

**Returns:** `int` - Total number of platforms

**Example:**

```php
require_once __DIR__ . '/helpers/PlatformHelper.php';

$total = getPlatformCountFromSMW('P');
echo "There are $total platforms starting with P";
```

#### `processPlatformQueryResults($results)`

**Internal utility function** - Processes raw SMW query results into structured platform data. This is called internally by `queryPlatformsFromSMW()` and typically doesn't need to be called directly.

### See Also

- `views/platforms-page.php` - Example implementation using platform queries
- `api/platforms-api.php` - API endpoint for AJAX platform filtering
- `ReleasesHelper.php` - Uses platform lookups for release data

---

## ReleasesHelper.php

Provides utility functions for querying and formatting release data from Semantic MediaWiki. This helper consolidates release logic used by both the new releases page view and the AJAX API endpoint.

**Note:** ReleasesHelper automatically loads DateHelper.php and PlatformHelper.php for date formatting and platform lookups.

### Functions

#### `groupReleasesByPeriod($releases)`

Groups releases by time period based on their date specificity. Uses `processDateForGrouping()` internally.

**Grouping Rules:**

- **Full dates** → Grouped by week (Sunday-Saturday)
- **Month dates** → Grouped by month
- **Quarter dates** → Grouped by quarter (Q1-Q4)
- **Year dates** → Grouped by year

**Parameters:**

- `$releases` (array) - Array of release data with 'releaseDateTimestamp' and 'dateSpecificity' keys

**Returns:** `array` - Array of grouped releases, sorted chronologically. Each group contains:

- `label` - Human-readable period label (e.g., "December 1, 2024 - December 7, 2024")
- `releases` - Array of releases in this period
- `sortKey` - Internal key for chronological sorting

**Example:**

```php
require_once __DIR__ . '/helpers/ReleasesHelper.php';

$releases = queryReleasesFromSMW();
$weekGroups = groupReleasesByPeriod($releases);

foreach ($weekGroups as $group) {
    echo $group['label'];           // "December 1, 2024 - December 7, 2024"
    echo count($group['releases']); // 15

    foreach ($group['releases'] as $release) {
        echo $release['title'];     // Game title
    }
}
```

#### `processDateForGrouping($timestamp, $specificity)`

**Internal utility function** - Converts a timestamp and specificity into grouping data. This is called internally by `groupReleasesByPeriod()` and typically doesn't need to be called directly.

**Parameters:**

- `$timestamp` (int) - Unix timestamp
- `$specificity` (string) - Date specificity: "full", "month", "quarter", or "year"

**Returns:** `array` - Array with keys:

- `groupKey` - Unique identifier for the group (e.g., "2024-51" for week 51)
- `groupLabel` - Human-readable label (e.g., "December 22, 2024 - December 28, 2024")
- `sortKey` - Sortable key for chronological ordering (e.g., "20241222")

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

#### `processReleaseQueryResults($results)`

**Internal utility function** - Processes raw SMW query results into structured release data with platform lookups and deduplication. This is called internally by `queryReleasesFromSMW()` and typically doesn't need to be called directly.

**Parameters:**

- `$results` (array) - Raw results from SMW API

**Returns:** `array` - Deduplicated array of release data

**Deduplication:** Automatically deduplicates releases based on:

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
- `DateHelper.php` - Date formatting utilities
- `PlatformHelper.php` - Used for platform abbreviations

---

## Testing

The helper functions are comprehensively tested using PHPUnit. Tests focus on pure data processing and utility functions that don't require database access.

### Test Files

#### `tests/phpunit/includes/helpers/DateHelperTest.php`

**16 tests** covering `formatReleaseDate()`:

- Full, Month, Quarter, and Year date formatting
- Quarter boundary testing (Q1-Q4)
- Edge cases (null/zero timestamps, leap years, year boundaries)
- Case sensitivity handling
- Unknown date type fallback

#### `tests/phpunit/includes/helpers/ReleasesHelperTest.php`

**24 tests** covering:

- `processDateForGrouping()` - 8 tests for week, month, quarter, and year grouping
- `processReleaseQueryResults()` - 9 tests for data processing and deduplication
- `groupReleasesByPeriod()` - 12 tests for release grouping and sorting

#### `tests/phpunit/includes/helpers/PlatformHelperTest.php`

**23 tests** covering:

- `processPlatformQueryResults()` - 17 tests for data processing, date handling, and edge cases
- `getPlatformAbbreviation()` - 6 tests for fallback behavior

### Test Coverage Summary

**Total: 63 PHPUnit tests**

- ✅ Date formatting and grouping logic
- ✅ Release data processing and deduplication
- ✅ Platform data processing
- ✅ Edge cases and error handling
- ✅ All date specificities (Full, Month, Quarter, Year)

**Note:** Database-dependent functions (like `queryReleasesFromSMW()`, `loadPlatformMappings()`, etc.) are not unit tested as they require a fully configured MediaWiki database with Semantic MediaWiki data. These will be tested through integration/end-to-end tests instead.

### Vue Component Tests

The frontend components that use these helpers also have comprehensive Jest tests:

- `PlatformFilter.spec.js` - 32 tests
- `PlatformList.spec.js` - 27 tests
- `ReleaseFilter.spec.js` - 32 tests (not part of this PR)
- `ReleaseList.spec.js` - 27 tests (not part of this PR)

Run frontend tests:

```bash
# Run all Vue component tests
pnpm jest

# Run platform component tests
pnpm jest Platform

# Run with coverage
pnpm jest --coverage
```

**Total: 122 tests (63 PHPUnit + 59 Jest)** ensuring data flows correctly from backend to frontend.
