<?php

namespace MediaWiki\Extension\GBGallery;

use MediaWiki\Hook\ParserFirstCallInitHook;
use Parser;
use PPFrame;

class GBGalleryHooks implements ParserFirstCallInitHook
{
    public function onParserFirstCallInit($parser)
    {
        $parser->setHook("gb-gallery", [self::class, "renderGallery"]);
    }

    public static function renderGallery(
        ?string $input,
        array $attrs,
        Parser $parser,
        PPFrame $frame,
    ): string {
        if ($input === null || trim($input) === "") {
            return "";
        }

        $config = $parser->getOutput()->getExtensionData("gb-gallery-config");
        if ($config === null) {
            $mwConfig = \MediaWiki\MediaWikiServices::getInstance()->getMainConfig();
            $config = [
                "baseUrl" => rtrim($mwConfig->get("GBGalleryBaseUrl"), "/"),
                "thumbSize" => $mwConfig->get("GBGalleryThumbSize"),
                "fullSize" => $mwConfig->get("GBGalleryFullSize"),
            ];
            $parser
                ->getOutput()
                ->setExtensionData("gb-gallery-config", $config);
        }

        $lines = explode("\n", $input);
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === "") {
                continue;
            }

            $parts = explode("|", $line, 2);
            $imagePath = trim($parts[0]);
            $caption = isset($parts[1]) ? trim($parts[1]) : "";

            if ($imagePath === "") {
                continue;
            }

            $thumbUrl =
                $config["baseUrl"] .
                "/" .
                $config["thumbSize"] .
                "/" .
                $imagePath;
            $fullUrl =
                $config["baseUrl"] .
                "/" .
                $config["fullSize"] .
                "/" .
                $imagePath;

            $thumbUrlEnc = htmlspecialchars($thumbUrl, ENT_QUOTES);
            $fullUrlEnc = htmlspecialchars($fullUrl, ENT_QUOTES);
            $captionEnc = htmlspecialchars($caption, ENT_QUOTES);

            $captionHtml = "";
            if ($caption !== "") {
                $captionHtml =
                    '<div class="gb-gallery-caption">' . $captionEnc . "</div>";
            }

            $items[] =
                '<div class="gb-gallery-item" data-thumb="' .
                $thumbUrlEnc .
                '" data-full="' .
                $fullUrlEnc .
                '" data-alt="' .
                $captionEnc .
                '">' .
                '<a href="' .
                $fullUrlEnc .
                '" target="_blank" rel="noopener"></a>' .
                $captionHtml .
                "</div>";
        }

        if (count($items) === 0) {
            return "";
        }

        $parser->getOutput()->addModules(["ext.gbgallery"]);
        return '<div class="gb-gallery" data-ad-context="gallery">' .
            implode("", $items) .
            "</div>";
    }
}
