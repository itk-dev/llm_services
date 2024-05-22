<?php

namespace Drupal\llm_services\Model;

/**
 * Represents the roles of a given message.
 *
 * The system message tells the chat model how it should behave or what its role
 * is in the conversation. E.g., "You are a friendly assistant here to help."
 *
 * @enum MessageRoles
 *
 * @type string
 */
enum MessageRoles: string {
  case Assistant = 'assistant';
  case System = "system";
  case User = 'user';
}
