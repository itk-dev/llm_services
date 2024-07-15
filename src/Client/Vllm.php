<?php

namespace Drupal\llm_services\Client;

use Drupal\llm_services\Exceptions\CommunicationException;
use Drupal\llm_services\Exceptions\NotSupportedException;
use Drupal\llm_services\Model\Payload;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Client to communicate with VLLM.
 *
 * @see https://docs.vllm.ai/en/latest/serving/openai_compatible_server.html
 */
class Vllm {

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
   *   The URL of the VLLM server.
   * @param int $port
   *   The port that VLLM is listening at.
   * @param \GuzzleHttp\ClientInterface $client
   *   The http client used to interact with VLLM.
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
   * List all models currently installed in VLLM.
   *
   * @return array<string, array<string, string>>
   *   Basic information about the models.
   *
   * @throws \Drupal\llm_services\Exceptions\CommunicationException
   * @throws \Exception
   */
  public function listLocalModels(): array {
    $response = $this->call(method: 'get', uri: '/v1/models');
    $data = $response->getBody()->getContents();
    $data = json_decode($data, TRUE);

    $models = [];
    foreach ($data['data'] as $item) {
      $models[$item['id']] = [
        'name' => $item['id'],
        'max_model_len' => $item['max_model_len'],
        'modified' => (new \DateTime("@" . $item['created']))->format('Y-m-d H:i:s'),
      ];
    }

    return $models;
  }

  /**
   * Install/update model in Vllm.
   *
   * @param string $modelName
   *   Name of the model.
   *
   * @throws \Drupal\llm_services\Exceptions\NotSupportedException
   */
  public function install(string $modelName): \Generator {
    throw new NotSupportedException('This provider do not support dynamically installing models');
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
   */
  public function completion(Payload $payload): \Generator {
    $response = $this->call(method: 'post', uri: 'v1/completions', options: [
      'json' => [
        'model' => $payload->getModel(),
        'prompt' => $payload->getMessages()[0]->content,
        'stream' => TRUE,
      ] + $payload->getOptions(),
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
    $response = $this->call(method: 'post', uri: '/v1/chat/completions', options: [
      'json' => [
        'model' => $payload->getModel(),
        'messages' => $this->chatMessagesAsArray($payload),
        'stream' => TRUE,
      ] + $payload->getOptions(),
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
   *   Array of messages to send to VLLM.
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

    foreach ($strings as $index => $str) {
      if (json_validate($str)) {
        // Special case where we have the string before done.
        if (array_key_exists($index + 1, $strings) && $strings[$index + 1] === " [DONE]\n\n") {
          $json = json_decode($str, TRUE, flags: JSON_THROW_ON_ERROR);
          $json['done'] = TRUE;
          yield $json;

          return;
        }

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
   * Parse data received from VLLM into string array.
   *
   * @param string $data
   *   The raw data string from the LLM.
   *
   * @return array<string>
   *   Array of strings.
   */
  private function parseDataToStrings(string $data): array {
    return preg_split('/(\n\ndata:|^data:)/', $data);
  }

  /**
   * Make request to VLLM.
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
