<?php

namespace Drupal\llm_services\Model;

/**
 * Represents a message to be sent using a specific model.
 */
class Payload {

  /**
   * Name of the model to use.
   *
   * @var string
   */
  public string $model;

  /**
   * Message(s) to send.
   *
   * @var array<\Drupal\llm_services\Model\Message>
   *
   * @see https://github.com/ollama/ollama/blob/main/docs/api.md#parameters-1
   */
  public array $messages;

  /**
   * Additional model parameters.
   *
   * @var array<string, string>
   *
   * @see https://github.com/ollama/ollama/blob/main/docs/modelfile.md#valid-parameters-and-values
   */
  public array $options;

}
