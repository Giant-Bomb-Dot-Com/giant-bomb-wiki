<?php
# MediaWiki Configuration
# See https://www.mediawiki.org/wiki/Manual:Configuration_settings

if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

$wikiEnv = getenv('MV_ENV') ?: ($_ENV['MV_ENV'] ?? 'dev');

$wgMemoryLimit = "2G";
$wgSitename = "gb";
$wgMetaNamespace = "Gb";

# URL Configuration
$wgScriptPath = "";
$wgArticlePath = "/wiki/$1";
$wgUsePathInfo = false;
$wgServer = "http://localhost:8080";

# SMW config directory
$smwgConfigFileDir = getenv('SMW_CONFIG_DIR') ?: '/var/www/html/images/smw-config';
$smwSetupInfoFile = rtrim( $smwgConfigFileDir, '/' ) . '/.smw.json';
if ( is_readable( $smwSetupInfoFile ) ) {
    $smwSetupInfo = json_decode( @file_get_contents( $smwSetupInfoFile ), true );
    if ( is_array( $smwSetupInfo ) && isset( $smwSetupInfo['upgrade_key'] ) && $smwSetupInfo['upgrade_key'] ) {
        $smwgUpgradeKey = $smwSetupInfo['upgrade_key'];
    }
}

$wgResourceBasePath = $wgScriptPath;

# Env-driven base origin/path for reverse-proxy hosting
$appBaseOrigin = getenv('APP_BASE_ORIGIN');
$appBasePath = getenv('APP_BASE_PATH');
if ( $appBaseOrigin ) {
    $wgServer = $appBaseOrigin;
}
if ( $appBasePath !== false && $appBasePath !== null && $appBasePath !== '' ) {
    $wgScriptPath = $appBasePath;
    $wgResourceBasePath = $wgScriptPath;
}

$canonicalServerEnv = getenv('CANONICAL_SERVER');
if ( $canonicalServerEnv !== false && $canonicalServerEnv !== null && trim($canonicalServerEnv) !== '' ) {
    $wgCanonicalServer = trim($canonicalServerEnv);
} else {
    $wgCanonicalServer = $wgServer;
}
$wgLoadScript = "$wgScriptPath/load.php";
$wgStylePath = "$wgResourceBasePath/skins";

$wgLogos = [
	'1x' => "$wgResourceBasePath/resources/assets/change-your-logo.svg",
	'icon' => "$wgResourceBasePath/resources/assets/change-your-logo-icon.svg",
];

# Email (disabled)
$wgEnableEmail = false;
$wgEnableUserEmail = true;
$wgEmergencyContact = "";
$wgPasswordSender = "";
$wgEnotifUserTalk = false;
$wgEnotifWatchlist = false;
$wgEmailAuthentication = true;

# Database
$wgDBtype = "mysql";
$wgDBserver = "db";
$wgDBname = getenv("MARIADB_DATABASE");
$wgDBuser = getenv("MARIADB_USER");
$wgDBpassword = getenv("MARIADB_PASSWORD");


# Cloud SQL socket support
$cloudSqlInstance = getenv('CLOUDSQL_INSTANCE');
if ( $cloudSqlInstance ) {
    $socketPath = '/cloudsql/' . $cloudSqlInstance;
    $wgDBserver = 'localhost:' . $socketPath;
    $wgDBsocket = $socketPath;
}

# DB env overrides (DB_* takes precedence over MARIADB_*)
$dbHost = getenv('DB_HOST');
if ( $dbHost ) {
    $wgDBserver = $dbHost;
}
$dbPort = getenv('DB_PORT');
if ( $dbPort ) {
    $wgDBport = intval($dbPort);
}
$dbName = getenv('DB_NAME');
if ( $dbName ) {
    $wgDBname = $dbName;
}
$dbUser = getenv('DB_USER');
if ( $dbUser ) {
    $wgDBuser = $dbUser;
}
$dbPassword = getenv('DB_PASSWORD');
if ( $dbPassword ) {
    $wgDBpassword = $dbPassword;
}

# Fallback if DB name is still empty
if ( !$wgDBname || trim($wgDBname) === '' ) {
    $wgDBname = 'mediawiki';
}

# External DB for gb_api_dump
$wgExternalDataSources['gb_api_dump'] = [
    'server' => 'db',
    'type' => 'mysql',
    'name' => getenv("MARIADB_API_DUMP_DATABASE"),
    'user' => getenv("MARIADB_USER"),
    'password' => getenv("MARIADB_PASSWORD")
];

$wgExternalDatabases['external_db'] = [
    'class' => 'DatabaseLoadBalancer',
    'hosts' => [
        [
            'type' => 'mysql',
            'host' => getenv( 'EXTERNAL_DB_HOST' ),
            'dbname' => getenv( 'EXTERNAL_DB_NAME' ),
            'user' => getenv( 'EXTERNAL_DB_USER' ),
            'password' => getenv( 'EXTERNAL_DB_PASSWORD' )
        ]
    ]
];

