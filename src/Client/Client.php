<?php

namespace Drupal\llm_services\Client;

use Drupal\llm_services\Exceptions\CommunicationException;
use Drupal\llm_services\Model\Payload;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Base client to communicate with LLM.
 */
abstract class Client {

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
    protected string $url,
    protected int $port,
    protected ClientInterface $client,
    protected string $username = '',
    protected string $password = '',
  ) {
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
  protected function chatMessagesAsArray(Payload $payload): array {
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
   * Make request to LLM framework.
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
  protected function call(string $method, string $uri, array $options = []): ResponseInterface {
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
  protected function getUrl(string $uri = ''): string {
    return $this->url . ':' . $this->port . ($uri ? '/' . ltrim($uri, '/') : '');
  }

}
