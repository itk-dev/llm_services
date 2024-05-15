<?php

namespace Drupal\llm_services\Plugin\LLModels;

/**
 * LLModels plugin interface that all plugins are required to implement.
 */
interface LLMProviderInterface {

  /**
   * List model supported by the provider.
   *
   * @return array<string, string>
   *   List of supported language models.
   */
  public function listModels(): array;

}
