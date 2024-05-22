<?php

namespace Drupal\llm_services\Client;

use Drupal\llm_services\Model\Payload;
use GuzzleHttp\RequestOptions;

/**
 * Client to communicate with Ollama.
 */
class Ollama {

  private string $parserCache = '';

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
  public function listLocalModels(): array {
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
   * Install/update model in Ollama.
   *
   * @param string $modelName
   *   Name of the model.
   *
   * @return string
   *
   * @see https://ollama.com/library
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \JsonException
   */
  public function install(string $modelName): string {
    $this->call(method: 'post', uri: '/api/pull', options: [
      'json' => [
        'name' => $modelName,
        'stream' => false,
      ],
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      RequestOptions::CONNECT_TIMEOUT => 10,
      RequestOptions::TIMEOUT => 300,
    ]);

    // @todo: change to stream and return status.
    return '';
  }

  /**
   * Ask a question to the model.
   *
   * @TODO make call function that can do the stream, if possible.
   *
   * @param \Drupal\llm_services\Model\Payload $payload
   *
   * @return \Generator
   *
   * @see https://github.com/ollama/ollama/blob/main/docs/api.md#generate-a-completion
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function completion(Payload $payload): \Generator {

    $json = [
      'model' => $payload->model,
      'prompt' => $payload->messages[0]->content,
      'stream' => true,
    ];

    $client = \Drupal::httpClient();
    $response = $client->request(
      'POST',
      $this->getURL('/api/generate'),
      [
        'json' => $json,
        RequestOptions::CONNECT_TIMEOUT => 10,
        RequestOptions::TIMEOUT => 300,
        RequestOptions::STREAM => true,
      ]
    );

    $body = $response->getBody();
    while (!$body->eof()) {
      $data = $body->read(1024);
      yield from $this->parse($data);;
    }

  }

  /**
   * Parse LLM stream.
   *
   * As the LLM streams the response, and we read them in chunks and given chunk
   * of data may not be complete json object. So this function parses the data
   * and joins chunks to make it valid parsable json. But at the same time
   * yield back json results as soon as possible to make the stream seam as live
   * response.
   *
   * @param string $data
   *   The data chunk to parse.
   *
   * @return \Generator
   *   Yield back json objects.
   *
   * @todo: should json be converted to valid LLMResObject?
   *
   * @throws \JsonException
   */
  private function parse(string $data): \Generator {
    // Split on new-lines.
    $strings = explode("\n", $data);

    foreach ($strings as $str) {
      if (json_validate($str)) {
          // Valid json string lets decode an yield it.
          yield json_decode($str, true, flags: JSON_THROW_ON_ERROR);
      }
      else {
        // Ignore empty strings.
        if (!empty($str)) {
          // If cached partial json object: append else store.
          if (empty($this->parserCache)) {
            $this->parserCache = $str;
          }
          else {
            $str = $this->parserCache . $str;
            if (!json_validate($str)) {
              // Still not json, just append until it becomes json.
              $this->parserCache .= $str;

              // Nothing to yield, no complet json string yet.
              return;
            }
            // Valid json string, yield, reset cache.
            yield json_decode($str, true, flags: JSON_THROW_ON_ERROR);
            $this->parserCache = '';
          }
        }
      }
    }
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

    $response = $client->request($method, $this->getURL($uri), $options);

    if ($response->getStatusCode() !== 200) {
      throw new \Exception('Request failed');
    }
    $data = $response->getBody()->getContents();

    return json_decode($data, TRUE, 512, JSON_THROW_ON_ERROR);
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
