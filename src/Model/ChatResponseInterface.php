<?php

namespace Drupal\llm_services\Model;

/**
 * Interface used for completion responses from models.
 */
interface ChatResponseInterface {

  /**
   * Get the name of the model used.
   *
   * @return string
   *   The name of the module.
   */
  public function getModel(): string;

  /**
   * The content from the model.
   *
   * @return string
   *   The text generated by the model.
   */
  public function getContent(): string;

  /**
   * The role of this response.
   *
   * Will almost always be 'assistant'.
   *
   * @return \Drupal\llm_services\Model\MessageRoles
   *   The role of the current message.
   */
  public function getRole(): MessageRoles;

  /**
   * The completion status.
   *
   * @return bool
   *   If false, the model has more to say.
   */
  public function isCompleted(): bool;

}
