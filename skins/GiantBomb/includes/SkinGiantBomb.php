<?php

use GiantBomb\Skin\Helpers\PageHelper;
use MediaWiki\MediaWikiServices;

class SkinGiantBomb extends SkinTemplate
{
    public $skinname = "giantbomb";
    public $stylename = "GiantBomb";
    public $template = "GiantBombTemplate";
    public $useHeadElement = true;

    public function initPage(OutputPage $out)
    {
        parent::initPage($out);

        $out->addMeta("viewport", "width=device-width, initial-scale=1.0");

        // Pass header asset URL to JavaScript
        $headerAssetsUrl = getenv("GB_SITE_SERVER");
        $out->addJsConfigVars("wgHeaderAssetsUrl", $headerAssetsUrl);

        $out->addModuleStyles("skins.giantbomb.styles");
        $out->addModules([
            "skins.giantbomb",
            "skins.giantbomb.js",
            "skins.giantbomb.wikijs",
        ]);
    }

    public static function onOutputPageBodyAttributes(
        OutputPage $out,
        Skin $skin,
        array &$bodyAttrs,
    ) {
        $user = $skin->getUser();
        if ($user->isRegistered() && $user->isAllowed("gb-premium")) {
            $bodyAttrs["class"] .= " _rx7q";
        }
    }

    public static function onParserAfterTidy(Parser &$parser, &$text)
    {
        $text = preg_replace(
            '/(<div\s+id=["\']imageData["\'][^>]*>)/',
            '$1</div>',
            $text,
        );

        $text = preg_replace(
            '/\bclass="((?:[^"]*\s)?gb-\w+-sidebar(?:\s[^"]*)?)"/',
            'class="$1 gb-sidebar"',
            $text,
        );

        return true;
    }

