<?php

namespace Drupal\llm_services\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the LLM plugin manager.
 *
 * @see \Drupal\llm_services\Annotation\LLModelsProvider
 * @see \Drupal\llm_services\Plugin\LLModels\LLModelsInterface
 * @see plugin_api
 */
class LLModelManager extends DefaultPluginManager
{
  /**
   * Constructor for LLModelManager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/LLModels',
      $namespaces,
      $module_handler,
      'Drupal\llm_services\Annotation\LLModelsProvider',
      'Drupal\llm_services\Plugin\LLModels\LLModelsInterface',
    );

    $this->alterInfo('llm_models_info');
    $this->setCacheBackend($cache_backend, 'llmodels_plugins');
  }

}
