<?php

namespace Drupal\llm_services\Plugin\LLModelsProviders;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * LLModelsProviders plugin interface.
 */
interface LLMProviderInterface extends PluginInspectionInterface {

  /**
   * List model supported by the provider.
   *
   * @return array<string, array<string>,<string>>
   *   List of supported language models.
   */
  public function listModels(): array;

  /**
   * Installs a model.
   *
   * @param string $modelName
   *   The name of the model to install.
   *
   * @return mixed
   *   The result of installing the model.
   */
  public function installModel(string $modelName): mixed;

  /**
   * Performs a completion process.
   *
   * @param array $body
   *   The body of the completion request. It should contain the necessary data
   *   for completion.
   *
   * @return mixed
   *   The result of the completion process.
   */
  public function completion(array $body): mixed;

  /**
   * Initiates a chat.
   *
   * @param array $body
   *   The body of the chat request.
   *
   * @return mixed
   *   The result of the chat initiation.
   */
  public function chat(array $body): mixed;

}
