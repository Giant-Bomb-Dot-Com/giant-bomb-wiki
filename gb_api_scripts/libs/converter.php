<?php

require_once __DIR__ . "/../libs/common.php";

class HtmlToMediaWikiConverter
{
    use CommonVariablesAndMethods;

    // rendered width = min(data-width, cap); caps = measured rendition widths.
    // data-size="large" serves scale_super (960w) -- scale_large is only 640w
    private const IMAGE_WIDTH_CAPS = [
        "small" => 320,
        "medium" => 480,
        "large" => 960,
    ];
    private const IMAGE_RENDITIONS = [
        "small" => "scale_small",
        "medium" => "scale_medium",
        "large" => "scale_super",
    ];

    private DbInterface $dbw;

    // GBFigure calls pulled out of <li> content, re-emitted before the list
    private array $hoistedFigures = [];

    private DOMDocument $dom;
    private int $typeId;
    private int $id;

    /**
     * Optional hook: resolve legacy data-ref-id guids to a full wiki page title (e.g. "Games/Foo")
     * before querying the local API database. Return null to fall through to built-in logic,
     * or false to drop the link entirely and keep just its display text.
     * Fourth argument is the &lt;a&gt; element (for slug fallback without DB).
     *
     * @var null|callable(string $contentGuid, int $contentTypeId, int $contentId, \DOMElement $link): null|false|string
     */
    private $wikiPageTitleResolver = null;

    public function __construct(DbInterface $dbw)
    {
        $this->dbw = $dbw;
        $this->dom = new DOMDocument("1.0", "UTF-8");
        libxml_use_internal_errors(true);
    }

    /**
     * @param null|callable(string,int,int,\DOMElement): ?string $resolver
     */
    public function setWikiPageTitleResolver(?callable $resolver): void
    {
        $this->wikiPageTitleResolver = $resolver;
    }

    /**
     * Optional hook: look up a legacy image id in the `image` table.
     * Return ['name' => ..., 'path' => ..., 'deleted' => 0|1] or null.
     */
    private $imageLookup = null;

    public function setImageLookup(?callable $lookup): void
    {
        $this->imageLookup = $lookup;
    }

    // rendition keys vary per image: non-jpg often exists only as ignore_jpg_*
    private function chooseRendition(array $row, string $size): string
    {
        $rendition = self::IMAGE_RENDITIONS[$size];
        $ext = strtolower(pathinfo($row["name"], PATHINFO_EXTENSION));
        $keys = array_map(
            "trim",
            explode(",", (string) ($row["image_sizes"] ?? "")),
        );
        if (
            !in_array($ext, ["jpg", "jpeg"], true) &&
            in_array("ignore_jpg_{$rendition}", $keys, true)
        ) {
            return "ignore_jpg_{$rendition}";
        }
        return $rendition;
    }

    // filenames can carry spaces etc -> encode, or the bare url breaks
    private function buildUploadUrl(string $rendition, array $row): string
    {
        return "https://www.giantbomb.com/a/uploads/{$rendition}/{$row["path"]}" .
            rawurlencode($row["name"]);
    }

    // media.giantbomb.com urls carry stale buckets + size-suffixed filenames;
    // the image table knows the real path/name. null = not media-hosted,
    // false = drop (deleted or unresolvable), array = the image row
    private function lookupMediaImage(string $url): null|false|array
    {
        if (
            strpos($url, "media.giantbomb.com/uploads") === false ||
            $this->imageLookup === null
        ) {
            return null;
        }
        $base = basename((string) parse_url($url, PHP_URL_PATH));
        if (!preg_match("/^(\d+)-/", $base, $m)) {
            return false;
        }
        $row = ($this->imageLookup)((int) $m[1]);
        return $row && empty($row["deleted"]) ? $row : false;
    }

