<?php

namespace Drupal\llm_services\Client;

use Drupal\llm_services\Exceptions\CommunicationException;
use Drupal\llm_services\Model\Payload;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Client to communicate with Ollama.
 */
class Ollama {

  /**
   * Cache for stream parsing.
   *
   * @var string
   *
   * @see parse()
   */
  private string $parserCache = '';

  /**
   * Default constructor.
   *
   * @param string $url
   *   The URL of the Ollama server.
   * @param int $port
   *   The port that Ollama is listening at.
   * @param \GuzzleHttp\ClientInterface $client
   *   The http client used to interact with ollama.
   * @param string $username
   *   Basic auth username (default: empty string).
   * @param string $password
   *   Basic auth password (default: empty string).
   */
  public function __construct(
    private readonly string $url,
    private readonly int $port,
    private readonly ClientInterface $client,
    private readonly string $username = '',
    private readonly string $password = '',
  ) {
  }

  /**
   * List all models currently installed in Ollama.
   *
   * @return array<string, array<string, string>>
   *   Basic information about the models.
   *
   * @throws \Drupal\llm_services\Exceptions\CommunicationException
   */
  public function listLocalModels(): array {
    $response = $this->call(method: 'get', uri: '/api/tags');
    $data = $response->getBody()->getContents();
    $data = json_decode($data, TRUE);

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
   * @return \Generator
   *   The progress of installation.
   *
   * @see https://ollama.com/library
   *
   * @throws \Drupal\llm_services\Exceptions\CommunicationException
   * @throws \JsonException
   */
  public function install(string $modelName): \Generator {
    $response = $this->call(method: 'post', uri: '/api/pull', options: [
      'json' => [
        'name' => $modelName,
        'stream' => TRUE,
      ],
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      RequestOptions::CONNECT_TIMEOUT => 10,
      RequestOptions::TIMEOUT => 300,
      RequestOptions::STREAM => TRUE,
    ]);

    $body = $response->getBody();
    while (!$body->eof()) {
      $data = $body->read(1024);
      yield from $this->parse($data);
    }
  }

  /**
   * Ask a question to the model.
   *
   * @param \Drupal\llm_services\Model\Payload $payload
   *   The question to ask the module.
   *
   * @return \Generator
   *   The response from the model as it completes.
   *
   * @throws \Drupal\llm_services\Exceptions\CommunicationException
   * @throws \JsonException
   *
   * @see https://github.com/ollama/ollama/blob/main/docs/api.md#generate-a-completion
   */
  public function completion(Payload $payload): \Generator {
    $response = $this->call(method: 'post', uri: '/api/generate', options: [
      'json' => [
        'model' => $payload->getModel(),
        'prompt' => $payload->getMessages()[0]->content,
        'stream' => TRUE,
        'options' => $payload->getOptions(),
      ],
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      RequestOptions::CONNECT_TIMEOUT => 10,
      RequestOptions::TIMEOUT => 300,
      RequestOptions::STREAM => TRUE,
    ]);

    $body = $response->getBody();
    while (!$body->eof()) {
      $data = $body->read(1024);
      yield from $this->parse($data);
    }
  }

  /**
   * Chat with a model.
   *
   * @param \Drupal\llm_services\Model\Payload $payload
   *   The question to ask the module and the chat history.
   *
   * @return \Generator
   *   The response from the model as it completes it.
   *
   * @throws \Drupal\llm_services\Exceptions\CommunicationException
   * @throws \JsonException
   */
  public function chat(Payload $payload): \Generator {
    $response = $this->call(method: 'post', uri: '/api/chat', options: [
      'json' => [
        'model' => $payload->getModel(),
        'messages' => $this->chatMessagesAsArray($payload),
        'stream' => TRUE,
        'options' => $payload->getOptions(),
      ],
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      RequestOptions::CONNECT_TIMEOUT => 10,
      RequestOptions::TIMEOUT => 300,
      RequestOptions::STREAM => TRUE,
    ]);

    $body = $response->getBody();
    while (!$body->eof()) {
      $data = $body->read(1024);
      yield from $this->parse($data);
    }
  }

  /**
   * Take all payload messages and change them into an array.
   *
   * This array of messages is used to give the model some chat context to make
   * the interaction appear more like real char with a person.
   *
   * @param \Drupal\llm_services\Model\Payload $payload
   *   The payload sent to the chat function.
   *
   * @return array{content: string, role: string}[]
   *   Array of messages to send to Ollama.
   *
   * @see https://github.com/ollama/ollama/blob/main/docs/api.md#chat-request-with-history
   */
  private function chatMessagesAsArray(Payload $payload): array {
    $messages = [];
    foreach ($payload->getMessages() as $message) {
      $messages[] = [
        'content' => $message->content,
        'role' => $message->role->value,
      ];
    }

    return $messages;
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
   * @throws \JsonException
   */
  private function parse(string $data): \Generator {
    $strings = $this->parseDataToStrings($data);

    foreach ($strings as $str) {
      if (json_validate($str)) {
        // Valid json string lets decode an yield it.
        yield json_decode($str, TRUE, flags: JSON_THROW_ON_ERROR);
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
            yield json_decode($str, TRUE, flags: JSON_THROW_ON_ERROR);
            $this->parserCache = '';
          }
        }
      }
    }
  }

