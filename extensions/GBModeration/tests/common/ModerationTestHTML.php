<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2015-2025 Edward Chernenko.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

/**
 * @file
 * Implements HTML parsing methods for the automated testsuite.
 */

namespace MediaWiki\Moderation\Tests;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DomXpath;

class ModerationTestHTML extends DOMDocument
{
    /**
     * @const Libxml error code for "unknown tag"
     * @see http://www.xmlsoft.org/html/libxml-xmlerror.html
     */
    private const XML_HTML_UNKNOWN_TAG = 801;

    /**
     * Utility function to warn about Libxml errors.
     * Ignores the fact that some HTML5 tags are unknown to libxml2.
     *
     * @return bool True if there were errors, false otherwise.
     */
    public static function checkLibxmlErrors()
    {
        $errorCount = 0;
        $errors = libxml_get_errors();
        foreach ($errors as $error) {
            if ($error->code == self::XML_HTML_UNKNOWN_TAG) {
                /* Ignore: libxml considers modern tags like <bdi> to be errors */
                continue;
            }

            $message = trim($error->message);

            // Workaround for MediaWiki sometimes producing incorrect HTML (unrelated to Moderation).
            if ($message == "Unexpected end tag : ul") {
                // Unneeded "</ul>" tag on Special:Log.
                continue;
            }
            if ($message == "Unexpected end tag : p") {
                // Unneeded "</p>" tag on some pages.
                continue;
            }
            if (preg_match('/^ID .* already defined$/', $message)) {
                // Duplicate IDs of HTML elements on some pages.
                continue;
            }

            print "LibXML error: $message\n";
            $errorCount++;
        }

        libxml_clear_errors();
        return $errorCount > 0;
    }

    /**
     * Load the HTML document from string that contains the HTML.
     * @param string $string
     * @return static
     */
    public function loadString($string)
    {
        $parts = explode("<!DOCTYPE html>", $string);
        if (count($parts) > 1) {
            [$phpWarnings, $string] = $parts;
            $phpWarnings = trim($phpWarnings);

            if ($phpWarnings !== "") {
                // Any text found before the doctype is likely PHP Deprecation warnings, etc.
                // It should result in test failure, so we are printing it here,
                // but it shouldn't prevent HTML of this document from being parsed.
                print "Found PHP warnings before the HTML document:\n" .
                    trim($phpWarnings) .
                    "\n";
            }
        }

        // Forget any unhandled errors from previous LibXML parse attempts.
        libxml_clear_errors();

        // Ignore "unknown tag" error, see checkLibxmlErrors() for details.
        libxml_use_internal_errors(true);

        $this->loadHTML($string);
        if (self::checkLibxmlErrors()) {
            print "LibXML failed to parse the following document: <<<\n" .
                trim($string) .
                "\n>>>\n";
        }

        return $this;
    }

    /**
     * Returns the text of the <title> tag.
     * @return string|null
     */
    public function getTitle()
    {
        $titleTag = $this->getElementsByTagName("title")->item(0);
        return $titleTag ? $titleTag->textContent : null;
    }

    /**
     * Returns HTML element of the error message shown by Moderation.
     * @return string|null
     */
    public function getModerationError()
    {
        $elem = $this->getElementById("mw-mod-error");
        return $elem ? $elem->textContent : null;
    }

    /**
     * Returns HTML element of the main text of the page.
     * @return DOMElement|null
     */
    public function getMainContent()
    {
        return $this->getElementById("mw-content-text");
    }

    /**
     * Returns main text of the page (without navigation, etc.).
     * @return string|null
     */
    public function getMainText()
    {
        $elem = $this->getMainContent();
        return $elem ? trim($elem->textContent) : null;
    }

    /**
     * Returns the text of the notice "You have new messages".
     * @return string|null
     */
    public function getNewMessagesNotice()
    {
        $elem = $this->getElementByXPath('//*[@class="usermessage"]');
        return $elem ? $elem->textContent : null;
    }

    /**
     * Returns the Submit button element.
     * @return DOMElement
     */
    public function getSubmitButton()
    {
        return $this->getElementByXPath(
            '//*[@id="mw-content-text"]//*[@type="submit"]',
        );
    }

    /**
     * Find the DOM element by XPath selector.
     * E.g. $t->html->getElementsByXPath( '//row[@name="wpSummary"]' )
     * @param string $selector
     * @param ?DOMNode $contextNode
     * @return DOMElement|null
     */
    public function getElementByXPath($selector, ?DOMNode $contextNode = null)
    {
        $elements = $this->getElementsByXPath($selector, $contextNode);
        if (!$elements) {
            return null;
        }

        $elem = $elements->item(0);

        '@phan-var DOMElement|null $elem';
        return $elem;
    }

    /**
     * Find all DOM elements matching the XPath selector.
     * E.g. $t->html->getElementsByXPath( '//a[@class="new"]' )
     * @param string $selector
     * @param ?DOMNode $contextNode
     * @return DOMNodeList
     */
    public function getElementsByXPath($selector, ?DOMNode $contextNode = null)
    {
        $xpath = new DomXpath($this);
        return $xpath->query($selector, $contextNode);
    }

    /**
     * Return the list of ResourceLoader modules which are used in the last fetched HTML.
     * @return string[]
     */
    public function getLoaderModulesList()
    {
        $list = [];
        foreach ($this->getElementsByTagName("script") as $script) {
            $matches = null;
            if (
                preg_match(
                    "/(?:mw\.loader\.load\(|RLPAGEMODULES=)\[([^]]+)\]/",
                    $script->textContent,
                    $matches,
                )
            ) {
                $items = explode(",", $matches[1]);

                foreach ($items as $item) {
                    $list[] = preg_replace('/^"(.*)"$/', '$1', $item);
                }
            }
        }
        return array_unique($list);
    }

    /**
     * Return the array of <input> elements in the form (name => value).
     * @param DOMElement|null $formElement
     * @return DOMElement[]
     */
    public function getFormElements(DOMElement $formElement = null)
    {
        if (!$formElement) {
            $formElement = $this;
        }

        $result = [];
        foreach ($formElement->getElementsByTagName("input") as $input) {
            $name = $input->getAttribute("name");
            $value = $input->getAttribute("value");

            $result[$name] = $value;
        }
        return $result;
    }
}