    /**
     * Add SEO meta tags for template-rendered game pages.
     * Reads SMW properties to populate OpenGraph, Twitter cards, and meta description.
     */
    public static function onBeforePageDisplay(OutputPage &$out, Skin &$skin)
    {
        $title = $out->getTitle();
        if (!$title) {
            return;
        }

        $request = $out->getRequest();
        $action = $request->getText("action", "view");
        $isViewAction =
            $action === "view" || $action === "purge" || $action === "";
        $isFrontFacing =
            $isViewAction &&
            !$title->isSpecialPage() &&
            $title->getNamespace() >= 0 &&
            $title->getNamespace() % 2 === 0;

        if ($isFrontFacing) {
            $out->addHeadItem(
                "pubnation",
                '<script src="//scripts.pubnation.com/tags/4812f039-a343-4e18-89f9-d9461276ff90.js" async="" data-noptimize="1" data-cfasync="false"></script>',
            );
        }

        $pageTitle = $title->getText();

        if (preg_match('#^([A-Za-z]+/[^/]+)/(Images|Reviews|Releases|DLC)$#', $pageTitle, $sub)) {
            self::addSubpageSeoTags($out, $sub[1], $sub[2]);
            return;
        }

        // Process game, character, or franchise pages rendered via templates
        $isGamePage =
            strpos($pageTitle, "Games/") === 0 &&
            substr_count($pageTitle, "/") === 1;
        $isCharacterPage =
            strpos($pageTitle, "Characters/") === 0 &&
            substr_count($pageTitle, "/") === 1;
        $isFranchisePage =
            strpos($pageTitle, "Franchises/") === 0 &&
            substr_count($pageTitle, "/") === 1;

        if (!$isGamePage && !$isCharacterPage && !$isFranchisePage) {
            return;
        }

        if ($isCharacterPage) {
            self::addCharacterSeoTags($out, $title, $pageTitle);
            return;
        }

        if ($isFranchisePage) {
            self::addFranchiseSeoTags($out, $title, $pageTitle);
            return;
        }

        // Get SMW properties for this page
        $store = \SMW\StoreFactory::getStore();
        $subject = \SMW\DIWikiPage::newFromTitle($title);

        $wikitext = self::getPageWikitext($title);

        $gameName =
            self::getSMWPropertyValue($store, $subject, "Has name") ?:
            self::extractTemplateNameFromWikitext($wikitext) ?:
            self::cleanSlugFallback($pageTitle, "Games/");

        $deck = self::getSMWPropertyValue($store, $subject, "Has deck") ?: "";
        if ($deck === "") {
            $deck = self::extractFirstParagraph($wikitext);
        }

        $out->setHTMLTitle($gameName . " (Game) - " . $GLOBALS["wgSitename"]);

        $metaDescription = $deck;
        if ($metaDescription === "") {
            $metaDescription =
                $gameName .
                " - Game info, reviews, and more on Giant Bomb Wiki.";
        }

        $out->addMeta(
            "description",
            PageHelper::sanitizeMetaText($metaDescription),
        );

        $canonicalUrl = $title->getFullURL();
        $out->setCanonicalUrl($canonicalUrl);

        // Get cover image (SMW property first, then legacy imageData fallback)
        $metaImage = self::getPageImage($title, $store, $subject);

        // Add OpenGraph tags
        PageHelper::addOpenGraphTags(
            $out,
            [
                "og:title" => $gameName . " (Game) - " . $GLOBALS["wgSitename"],
                "og:description" => PageHelper::sanitizeMetaText(
                    $metaDescription,
                ),
                "og:url" => $canonicalUrl,
                "og:site_name" => $GLOBALS["wgSitename"],
                "og:type" => "video.game",
                "og:locale" => "en_US",
            ],
            $metaImage,
        );

        // Add Twitter Card tags
        PageHelper::addTwitterTags(
            $out,
            [
                "twitter:card" => $metaImage
                    ? "summary_large_image"
                    : "summary",
                "twitter:title" =>
                    $gameName . " (Game) - " . $GLOBALS["wgSitename"],
                "twitter:description" => PageHelper::sanitizeMetaText(
                    $metaDescription,
                ),
                "twitter:site" => "@giantbomb",
            ],
            $metaImage,
            $gameName,
        );

        // Add JSON-LD structured data for VideoGame
        $jsonLd = [
            "@context" => "https://schema.org",
            "@type" => "VideoGame",
            "name" => $gameName,
            "description" => PageHelper::sanitizeMetaText($metaDescription),
            "url" => $canonicalUrl,
        ];

        if ($metaImage) {
            $jsonLd["image"] = $metaImage;
        }

        // Get release date
        $releaseDate = self::getSMWPropertyValue(
            $store,
            $subject,
            "Has release date",
        );
        if ($releaseDate) {
            $jsonLd["datePublished"] = $releaseDate;
        }

        // Get genres
        $genres = self::getSMWPropertyValues($store, $subject, "Has genres");
        if (!empty($genres)) {
            $jsonLd["genre"] = array_map(function ($g) {
                return str_replace("Genres/", "", $g);
            }, $genres);
        }

        $jsonLd += self::cachedReleaseJsonLdFields($title);

        $out->addHeadItem(
            "jsonld-videogame",
            '<script type="application/ld+json">' .
                json_encode($jsonLd, JSON_UNESCAPED_SLASHES) .
                "</script>",
        );
    }

    /**
     * Get a single SMW property value for a subject.
     */
    private static function getSMWPropertyValue(
        $store,
        $subject,
        string $propertyName,
    ): ?string {
        try {
            // DIProperty wants the db key -> "Has guid" (label form) throws
            $property = new \SMW\DIProperty(
                str_replace(" ", "_", $propertyName),
            );
            $values = $store->getPropertyValues($subject, $property);
            if (!empty($values)) {
                $value = reset($values);
                if ($value instanceof \SMWDIBlob) {
                    return $value->getString();
                } elseif ($value instanceof \SMW\DIWikiPage) {
                    return $value->getTitle()->getText();
                } elseif ($value instanceof \SMWDITime) {
                    return $value->getMwTimestamp();
                }
            }
        } catch (\Exception $e) {
            // Property doesn't exist or SMW error
        }
        return null;
    }

    /**
     * Get multiple SMW property values for a subject.
     */
    private static function getSMWPropertyValues(
        $store,
        $subject,
        string $propertyName,
    ): array {
        $result = [];
        try {
            $property = new \SMW\DIProperty($propertyName);
            $values = $store->getPropertyValues($subject, $property);
            foreach ($values as $value) {
                if ($value instanceof \SMWDIBlob) {
                    $result[] = $value->getString();
                } elseif ($value instanceof \SMW\DIWikiPage) {
                    $result[] = $value->getTitle()->getText();
                }
            }
        } catch (\Exception $e) {
            // Property doesn't exist or SMW error
        }
        return $result;
    }

