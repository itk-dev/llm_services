<?php

namespace Drupal\llm_services\Exceptions;

/**
 * Base exception that all other exceptions should extend.
 *
 * This will enable other modules to use this exception as an catch all.
 */
class LLMException extends \Exception {

}
