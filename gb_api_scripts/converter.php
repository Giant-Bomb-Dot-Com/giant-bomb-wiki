<?php

require_once(__DIR__.'/common.php');
require_once(__DIR__.'/resource.php');

use Wikimedia\Rdbms\IDatabase;

class HtmlToMediaWikiConverter
{
    use CommonVariablesAndMethods;

    private DOMDocument $dom;
    private Resource $content;
    private int $typeId;
    private int $id;

    public function __construct(Resource $content) 
    {
        $this->content = $content;
        $this->dom = new DOMDocument();
        libxml_use_internal_errors(true);
    }

    /**
     * Downloads an image file to a local folder
     * 
     * @param string $imageUrl
     * @param string $storagePath Path to storage
     */
    public function downloadImage(string $imageUrl, string $storage)
    {
        $ch = curl_init($imageUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

        $image = curl_exec($ch);
        curl_close($ch);

        if ($image !== false) {
            file_put_contents($storage, $image);
            echo "Image downloaded successfully to: " . $storage . "\r\n";
        } else {
            echo "Error downloading image.\r\n";
        }
    }

    /**
     * Converts the description to a MW friendly format
     * 
     * @param string $description
     * @param int    $typeId
     * @param int    $id
     * @return string
     */
    public function convert(string $description, int $typeId, int $id): string
    {
        $this->typeId = $typeId;
        $this->id = $id;

        // replace the h2s with ==
        $description = preg_replace('/<h2>(.*?)<\/h2>/', "\n==$1==\n", $description);

        // replace the h3s with ===
        $description = preg_replace('/<h3>(.*?)<\/h3>/', "\n===$1===\n", $description);

        // replace the h4s with ====
        $description = preg_replace('/<h4>(.*?)<\/h4>/', "\n====$1====\n", $description);

        // replace the h5s with =====
        $description = preg_replace('/<h5>(.*?)<\/h5>/', "\n=====$1=====\n", $description);

        // replace the i|em with ''
        $description = preg_replace('/<\/?(?:i|em)>/', "''", $description);

        // replace the b|strong with '''
        $description = preg_replace('/<\/?(?:b|strong)>/', "'''", $description);

        // replace the empty <p> tags
        $description = preg_replace('/<p>\s?<\/p>/', "", $description);

        // replace <p> with \n
        $description = preg_replace('/<p>(.*?)<\/p>/', "$1\n", $description);

        libxml_use_internal_errors(true);
        $this->dom->loadHTML('<div>'.$description.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $block = $this->dom->getElementsByTagName('div')->item(0);

        // figures has an embedded link to the image so we process this first
        $figuresToProcess = [];
        $figures = $block->getElementsByTagName('figure');
        foreach ($figures as $figureNode) {
            $figuresToProcess[] = $figureNode;
        }
        foreach (array_reverse($figuresToProcess) as $figure) {
            $mwFigure = $this->convertFigure($figure);
            if ($mwFigure === false) {
                continue;
            }
            $textNode = $this->dom->createTextNode($mwFigure);
            if ($figure->parentNode) {
                $figure->parentNode->replaceChild($textNode, $figure);
            }
        }

        // followed by all the links
        $linksToProcess = [];
        $links = $block->getElementsByTagName('a');
        foreach ($links as $linkNode) {
            $linksToProcess[] = $linkNode;
        }
        foreach (array_reverse($linksToProcess) as $link) {
            $mwLink = $this->convertLink($link);
            $textNode = $this->dom->createTextNode($mwLink);
            if ($link->parentNode) {
                $link->parentNode->replaceChild($textNode, $link);
            }
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

        $tablesToProcess = [];
        $tables = $block->getElementsByTagName('table');
        foreach ($tables as $tableNode) {
            $tablesToProcess[] = $tableNode;
        }
        foreach (array_reverse($tablesToProcess) as $table) {
            $mwTable = $this->convertTable($table);
            if ($mwTable === false) {
                continue;
            }
            $textNode = $this->dom->createTextNode($mwTable);
            if ($table->parentNode) {
                $table->parentNode->replaceChild($textNode, $table);
            }
        }

        // return the modified description
        return $this->getInnerHtml($block);
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
    function convertTable(DOMElement $table): string
    {
        $mwTable = "\n{| class='wikitable' style='margin:auto'\n";

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
    function convertLink(DOMElement $link): string
    {
        $mwLink = '';
        $contentGuid = $link->getAttribute('data-ref-id');
        $href = $link->getAttribute('href');
        $displayText = $this->getInnerHtml($link);

        // check for external link
        $parts = pathinfo($href);
        $isExternalLink = (bool)preg_match('/^http/', $parts['dirname']);

        if ($isExternalLink) {
            // different format for external link
            $mwLink = "[$href $displayText]";
        }
        else {
            if (!empty($contentGuid) && strpos($contentGuid, '-') !== false) {

                list($contentTypeId, $contentId) = explode('-', $contentGuid);

                $contentTypeId = (int)$contentTypeId;
                $contentId = (int)$contentId;

                // instantiate the content class to retrieve the name for the page
                if (is_null($this->map[$contentTypeId]['content'])) {
                    $resource = $this->map[$contentTypeId]['className'];
                    require_once(__DIR__.'/'.$resource.'.php');
                    $classname = ucfirst($resource);
                    $this->map[$contentTypeId]['content'] = new $classname($this->content->getDb());
                }

                $name = $this->map[$contentTypeId]['content']->getName($contentId);
                // convert the slug into the name if missing from the db
                if ($name === false) {
                    $name = str_replace('-',' ',$name);
                    $name = ucwords($name);
                }

                $mwLink = "[[$name|$displayText]]";
            }
        }

        // replace the link with the mw version
        $textNode = $this->dom->createTextNode($mwLink);
        $link->parentNode->replaceChild($textNode, $link);
        
        return $mwLink;
    }

    /**
     * Converts <figure> to MediaWiki image syntax.
     *
     * @param DOMElement  $figure The <figure> element to convert.
     * @return string|false The MediaWiki formatted image string.
     */
    function convertFigure(DOMElement $figure): string|false
    {
        $mwFigure = '';
        $caption = '';
        $align = $figure->getAttribute('data-align');
        $width = $figure->getAttribute('data-width');
        $imageGuid = $figure->getAttribute('data-ref-id');

        $img = $figure->getElementsByTagName('img')->item(0);
        if ($img) {
            $src = $img->getAttribute('data-src') ?: $img->getAttribute('src');
            $caption = $img->getAttribute('alt');
        }
        else {
            echo "WARNING: Missing img tag in figure element.\r\n";
            // skip if image is missing
            return false;
        }

        $parts = pathinfo($src);
        $filename = $parts['basename'];


        $layout = (empty($caption) || $caption == "No Caption Provided") ? "thumb" : "frame|$caption";

        $mwFigure = "[[File:$filename|$align|{$width}px|$layout]]";

        // create a db entry for the image found in the description
        list($imageTypeId, $imageId) = explode('-', $imageGuid);
        $this->content->insertOrUpdate("image", [
            'id' => $imageId,
            'assoc_type_id' => $this->typeId,
            'assoc_id' => $this->id,
            'image' => $src,
            'caption' => $caption,
        ], ['assoc_type_id', 'assoc_id', 'image']);

        // create directory for image with the pattern /images/entity-guid/image-id/
        $entityPath = sprintf(__DIR__.'/images/%s-%s/', $this->typeId, $this->id);
        $storagePath = sprintf('%s/%s/', $entityPath, $imageId);
        if (!file_exists($storagePath.$filename)) {
            if (!is_dir($entityPath)) {
                mkdir($entityPath);
            }
            if (!is_dir($storagePath)) {
                mkdir($storagePath);
            }

            // download the image to store in the local file system
            $this->downloadImage($src, $storagePath.$filename);
        }
        else {
            echo sprintf("Image already exists in the filesystem at %s%s \r\n", $storagePath, $filename);
        }

        return $mwFigure;
    }

    /**
     * Recursively converts <ul> or <ol> into MediaWiki list syntax.
     *
     * @param DOMElement  $listElement The <ul> or <ol> element to convert.
     * @param int         $depth The current indentation depth (starts at 1).
     * @return string The MediaWiki formatted list string.
     */
    function convertList(DOMElement $list, int $depth = 1): string
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
                        $mwList .= convertList($listChild, $depth + 1);
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
}