    /**
     * Get the cover/profile image for a page.
     *
     * Checks in order:
     * 1. SMW "Has image" property (preferred - structured data)
     * 2. Legacy imageData div in page content (fallback for old imports)
     *
     * @param \Title $title The page title
     * @param mixed $store Optional SMW store (will be created if not provided)
     * @param mixed $subject Optional SMW subject (will be created if not provided)
     * @return string|null The image URL or null if not found
     */
    private static function getPageImage(
        \Title $title,
        $store = null,
        $subject = null,
    ): ?string {
        // Try SMW "Has image" property first
        if (!$store) {
            $store = \SMW\StoreFactory::getStore();
        }
        if (!$subject) {
            $subject = \SMW\DIWikiPage::newFromTitle($title);
        }

        $smwImage = self::getSMWPropertyValue($store, $subject, "Has image");
        if ($smwImage) {
            // Has image stores the GB image path (e.g., "scale_large/xxx/filename.jpg")
            // or could be a full URL - check and build appropriately
            if (strpos($smwImage, "http") === 0) {
                return $smwImage;
            }
            // Assume it's a GB image path - build full URL
            return "https://www.giantbomb.com/a/uploads/" . $smwImage;
        }

        // Fall back to parsing legacy imageData from page content
        try {
            $wikiPage = MediaWikiServices::getInstance()
                ->getWikiPageFactory()
                ->newFromTitle($title);
            $content = $wikiPage->getContent();

            if (!$content) {
                return null;
            }

            $text = $content->getText();
            $imageData = PageHelper::parseLegacyImageData($text);

            if ($imageData && isset($imageData["infobox"])) {
                return PageHelper::buildLegacyImageUrl($imageData["infobox"], [
                    "scale_super",
                    "scale_large",
                    "scale_medium",
                    "screen_kubrick",
                ]);
            }
        } catch (\Exception $e) {
            // Page doesn't exist or content inaccessible
        }

        return null;
    }

    /**
     * @deprecated Use getPageImage() instead
     */
    private static function getGameCoverImage(\Title $title): ?string
    {
        return self::getPageImage($title);
    }

    /**
     * Add SEO meta tags for character pages.
     */
    private static function addCharacterSeoTags(
        OutputPage &$out,
        \Title $title,
        string $pageTitle,
    ): void {
        $store = \SMW\StoreFactory::getStore();
        $subject = \SMW\DIWikiPage::newFromTitle($title);

        $wikitext = self::getPageWikitext($title);

        $characterName =
            self::getSMWPropertyValue($store, $subject, "Has name") ?:
            self::extractTemplateNameFromWikitext($wikitext) ?:
            self::cleanSlugFallback($pageTitle, "Characters/");

        $deck = self::getSMWPropertyValue($store, $subject, "Has deck") ?: "";
        if ($deck === "") {
            $deck = self::extractFirstParagraph($wikitext);
        }

        $out->setHTMLTitle(
            $characterName . " (Character) - " . $GLOBALS["wgSitename"],
        );

        $metaDescription = $deck;
        if ($metaDescription === "") {
            $metaDescription =
                $characterName .
                " - Character info and appearances on Giant Bomb Wiki.";
        }

        $out->addMeta(
            "description",
            PageHelper::sanitizeMetaText($metaDescription),
        );

        $canonicalUrl = $title->getFullURL();
        $out->setCanonicalUrl($canonicalUrl);
        $metaImage = self::getPageImage($title, $store, $subject);

        PageHelper::addOpenGraphTags(
            $out,
            [
                "og:title" =>
                    $characterName . " (Character) - " . $GLOBALS["wgSitename"],
                "og:description" => PageHelper::sanitizeMetaText(
                    $metaDescription,
                ),
                "og:url" => $canonicalUrl,
                "og:site_name" => $GLOBALS["wgSitename"],
                "og:type" => "profile",
                "og:locale" => "en_US",
            ],
            $metaImage,
        );

        PageHelper::addTwitterTags(
            $out,
            [
                "twitter:card" => $metaImage
                    ? "summary_large_image"
                    : "summary",
                "twitter:title" =>
                    $characterName . " (Character) - " . $GLOBALS["wgSitename"],
                "twitter:description" => PageHelper::sanitizeMetaText(
                    $metaDescription,
                ),
                "twitter:site" => "@giantbomb",
            ],
            $metaImage,
            $characterName,
        );

        // FictionalCharacter is the correct Schema.org type for video game characters
        $jsonLd = [
            "@context" => "https://schema.org",
            "@type" => "FictionalCharacter",
            "name" => $characterName,
            "description" => PageHelper::sanitizeMetaText($metaDescription),
            "url" => $canonicalUrl,
        ];

        if ($metaImage) {
            $jsonLd["image"] = $metaImage;
        }

        $out->addHeadItem(
            "jsonld-character",
            '<script type="application/ld+json">' .
                json_encode($jsonLd, JSON_UNESCAPED_SLASHES) .
                "</script>",
        );
    }

