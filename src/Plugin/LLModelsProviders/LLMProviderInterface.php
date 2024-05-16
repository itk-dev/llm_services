<?php

namespace Drupal\llm_services\Plugin\LLModelsProviders;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * LLModelsProviders plugin interface that all plugins are required to implement.
 */
interface LLMProviderInterface extends PluginInspectionInterface {

  /**
   * List model supported by the provider.
   *
   * @return array<string, string>
   *   List of supported language models.
   */
  public function listModels(): array;

}
