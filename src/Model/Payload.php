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
  private string $model;

  /**
   * Message(s) to send.
   *
   * @var array<\Drupal\llm_services\Model\Message>
   *
   * @see https://github.com/ollama/ollama/blob/main/docs/api.md#parameters-1
   */
  private array $messages;

  /**
   * Additional model parameters.
   *
   * @var array<string, string>
   *
   * @see https://github.com/ollama/ollama/blob/main/docs/modelfile.md#valid-parameters-and-values
   */
  private array $options;

  /**
   * Get model.
   *
   * @return string
   *   Name of the model.
   */
  public function getModel(): string {
    return $this->model;
  }

  /**
   * Set model name.
   *
   * @param string $model
   *   The name of the model.
   */
  public function setModel(string $model): self {
    $this->model = $model;

    return $this;
  }

  /**
   * Get messages.
   *
   * @return \Drupal\llm_services\Model\Message[]
   *   Array of the messages.
   */
  public function getMessages(): array {
    return $this->messages;
  }

  /**
   * Add a message to the message array.
   *
   * @param Message $message
   *   The message to add
   */
  public function addMessage(Message $message): self {
    $this->messages[] = $message;

    return $this;
  }

  /**
   * Retrieves the options currently set.
   *
   * @return array
   *   The options array.
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * Adds an option to the options array.
   *
   * Not that existing options will be overriden if the option name is the same.
   *
   * @param string $name
   *   The name of the option.
   * @param mixed $value
   *   The value of the option.
   *
   * @return self
   *   The updated instance of the class.
   */
  public function addOption(string $name, mixed $value): self {
    $this->options[$name] = $value;

    return $this;
  }
}
