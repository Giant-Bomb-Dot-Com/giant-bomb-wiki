<?php

namespace MediaWiki\Extension\AlgoliaSearch;

use MediaWiki\MediaWikiServices;
use FauxRequest;
use RequestContext;
use ApiMain;
use Title;

class RecordMapper
{
    public static function getSupportedTypes(): array
    {
        return [
            "Game",
            "Character",
            "Concept",
            "Accessory",
            "Location",
            "Person",
            "Franchise",
            "Platform",
            "Company",
            "Object",
        ];
    }

    public static function mapRecord(
        string $type,
        Title $title,
        array $context = [],
    ): ?array {
        if (!in_array($type, self::getSupportedTypes(), true)) {
            return null;
        }

        $pageId = $title->getArticleID();
        if (!$pageId) {
            return null;
        }

        $href = $title->getLocalURL();
        $slug = $title->getPrefixedURL();

        $objectId = "wiki:" . $pageId;

        $record = [
            "objectID" => $objectId,
            "type" => $type,
            "title" => self::computeDisplayTitle($title, $type),
            "slug" => $slug,
            "href" => $href,
            "excerpt" => null,
            "thumbnail" => null, // TODO: confirm final thumbnail field
            "categories" => self::getCategoriesForPageId($pageId),
            "tags" => [],
            "publishDate" => null,
            "_updatedAt" => null,
        ];

        if ($type === "Game") {
            $template = self::getGameTemplateFields($title);
            if (
                isset($template["deck"]) &&
                is_string($template["deck"]) &&
                $template["deck"] !== ""
            ) {
                $record["excerpt"] = self::truncatePlaintext(
                    strip_tags($template["deck"]),
                    280,
                );
            }
            if (
                isset($template["image"]) &&
                is_string($template["image"]) &&
                $template["image"] !== ""
            ) {
                $thumb = self::resolveImageThumbUrl($template["image"], 640);
                if ($thumb) {
                    $record["thumbnail"] = $thumb;
                }
            }
        }
        if ($record["thumbnail"] === null) {
            $record["thumbnail"] = self::getThumbnailForTitle($title);
        }
        if ($record["thumbnail"] === null) {
            $legacyImage = LegacyImageHelper::findLegacyImageForTitle($title);
            if ($legacyImage !== null) {
                $record["thumbnail"] =
                    $legacyImage["thumb"] ?? $legacyImage["full"];
            }
        }
        if ($record["excerpt"] === null || $record["excerpt"] === "") {
            $fromExtract = self::getExcerptForTitle($title);
            if (is_string($fromExtract) && $fromExtract !== "") {
                $record["excerpt"] = self::truncatePlaintext($fromExtract, 280);
            }
        }

		if ( is_string( $record['thumbnail'] ) ) {
			$record['thumbnail'] = self::rewriteCdnUrl( $record['thumbnail'] );
		}

		$timestamps = self::getRevisionTimestamps($title);
		$record["_updatedAt"] = $timestamps["latest"] ?? null;
		$record["publishDate"] = $timestamps["first"] ?? null;

		return $record;
	}

    private static function getGameTemplateFields(Title $title): array
    {
        $out = ["deck" => null, "image" => null];
        $services = MediaWikiServices::getInstance();
        $page = $services->getWikiPageFactory()->newFromTitle($title);
        $content = $page ? $page->getContent() : null;
        if (!$content) {
            return $out;
        }
        $text = $content->getText();
        if (preg_match('/\| Deck=([^\n]+)/', $text, $m)) {
            $out["deck"] = trim($m[1]);
        }
        if (preg_match('/\| Image=([^\n]+)/', $text, $m)) {
            $out["image"] = trim($m[1]);
        }
        return $out;
    }

    private static function resolveImageThumbUrl(
        string $imageName,
        int $width = 640,
    ): ?string {
        $name = trim($imageName);
        if ($name === "") {
            return null;
        }
        if (stripos($name, "File:") !== 0) {
            $name = "File:" . $name;
        }
        $title = Title::newFromText($name);
        if (!$title) {
            return null;
        }
        $services = MediaWikiServices::getInstance();
        $file = $services->getRepoGroup()->findFile($title);
        if (!$file) {
            return null;
        }
        $thumbOutput = $file->transform(["width" => $width]);
        if ($thumbOutput && !$thumbOutput->isError()) {
            $url = $thumbOutput->getUrl();
            if ($url !== null) {
                return \wfExpandUrl($url, \PROTO_CANONICAL);
            }
        }
        $url = $file->getFullUrl();
        return is_string($url) && $url !== "" ? $url : null;
    }

    private static function rewriteCdnUrl( string $url ): string {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$cdnBase = $config->get( 'AlgoliaImageCdnBase' );
		if ( !is_string( $cdnBase ) || $cdnBase === '' ) {
			return $url;
		}

		$bucketName = $config->get( 'AWSBucketName' );
		if ( !is_string( $bucketName ) || $bucketName === '' ) {
			return $url;
		}

		$gcsPrefix = 'https://storage.googleapis.com/' . $bucketName . '/';
		if ( strpos( $url, $gcsPrefix ) === 0 ) {
			return rtrim( $cdnBase, '/' ) . '/' . substr( $url, strlen( $gcsPrefix ) );
		}

		return $url;
	}

