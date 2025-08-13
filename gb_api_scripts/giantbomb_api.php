<?php

/**
 * GiantBombAPI Class
 *
 * A PHP library for interacting with the Giant Bomb API.
 * Provides methods for making API requests and paginating through results.
 */
class GiantBombAPI
{
    private string $apiKey;
    private string $baseUrl = 'https://www.giantbomb.com/api/';
    private int $defaultLimit = 100; // Max limit per request for Giant Bomb API
    private bool $nowait = false; // Flag to stop run once request limit has been reached

    /**
     * @var int $currentPage A static variable to keep track of the current page number during pagination.
     * This is calculated as (offset / limit) + 1.
     * It will be updated with each successful page fetch within the paginate method.
     */
    public static int $currentPage = 0;

    /**
     * @var int Tracks the number of API requests made within the current hour window.
     */
    public static int $requestCount = 0;

    /**
     * @var int Stores the Unix timestamp (seconds) when the current hourly rate limit window started or was last reset.
     */
    public static int $lastResetTime = 0;

    private const RATE_LIMIT_PER_HOUR = 200;
    private const RATE_LIMIT_INTERVAL_SECONDS = 3600;

    /**
     * Constructor
     *
     * @param string $apiKey Your Giant Bomb API key.
     * @throws InvalidArgumentException If the API key is empty.
     */
    public function __construct(string $apiKey, bool $nowait)
    {
        if (empty($apiKey)) {
            throw new InvalidArgumentException("Giant Bomb API Key cannot be empty.  Go to https://www.giantbomb.com/api and copy it into your .env file.");
        }
        $this->apiKey = $apiKey;
        $this->nowait = $nowait;

        // Initialize lastResetTime if it's the very first time the class is loaded
        if (self::$lastResetTime === 0) {
            self::$lastResetTime = time();
        }
    }

    /**
     * Makes a single request to the Giant Bomb API.
     * This method includes the rate limiting logic.
     *
     * @param string $endpoint The API endpoint (e.g., 'games', 'platforms').
     * @param array $params Optional query parameters (e.g., ['query' => 'zelda', 'field_list' => 'name,deck']).
     * @param bool $trackRateLimit By default it will track for 200 requests per hour with the assumption of one resource being requested
     * @return array|object Decoded JSON response as a PHP array or object.
     * @throws Exception If the cURL request fails, JSON decoding fails, or API returns an error.
     */
    public function request(string $endpoint, array $params = [], bool $trackRateLimit = true)
    {
        if ($trackRateLimit) {
            $currentTime = time();

            // Check if a new hour window has begun since the last reset
            if ($currentTime - self::$lastResetTime >= self::RATE_LIMIT_INTERVAL_SECONDS) {
                self::$requestCount = 0;                // Reset count for the new hour
                self::$lastResetTime = $currentTime;    // Reset timestamp for the new hour
            }

            // Check if we exceeded the rate limit for the current hour (don't need to check for resource because its one resource per run)
            if (self::$requestCount >= self::RATE_LIMIT_PER_HOUR) {
                $timeToWait = self::RATE_LIMIT_INTERVAL_SECONDS - ($currentTime - self::$lastResetTime);

                if ($timeToWait > 0) {
                    if ($this->nowait) {
                        echo "Rate limit reached (" . self::RATE_LIMIT_PER_HOUR . " requests/hour for " . $endpoint ."). Nowait flag set to true. Exiting...\n";
                        // return empty array to trigger end of loop
                        return [];
                    }
                    else {
                        echo "Rate limit reached (" . self::RATE_LIMIT_PER_HOUR . " requests/hour for " . $endpoint . "). Waiting for " . $timeToWait . " seconds...\n";
                        sleep($timeToWait); 
                        
                        // its a new hour
                        self::$requestCount = 0;        
                        self::$lastResetTime = time();  
                        echo "Resuming requests.\n";
                    }
                }
            }

            // Increment the request counter for the current request
            self::$requestCount++;
        }

        $params['api_key'] = $this->apiKey;
        $params['format'] = 'json';

        $url = $this->baseUrl . $endpoint . '/?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'GiantBombPHPAPI/1.0');

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL Error: " . $error_msg);
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 400) {
            throw new Exception("API Request failed with HTTP status code: " . $http_code . " Response: " . $response);
        }

        $decodedResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON Decoding Error: " . json_last_error_msg());
        }

        if (isset($decodedResponse['error']) && $decodedResponse['error'] !== 'OK') {
            throw new Exception("Giant Bomb API Error: " . $decodedResponse['error'] . " (Status Code: " . $decodedResponse['status_code'] . ") URL: " . $url);
        }

        return $decodedResponse;
    }

    /**
     * Paginates through an API endpoint to retrieve all the results.
     *
     * @param string $endpoint The API endpoint.
     * @param array $params Optional query parameters to apply to each request.
     * @param int $maxResults The maximum number of results to fetch. Use -1 for all available results.
     * @return array An array containing all fetched results.
     * @throws Exception If any underlying API request fails during pagination.
     */
    public function paginate(string $endpoint, array $params = [], int $maxResults = -1): array
    {
        $allResults = [];
        $limit = $this->defaultLimit;
        $offset = ($params['offset'] < 0) ? 0 : $params['offset'];

        // Reset static page counter for a new pagination process
        self::$currentPage = 0;

        do {
            $params['offset'] = $offset;

            try {
                $response = $this->request($endpoint, $params);
            }
            catch (Exception $e) {
                // exit with what we have instead of dying
                break;
            }
            sleep(1);

            echo "Pulled " . $offset . " to " . ($offset + $response['number_of_page_results']) . "\r\n";

            if (!isset($response['results']) || !is_array($response['results'])) {
                // no more results
                break;
            }

            // Add fetched results to the main array
            $allResults = array_merge($allResults, $response['results']);

            $numberOfPageResults = $response['number_of_page_results'] ?? 0;
            $numberOfTotalResults = $response['number_of_total_results'] ?? 0;

            // Update offset for the next request
            $offset += $numberOfPageResults;

            // Update static page number
            self::$currentPage = ($offset > 0) ? (int)ceil($offset / $limit) : 1;

            // Break if maxResults is reached or exceeded
            if ($maxResults !== -1 && count($allResults) >= $maxResults) {
                // Trim results if we fetched more than maxResults on the last page
                if (count($allResults) > $maxResults) {
                    $allResults = array_slice($allResults, 0, $maxResults);
                }
                break;
            }

            // Break if we've fetched all available results
            if ($offset >= $numberOfTotalResults) {
                break;
            }

            // If number_of_page_results is 0 but offset is less than total,
            // it might indicate an issue or end of results.
            if ($numberOfPageResults === 0 && $offset < $numberOfTotalResults) {
                 break;
            }

        } while (true);

        return $allResults;
    }
}

?>