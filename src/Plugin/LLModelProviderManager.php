<?php

namespace Drupal\llm_services\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\llm_services\Form\PluginSettingsForm;
use Drupal\llm_services\Plugin\LLModelProviders\LLMProviderInterface;

/**
 * Provides the LLM plugin manager.
 *
 * @see \Drupal\llm_services\Annotation\LLModelProvider
 * @see \Drupal\llm_services\Plugin\LLModelProviders\LLMProviderInterface
 * @see plugin_api
 */
class LLModelProviderManager extends DefaultPluginManager {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * Constructor for LLModelProviderManager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;

    parent::__construct(
      'Plugin/LLModelProviders',
      $namespaces,
      $module_handler,
      'Drupal\llm_services\Plugin\LLModelProviders\LLMProviderInterface',
      'Drupal\llm_services\Annotation\LLModelProvider',
    );

    $this->alterInfo('llm_services_providers_info');
    $this->setCacheBackend($cache_backend, 'llm_services_providers_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []): LLMProviderInterface {
    if (empty($configuration)) {
      $configuration = $this->configFactory->get(PluginSettingsForm::getConfigName())->get($plugin_id);
      if ($configuration === NULL) {
        // Fallback to default configuration for the provider.
        $configuration = [];
      }
    }

    /** @var \Drupal\llm_services\Plugin\LLModelProviders\LLMProviderInterface $provider */
    $provider = parent::createInstance($plugin_id, $configuration);

    return $provider;
  }

}
