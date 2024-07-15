<?php

namespace Drupal\llm_services\Client;

use Drupal\llm_services\Model\CompletionResponseInterface;

/**
 * This class represents a completion response in the VLLM provider.
 */
readonly class VllmCompletionResponse implements CompletionResponseInterface {

  /**
   * Default constructor.
   *
   * @param string $model
   *   Name of the model.
   * @param string $response
   *   The response from the model.
   * @param bool $done
   *   The module completion state.
   */
  public function __construct(
    private string $model,
    private string $response,
    private bool $done,
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

}