    private static function truncatePlaintext(string $text, int $limit): string
    {
        $t = trim(preg_replace("/\s+/", " ", $text) ?? "");
        if (mb_strlen($t) <= $limit) {
            return $t;
        }
        $cut = mb_substr($t, 0, $limit);
        $space = mb_strrpos($cut, " ");
        if ($space !== false && $space >= (int) ($limit * 0.6)) {
            $cut = mb_substr($cut, 0, $space);
        }
        return rtrim($cut, " \t\n\r\0\x0B.,;:–—-_") . "…";
    }

    private static function computeDisplayTitle(
        Title $title,
        string $type,
    ): string {
        // smw Has name is the canonical display name set by entity templates
        try {
            $store = \SMW\StoreFactory::getStore();
            $subject = \SMW\DIWikiPage::newFromTitle($title);
            $vals = $store->getPropertyValues(
                $subject,
                new \SMW\DIProperty("Has name"),
            );
            if ($vals) {
                $first = reset($vals);
                if ($first instanceof \SMWDIBlob) {
                    $name = trim($first->getString());
                    if ($name !== "") {
                        return $name;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        // fallback: strip entity prefix, trailing _NNNN legacy id, underscores
        $text = $title->getText();
        $services = MediaWikiServices::getInstance();
        $config = $services->getMainConfig();
        $map = (array) $config->get("AlgoliaTypePrefixMap");
        $prefix = $map[$type] ?? null;
        if (is_string($prefix) && $prefix !== "") {
            $prefixWithSlash = $prefix . "/";
            if (strpos($text, $prefixWithSlash) === 0) {
                $text = substr($text, strlen($prefixWithSlash));
            }
        }
        $parts = explode("/", $text);
        $leaf = end($parts);
        $candidate = $leaf !== false && $leaf !== "" ? $leaf : $text;
        $candidate = preg_replace('/_\d+$/', '', $candidate);
        return str_replace("_", " ", $candidate);
    }

    private static function getExcerptForTitle(Title $title): ?string
    {
        try {
            $params = [
                "action" => "query",
                "prop" => "extracts",
                "exintro" => 1,
                "explaintext" => 1,
                "exsentences" => 2,
                "titles" => $title->getPrefixedText(),
                "format" => "json",
            ];
            $data = self::runApiQuery($params);
            if (!$data || empty($data["query"]["pages"])) {
                return null;
            }
            foreach ($data["query"]["pages"] as $page) {
                if (isset($page["extract"]) && is_string($page["extract"])) {
                    $excerpt = trim($page["extract"]);
                    return $excerpt !== "" ? $excerpt : null;
                }
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

    private static function getThumbnailForTitle(Title $title): ?string
    {
        try {
            $params = [
                "action" => "query",
                "prop" => "pageimages",
                "pithumbsize" => 640,
                "titles" => $title->getPrefixedText(),
                "format" => "json",
            ];
            $data = self::runApiQuery($params);
            if (!$data || empty($data["query"]["pages"])) {
                return null;
            }
            foreach ($data["query"]["pages"] as $page) {
                if (
                    isset($page["thumbnail"]["source"]) &&
                    is_string($page["thumbnail"]["source"])
                ) {
                    $url = trim($page["thumbnail"]["source"]);
                    return $url !== ""
                        ? \wfExpandUrl($url, \PROTO_CANONICAL)
                        : null;
                }
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

	private static function getCategoriesForPageId( int $pageId ): array {
		$services = MediaWikiServices::getInstance();
		$dbr = $services->getDBLoadBalancer()->getConnection( \DB_REPLICA );

		// Exclude hidden categories (tracking/maintenance categories marked with __HIDDENCAT__)
		$rows = $dbr->newSelectQueryBuilder()
			->select( 'cl_to' )
			->from( 'categorylinks' )
			->leftJoin( 'page', null, [
				'page_title = cl_to',
				'page_namespace' => NS_CATEGORY,
			] )
			->leftJoin( 'page_props', null, [
				'pp_page = page_id',
				'pp_propname' => 'hiddencat',
			] )
			->where( [
				'cl_from' => $pageId,
				'pp_value IS NULL',
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$categories = [];
		foreach ( $rows as $row ) {
			$categories[] = str_replace( '_', ' ', (string)$row->cl_to );
		}

		$config = $services->getMainConfig();
		$excludePatterns = (array)$config->get( 'AlgoliaExcludeCategoryPatterns' );
		if ( $excludePatterns ) {
			$categories = array_filter( $categories, static function ( string $cat ) use ( $excludePatterns ) {
				foreach ( $excludePatterns as $pattern ) {
					if ( preg_match( $pattern, $cat ) ) {
						return false;
					}
				}
				return true;
			} );
		}

		return array_values( array_unique( $categories ) );
	}

    private static function getRevisionTimestamps(Title $title): array
    {
        $services = MediaWikiServices::getInstance();
        $revisionLookup = $services->getRevisionLookup();
        $latest = $revisionLookup->getKnownCurrentRevision($title);
        $first = null;
        $firstTs = null;
        if (method_exists($revisionLookup, "getFirstRevision")) {
            $first = $revisionLookup->getFirstRevision($title);
        }
        return [
            "first" => $first ? $first->getTimestamp() : null,
            "latest" => $latest ? $latest->getTimestamp() : null,
        ];
    }

    // Legacy image helper methods were deduplicated into LegacyImageHelper.

    private static function runApiQuery(array $params): ?array
    {
        $fauxRequest = new FauxRequest($params);
        $context = new RequestContext();
        $context->setRequest($fauxRequest);
        $api = new ApiMain($context, true);
        $api->execute();
        $data = $api->getResult()->getResultData(null, [
            "Strip" => "all",
            "BC" => [],
        ]);
        return is_array($data) ? $data : null;
    }
}
