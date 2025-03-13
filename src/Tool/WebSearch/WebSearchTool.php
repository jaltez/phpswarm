<?php

declare(strict_types=1);

namespace PhpSwarm\Tool\WebSearch;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PhpSwarm\Exception\Tool\ToolExecutionException;
use PhpSwarm\Tool\BaseTool;

/**
 * Tool for performing web searches using a search API.
 */
class WebSearchTool extends BaseTool
{
    /**
     * @var Client HTTP client
     */
    private readonly Client $client;

    /**
     * @var string API key for the search service
     */
    private readonly string $apiKey;

    /**
     * @var string Search engine ID (for services like Google Custom Search)
     */
    private readonly string $searchEngineId;

    /**
     * @var string The search service to use (google, bing, etc.)
     */
    private readonly string $service;

    /**
     * Create a new WebSearchTool instance.
     *
     * @param array<string, mixed> $config Configuration options
     */
    public function __construct(array $config = [])
    {
        parent::__construct(
            'web_search',
            'Search the web for information on a given query'
        );

        $this->parametersSchema = [
            'query' => [
                'type' => 'string',
                'description' => 'The search query',
                'required' => true,
            ],
            'num_results' => [
                'type' => 'integer',
                'description' => 'Number of results to return',
                'required' => false,
                'default' => 5,
            ],
        ];

        $this->apiKey = $config['api_key'] ?? getenv('SEARCH_API_KEY');
        $this->searchEngineId = $config['search_engine_id'] ?? getenv('SEARCH_ENGINE_ID');
        $this->service = $config['service'] ?? 'google';

        $this->client = new Client();

        $this->addTag('web');
        $this->addTag('search');
        $this->setRequiresAuthentication(true);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function run(array $parameters = []): mixed
    {
        $this->validateParameters($parameters);

        if ($this->apiKey === '' || $this->apiKey === '0') {
            throw new ToolExecutionException(
                'Search API key is required. Set it in the configuration or as an environment variable SEARCH_API_KEY.',
                $parameters,
                $this->getName()
            );
        }

        $query = $parameters['query'];
        $numResults = $parameters['num_results'] ?? 5;

        try {
            return $this->performSearch($query, $numResults);
        } catch (GuzzleException $e) {
            throw new ToolExecutionException(
                "Search request failed: {$e->getMessage()}",
                $parameters,
                $this->getName(),
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isAvailable(): bool
    {
        return $this->apiKey !== '' && $this->apiKey !== '0';
    }

    /**
     * Perform the search using the configured service.
     *
     * @param string $query The search query
     * @param int $numResults Number of results to return
     * @return array<int, array<string, string>> The search results
     * @throws GuzzleException If the request fails
     * @throws ToolExecutionException If the search service is not supported
     */
    private function performSearch(string $query, int $numResults): array
    {
        return match ($this->service) {
            'google' => $this->googleSearch($query, $numResults),
            'bing' => $this->bingSearch($query, $numResults),
            default => throw new ToolExecutionException(
                "Unsupported search service: {$this->service}",
                ['query' => $query, 'num_results' => $numResults],
                $this->getName()
            ),
        };
    }

    /**
     * Perform a search using Google Custom Search API.
     *
     * @param string $query The search query
     * @param int $numResults Number of results to return
     * @return array<int, array<string, string>> The search results
     * @throws GuzzleException If the request fails
     * @throws ToolExecutionException If the search engine ID is missing
     */
    private function googleSearch(string $query, int $numResults): array
    {
        if ($this->searchEngineId === '' || $this->searchEngineId === '0') {
            throw new ToolExecutionException(
                'Google Custom Search requires a search engine ID. Set it in the configuration or as an environment variable SEARCH_ENGINE_ID.',
                ['query' => $query, 'num_results' => $numResults],
                $this->getName()
            );
        }

        $response = $this->client->get('https://www.googleapis.com/customsearch/v1', [
            'query' => [
                'key' => $this->apiKey,
                'cx' => $this->searchEngineId,
                'q' => $query,
                'num' => min($numResults, 10), // Google API limits to 10 results per page
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (!isset($data['items']) || !is_array($data['items'])) {
            return [];
        }

        $results = [];
        foreach ($data['items'] as $item) {
            $results[] = [
                'title' => $item['title'] ?? 'No title',
                'link' => $item['link'] ?? '',
                'snippet' => $item['snippet'] ?? '',
            ];

            if (count($results) >= $numResults) {
                break;
            }
        }

        return $results;
    }

    /**
     * Perform a search using Bing Search API.
     *
     * @param string $query The search query
     * @param int $numResults Number of results to return
     * @return array<int, array<string, string>> The search results
     * @throws GuzzleException If the request fails
     */
    private function bingSearch(string $query, int $numResults): array
    {
        $response = $this->client->get('https://api.bing.microsoft.com/v7.0/search', [
            'headers' => [
                'Ocp-Apim-Subscription-Key' => $this->apiKey,
            ],
            'query' => [
                'q' => $query,
                'count' => min($numResults, 50), // Bing API allows up to 50 results
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (!isset($data['webPages']['value']) || !is_array($data['webPages']['value'])) {
            return [];
        }

        $results = [];
        foreach ($data['webPages']['value'] as $item) {
            $results[] = [
                'title' => $item['name'] ?? 'No title',
                'link' => $item['url'] ?? '',
                'snippet' => $item['snippet'] ?? '',
            ];

            if (count($results) >= $numResults) {
                break;
            }
        }

        return $results;
    }
}
