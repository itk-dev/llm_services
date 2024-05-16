<?php

namespace Drupal\llm_services\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the LLM plugin manager.
 *
 * @see \Drupal\llm_services\Annotation\LLModelsProvider
 * @see \Drupal\llm_services\Plugin\LLModelsProviders\LLMProviderInterface
 * @see plugin_api
 */
class LLModelProviderManager extends DefaultPluginManager {

  /**
   * Constructor for LLModelProviderManager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/LLModelsProviders',
      $namespaces,
      $module_handler,
      'Drupal\llm_services\Plugin\LLModelsProviders\LLMProviderInterface',
      'Drupal\llm_services\Annotation\LLModelsProvider',
    );

    $this->alterInfo('llm_services_providers_info');
    $this->setCacheBackend($cache_backend, 'llm_services_providers_plugins');
  }

}