$wgDBprefix = "";
$wgDBssl = false;
$wgDBTableOptions = "ENGINE=InnoDB, DEFAULT CHARSET=binary";
$wgSharedTables[] = "actor";

# =============================================================================
# CACHING
# =============================================================================

# Localisation cache - use file system instead of database
$wgCacheDirectory = "$IP/cache";
$wgLocalisationCacheConf['store'] = 'file';

$wgMainCacheType = CACHE_ACCEL;
$wgMemCachedServers = [];
$wgParserCacheType = CACHE_DB;
$wgParserCacheExpireTime = 86400 * 7;
$wgMessageCacheType = CACHE_ACCEL;
$wgSessionCacheType = CACHE_DB;
$wgEnableSidebarCache = true;
$wgSidebarCacheExpiry = 86400;

if ( $wikiEnv === 'prod' ) {
    $wgUseFileCache = true;
    $wgFileCacheDirectory = "$IP/cache/html";
    $wgShowIPinHeader = false;
    $wgFileCacheDepth = 2;
}

$wgResourceLoaderMaxage = [
    'versioned' => 30 * 24 * 60 * 60,
    'unversioned' => 5 * 60,
];
$wgResourceLoaderUniqueVersion = '20260109-v1';
$wgUseETag = true;
$wgInvalidateCacheOnLocalSettingsChange = true;

# Jobs disabled on page views - run via cron/systemd instead
$wgJobRunRate = 0;

# =============================================================================
# UPLOADS & IMAGES
# =============================================================================

$wgEnableUploads = true;
$wgUseImageMagick = true;
$wgImageMagickConvertCommand = "/usr/bin/convert";

if ($wikiEnv == 'prod') {
    $uploadsSubdir = getenv('UPLOADS_SUBDIR');
    if ( $uploadsSubdir ) {
        $wgUploadDirectory = '/var/www/html/images/' . trim($uploadsSubdir, "/");
        $wgUploadPath = $wgScriptPath . '/images/' . trim($uploadsSubdir, "/");
    } else {
        $wgUploadDirectory = '/var/www/html/images';
        $wgUploadPath = $wgScriptPath.'/images';
    }
}

$wgAddImgTagWhitelist = true;
$wgAddImgTagWhitelistDomainsList = ['www.giantbomb.com'];
$wgAllowExternalImagesFrom = ['https://www.giantbomb.com/'];
$wgUseInstantCommons = false;

# =============================================================================
# GENERAL SETTINGS
# =============================================================================

$wgPingback = false;
$wgLanguageCode = "en";
$wgLocaltimezone = "UTC";

# Cookie config
$cookieDomain = getenv('COOKIE_DOMAIN');
if ( $cookieDomain ) {
    $wgCookieDomain = $cookieDomain;
}
$cookiePath = getenv('COOKIE_PATH');
if ( !$cookiePath ) {
    $cookiePath = '/';
}
$wgCookiePath = $cookiePath;
if ( $wikiEnv === 'prod' ) {
    $wgCookieSecure = true;
}

$wgSecretKey = getenv('MW_SK');
$wgAuthenticationTokenVersion = "1";
$wgUpgradeKey = getenv('MW_UK');

# License
$wgRightsPage = "";
$wgRightsUrl = "https://creativecommons.org/licenses/by-nc-sa/4.0/";
$wgRightsText = "Creative Commons Attribution-NonCommercial-ShareAlike";
$wgRightsIcon = "$wgResourceBasePath/resources/assets/licenses/cc-by-nc-sa.png";

$wgDiff3 = "/usr/bin/diff3";
$wgGroupPermissions["*"]["edit"] = false;

# =============================================================================
# SKINS & EXTENSIONS
# =============================================================================

$wgDefaultSkin = "giantbomb";
wfLoadSkin( 'GiantBomb' );
wfLoadSkin( 'Vector' );

wfLoadExtension( 'CodeEditor' );
wfLoadExtension( 'PageImages' );
wfLoadExtension( 'ParserFunctions' );
wfLoadExtension( 'Popups' );
wfLoadExtension( 'Scribunto' );
wfLoadExtension( 'SemanticExtraSpecialProperties' );
wfLoadExtension( 'SemanticMediaWiki' );
wfLoadExtension( 'SemanticResultFormats' );
wfLoadExtension( 'SemanticScribunto' );
wfLoadExtension( 'TemplateData' );
wfLoadExtension( 'TemplateStyles' );
wfLoadExtension( 'TemplateStylesExtender' );
wfLoadExtension( 'TextExtracts' );
wfLoadExtension( 'WikiEditor' );
wfLoadExtension( 'DisplayTitle' );
wfLoadExtension( 'PageForms' );
wfLoadExtension( 'GiantBombResolve' );
wfLoadExtension( 'AlgoliaSearch' );

