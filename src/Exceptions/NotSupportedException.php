<?php

namespace Drupal\llm_services\Exceptions;

/**
 * Not all plugins will support all types of operations.
 *
 * So this except is used to indicate that a given operations/method call is not
 * supported.
 */
class NotSupportedException extends LLMException {

}