    /**
     * Converts the description to a MW friendly format
     *
     * @param string $description
     * @param int    $typeId
     * @param int    $id
     * @return string|false
     */
    public function convert(
        string $description,
        int $typeId,
        int $id,
    ): string|false {
        if (empty($description)) {
            return "";
        }

        $this->typeId = $typeId;
        $this->id = $id;

        $description = $this->preProcess($description);

        libxml_use_internal_errors(true);
        // UTF-8 is declared in <meta>; avoid mb_convert_encoding(..., 'HTML-ENTITIES', ...) (deprecated PHP 8.2+).
        $wrappedDescription =
            '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' .
            $description .
            "</body></html>";

        $success = $this->dom->loadHTML(
            $wrappedDescription,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        if (!$success) {
            echo sprintf(
                "WARNING: failed to load html for %s-%s.\n",
                $typeId,
                $id,
            );
            return false;
        }

        $block = $this->dom->getElementsByTagName("body")->item(0);

        // figures has an embedded link to the image so we process this first
        $figureTags = $block->getElementsByTagName("figure");
        for ($i = $figureTags->length - 1; $i >= 0; $i--) {
            $figureTag = $figureTags->item($i);
            $parent = $figureTag->parentNode;

            $mwFigure = $this->convertFigure($figureTag);
            if ($mwFigure === false) {
                continue;
            }
            $newTextNode = $this->dom->createTextNode($mwFigure);

            $parent->insertBefore($newTextNode, $figureTag);
            $parent->removeChild($figureTag);
        }

        // <image> pass runs BEFORE the <img> pass: many legacy embeds nest
        // <a><img/></a> inside <image> -- an earlier img pass turned that img
        // into a bare url text node that convertImage then read as the caption,
        // rendering a duplicate copy of the image
        $imagesToProcess = [];
        $images = $block->getElementsByTagName("image");
        foreach ($images as $imageNode) {
            $imagesToProcess[] = $imageNode;
        }
        foreach (array_reverse($imagesToProcess) as $image) {
            $mwImage = $this->convertImage($image);
            if ($mwImage === false) {
                if ($image->parentNode) {
                    $image->parentNode->removeChild($image);
                }
            } else {
                $textNode = $this->dom->createTextNode($mwImage);
                if ($image->parentNode) {
                    $image->parentNode->replaceChild($textNode, $image);
                }
            }
        }

        // followed by remaining standalone img tags
        $imagesToProcess = [];
        $images = $block->getElementsByTagName("img");
        foreach ($images as $imageNode) {
            $imagesToProcess[] = $imageNode;
        }
        foreach (array_reverse($imagesToProcess) as $image) {
            if (
                $image->hasAttribute("style") &&
                preg_match("/display/", $image->getAttribute("style")) === false
            ) {
                continue;
            }
            $mwImage = $this->convertImg($image);
            if ($mwImage === false) {
                if ($image->parentNode) {
                    $image->parentNode->removeChild($image);
                }
            } else {
                $textNode = $this->dom->createTextNode($mwImage);
                if ($image->parentNode) {
                    $image->parentNode->replaceChild($textNode, $image);
                }
            }
        }

        // followed by all the links
        $aTags = $block->getElementsByTagName("a");
        for ($i = $aTags->length - 1; $i >= 0; $i--) {
            $aTag = $aTags->item($i);
            $parent = $aTag->parentNode;

            $mwLink = $this->convertLink($aTag);
            $newTextNode = $this->dom->createTextNode($mwLink);

            $parent->insertBefore($newTextNode, $aTag);
            $parent->removeChild($aTag);
        }

        // lists and tables go last because when getInnerHtml is called the
        //   brackets are converted to their html entity form and wouldn't
        //   get picked up otherwise
        $listsToProcess = [];
        $orderedLists = $block->getElementsByTagName("ol");
        foreach ($orderedLists as $listNode) {
            $listsToProcess[] = $listNode;
        }
        $unorderedLists = $block->getElementsByTagName("ul");
        foreach ($unorderedLists as $listNode) {
            $listsToProcess[] = $listNode;
        }
        foreach (array_reverse($listsToProcess) as $list) {
            $this->hoistedFigures = [];
            $mwList = $this->convertList($list);
            // figures found inside list items float beside the list on the old
            // site; a block template inside a "#" line splits the list instead
            if ($this->hoistedFigures) {
                $mwList = implode("\n", $this->hoistedFigures) . "\n" . $mwList;
            }
            $textNode = $this->dom->createTextNode($mwList);
            if ($list->parentNode) {
                $list->parentNode->replaceChild($textNode, $list);
            }
        }

        $tableTags = $block->getElementsByTagName("table");
        for ($i = $tableTags->length - 1; $i >= 0; $i--) {
            $tableTag = $tableTags->item($i);
            $parent = $tableTag->parentNode;

            $mwTable = $this->convertTable($tableTag);
            if ($mwTable === false) {
                continue;
            }
            $newTextNode = $this->dom->createTextNode($mwTable);

            $parent->insertBefore($newTextNode, $tableTag);
            $parent->removeChild($tableTag);
        }

        $divTags = $block->getElementsByTagName("div");
        for ($i = $divTags->length - 1; $i >= 0; $i--) {
            $divTag = $divTags->item($i);
            $parent = $divTag->parentNode;

            if ($divTag->hasAttribute("data-embed-type")) {
                if (
                    in_array($divTag->getAttribute("data-embed-type"), [
                        "tweet",
                        "video",
                    ])
                ) {
                    $src = $divTag->getAttribute("data-src");
                    $newTextNode = $this->dom->createTextNode($src);

                    $parent->insertBefore($newTextNode, $divTag);
                    $parent->removeChild($divTag);
                }
            }
        }

        $scriptTags = $block->getElementsByTagName("script");
        for ($i = $scriptTags->length - 1; $i >= 0; $i--) {
            $scriptTag = $scriptTags->item($i);
            $parent = $scriptTag->parentNode;
            $parent->removeChild($scriptTag);
        }

        $pTags = $block->getElementsByTagName("p");
        for ($i = $pTags->length - 1; $i >= 0; $i--) {
            $pTag = $pTags->item($i);
            $parent = $pTag->parentNode;

            $pContent = "";
            foreach ($pTag->childNodes as $child) {
                $pContent .= $this->dom->saveHTML($child);
            }

            // Use double newlines for proper paragraph separation in MediaWiki
            $newTextNode = $this->dom->createTextNode($pContent . "\n\n");

            $parent->insertBefore($newTextNode, $pTag);
            $parent->removeChild($pTag);
        }

        $modifiedDescription = $this->getInnerHtml($block);

        return $this->postProcess($modifiedDescription);
    }

    /**
     * String manipulation of description to catch the easy ones
     *
     * @param string $description
     * @return string
     */
    public function preProcess(string $description): string
    {
        // replace empty h# tags. [^>]* + \1 keep the match inside one same-level
        // tag -- lazy .*? crawled the single-line html and ate half the article
        $description = preg_replace(
            "/<h(\d)[^>]*>\s*<\/h\\1>/",
            "",
            $description,
        );

        // replace h2-h5 with ==..== markup. strip <br> and collapse whitespace
        // inside the heading -- a <br> in there becomes a newline later and
        // splits the heading across lines, which mw renders as literal ='s
        foreach (
            [2 => "==", 3 => "===", 4 => "====", 5 => "====="]
            as $level => $marks
        ) {
            $description = preg_replace_callback(
                "/<h{$level}.*?>(.*?)<\/h{$level}>/s",
                function ($m) use ($marks) {
                    $inner = trim(preg_replace("/<br[^>]*>|\s+/", " ", $m[1]));
                    // <br>-only heading -> bare ==== line -> mw renders
                    // literal ='s. drop it
                    if ($inner === "") {
                        return "\n";
                    }
                    return "\n{$marks}{$inner}{$marks}\n";
                },
                $description,
            );
        }

        // unwrap spans -- old-editor font-size styling noise; leaving them in
        // ends as literal &lt;span&gt; text once a saveHTML pass escapes them
        $description = preg_replace("/<\/?span[^>]*>/i", "", $description);

        // replace the i|em with ''
        $description = preg_replace("/<\/?(?:i|em)>/", "''", $description);

        // replace the b|strong with '''
        $description = preg_replace("/<\/?(?:b|strong)>/", "'''", $description);

        // replace <br> with newline
        $description = preg_replace("/<br.*?\/?>/", "\n", $description);

        // replace hr with markup version
        $description = preg_replace("/<hr.*?>/", "-----", $description);

        return $description;
    }

    /**
     * String manipulation of description to catch the overaggressive conversions
     *
     * @param string $description
     * @return string
     */
    public function postProcess(string $description): string
    {
        // account for entities that were not saved by the utf-8 encoding
        $description = str_replace("&amp;lt;", "&lt;", $description);
        $description = str_replace("&amp;gt;", "&gt;", $description);

        // inline elements that survive to a text node get entity-escaped by the
        // final saveHTML; these are all mw-legal attr-less tags -> restore them.
        // p happens inside table cells (convertTable runs before the p pass)
        foreach (["u", "sub", "sup", "s", "strike", "del", "p"] as $tag) {
            $description = str_ireplace(
                ["&lt;{$tag}&gt;", "&lt;/{$tag}&gt;"],
                ["<{$tag}>", "</{$tag}>"],
                $description,
            );
        }

        // replace the ampersand with and for page links. target only -- doing
        // the label too turned "Good &amp; Evil" into "Good andamp;amp; Evil"
        $description = preg_replace_callback(
            "/\[\[([^\]]+)\]\]/",
            function ($matches) {
                $parts = explode("|", $matches[1], 2);
                $parts[0] = str_replace("&", "and", $parts[0]);
                return "[[" . implode("|", $parts) . "]]";
            },
            $description,
        );

        // replace the ampersand with &amp; outside of page links
        $description = str_replace("&amp;amp;", "&amp;", $description);

        $description = str_replace("\r\n", "\n", $description);
        // Pretty-printed legacy HTML leaves newlines + leading spaces inside <p>; MediaWiki
        // treats a line beginning with a space as preformatted (<pre>). Strip line-start spaces.
        $description = preg_replace('/^[ \t]+/m', "", $description);

        return $description;
    }

    /**
     * Converts <table> to MediaWiki table syntax.
     *
     * https://www.mediawiki.org/wiki/Help:Tables
     *
     * {| class="wikitable" style="margin:auto"
     * |+ Caption text
     * |-
     * ! Header text !! Header text !! Header text
     * |-
     * | Example || Example || Example
     * |-
     * | Example || Example || Example
     * |-
     * | Example || Example || Example
     * |}
     *
     * @param DOMElement  $table The table element to convert.
     * @return string The MediaWiki formatted table.
     */
    public function convertTable(DOMElement $table): string
    {
        // legacy html only marks some tables full-width; the rest render natural
        // (gb-table-natural beats the skin's forced 100% width)
        $mwTable =
            $table->getAttribute("data-max-width") === "true"
                ? "\n{| class='wikitable' style='margin:auto;width:100%;'\n"
                : "\n{| class='wikitable gb-table-natural'\n";

        $caption = $table->getElementsByTagName("caption")->item(0);
        if ($caption) {
            $mwTable .= "|+ " . trim($this->getInnerHtml($caption)) . "\n";
        }

        $processOneRow = function (DOMElement $tr): string {
            $out = "|-\n";
            $cells = [];
            $cellType = "| ";
            foreach ($tr->childNodes as $cell) {
                if (
                    $cell->nodeType === XML_ELEMENT_NODE &&
                    in_array($cell->tagName, ["th", "td"])
                ) {
                    $cellType = $cell->tagName === "th" ? "! " : "| ";
                    $cells[] = trim($this->getInnerHtml($cell));
                }
            }
            if (!empty($cells)) {
                // a heading only parses at the start of a line, so a row with
                // one gets a cell per line instead of the inline || form
                if (preg_grep('/^={2,6}[^=].*={2,6}\s*$/m', $cells)) {
                    foreach ($cells as $c) {
                        $out .= rtrim($cellType) . "\n" . $c . "\n";
                    }
                    return $out;
                }
                $separator = strpos($cells[0], "!") === 0 ? "!!" : "||";
                $out .= $cellType . implode($separator, $cells) . "\n";
            }
            return $out;
        };
        $processRows = function (DOMElement $parent) use (
            $processOneRow,
        ): string {
            $sectionContent = "";
            foreach ($parent->getElementsByTagName("tr") as $tr) {
                $sectionContent .= $processOneRow($tr);
            }
            return $sectionContent;
        };

        $thead = $table->getElementsByTagName("thead")->item(0);
        if ($thead) {
            $mwTable .= $processRows($thead);
        }

        $tbodies = $table->getElementsByTagName("tbody");
        if ($tbodies->length > 0) {
            foreach ($tbodies as $tbody) {
                $mwTable .= $processRows($tbody);
            }
        } else {
            // no tbody: emit each row ONCE. (bug was processRows($tr->parentNode)
            // per row, which re-emitted every sibling row N times)
            foreach ($table->getElementsByTagName("tr") as $tr) {
                if (
                    $tr->parentNode->nodeName !== "thead" &&
                    $tr->parentNode->nodeName !== "tfoot"
                ) {
                    $mwTable .= $processOneRow($tr);
                }
            }
        }

        $tfoot = $table->getElementsByTagName("tfoot")->item(0);
        if ($tfoot) {
            $mwTable .= $processRows($tfoot);
        }

        $mwTable .= "|}\n\n";

        return $mwTable;
    }

    /**
     * Converts <a> to MediaWiki link syntax.
     *
     * @param DOMElement  $link The <a> element to convert.
     * @return string The MediaWiki formatted link string.
     */
    public function convertLink(DOMElement $link): string
    {
        $mwLink = "";
        $contentGuid = $link->getAttribute("data-ref-id");
        $href = $link->getAttribute("href");
        if (empty($href)) {
            return $link->textContent;
        }

        $displayText = trim($this->getInnerHtml($link));
        if (preg_match('/<img src="(.+)"\/?>/', $displayText, $matches)) {
            $displayText = $matches[1];
        }

        // legacy has anchors with blank text (<a ...> </a>) that render as
        // nothing -- emitting [[x|]] makes the pipe trick print the raw title
        if ($displayText === "") {
            return "";
        }

        // check for external link
        $parts = pathinfo($href);
        $isExternalLink = (bool) preg_match("/^http/", $parts["dirname"]);

        if ($isExternalLink) {
            $parts["dirname"] = str_replace(
                "static.giantbomb.com",
                "www.giantbomb.com/a",
                $parts["dirname"],
            );
            $parts["dirname"] = str_replace(
                "giantbomb1.cbsistatic.com",
                "www.giantbomb.com/a",
                $parts["dirname"],
            );
            $href = $parts["dirname"] . "/" . $parts["basename"];
            // different format for external link
            $mwLink = "[$href $displayText]";
        } else {
            // check for a relative link using the pre-CBS GB type id
            if (empty($contentGuid)) {
                if (
                    preg_match(
                        "/\.\.\/\.\.\/(.+)\/(\d{2})\-(\d+)/",
                        $href,
                        $matches,
                    )
                ) {
                    $contentGuid =
                        $this->typeIdMap[$matches[2]] . "-" . $matches[3];
                    $href =
                        "https://www.giantbomb.com/" .
                        $matches[1] .
                        "/" .
                        $contentGuid .
                        "/"; // in case its non-wiki gb url
                }
            }

            if (!empty($contentGuid) && strpos($contentGuid, "-") !== false) {
                [$contentTypeId, $contentId] = explode("-", $contentGuid, 2);

                $contentTypeId = (int) $contentTypeId;
                $contentId = (int) $contentId;

                if ($this->wikiPageTitleResolver !== null) {
                    $resolved = ($this->wikiPageTitleResolver)(
                        $contentGuid,
                        $contentTypeId,
                        $contentId,
                        $link,
                    );
                    if ($resolved === false) {
                        // no wiki page for this guid -> plain text
                        return $displayText;
                    }
                    if (is_string($resolved) && $resolved !== "") {
                        return "[[" . $resolved . "|" . $displayText . "]]";
                    }
                }

                if (
                    isset($this->map[$contentTypeId]) &&
                    !is_null($this->map[$contentTypeId]["plural"])
                ) {
                    // instantiate the content class to retrieve the name for the page
                    if (is_null($this->map[$contentTypeId]["content"])) {
                        $resource = $this->map[$contentTypeId]["className"];
                        require_once __DIR__ .
                            "/../content/" .
                            $resource .
                            ".php";
                        $classname = ucfirst($resource);
                        $this->map[$contentTypeId]["content"] = new $classname(
                            $this->dbw,
                        );
                    }

                    $name = $this->map[$contentTypeId]["content"]->getPageName(
                        $contentId,
                    );

                    // convert the slug into the name if missing from the db
                    if ($name === false || empty($name)) {
                        // Try to extract name from display text or href
                        $name = !empty($displayText)
                            ? $displayText
                            : basename($href);
                        $name = str_replace("-", " ", $name);
                        $name = ucwords($name);
                    }

                    $mwLink = "[[$name|$displayText]]";
                } else {
                    echo $contentTypeId .
                        "-" .
                        $contentId .
                        ": 0 is external link, unmatched number is non-wiki gb url.\r\n";

                    $mwLink = "[$href $displayText]";
                }
            }
        }

        return $mwLink;
    }

    /**
     * Converts <figure> to MediaWiki image syntax using full URL.
     *
     * @param DOMElement  $figure The <figure> element to convert.
     * @return string|false The MediaWiki formatted image string with full URL.
     */
    public function convertFigure(DOMElement $figure): string|false
    {
        $img = $figure->getElementsByTagName("img")->item(0);
        if (!$img) {
            echo "WARNING: Missing img tag in figure element.\r\n";
            // skip if image is missing
            return false;
        }
        $full = $this->rewriteImageHost(
            $img->getAttribute("data-src") ?: $img->getAttribute("src"),
        );
        if ($full === "") {
            return false;
        }

        $size = strtolower($figure->getAttribute("data-size"));
        if (!isset(self::IMAGE_WIDTH_CAPS[$size])) {
            $size = "medium";
        }

        $src = $this->rewriteImageHost(
            $figure->getAttribute("data-resize-url"),
        );
        if ($src === "") {
            $src = $this->buildScaledUrl($full, $size);
        }

        $width = null;
        $nativeWidth = (int) $figure->getAttribute("data-width");
        if ($nativeWidth > 0) {
            $width = min($nativeWidth, self::IMAGE_WIDTH_CAPS[$size]);
        }

        $caption = "";
        $figcaption = $figure->getElementsByTagName("figcaption")->item(0);
        if ($figcaption) {
            $caption = trim($figcaption->textContent);
        }

        return $this->buildFigureTemplate(
            $src,
            $full,
            $figure->getAttribute("data-align"),
            $width,
            $caption,
        );
    }

    private function rewriteImageHost(string $url): string
    {
        return str_replace(
            ["static.giantbomb.com", "giantbomb1.cbsistatic.com"],
            "www.giantbomb.com/a",
            $url,
        );
    }

    // original -> sized rendition. plain size segments serve every format;
    // ignore_jpg_ only exists on urls data-resize-url carried verbatim
    private function buildScaledUrl(string $originalUrl, string $size): string
    {
        $rendition = self::IMAGE_RENDITIONS[$size];
        $scaled = preg_replace(
            "#/uploads/original/#",
            "/uploads/{$rendition}/",
            $originalUrl,
            1,
        );
        return $scaled ?? $originalUrl;
    }

    private function escapeTemplateParam(string $value): string
    {
        // keep template syntax from breaking out of the param
        return str_replace(
            ["|", "{{", "}}"],
            ["{{!}}", "&#123;&#123;", "&#125;&#125;"],
            $value,
        );
    }

    private function buildFigureTemplate(
        string $src,
        string $full,
        string $align,
        ?int $width,
        string $caption,
    ): string {
        $align = strtolower($align);
        if (!in_array($align, ["left", "right", "center"], true)) {
            $align = "none";
        }

        $tpl = "{{GBFigure|src=" . $this->escapeTemplateParam($src);
        if ($full !== "" && $full !== $src) {
            $tpl .= "|full=" . $this->escapeTemplateParam($full);
        }
        $tpl .= "|align=" . $align;
        if ($width !== null) {
            $tpl .= "|width=" . $width;
        }
        if ($caption !== "") {
            $tpl .= "|caption=" . $this->escapeTemplateParam($caption);
        }
        return "\n" . $tpl . "}}\n";
    }

    /**
     * Converts <img> to MediaWiki image syntax. External image links is just the url.
     *
     * @param DOMElement  $img The <img> element to convert.
     * @return string The MediaWiki formatted image string.
     */
    public function convertImg(DOMElement $img): string|false
    {
        $src = $img->getAttribute("src");

        if (true === preg_match("/data:image\/png;base64/", $src)) {
            return false;
        }

        $hit = $this->lookupMediaImage($src);
        if ($hit === false) {
            return false;
        }
        if (is_array($hit)) {
            return $this->buildUploadUrl(
                $this->chooseRendition($hit, "medium"),
                $hit,
            ) . " ";
        }

        return $this->rewriteImageHost($src) . " ";
    }

    /**
     * Converts <image> to MediaWiki image syntax using full URL.
     *
     * @param DOMElement  $image The <image> element to convert.
     * @return string The MediaWiki formatted image string with full URL.
     */
    public function convertImage(DOMElement $image): string|false
    {
        $full = $this->rewriteImageHost($image->getAttribute("data-img-src"));
        if ($full === "") {
            return false;
        }

        $size = strtolower($image->getAttribute("data-size"));
        if (!isset(self::IMAGE_WIDTH_CAPS[$size])) {
            $size = "medium";
        }

        $hit = $this->lookupMediaImage($full);
        if ($hit === false) {
            return false;
        }
        if (is_array($hit)) {
            // stale media-host embed: rebuild both urls from the image row
            $full = $this->buildUploadUrl("original", $hit);
            $src = $this->buildUploadUrl(
                $this->chooseRendition($hit, $size),
                $hit,
            );
        } else {
            // data-resize-url is the old site's own choice -> prefer it verbatim
            $src = $this->rewriteImageHost(
                $image->getAttribute("data-resize-url"),
            );
            if ($src === "" || strpos($src, "media.giantbomb.com") !== false) {
                // data-ref-id = 1300-<image id> -> pick the rendition key that
                // actually exists for this image when we can look it up
                $row = null;
                if (
                    $this->imageLookup !== null &&
                    preg_match(
                        '/^1300-(\d+)$/',
                        $image->getAttribute("data-ref-id"),
                        $m,
                    )
                ) {
                    $row = ($this->imageLookup)((int) $m[1]);
                }
                $src =
                    $row && empty($row["deleted"])
                        ? $this->buildUploadUrl(
                            $this->chooseRendition($row, $size),
                            $row,
                        )
                        : $this->buildScaledUrl($full, $size);
            }
        }

        $width = null;
        $nativeWidth = (int) $image->getAttribute("data-width");
        if ($nativeWidth > 0) {
            $width = min($nativeWidth, self::IMAGE_WIDTH_CAPS[$size]);
        }

        // inner text is the caption; runs before the link pass so textContent is
        // complete. bare urls in a caption would render as a second image -> strip
        $caption = trim(
            preg_replace(
                ["#https?://\S+#", "/\s+/"],
                ["", " "],
                $image->textContent,
            ),
        );

        return $this->buildFigureTemplate(
            $src,
            $full,
            $image->getAttribute("data-align"),
            $width,
            $caption,
        );
    }

    /**
     * Recursively converts <ul> or <ol> into MediaWiki list syntax.
     *
     * @param DOMElement  $listElement The <ul> or <ol> element to convert.
     * @param int         $depth The current indentation depth (starts at 1).
     * @return string The MediaWiki formatted list string.
     */
    public function convertList(DOMElement $list, int $depth = 1): string
    {
        $mwList = "";
        $listPrefix = $list->tagName === "ul" ? "*" : "#";

        foreach ($list->childNodes as $child) {
            if (
                $child->nodeType === XML_ELEMENT_NODE &&
                $child->tagName === "li"
            ) {
                $currentLinePrefix = str_repeat($listPrefix, $depth);
                $listContent = trim($this->getInnerHtml($child, ["ul", "ol"]));

                // hoist figures out of the item: their newlines split the list
                // and restart numbering. a list item is one line anyway, so
                // collapsing the remaining whitespace is safe
                if (
                    preg_match_all(
                        "/\{\{GBFigure\|[^}]*\}\}/",
                        $listContent,
                        $figs,
                    )
                ) {
                    array_push($this->hoistedFigures, ...$figs[0]);
                    $listContent = trim(
                        preg_replace(
                            ["/\{\{GBFigure\|[^}]*\}\}/", "/\s+/"],
                            ["", " "],
                            $listContent,
                        ),
                    );
                }

                // append the list item text (figure-only items have none, but
                // still fall through to the nested-list scan below)
                if ($listContent !== "") {
                    $mwList .= $currentLinePrefix . " " . $listContent . "\n";
                }

                // check for nested lists within this <li> element
                foreach ($child->childNodes as $listChild) {
                    if (
                        $listChild->nodeType === XML_ELEMENT_NODE &&
                        in_array($listChild->tagName, ["ul", "ol"])
                    ) {
                        $mwList .= $this->convertList($listChild, $depth + 1);
                    }
                }
            } elseif (
                $child->nodeType === XML_ELEMENT_NODE &&
                in_array($child->tagName, ["ul", "ol"])
            ) {
                // malformed legacy html nests lists directly inside lists
                $mwList .= $this->convertList($child, $depth);
            } elseif (
                $child->nodeType === XML_ELEMENT_NODE &&
                $child->tagName === "table"
            ) {
                // stray table inside a list -- convert it now, the list
                // replacement removes it before the table pass would run
                $mwList .= $this->convertTable($child) . "\n";
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                // stray non-li content (legacy wraps <p> in <ul>) -- dropping it ate whole blocks
                $stray = trim($this->getInnerHtml($child));
                if ($stray !== "") {
                    $mwList .= $stray . "\n";
                }
            } elseif ($child->nodeType === XML_TEXT_NODE) {
                // text nodes include inner lists already converted this pass
                $stray = trim($child->textContent);
                if ($stray !== "") {
                    $mwList .= $stray . "\n";
                }
            }
        }

        return $mwList;
    }

    /**
     * Reconstructs the html block by saving the child nodes as html
     *
     * @param DOMNode     $parentNode
     * @param array       $skipElements
     * @return string
     */
    public function getInnerHtml(
        DOMNode $parentNode,
        array $skipElements = [],
    ): string {
        $result = "";
        if ($parentNode) {
            foreach ($parentNode->childNodes as $child) {
                if (!empty($skipElements)) {
                    if (
                        $child->nodeType === XML_ELEMENT_NODE &&
                        in_array($child->tagName, $skipElements)
                    ) {
                        continue;
                    }
                }
                $result .= $this->dom->saveHTML($child);
            }
        }

        return $result;
    }

    /**
     * Converts the name to a MW friendly format
     *
     * @param string $pageName
     * @return string
     */
    public function convertName(string $pageName): string
    {
        // handle accents
        if (class_exists("Transliterator")) {
            $transliterator = Transliterator::createFromRules(
                ":: Any-Latin; :: Latin-ASCII;",
            );
            $pageName = $transliterator->transliterate($pageName);
        }

        // remove percent-encoded characters
        $pageName = preg_replace("/%[0-9a-fA-F]{2}/", "", $pageName);

        // replace '&amp;' and '&'
        $pageName = str_replace(["&amp;", "&"], " And ", $pageName);

        // remove apostrophes between letters
        $pageName = preg_replace(
            '/([a-zA-Z])[\'’]([a-zA-Z])/u',
            '$1$2',
            $pageName,
        );

        // replace all non-alphanumeric characters with a space
        $pageName = preg_replace("/[^a-zA-Z0-9]/", " ", $pageName);

        // reduce consecutive spaces
        $pageName = preg_replace("/\s+/", " ", $pageName);

        // trim and replace spaces with underscores
        $pageName = str_replace(" ", "_", trim($pageName));

        return $pageName;
    }
}