# =============================================================================
# GIANTBOMB RESOLVE
# =============================================================================

$wgGiantBombResolveFields = [
	'displaytitle',
	'fullurl',
	'fulltext',
	'pageid',
	'namespace',
	'image',
];

$gbResolveToken = getenv( 'MW_GIANTBOMB_RESOLVE_INTERNAL_TOKEN' );
if ( $gbResolveToken !== false && $gbResolveToken !== null && $gbResolveToken !== '' ) {
	$wgGiantBombResolveInternalToken = $gbResolveToken;
}

$gbResolveBaseOrigin = getenv( 'MW_GIANTBOMB_RESOLVE_BASE_ORIGIN' );
if ( $gbResolveBaseOrigin !== false && $gbResolveBaseOrigin !== null && trim( $gbResolveBaseOrigin ) !== '' ) {
	$wgGiantBombResolveBaseOrigin = trim( $gbResolveBaseOrigin );
}

# =============================================================================
# SEMANTIC MEDIAWIKI
# =============================================================================

enableSemantics();

$smwgMainCacheType = CACHE_ACCEL;
$smwgQueryResultCacheType = CACHE_ACCEL;
$smwgQueryResultCacheLifetime = 3600;
$smwgFactboxUseCache = true;
$smwgFactboxCacheRefreshOnPurge = true;
$smwgAutoRefreshOnPurge = true;
$smwgEnabledQueryDependencyLinksStore = true;

$wgPFEnableStringFunctions = true;
$wgPopupsHideOptInOnPreferencesPage = true;
$wgPopupsReferencePreviewsBetaFeature = false;
$wgPageFormsUseDisplayTitle = false;

# =============================================================================
# ALGOLIA
# =============================================================================

$wgAlgoliaSearchEnabled = false;
$algoliaEnabled = getenv( 'ALGOLIA_SEARCH_ENABLED' );
if ( $algoliaEnabled !== false && $algoliaEnabled !== null ) {
	$flag = strtolower( trim( (string)$algoliaEnabled ) );
	if ( $flag === '1' || $flag === 'true' || $flag === 'yes' ) {
		$wgAlgoliaSearchEnabled = true;
	}
}
$algoliaAppId = getenv( 'ALGOLIA_APP_ID' );
if ( $algoliaAppId !== false && $algoliaAppId !== null && trim( $algoliaAppId ) !== '' ) {
	$wgAlgoliaAppId = trim( $algoliaAppId );
}
$algoliaAdminKey = getenv( 'ALGOLIA_ADMIN_API_KEY' );
if ( $algoliaAdminKey !== false && $algoliaAdminKey !== null && trim( $algoliaAdminKey ) !== '' ) {
	$wgAlgoliaAdminApiKey = trim( $algoliaAdminKey );
}
$algoliaIndex = getenv( 'ALGOLIA_INDEX_CONTENT' );
if ( $algoliaIndex !== false && $algoliaIndex !== null && trim( $algoliaIndex ) !== '' ) {
	$wgAlgoliaIndexName = trim( $algoliaIndex );
} else {
	$wgAlgoliaIndexName = 'gb_content';
}

# =============================================================================
# MISC
# =============================================================================

$wgNamespacesWithSubpages[NS_MAIN] = true;
$wgNamespacesWithSubpages[NS_TEMPLATE] = true;
$wgAllowDisplayTitle = true;
$wgRestrictDisplayTitle = false;
$smwgEnabledFulltextSearch = true;
$smwgPageSpecialProperties[] = '_CDAT';

# Dev mode settings
if ( $wikiEnv === 'dev' ) {
    $wgShowExceptionDetails = true;
    $wgDevelopmentWarnings = true;
    error_reporting( -1 );
    ini_set( 'display_errors', 1 );
    $smwgIgnoreUpgradeKeyCheck = true;
}

# SMW query limits
$smwgQUpperbound = 200000;
$smwgQMaxInlineLimit = 200000;
$smwgQMaxLimit = 200000;
$smwgQMaxSize = 100;

$wgFavicon = "$wgStylePath/GiantBomb/resources/assets/favicon.ico";

# Scribunto/Lua
$wgScribuntoDefaultEngine = 'luastandalone';
$wgScribuntoEngineConf['luastandalone']['errorFile'] = '/var/log/mediawiki/lua_err.log';
$wgScribuntoEngineConf['luastandalone']['memoryLimit'] = 209715200;

# Auto-append {{GameEnd}} to game pages missing it
$wgHooks['ParserBeforeInternalParse'][] = function( &$parser, &$text, &$strip_state ) {
    $title = $parser->getTitle();
    if ( $title && strpos( $title->getText(), 'Games/' ) === 0 ) {
        if ( preg_match( '/\{\{Game\s*[\|\}]/i', $text ) && stripos( $text, '{{GameEnd}}' ) === false ) {
            $text .= "\n{{GameEnd}}";
        }
    }
    return true;
};
