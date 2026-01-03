<?php

namespace GiantBombApi\Datastore;

use Exception;
use GiantBombApi\Helpers\PageHelper;
use SMW;
use SMWQueryProcessor;

/**
 * Datastore connection for retrieving games.
 */
class GamesDatastore {

    /**
     * Get a list of games.
     * @param SortOrder $sortOrder Sort order of the results.
     * @param int $limit How many results to fetch.
     * @param int $offset Result offset.
     * @return array List of filtered, limited & sorted games, along with the total games.
     */
    public static function getGames( SortOrder $sortOrder, int $limit, int $offset ): array {
        $store = SMW\StoreFactory::getStore();

        $queryConditions = '[[Category:Games]]';
        $queryParams = [
            $queryConditions,
            'limit=' . $limit,
            'offset=' . $offset,
            '?Has guid',
            '?Has name',
            '?Has deck',
            '?Has image',
            '?Has release date',
            '?Has platforms'
        ];

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

        $queryResult = $store->getQueryResult($query);
        $results = [];
        while ($row = $queryResult->getNext()) {
            $subject = $row[0]->getResultSubject();
            $title = $subject->getTitle();

            $pageData = [
                'id' => $subject->getSerialization(),
                'guid' => null,
                'url' => '/wiki/' . $title->getPrefixedDBkey(),
                'deck' => null,
                'name' => PageHelper::humanizeTitle($title->getText()),
                'description' => null,
                'image' => null,
                'original_release_date' => null,
                'platforms' => null,
            ];

            // Extract property values
            for ($i = 0; $i < count($row); $i++) {
                $field = $row[$i];
                $label = $field->getPrintRequest()->getLabel();

                $values = [];
                $dv = null;
                while ($tempDV = $field->getNextDataValue()) {
                    $dv = $tempDV;
                    $values[] = $dv->getShortWikiText();
                }

                switch ($label) {
                    case 'Has guid':
                        $pageData['guid'] = $values[0] ?? '';
                        break;
                    case 'Has name':
                        $pageData['name'] = $values[0] ?? $pageData['name'];
                        break;
                    case 'Has deck':
                        $pageData['deck'] = $values[0] ?? '';
                        break;
                    case 'Has image':
                        $pageData['image']= $values[0] ?? '';
                        break;
                    case 'Has release date':
                        if (!empty($values[0])) {
                            $timestamp = strtotime($values[0]);
                            if ($timestamp !== false) {
                                $pageData['original_release_date'] = date('Y-m-d', $timestamp);
                            }
                        }
                        break;
                    case 'Has platforms':
                        $pageData['platforms'] = array_map(function($p) {
                            return PageHelper::humanizeTitle($p);
                        }, $values);
                        break;
                    default:
                        // TODO: more graceful failing
                        throw new Exception('Unhandled label: ' . $label);
                }
            }

            $results[] = $pageData;
        }

        return [
            $results,
            123, // TODO: total results
        ];
    }
}
