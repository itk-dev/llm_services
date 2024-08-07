<?php

namespace Drupal\llm_services\Client;

use Drupal\llm_services\Model\ChatResponseInterface;
use Drupal\llm_services\Model\MessageRoles;

/**
 * This class represents a completion response in the VLLM provider.
 */
readonly class VllmChatResponse implements ChatResponseInterface {

  /**
   * Default constructor.
   *
   * @param string $model
   *   Name of the model.
   * @param string $content
   *   The content of the message from the model.
   * @param \Drupal\llm_services\Model\MessageRoles $role
   *   The role of the message.
   * @param bool $completed
   *   The module completion state.
   */
  public function __construct(
    private string $model,
    private string $content,
    private MessageRoles $role,
    private bool $completed,
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
  public function isCompleted(): bool {
    return $this->completed;
  }

  /**
   * {@inheritdoc}
   */
  public function getContent(): string {
    return $this->content;
  }

  /**
   * {@inheritdoc}
   */
  public function getRole(): MessageRoles {
    return $this->role;
  }

}
