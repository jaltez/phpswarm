<?php

declare(strict_types=1);

namespace PhpSwarm\Tool\Weather;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PhpSwarm\Exception\Tool\ToolExecutionException;
use PhpSwarm\Tool\BaseTool;

/**
 * Tool for fetching weather information for a location.
 */
class WeatherTool extends BaseTool
{
    /**
     * @var Client HTTP client
     */
    private readonly Client $client;

    /**
     * @var string API key for the weather service
     */
    private readonly string $apiKey;

    /**
     * @var string Base URL for the weather API
     */
    private readonly string $baseUrl;

    /**
     * @var string The weather service to use
     */
    private readonly string $service;

    /**
     * Create a new WeatherTool instance.
     *
     * @param array<string, mixed> $config Configuration options
     */
    public function __construct(array $config = [])
    {
        parent::__construct(
            'weather',
            'Get current weather or forecast for a location'
        );

        $this->parametersSchema = [
            'location' => [
                'type' => 'string',
                'description' => 'The location to get weather for (city name, zip code, coordinates)',
                'required' => true,
            ],
            'type' => [
                'type' => 'string',
                'description' => 'The type of weather information to get (current, forecast)',
                'required' => false,
                'default' => 'current',
                'enum' => ['current', 'forecast'],
            ],
            'days' => [
                'type' => 'integer',
                'description' => 'Number of days for forecast (only used if type is forecast)',
                'required' => false,
                'default' => 5,
            ],
        ];

        $this->apiKey = $config['api_key'] ?? getenv('WEATHER_API_KEY');
        $this->service = $config['service'] ?? 'openweathermap';

        // Set the base URL based on the service
        $this->baseUrl = match ($this->service) {
            'openweathermap' => 'https://api.openweathermap.org/data/2.5',
            'weatherapi' => 'https://api.weatherapi.com/v1',
            default => $config['base_url'] ?? '',
        };

        $this->client = new Client();

        $this->addTag('weather');
        $this->addTag('location');
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
                'Weather API key is required. Set it in the configuration or as an environment variable WEATHER_API_KEY.',
                $parameters,
                $this->getName()
            );
        }

        $location = $parameters['location'];
        $type = $parameters['type'] ?? 'current';
        $days = $parameters['days'] ?? 5;

        try {
            return match ($this->service) {
                'openweathermap' => $this->fetchFromOpenWeatherMap($location, $type, $days),
                'weatherapi' => $this->fetchFromWeatherApi($location, $type, $days),
                default => throw new ToolExecutionException(
                    "Unsupported weather service: {$this->service}",
                    $parameters,
                    $this->getName()
                ),
            };
        } catch (GuzzleException $e) {
            throw new ToolExecutionException(
                "Weather request failed: {$e->getMessage()}",
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
        return $this->apiKey !== '' && $this->apiKey !== '0' && ($this->baseUrl !== '' && $this->baseUrl !== '0');
    }

    /**
     * Fetch weather information from OpenWeatherMap API.
     *
     * @param string $location The location to get weather for
     * @param string $type The type of weather information to get
     * @param int $days Number of days for forecast
     * @return array<string, mixed> The weather information
     * @throws GuzzleException If the request fails
     */
    private function fetchFromOpenWeatherMap(string $location, string $type, int $days): array
    {
        $endpoint = $type === 'current' ? 'weather' : 'forecast';
        $params = [
            'q' => $location,
            'appid' => $this->apiKey,
            'units' => 'metric', // Use metric units by default
        ];

        $response = $this->client->get("{$this->baseUrl}/{$endpoint}", [
            'query' => $params,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        // Process and format the response based on the type
        if ($type === 'current') {
            return $this->formatOpenWeatherMapCurrent($data);
        }
        return $this->formatOpenWeatherMapForecast($data, $days);
    }

    /**
     * Format current weather data from OpenWeatherMap.
     *
     * @param array<string, mixed> $data The raw weather data
     * @return array<string, mixed> The formatted weather data
     */
    private function formatOpenWeatherMapCurrent(array $data): array
    {
        return [
            'location' => [
                'name' => $data['name'] ?? 'Unknown',
                'country' => $data['sys']['country'] ?? '',
                'coordinates' => [
                    'latitude' => $data['coord']['lat'] ?? 0,
                    'longitude' => $data['coord']['lon'] ?? 0,
                ],
            ],
            'current' => [
                'temperature' => [
                    'value' => $data['main']['temp'] ?? 0,
                    'unit' => 'C',
                    'feels_like' => $data['main']['feels_like'] ?? 0,
                ],
                'condition' => [
                    'text' => $data['weather'][0]['description'] ?? '',
                    'code' => $data['weather'][0]['id'] ?? 0,
                    'icon' => $data['weather'][0]['icon'] ?? '',
                ],
                'wind' => [
                    'speed' => $data['wind']['speed'] ?? 0,
                    'direction' => $data['wind']['deg'] ?? 0,
                    'unit' => 'm/s',
                ],
                'humidity' => $data['main']['humidity'] ?? 0,
                'pressure' => $data['main']['pressure'] ?? 0,
                'visibility' => $data['visibility'] ?? 0,
                'updated_at' => date('Y-m-d H:i:s', $data['dt'] ?? time()),
            ],
        ];
    }

    /**
     * Format forecast data from OpenWeatherMap.
     *
     * @param array<string, mixed> $data The raw forecast data
     * @param int $days Number of days for forecast
     * @return array<string, mixed> The formatted forecast data
     */
    private function formatOpenWeatherMapForecast(array $data, int $days): array
    {
        $forecast = [
            'location' => [
                'name' => $data['city']['name'] ?? 'Unknown',
                'country' => $data['city']['country'] ?? '',
                'coordinates' => [
                    'latitude' => $data['city']['coord']['lat'] ?? 0,
                    'longitude' => $data['city']['coord']['lon'] ?? 0,
                ],
            ],
            'forecast' => [],
        ];

        // OpenWeatherMap's free tier provides forecast in 3-hour intervals
        // We'll aggregate these into daily forecasts
        $dailyForecasts = [];

        foreach ($data['list'] as $item) {
            $date = date('Y-m-d', $item['dt']);

            if (!isset($dailyForecasts[$date])) {
                $dailyForecasts[$date] = [
                    'date' => $date,
                    'temp_min' => $item['main']['temp_min'],
                    'temp_max' => $item['main']['temp_max'],
                    'conditions' => [$item['weather'][0]['description']],
                    'humidity' => $item['main']['humidity'],
                    'wind_speed' => $item['wind']['speed'],
                    'pressure' => $item['main']['pressure'],
                ];
            } else {
                $dailyForecasts[$date]['temp_min'] = min($dailyForecasts[$date]['temp_min'], $item['main']['temp_min']);
                $dailyForecasts[$date]['temp_max'] = max($dailyForecasts[$date]['temp_max'], $item['main']['temp_max']);
                $dailyForecasts[$date]['conditions'][] = $item['weather'][0]['description'];
            }
        }

        // Limit to the requested number of days
        $count = 0;
        foreach ($dailyForecasts as $day) {
            if ($count >= $days) {
                break;
            }

            // Get most common condition for the day
            $conditions = array_count_values($day['conditions']);
            arsort($conditions);
            $mainCondition = key($conditions);

            $forecast['forecast'][] = [
                'date' => $day['date'],
                'temperature' => [
                    'min' => $day['temp_min'],
                    'max' => $day['temp_max'],
                    'unit' => 'C',
                ],
                'condition' => $mainCondition,
                'humidity' => $day['humidity'],
                'wind_speed' => $day['wind_speed'],
                'pressure' => $day['pressure'],
            ];

            $count++;
        }

        return $forecast;
    }

    /**
     * Fetch weather information from WeatherAPI.com.
     *
     * @param string $location The location to get weather for
     * @param string $type The type of weather information to get
     * @param int $days Number of days for forecast
     * @return array<string, mixed> The weather information
     * @throws GuzzleException If the request fails
     */
    private function fetchFromWeatherApi(string $location, string $type, int $days): array
    {
        $endpoint = $type === 'current' ? 'current.json' : 'forecast.json';
        $params = [
            'q' => $location,
            'key' => $this->apiKey,
            'days' => $type === 'forecast' ? $days : 1,
        ];

        $response = $this->client->get("{$this->baseUrl}/{$endpoint}", [
            'query' => $params,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        // Process and format the response based on the type
        if ($type === 'current') {
            return $this->formatWeatherApiCurrent($data);
        }
        return $this->formatWeatherApiForecast($data);
    }

    /**
     * Format current weather data from WeatherAPI.com.
     *
     * @param array<string, mixed> $data The raw weather data
     * @return array<string, mixed> The formatted weather data
     */
    private function formatWeatherApiCurrent(array $data): array
    {
        return [
            'location' => [
                'name' => $data['location']['name'] ?? 'Unknown',
                'country' => $data['location']['country'] ?? '',
                'coordinates' => [
                    'latitude' => $data['location']['lat'] ?? 0,
                    'longitude' => $data['location']['lon'] ?? 0,
                ],
            ],
            'current' => [
                'temperature' => [
                    'value' => $data['current']['temp_c'] ?? 0,
                    'unit' => 'C',
                    'feels_like' => $data['current']['feelslike_c'] ?? 0,
                ],
                'condition' => [
                    'text' => $data['current']['condition']['text'] ?? '',
                    'code' => $data['current']['condition']['code'] ?? 0,
                    'icon' => $data['current']['condition']['icon'] ?? '',
                ],
                'wind' => [
                    'speed' => $data['current']['wind_kph'] ?? 0,
                    'direction' => $data['current']['wind_degree'] ?? 0,
                    'unit' => 'kph',
                ],
                'humidity' => $data['current']['humidity'] ?? 0,
                'pressure' => $data['current']['pressure_mb'] ?? 0,
                'visibility' => $data['current']['vis_km'] ?? 0,
                'updated_at' => $data['current']['last_updated'] ?? date('Y-m-d H:i'),
            ],
        ];
    }

    /**
     * Format forecast data from WeatherAPI.com.
     *
     * @param array<string, mixed> $data The raw forecast data
     * @return array<string, mixed> The formatted forecast data
     */
    private function formatWeatherApiForecast(array $data): array
    {
        $forecast = [
            'location' => [
                'name' => $data['location']['name'] ?? 'Unknown',
                'country' => $data['location']['country'] ?? '',
                'coordinates' => [
                    'latitude' => $data['location']['lat'] ?? 0,
                    'longitude' => $data['location']['lon'] ?? 0,
                ],
            ],
            'forecast' => [],
        ];

        if (isset($data['forecast']['forecastday']) && is_array($data['forecast']['forecastday'])) {
            foreach ($data['forecast']['forecastday'] as $day) {
                $forecast['forecast'][] = [
                    'date' => $day['date'],
                    'temperature' => [
                        'min' => $day['day']['mintemp_c'],
                        'max' => $day['day']['maxtemp_c'],
                        'unit' => 'C',
                    ],
                    'condition' => $day['day']['condition']['text'],
                    'humidity' => $day['day']['avghumidity'],
                    'wind_speed' => $day['day']['maxwind_kph'],
                    'chance_of_rain' => $day['day']['daily_chance_of_rain'],
                ];
            }
        }

        return $forecast;
    }
}
