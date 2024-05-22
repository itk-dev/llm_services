<?php

namespace Drupal\llm_services\Model;

/**
 * Represents a chat message.
 *
 * @see https://github.com/ollama/ollama/blob/main/docs/api.md#parameters-1
 */
class ChatMessage {

  /**
   * The role of this message.
   *
   * @var \Drupal\llm_services\Model\MessageRoles
   */
  public MessageRoles $role;

  /**
   * The message content.
   *
   * @var string
   */
  public string $content;

  /**
   * Images base64 encoded.
   *
   * Used for multimodal models such as llava. Which can describe the content of
   * the image.
   *
   * @var array<string>
   */
  public array $images;

}
