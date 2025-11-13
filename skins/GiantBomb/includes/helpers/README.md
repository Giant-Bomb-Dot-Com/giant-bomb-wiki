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

### See Also

- `views/new-releases-page.php` - Example implementation using platform lookups