    /**
     * Add SEO meta tags for franchise pages.
     */
    private static function addFranchiseSeoTags(
        OutputPage &$out,
        \Title $title,
        string $pageTitle,
    ): void {
        $store = \SMW\StoreFactory::getStore();
        $subject = \SMW\DIWikiPage::newFromTitle($title);

        $wikitext = self::getPageWikitext($title);

        $franchiseName =
            self::getSMWPropertyValue($store, $subject, "Has name") ?:
            self::extractTemplateNameFromWikitext($wikitext) ?:
            self::cleanSlugFallback($pageTitle, "Franchises/");

        $deck = self::getSMWPropertyValue($store, $subject, "Has deck") ?: "";
        if ($deck === "") {
            $deck = self::extractFirstParagraph($wikitext);
        }

        $out->setHTMLTitle(
            $franchiseName . " (Franchise) - " . $GLOBALS["wgSitename"],
        );

        $metaDescription = $deck;
        if ($metaDescription === "") {
            $metaDescription =
                $franchiseName .
                " - Franchise info, games, and characters on Giant Bomb Wiki.";
        }

        $out->addMeta(
            "description",
            PageHelper::sanitizeMetaText($metaDescription),
        );

        $canonicalUrl = $title->getFullURL();
        $out->setCanonicalUrl($canonicalUrl);
        $metaImage = self::getPageImage($title, $store, $subject);

        PageHelper::addOpenGraphTags(
            $out,
            [
                "og:title" =>
                    $franchiseName . " (Franchise) - " . $GLOBALS["wgSitename"],
                "og:description" => PageHelper::sanitizeMetaText(
                    $metaDescription,
                ),
                "og:url" => $canonicalUrl,
                "og:site_name" => $GLOBALS["wgSitename"],
                "og:type" => "website",
                "og:locale" => "en_US",
            ],
            $metaImage,
        );

        PageHelper::addTwitterTags(
            $out,
            [
                "twitter:card" => $metaImage
                    ? "summary_large_image"
                    : "summary",
                "twitter:title" =>
                    $franchiseName . " (Franchise) - " . $GLOBALS["wgSitename"],
                "twitter:description" => PageHelper::sanitizeMetaText(
                    $metaDescription,
                ),
                "twitter:site" => "@giantbomb",
            ],
            $metaImage,
            $franchiseName,
        );

        $jsonLd = [
            "@context" => "https://schema.org",
            "@type" => "CreativeWorkSeries",
            "name" => $franchiseName,
            "description" => PageHelper::sanitizeMetaText($metaDescription),
            "url" => $canonicalUrl,
        ];

        if ($metaImage) {
            $jsonLd["image"] = $metaImage;
        }

        $out->addHeadItem(
            "jsonld-franchise",
            '<script type="application/ld+json">' .
                json_encode($jsonLd, JSON_UNESCAPED_SLASHES) .
                "</script>",
        );
    }

