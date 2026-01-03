<?php

namespace GiantBombApi\Datastore;

use Exception;
use GiantBombApi\Helpers\PageHelper;
use SMW;
use SMWQueryProcessor;

/**
 * Datastore connection for retrieving pages from SMW and massaging the data to
 * be API-friendly.
 */
class BaseDatastore {

    /**
     * Get a list of resources based off a category of pages and their properties.
     * @param string $category Title of the category to fetch pages for.
     * @param array $properties List of properties to extract from the pages.
     * @param SortOrder $sortOrder Order of the pages.
     * @param int $limit Amount of pages to fetch.
     * @param int $offset Page count offset.
     * @return array Array containing the list of results and the total number of pages.
     */
    protected static function getResources( string $category, array $properties, SortOrder $sortOrder, int $limit, int $offset ): array {
        $store = SMW\StoreFactory::getStore(); // TODO: is this a MW service that can be dependency injected?

        $queryConditions = '[[Category:' . $category . ']]';
        $queryParams = [
            $queryConditions,
            'limit=' . $limit,
            'offset=' . $offset,
        ];
        foreach ($properties as $property) {
            $queryParams[] = '?Has ' . $property;
        }

        // Apply sorting
        switch ($sortOrder) {
            case SortOrder::Default:
                // do nothing
                break;
            case SortOrder::NameAsc:
                $queryParams[] = 'sort=Has name';
                $queryParams[] = 'order=asc';
                break;
            case SortOrder::NameDesc:
                $queryParams[] = 'sort=Has name';
                $queryParams[] = 'order=desc';
                break;
            default:
                // TODO: more graceful failing
                throw new Exception('Unhandled sort order: ' . $sortOrder->value);
        }

        // Setup query
        list($queryString, $params, $printouts) = SMWQueryProcessor::getComponentsFromFunctionParams(
            $queryParams,
            false
        );
        $query = SMWQueryProcessor::createQuery(
            $queryString,
            SMWQueryProcessor::getProcessedParams($params),
            SMWQueryProcessor::INLINE_QUERY,
            '',
            $printouts
        );

        // Fetch query results
        $queryResult = $store->getQueryResult($query);
        $results = [];
        while ($row = $queryResult->getNext()) {
            $subject = $row[0]->getResultSubject();
            $title = $subject->getTitle();

            $pageData = [
                'id' => $subject->getSerialization(),
                'title' => self::humanizeTitle($title->getText()),
                'url' => '/wiki/' . $title->getPrefixedDBkey(),
            ];
            foreach ($properties as $property) {
                $name = self::sanitizePropertyName($property);
                $pageData[$name] = null;
            }

            // Extract property values
            foreach ($row as $field) {
                $label = $field->getPrintRequest()->getLabel();
                $values = [];
                while ($dataValue = $field->getNextDataValue()) {
                    $values[] = $dataValue->getShortWikiText();
                }

                $name = self::sanitizePropertyName(
                    substr($label, 4) // remove the "Has " prefix
                );
                $pageData[$name] = self::parseProperty($label, $values);
            }

            $results[] = $pageData;
        }

        // Get total count
        list($countQueryString, $countParamsProcessed, $countPrintouts) = SMWQueryProcessor::getComponentsFromFunctionParams(
            [$queryConditions, 'format=count'],
            false
        );
        $countQuery = SMWQueryProcessor::createQuery(
            $countQueryString,
            SMWQueryProcessor::getProcessedParams($countParamsProcessed),
            SMWQueryProcessor::INLINE_QUERY,
            'count',
            $countPrintouts
        );
        $countResult = $store->getQueryResult($countQuery);
        $totalAvailable = $countResult->getCountValue() ?: 0;

        return [
            $results,
            $totalAvailable,
        ];
    }

    /**
     * Convert a page title to a human-readable one, removing the prefix and
     * converting underscores to spaces.
     * @param string $title Title to format.
     * @return string Formatted title.
     */
    public static function humanizeTitle( string $title ): string {
        return str_replace('_', ' ', substr($title, strpos($title, '/') + 1));
    }

    /**
     * Parse a page property values into an API-friendly format.
     * @param string $label Label of the property.
     * @param array $values The values stored in the property.
     * @return string|array|null API-friendly version of the property.
     */
    protected static function parseProperty( string $label, array $values): string|array|null {
        switch ($label) {
            case 'Has guid':
            case 'Has deck':
            case 'Has image':
            case 'Has name':
                return $values[0] ?? '';
                break;

            case 'Has platforms':
                return array_map(
                    fn ($p) => self::humanizeTitle($p),
                    $values
                );
                break;

            case 'Has release date':
                if (!empty($values[0])) {
                    $timestamp = strtotime($values[0]);
                    if ($timestamp !== false) {
                        return date('Y-m-d', $timestamp);
                    }
                }
                break;

            default:
                // TODO: more graceful failing
                throw new Exception('Unhandled label: ' . $label);
        }

        return null;
    }

    /**
     * Convert a property name to a keyword-style name, removing spaces and
     * making lowercase.
     * @param string $property Property name to sanitize.
     * @return string A sanitized name.
     */
    protected static function sanitizePropertyName( string $property ): string {
        return strtolower(str_replace(' ', '_', $property));
    }
}