  /**
   * Parse data received from Ollama into string array.
   *
   * The data from Ollama when request in stream may not be complete JSON
   * objects separated by new-lines. It may be partial JSON objects, and the
   * objects theme self may contain new-lines (as the result from the LLM itself
   * uses new-lines). So simply splitting on new-lines is not a solution.
   *
   * Also, the first and last parts of the data may be partial objects or string
   * that will be completed in the next response.
   *
   * @param string $data
   *   The raw data string from the LLM.
   *
   * @return array<string>
   *   Array of strings.
   */
  private function parseDataToStrings(string $data): array {
    $results = [];

    // Match all json objects in the data.
    preg_match_all('/\{(?:[^{}]|(?R))*}/x', $data, $matches, PREG_OFFSET_CAPTURE);

    // Check the first and last matches as they may not be complete. We know
    // that a complet ollama json response contains both "model" and "done"
    // keys.
    $completResponseFound = FALSE;
    $pattern = '/\bmodel\b.*\bdone\b/';
    foreach ($matches[0] as $index => $match) {
      if (!preg_match($pattern, $match[0])) {
        // By looking at the next item and its offset, we can find the end of
        // the first invalid part.
        if (array_key_exists($index + 1, $matches[0])) {
          // The next match exists, so this is at the beginning of the data
          // string.
          $start = 0;
          $end = $matches[0][$index + 1][1];
          $results[] = trim(substr($data, $start, $end));
        }
        else {
          // This is in the last match, so find the start of the latest result
          // and add the length of that to the offset.
          $start = (int) $matches[0][$index - 1][1] + mb_strlen($results[$index - 1]);
          $results[] = trim(substr($data, $start));
        }
      }
      elseif ($index === 0 && $match[1] !== 0) {
        // Edge case: Where the preg_match_all do not match the first part as
        // json objects.
        $results[] = trim(substr($data, 0, $match[1]));

        // Add the actual match form the matches array.
        $results[] = $match[0];
        $completResponseFound = TRUE;
      }
      else {
        $results[] = $match[0];
        $completResponseFound = TRUE;
      }
    }

    // If a complete JSON response was not found, then the function checks for
    // the offset of the first "}\n{" (closing of one JSON object and start of
    // another) in the data and if found, splits the string into two parts from
    // this offset. If not found, it just adds the trimmed data as it is.
    if (!$completResponseFound) {
      $results = [];
      if ($end = strpos($data, "}\n{")) {
        $results[] = substr($data, 0, $end + 1);
        $results[] = substr($data, $end + 2);
      }
      else {
        $results[] = trim($data);
      }
    }
    else {
      // If a complete JSON response was found, then the function checks if
      // there is any leftover data after adding up the lengths of all the
      // strings in $results. If there is any leftover data, then it is
      // considered as the final fragment of a JSON object and appended to
      // $results.
      $total = (int) array_sum(array_map('strlen', $results)) + count($results) - 1;
      if ($total < mb_strlen($data)) {
        $results[] = trim(substr($data, $total));
      }
    }

    return $results;
  }

  /**
   * Make request to Ollama.
   *
   * @param string $method
   *   The method to use (GET/POST).
   * @param string $uri
   *   The API endpoint to call.
   * @param array<string, mixed> $options
   *   Extra options and/or payload to post.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response object.
   *
   * @throws \Drupal\llm_services\Exceptions\CommunicationException
   */
  private function call(string $method, string $uri, array $options = []): ResponseInterface {
    try {
      // Add basic auth if given.
      if (!empty($this->username)) {
        $auth = 'Basic ' . base64_encode($this->username . ':' . $this->password);
        if (isset($options['headers'])) {
          $options['headers']['Authorization'] = $auth;
        }
        else {
          $options['headers'] = ['Authorization' => $auth];
        }
      }
      $response = $this->client->request($method, $this->getUrl($uri), $options);
      if ($response->getStatusCode() !== 200) {
        throw new CommunicationException('Request failed', $response->getStatusCode());
      }
    }
    catch (GuzzleException $exception) {
      throw new CommunicationException('Request failed: ' . $exception->getMessage(), $exception->getCode(), $exception);
    }

    return $response;
  }

  /**
   * Returns a URL string with the given URI appended to the base URL.
   *
   * @param string $uri
   *   The URI to append to the base URL. Default is an empty string.
   *
   * @return string
   *   The complete URL string.
   */
  private function getUrl(string $uri = ''): string {
    return $this->url . ':' . $this->port . ($uri ? '/' . ltrim($uri, '/') : '');
  }

}
