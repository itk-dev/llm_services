<?php

namespace Drupal\llm_services\Model;

/**
 * Interface used for completion responses from models.
 */
interface CompletionResponseInterface {

  /**
   * Get the name of the model used.
   *
   * @return string
   *   The name of the module.
   */
  public function getModel(): string;

  /**
   * The response from the module.
   *
   * @return string
   *   The text generated by the modul.
   */
  public function getResponse(): string;

  /**
   * The completion status.
   *
   * @return bool
   *   If false, the model has more to say.
   */
  public function getStatus(): bool;

  /**
   * The context generated, which can be used in the next completion.
   *
   * This can be seen at chat history in completion requests to make the model
   * more context aware.
   *
   * @return mixed
   *   Context from the completion.
   */
  public function getContext(): mixed;

}
