<?php

trait CommonVariablesAndMethods 
{
    /**
     * Maps wiki type ids to their info
     */
	protected $map = [
        3000 => ['className' => 'accessory', 'plural' => 'accessories', 'content' => null, 'count' => 0],
        3005 => ['className' => 'character', 'plural' => 'characters', 'content' => null, 'count' => 0],
        3010 => ['className' => 'company', 'plural' => 'companies', 'content' => null, 'count' => 0],
        3015 => ['className' => 'concept', 'plural' => 'concepts', 'content' => null, 'count' => 0],
        3020 => ['className' => 'dlc', 'plural' => 'dlc', 'content' => null, 'count' => 0],
        3025 => ['className' => 'franchise', 'plural' => 'franchises', 'content' => null, 'count' => 0],
        3030 => ['className' => 'game', 'plural' => 'games', 'content' => null, 'count' => 0],
        3065 => ['className' => 'game_rating', 'content' => null, 'count' => 0],
        3060 => ['className' => 'genre', 'plural' => 'genres', 'content' => null, 'count' => 0],
        3035 => ['className' => 'location', 'plural' => 'locations', 'content' => null, 'count' => 0],
        3040 => ['className' => 'person', 'plural' => 'people', 'content' => null, 'count' => 0],
        3045 => ['className' => 'platform', 'plural' => 'platforms', 'content' => null, 'count' => 0],
        3070 => ['className' => 'rating_board', 'plural' => null, 'content' => null, 'count' => 0],
        3075 => ['className' => 'region', 'plural' => null, 'content' => null, 'count' => 0],
        3050 => ['className' => 'release', 'plural' => null, 'content' => null, 'count' => 0],
        3032 => ['className' => 'theme', 'plural' => 'themes', 'content' => null, 'count' => 0],
        3055 => ['className' => 'thing', 'plural' => 'objects', 'content' => null, 'count' => 0],
    ];

    /**
     * Mediawiki namespaces
     */
    protected $namespaces = [
        'page' => 0,
        'core' => 8,
        'template' => 10,
        'category' => 14,
        'property' => 102,
        'form' => 106
    ];

    /**
     * Creates and saves xml file to be imported into mediawiki through importDump
     * 
     * @param string $filename
     * @param array  $data
     * @return bool
     */
    protected function createXML(string $filename, array $data): bool
    {
        $path = '/var/www/html/maintenance/gb_api_scripts/import_xml/';

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        // Define namespaces
        $mwNamespace = 'http://www.mediawiki.org/xml/export-0.11/';
        $xsiNamespace = 'http://www.w3.org/2001/XMLSchema-instance';
        $dom->createElementNS($mwNamespace, 'mediawiki');

        // Create the root element with namespaces and attributes
        $mediawiki = $dom->createElementNS($mwNamespace, 'mediawiki');
        $mediawiki->setAttribute('xmlns:xsi', $xsiNamespace);
        $mediawiki->setAttribute('xsi:schemaLocation', 'http://www.mediawiki.org/xml/export-0.11/ http://www.mediawiki.org/xml/export-0.11.xsd');
        $mediawiki->setAttribute('version', '0.11');
        $mediawiki->setAttribute('xml:lang', 'en');
        $dom->appendChild($mediawiki);

        echo "Generating xml...\n";
        $count = 0;
        foreach ($data as $set) {
            // Create the <page> element
            $page = $dom->createElementNS($mwNamespace, 'page');
            $mediawiki->appendChild($page);

            // Create and append child elements to <page>
            $title = $dom->createElementNS($mwNamespace, 'title', htmlspecialchars($set['title']));
            $page->appendChild($title);

            $ns = $dom->createElementNS($mwNamespace, 'ns', $set['namespace']);
            $page->appendChild($ns);

            // Create the <revision> element
            $revision = $dom->createElementNS($mwNamespace, 'revision');
            $page->appendChild($revision);

            // Create the contributor element
            $contributor = $dom->createElementNS($mwNamespace, 'contributor');
            $revision->appendChild($contributor);

            // Add Giantbomb user as contributor
            $giantbomb = $dom->createElementNS($mwNamespace, 'username', 'Giantbomb');
            $contributor->appendChild($giantbomb);
            $userId = $dom->createElementNS($mwNamespace, 'id', 1);
            $contributor->appendChild($userId);

            // Create and append child elements to <revision>
            $model = $dom->createElementNS($mwNamespace, 'model', (array_key_exists('model', $set)) ? $set['model'] : 'wikitext');
            $revision->appendChild($model);

            $format = $dom->createElementNS($mwNamespace, 'format', (array_key_exists('format', $set)) ? $set['format'] : 'text/x-wiki');
            $revision->appendChild($format);

            // Create the <text> element with the generated content
            $text = $dom->createElementNS($mwNamespace, 'text', $set['description']);
            $text->setAttribute('xml:space', 'preserve');
            $revision->appendChild($text);

            if ($count % 1000 == 0) {
                echo "$count pages blocks created...\n";
            }
            $count++;
        }

        try {
            // Save the XML to the specified file
            $result = $dom->save($path.$filename);
            if ($result !== false) {
                echo "MediaWiki XML file '{$filename}' created successfully.\n";
                return true;
            } else {
                echo "Error: Could not save the XML file.\n";
                return false;
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Creates pages blocks and streams direct to file. Faster than using domdocument.
     * 
     * @param string $filename
     * @param array  $data
     * @return bool
     */
    protected function streamXML(string $filename, array $data)
    {
        $path = '/var/www/html/maintenance/gb_api_scripts/import_xml/';
        $file = $path.$filename;

        $xml = new XMLWriter();
        $xml->openURI($file);
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $mediaWikiNS = 'http://www.mediawiki.org/xml/export-0.11/';
        $xsiNS = 'http://www.w3.org/2001/XMLSchema-instance';

        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElementNS(null, 'mediawiki', $mediaWikiNS);
            $xml->writeAttribute('xmlns:xsi', $xsiNS);
            $xml->writeAttributeNS('xsi', 'schemaLocation', null, "http://www.mediawiki.org/xml/export-0.11/ http://www.mediawiki.org/xml/export-0.11.xsd");
            $xml->writeAttribute('version', '0.11');
            $xml->writeAttribute('xml:lang', 'en');

            $count = 0;
            foreach ($data as $page) {
                $xml->startElement('page');
                    $xml->writeElement('title', $page['title']);
                    $xml->writeElement('ns', $page['namespace']);
                    $xml->startElement('revision');
                        $xml->startElement('contributor');
                            $xml->writeElement('username', 'Giantbomb');
                            $xml->writeElement('id', 1);
                        $xml->endElement(); 
                        $xml->writeElement('model', (array_key_exists('model', $page)) ? $page['model'] : 'wikitext');
                        $xml->writeElement('format', (array_key_exists('format', $page)) ? $page['format'] : 'text/x-wiki');
                        $xml->startElement('text');
                            $xml->writeAttribute('xml:space', 'preserve');
                            $xml->writeRaw($page['description']);
                        $xml->endElement();
                    $xml->endElement();
                $xml->endElement();

                if ($count % 1000 == 0) {
                    echo "$count pages blocks created...\n";
                }
                $count++;
            }

        $xml->endElement();
        $xml->endDocument();

        $xml->flush();

        echo "Generated $file!\n";
        echo "Total pages generated: $count\n";
    }
}