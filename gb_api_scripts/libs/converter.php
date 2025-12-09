<?php

require_once(__DIR__.'/../libs/common.php');

use Wikimedia\Rdbms\IDatabase;

class HtmlToMediaWikiConverter
{
    use CommonVariablesAndMethods;

    private DOMDocument $dom;
    private int $typeId;
    private int $id;

    public function __construct(DbInterface $dbw) 
    {
        $this->dbw = $dbw;
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
    }

    /**
     * Converts the description to a MW friendly format
     * 
     * @param string $description
     * @param int    $typeId
     * @param int    $id
     * @return string|false
     */
    public function convert(string $description, int $typeId, int $id): string|false
    {
        if (empty($description)) {
            return '';
        }

        $this->typeId = $typeId;
        $this->id = $id;

        $description = $this->preProcess($description);

        libxml_use_internal_errors(true);
        $wrappedDescription = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' . 
                              mb_convert_encoding($description, 'HTML-ENTITIES', 'UTF-8') . 
                              '</body></html>';

        $success = $this->dom->loadHTML($wrappedDescription, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        if (!$success) {
            echo sprintf("WARNING: failed to load html for %s-%s.\n", $typeId, $id);
            return false;
        }

        $block = $this->dom->getElementsByTagName('body')->item(0);

        // figures has an embedded link to the image so we process this first
        $figureTags = $block->getElementsByTagName('figure');
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

        // followed by non-figure img tags
        $imagesToProcess = [];
        $images = $block->getElementsByTagName('img');
        foreach ($images as $imageNode) {
            $imagesToProcess[] = $imageNode;
        }
        foreach (array_reverse($imagesToProcess) as $image) {
            if ($image->hasAttribute('style') && preg_match('/display/', $image->getAttribute('style')) === false) {
                continue;
            }
            $mwImage = $this->convertImg($image);
            if ($mwImage === false) {
                if ($image->parentNode) {
                    $image->parentNode->removeChild($image);
                }
            }
            else {
                $textNode = $this->dom->createTextNode($mwImage);
                if ($image->parentNode) {
                    $image->parentNode->replaceChild($textNode, $image);
                }
            }
        }

        // followed by non-figure image tags
        $imagesToProcess = [];
        $images = $block->getElementsByTagName('image');
        foreach ($images as $imageNode) {
            $imagesToProcess[] = $imageNode;
        }
        foreach (array_reverse($imagesToProcess) as $image) {
            $mwImage = $this->convertImage($image);
            if ($mwImage === false) {
                if ($image->parentNode) {
                    $image->parentNode->removeChild($image);
                }
            }
            else {
                $textNode = $this->dom->createTextNode($mwImage);
                if ($image->parentNode) {
                    $image->parentNode->replaceChild($textNode, $image);
                }
            }
        }

        // followed by all the links
        $aTags = $block->getElementsByTagName('a');
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
        $orderedLists = $block->getElementsByTagName('ol');
        foreach ($orderedLists as $listNode) {
            $listsToProcess[] = $listNode;
        }
        $unorderedLists = $block->getElementsByTagName('ul');
        foreach ($unorderedLists as $listNode) {
            $listsToProcess[] = $listNode;
        }
        foreach (array_reverse($listsToProcess) as $list) {
            $mwList = $this->convertList($list);
            $textNode = $this->dom->createTextNode($mwList);
            if ($list->parentNode) {
                $list->parentNode->replaceChild($textNode, $list);
            }
        }

        $tableTags = $block->getElementsByTagName('table');
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

        $divTags = $block->getElementsByTagName('div');
        for ($i = $divTags->length - 1; $i >= 0; $i--) {
            $divTag = $divTags->item($i);
            $parent = $divTag->parentNode;

            if ($divTag->hasAttribute('data-embed-type')) {
                if (in_array($divTag->getAttribute('data-embed-type'), ['tweet', 'video'])) {
                    $src = $divTag->getAttribute('data-src');
                    $newTextNode = $this->dom->createTextNode($src);
                    
                    $parent->insertBefore($newTextNode, $divTag);
                    $parent->removeChild($divTag);
                }
            }
        }

        $scriptTags = $block->getElementsByTagName('script');
        for ($i = $scriptTags->length - 1; $i >= 0; $i--) {
            $scriptTag = $scriptTags->item($i);
            $parent = $scriptTag->parentNode;
            $parent->removeChild($scriptTag);
        }

        $pTags = $block->getElementsByTagName('p');
        for ($i = $pTags->length - 1; $i >= 0; $i--) {
            $pTag = $pTags->item($i);
            $parent = $pTag->parentNode;

            $pContent = '';
            foreach ($pTag->childNodes as $child) {
                $pContent .= $this->dom->saveHTML($child);
            }
            
            $newTextNode = $this->dom->createTextNode($pContent . "\n");

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
        // replace empty h# tags
        $description = preg_replace('/<h\d{1}.*?>\s*<\/h\d{1}>/', "", $description);

        // replace the h2s with ==
        $description = preg_replace('/<h2.*?>(.*?)<\/h2>/', "\n==$1==\n", $description);

        // replace the h3s with ===
        $description = preg_replace('/<h3.*?>(.*?)<\/h3>/', "\n===$1===\n", $description);

        // replace the h4s with ====
        $description = preg_replace('/<h4.*?>(.*?)<\/h4>/', "\n====$1====\n", $description);

        // replace the h5s with =====
        $description = preg_replace('/<h5.*?>(.*?)<\/h5>/', "\n=====$1=====\n", $description);

        // replace the i|em with ''
        $description = preg_replace('/<\/?(?:i|em)>/', "''", $description);

        // replace the b|strong with '''
        $description = preg_replace('/<\/?(?:b|strong)>/', "'''", $description);

        // replace <br> with newline
        $description = preg_replace('/<br.*?\/?>/', "\n", $description);

        // replace hr with markup version
        $description = preg_replace('/<hr.*?>/', "-----", $description);

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
        $description = str_replace('&amp;lt;', '&lt;', $description);
        $description = str_replace('&amp;gt;', '&gt;', $description);

        // replace the ampersand with and for page links
        $description = preg_replace_callback('/\[\[([^\]]+)\]\]/', function($matches) {
            return '[[' . str_replace('&', 'and', $matches[1]) . ']]';
        }, $description);

        // replace the ampersand with &amp; outside of page links
        $description = str_replace('&amp;amp;', '&amp;', $description);

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
        $mwTable = "\n{| class='wikitable' style='margin:auto;width:100%;'\n";

        $caption = $table->getElementsByTagName('caption')->item(0);
        if ($caption) {
            $mwTable .= "|+ " . trim($this->getInnerHtml($caption)) . "\n";
        }

        $processRows = function(DOMElement $parent): string {
            $sectionContent = '';
            foreach ($parent->getElementsByTagName('tr') as $tr) {
                $sectionContent .= "|-\n";
                $cells = [];
                $cellType = '| ';

                foreach ($tr->childNodes as $cell) {
                    if ($cell->nodeType === XML_ELEMENT_NODE && in_array($cell->tagName, ['th','td'])) {
                        $cellType = ($cell->tagName === 'th') ? '! ' : '| ';
                        $cellInnerHtml = trim($this->getInnerHtml($cell));
                        $cells[] = $cellInnerHtml;
                    }
                }

                if (!empty($cells)) {
                    $separator = (strpos($cells[0], '!') === 0) ? '!!' : '||';
                    $sectionContent .= $cellType . implode($separator, $cells) . "\n";
                }
            }
            return $sectionContent;
        };

        $thead = $table->getElementsByTagName('thead')->item(0);
        if ($thead) {
            $mwTable .= $processRows($thead);
        }

        $tbodies = $table->getElementsByTagName('tbody');
        if ($tbodies->length > 0) {
            foreach ($tbodies as $tbody) {
                $mwTable .= $processRows($tbody);
            }
        } else {
            foreach ($table->getElementsByTagName('tr') as $tr) {
                if ($tr->parentNode->nodeName !== 'thead' && $tr->parentNode->nodeName !== 'tfoot') {
                    $mwTable .= "|-\n";
                    $mwTable .= $processRows($tr->parentNode);
                }
            }
        }

        $tfoot = $table->getElementsByTagName('tfoot')->item(0);
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
        $mwLink = '';
        $contentGuid = $link->getAttribute('data-ref-id');
        $href = $link->getAttribute('href');
        if (empty($href)) {
            return $link->textContent;
        }

        $displayText = trim($this->getInnerHtml($link));
        if (preg_match('/<img src="(.+)"\/?>/', $displayText, $matches)) {
            $displayText = $matches[1];
        }

        // check for external link
        $parts = pathinfo($href);
        $isExternalLink = (bool)preg_match('/^http/', $parts['dirname']);

        if ($isExternalLink) {
            $parts['dirname'] = str_replace('static.giantbomb.com', 'www.giantbomb.com/a', $parts['dirname']);
            $parts['dirname'] = str_replace('giantbomb1.cbsistatic.com', 'www.giantbomb.com/a', $parts['dirname']);
            $href = $parts['dirname'] . '/' . $parts['basename'];
            // different format for external link
            $mwLink = "[$href $displayText]";
        }
        else {
            // check for a relative link using the pre-CBS GB type id
            if (empty($contentGuid)) {
                if (preg_match('/\.\.\/\.\.\/(.+)\/(\d{2})\-(\d+)/', $href, $matches)) {
                    $contentGuid = $this->typeIdMap[$matches[2]] . '-' . $matches[3];
                    $href = 'https://www.giantbomb.com/'.$matches[1].'/'.$contentGuid.'/'; // in case its non-wiki gb url
                }
            }

            if (!empty($contentGuid) && strpos($contentGuid, '-') !== false) {

                list($contentTypeId, $contentId) = explode('-', $contentGuid);

                $contentTypeId = (int)$contentTypeId;
                $contentId = (int)$contentId;

                if (isset($this->map[$contentTypeId]) && !is_null($this->map[$contentTypeId]['plural'])) {

                    // instantiate the content class to retrieve the name for the page
                    if (is_null($this->map[$contentTypeId]['content'])) {
                        $resource = $this->map[$contentTypeId]['className'];
                        require_once('/var/www/html/maintenance/gb_api_scripts/content/'.$resource.'.php');
                        $classname = ucfirst($resource);
                        $this->map[$contentTypeId]['content'] = new $classname($this->dbw);
                    }

                    $name = $this->map[$contentTypeId]['content']->getPageName($contentId);

                    // convert the slug into the name if missing from the db
                    if ($name === false || empty($name)) {
                        // Try to extract name from display text or href
                        $name = !empty($displayText) ? $displayText : basename($href);
                        $name = str_replace('-',' ',$name);
                        $name = ucwords($name);
                    }

                    $mwLink = "[[$name|$displayText]]";
                }
                else {
                    echo $contentTypeId."-".$contentId.": 0 is external link, unmatched number is non-wiki gb url.\r\n";

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
        $align = $figure->getAttribute('data-align');

        $src = '';
        $img = $figure->getElementsByTagName('img')->item(0);

        if ($img) {
            $src = $img->getAttribute('data-src') ?: $img->getAttribute('src');
            $src = str_replace('static.giantbomb.com', 'www.giantbomb.com/a', $src);
            $src = str_replace('giantbomb1.cbsistatic.com', 'www.giantbomb.com/a', $src);
        }
        else {
            echo "WARNING: Missing img tag in figure element.\r\n";
            // skip if image is missing
            return false;
        }

        return $src.' ';
    }

    /**
     * Converts <img> to MediaWiki image syntax. External image links is just the url.
     *
     * @param DOMElement  $img The <img> element to convert.
     * @return string The MediaWiki formatted image string.
     */
    public function convertImg(DOMElement $img): string|false
    {
        $src = $img->getAttribute('src');

        if (true === preg_match('/data:image\/png;base64/', $src)) {
            return false;
        }

        $src = str_replace('static.giantbomb.com', 'www.giantbomb.com/a', $src);
        $src = str_replace('giantbomb1.cbsistatic.com', 'www.giantbomb.com/a', $src);

        return $src.' ';
    }

    /**
     * Converts <image> to MediaWiki image syntax using full URL.
     *
     * @param DOMElement  $image The <image> element to convert.
     * @return string The MediaWiki formatted image string with full URL.
     */
    public function convertImage(DOMElement $image): string|false
    {
        $src = $image->getAttribute('data-img-src');
        
        $src = str_replace('static.giantbomb.com', 'www.giantbomb.com/a', $src);
        $src = str_replace('giantbomb1.cbsistatic.com', 'www.giantbomb.com/a', $src);

        return $src.' ';
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
        $mwList = '';
        $listPrefix = ($list->tagName === 'ul') ? '*' : '#';

        foreach ($list->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->tagName === 'li') {
                $currentLinePrefix = str_repeat($listPrefix, $depth);
                $listContent = trim($this->getInnerHtml($child, ['ul','ol']));

                // append the list item text
                $mwList .= $currentLinePrefix . ' ' . $listContent . "\n";

                // check for nested lists within this <li> element
                foreach ($child->childNodes as $listChild) {
                    if ($listChild->nodeType === XML_ELEMENT_NODE && in_array($listChild->tagName, ['ul','ol'])) {
                        $mwList .= $this->convertList($listChild, $depth + 1);
                    }
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
    public function getInnerHtml(DOMNode $parentNode, array $skipElements = []): string
    {
        $result = '';
        if ($parentNode) {
            foreach ($parentNode->childNodes as $child) {
                if (!empty($skipElements)) {
                    if ($child->nodeType === XML_ELEMENT_NODE && in_array($child->tagName, $skipElements)) {
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
        if (class_exists('Transliterator')) {
            $transliterator = Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII;');
            $pageName = $transliterator->transliterate($pageName);
        }

        // remove percent-encoded characters
        $pageName = preg_replace('/%[0-9a-fA-F]{2}/', '', $pageName);

        // replace '&amp;' and '&'
        $pageName = str_replace(['&amp;', '&'], ' And ', $pageName);

        // remove apostrophes between letters
        $pageName = preg_replace('/([a-zA-Z])[\'’]([a-zA-Z])/u', '$1$2', $pageName);

        // replace all non-alphanumeric characters with a space
        $pageName = preg_replace('/[^a-zA-Z0-9]/', ' ', $pageName);

        // reduce consecutive spaces
        $pageName = preg_replace('/\s+/', ' ', $pageName);

        // trim and replace spaces with underscores
        $pageName = str_replace(' ', '_', trim($pageName));

        return $pageName;
    }
}