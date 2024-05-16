<?php

namespace Drupal\llm_services\Client;

/**
 * Client to communicate with Ollama.
 */
class Ollama {

  /**
   * Default constructor.
   *
   * @param string $url
   *   The URL of the Ollama server.
   * @param int $port
   *   The port that Ollama is listing on.
   */
  public function __construct(
    private readonly string $url,
    private readonly int $port,
  ) {
  }

  /**
   * List all models currently installed in Ollama.
   *
   * @return array<string, array<string, string>>
   *   Basic information about the models.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \JsonException
   */
  function listLocalModels(): array {
    $data = $this->call(method: 'get', uri: '/api/tags');

    // @todo: change to value objects.
    $models = [];
    foreach ($data['models'] as $item) {
      $models[$item['model']] = [
        'name' => $item['name'],
        'size' => $item['size'],
        'modified' => $item['modified_at'],
        'digest' => $item['digest'],
      ];
    }

    return $models;
  }

  /**
   * Make request to Ollama.
   *
   * @param string $method
   *   The method to use (GET/POST).
   * @param string $uri
   *   The API endpoint to call
   * @param array $options
   *   Extra options and/or payload to post.
   *
   * @return mixed
   *   The result of the call.
   *
   * @todo: what about stream calls?
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \JsonException
   */
  private function call(string $method, string $uri, array $options = []): mixed {
    $client = \Drupal::httpClient();

    $request = $client->request($method, $this->getURL($uri), $options);

    if ($request->getStatusCode() !== 200) {
      throw new \Exception('Request failed');
    }
    $response = $request->getBody()->getContents();

    return json_decode($response, TRUE, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Returns a URL string with the given URI appended to the base URL.
   *
   * @param string $uri
   *   The URI to append to the base URL. Default is an empty string.
   *
   * @return string T
   *   The complete URL string.
   */
  private function getURL(string $uri = ''): string {
    return $this->url . ':' . $this->port . ($uri ? '/' . ltrim($uri, '/') : '');
  }

}