    /**
     * Load a page's raw wikitext, cached per request.
     */
    private static function getPageWikitext(\Title $title): string
    {
        static $cache = [];
        $key = $title->getPrefixedDBkey();
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $wikiPage = MediaWikiServices::getInstance()
                ->getWikiPageFactory()
                ->newFromTitle($title);
            $content = $wikiPage->getContent();
            if (!$content) {
                return $cache[$key] = "";
            }
            return $cache[$key] = $content->getText();
        } catch (\Throwable $e) {
            return $cache[$key] = "";
        }
    }

    // Last-resort fallback when neither SMW nor the template provides a name.
    // Strips the entity prefix and any trailing legacy disambiguation id (e.g.
    // "Games/Sprout 64629" -> "Sprout"). Threshold is >=5 trailing digits so
    // legitimate year/sequel numbers survive: "Halo 2600", "Madden NFL 2008",
    // "FIFA 99", "Tekken 7".
    // /Images + /Reviews meta -> parent entity's name/deck/cover
    private static function addSubpageSeoTags(
        OutputPage $out,
        string $parentText,
        string $kind,
    ): void {
        $parentTitle = \Title::newFromText($parentText);
        if (!$parentTitle) {
            return;
        }
        $store = \SMW\StoreFactory::getStore();
        $subject = \SMW\DIWikiPage::newFromTitle($parentTitle);
        $prefix = substr($parentText, 0, strpos($parentText, "/") + 1);
        $name =
            self::getSMWPropertyValue($store, $subject, "Has name") ?:
            self::cleanSlugFallback($parentText, $prefix);
        $deck = self::getSMWPropertyValue($store, $subject, "Has deck") ?: "";
        $metaImage = self::getPageImage($parentTitle, $store, $subject);

        $releaseItems = null;
        if ($kind === "Images") {
            $htmlTitle = "$name Images - " . $GLOBALS["wgSitename"];
            $desc = "Images, screenshots, and artwork for $name.";
            if ($deck !== "") {
                $desc .= " " . $deck;
            }
        } elseif ($kind === "Releases") {
            $htmlTitle = "$name Releases - " . $GLOBALS["wgSitename"];
            $releaseItems = self::getReleaseItems($out->getTitle());
            $desc = self::buildReleasesSeoDescription($name, $releaseItems);
        } elseif ($kind === "DLC") {
            $htmlTitle = "$name DLC - " . $GLOBALS["wgSitename"];
            $desc = "Downloadable content for $name - add-ons, expansions, release dates, and platforms.";
            if ($deck !== "") {
                $desc .= " " . $deck;
            }
        } else {
            $htmlTitle = "$name Reviews - " . $GLOBALS["wgSitename"];
            $guid = self::getSMWPropertyValue($store, $subject, "Has guid") ?: "";
            $desc = self::buildReviewsDescription($name, $guid);
            if ($desc === "") {
                $desc = "Reviews and user ratings for $name on " . $GLOBALS["wgSitename"] . ".";
            }
        }

        // decks carry emdashes -> plain hyphens in embed text
        $desc = str_replace(["\u{2014}", "\u{2013}"], "-", $desc);
        $htmlTitle = str_replace(["\u{2014}", "\u{2013}"], "-", $htmlTitle);

        $out->setHTMLTitle($htmlTitle);
        $out->addMeta("description", PageHelper::sanitizeMetaText($desc));
        $canonicalUrl = $out->getTitle()->getFullURL();
        $out->setCanonicalUrl($canonicalUrl);

        PageHelper::addOpenGraphTags(
            $out,
            [
                "og:title" => $htmlTitle,
                "og:description" => PageHelper::sanitizeMetaText($desc),
                "og:url" => $canonicalUrl,
                "og:site_name" => $GLOBALS["wgSitename"],
                "og:type" => "website",
                "og:locale" => "en_US",
            ],
            $metaImage,
        );
        PageHelper::addTwitterTags(
            $out,
            [
                "twitter:card" => $metaImage ? "summary_large_image" : "summary",
                "twitter:title" => $htmlTitle,
                "twitter:description" => PageHelper::sanitizeMetaText($desc),
                "twitter:site" => "@giantbomb",
            ],
            $metaImage,
            $name,
        );

        if ($kind === "Releases" && !empty($releaseItems)) {
            $jsonLd = [
                "@context" => "https://schema.org",
                "@type" => "VideoGame",
                "name" => $name,
                "url" => $parentTitle->getFullURL(),
                "mainEntityOfPage" => $canonicalUrl,
            ];
            if ($metaImage) {
                $jsonLd["image"] = $metaImage;
            }
            $jsonLd += self::releaseJsonLdFields($releaseItems);
            $out->addHeadItem(
                "jsonld-releases",
                '<script type="application/ld+json">' .
                    json_encode($jsonLd, JSON_UNESCAPED_SLASHES) .
                    "</script>",
            );
        }
    }

    // hot path (every game page view) -> wan-cached, keyed by the subpage
    // revision so release edits refresh it
    private static function cachedReleaseJsonLdFields(\Title $gameTitle): array
    {
        $subpage = \Title::newFromText(
            $gameTitle->getPrefixedText() . "/Releases",
        );
        if (!$subpage || !$subpage->exists()) {
            return [];
        }
        $cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
        return $cache->getWithSetCallback(
            $cache->makeKey(
                "gb-release-jsonld",
                $subpage->getLatestRevID(),
            ),
            86400,
            function () use ($subpage) {
                return self::releaseJsonLdFields(
                    self::getReleaseItems($subpage),
                );
            },
        );
    }

    private static function getReleaseItems(\Title $subpage): array
    {
        $wikitext = self::getPageWikitext($subpage);
        if ($wikitext === "") {
            return [];
        }
        return PageHelper::extractReleaseData($wikitext)["items"];
    }

    private static function buildReleasesSeoDescription(
        string $name,
        array $items,
    ): string {
        if (empty($items)) {
            return "Release information for $name - platforms, regions, dates, ratings, and product codes.";
        }
        $platforms = [];
        foreach ($items as $item) {
            if ($item["platform"] !== "") {
                $platforms[$item["platform"]] = true;
            }
        }
        $platforms = array_keys($platforms);
        $count = count($items);
        $desc = sprintf(
            "All %d release%s of %s",
            $count,
            $count === 1 ? "" : "s",
            $name,
        );
        if (!empty($platforms)) {
            $shown = array_slice($platforms, 0, 4);
            $desc .= " on " . implode(", ", $shown);
            if (count($platforms) > 4) {
                $desc .= " and " . (count($platforms) - 4) . " more";
            }
        }
        $desc .= " - release dates, regions, ratings, and product codes.";
        return $desc;
    }

    // jsonld fields shared by the game page and the /Releases subpage.
    // only exact (Full) dates become releasedEvent entries -- fuzzy dates have
    // an empty ReleaseDate param, so no fabricated precision
    private static function releaseJsonLdFields(array $items): array
    {
        $fields = [];
        $platforms = [];
        $events = [];
        $rating = "";
        foreach ($items as $item) {
            if ($item["platform"] !== "") {
                $platforms[$item["platform"]] = true;
            }
            if ($rating === "" && $item["rating"] !== "N/A") {
                $rating = $item["rating"];
            }
            if (strpos($item["rating"], "ESRB") === 0) {
                $rating = $item["rating"];
            }
            if (
                count($events) < 20 &&
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $item["releaseDate"])
            ) {
                $event = [
                    "@type" => "PublicationEvent",
                    "name" => trim(
                        $item["platform"] .
                            ($item["region"] !== ""
                                ? " (" . $item["region"] . ")"
                                : ""),
                    ),
                    "startDate" => $item["releaseDate"],
                ];
                if ($item["region"] !== "") {
                    $event["location"] = [
                        "@type" => "Country",
                        "name" => $item["region"],
                    ];
                }
                $events[] = $event;
            }
        }
        if (!empty($platforms)) {
            $fields["gamePlatform"] = array_keys($platforms);
        }
        if ($rating !== "") {
            $fields["contentRating"] = $rating;
        }
        if (!empty($events)) {
            $fields["releasedEvent"] = $events;
        }
        return $fields;
    }

    // user aggregate + staff score via the public api, wan-cached 1h
    private static function buildReviewsDescription(
        string $name,
        string $guid,
    ): string {
        if ($guid === "" || !getenv("GB_API_KEY")) {
            return "";
        }
        $cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
        return $cache->getWithSetCallback(
            $cache->makeKey("gb-reviews-meta", $guid),
            3600,
            function ($oldValue, &$ttl) use ($name, $guid) {
                $key = getenv("GB_API_KEY");
                $http = MediaWikiServices::getInstance()->getHttpRequestFactory();
                $get = function (string $url) use ($http) {
                    $req = $http->create($url, ["timeout" => 4], __METHOD__);
                    if (!$req->execute()->isOK()) {
                        return null;
                    }
                    return json_decode($req->getContent(), true);
                };

                $parts = [];

                // user scores are 0-100 -> shown /5
                $sum = 0;
                $count = 0;
                for ($offset = 0; $offset < 500; $offset += 100) {
                    $data = $get(
                        "https://giantbomb.com/api/public/user-reviews?limit=100&offset=$offset&game_guid=$guid&api_key=$key&format=json",
                    );
                    foreach ($data["results"] ?? [] as $r) {
                        $s = (float) ($r["score"] ?? -1);
                        if ($s >= 0 && $s <= 100) {
                            $sum += $s;
                            $count++;
                        }
                    }
                    if (empty($data["pagination"]["has_next"])) {
                        break;
                    }
                }
                if ($count > 0) {
                    $parts[] = sprintf(
                        "User rating %.1f/5 from %d review%s",
                        $sum / $count / 20,
                        $count,
                        $count === 1 ? "" : "s",
                    );
                }

                // staff score is already /5
                $staff = $get(
                    "https://giantbomb.com/api/public/reviews?limit=1&game_guid=$guid&api_key=$key&sort=publish_date:desc&format=json",
                );
                $sr = $staff["results"][0] ?? null;
                if ($sr && isset($sr["score"])) {
                    $by = $sr["reviewer"]["name"] ?? "";
                    $parts[] = sprintf(
                        "Giant Bomb review: %s/5%s",
                        rtrim(rtrim(number_format((float) $sr["score"], 1), "0"), "."),
                        $by !== "" ? " by $by" : "",
                    );
                }

                if (!$parts) {
                    // empty or failed fetch -> retry sooner
                    $ttl = 300;
                    return "";
                }
                return "$name reviews. " . implode(". ", $parts) . ".";
            },
        );
    }

    private static function cleanSlugFallback( string $pageTitle, string $prefix ): string {
        $slug = str_replace( $prefix, '', $pageTitle );
        $slug = str_replace( '_', ' ', $slug );
        return rtrim( preg_replace( '/[ _]\d{5,}$/', '', $slug ) );
    }

    /**
     * Pull the "Name" parameter from a {{Game|...}} / {{Character|...}} / {{Franchise|...}}
     * invocation. The template value keeps punctuation (colons, ampersands) that the URL
     * slug strips.
     */
    private static function extractTemplateNameFromWikitext(
        string $wikitext,
    ): string {
        if ($wikitext === "") {
            return "";
        }
        if (preg_match('/^\s*\|\s*Name\s*=\s*(.+?)\s*$/mi', $wikitext, $m)) {
            return trim($m[1]);
        }
        return "";
    }

    /**
     * Pull a short plain-text excerpt for meta description use. Tries a template "Deck"
     * parameter first, then the first non-trivial <p>...</p> block (legacy HTML-in-wikitext
     * pages), then the first non-markup line. Returns empty if nothing usable is found.
     */
    private static function extractFirstParagraph(
        string $wikitext,
        int $maxLen = 280,
        int $minLen = 40,
    ): string {
        if ($wikitext === "") {
            return "";
        }

        if (preg_match('/^\s*\|\s*Deck\s*=\s*(.+?)\s*$/mi', $wikitext, $m)) {
            $text = PageHelper::sanitizeMetaText($m[1], $maxLen);
            if ($text !== "" && mb_strlen($text) >= $minLen) {
                return $text;
            }
        }

        $stripped = preg_replace(
            "/\{\{[^{}]*(?:\{\{[^{}]*\}\}[^{}]*)*\}\}/s",
            "",
            $wikitext,
        );
        if ($stripped === null) {
            $stripped = $wikitext;
        }

        if (preg_match_all("/<p[^>]*>(.*?)<\/p>/is", $stripped, $matches)) {
            foreach ($matches[1] as $candidate) {
                $text = PageHelper::sanitizeMetaText($candidate, $maxLen);
                if ($text !== "" && mb_strlen($text) >= $minLen) {
                    return $text;
                }
            }
        }

        foreach (preg_split('/\r?\n/', $stripped) as $line) {
            $line = trim($line);
            if ($line === "") {
                continue;
            }
            $first = $line[0];
            if (
                $first === "<" ||
                $first === "{" ||
                $first === "|" ||
                $first === "=" ||
                $first === "*" ||
                $first === "#" ||
                $first === ":"
            ) {
                continue;
            }
            $text = PageHelper::sanitizeMetaText($line, $maxLen);
            if ($text !== "" && mb_strlen($text) >= $minLen) {
                return $text;
            }
        }

        return "";
    }
}
