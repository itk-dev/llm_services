<?php

namespace Drupal\llm_services\Client;

use Drupal\llm_services\Model\CompletionResponseInterface;

/**
 * This class represents a completion response in the Ollama provider.
 */
readonly class OllamaCompletionResponse implements CompletionResponseInterface {

  /**
   * Default constructor.
   *
   * @param string $model
   *   Name of the model.
   * @param string $response
   *   The response from the model.
   * @param bool $done
   *   The module completion state.
   * @param array<int> $context
   *   The generated context when completed.
   */
  public function __construct(
    private string $model,
    private string $response,
    private bool $done,
    private array $context,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getModel(): string {
    return $this->model;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(): string {
    return $this->response;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return $this->done;
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): array {
    return $this->context;
  }

}
