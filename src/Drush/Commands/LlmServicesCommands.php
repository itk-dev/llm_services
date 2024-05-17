<?php

namespace Drupal\llm_services\Drush\Commands;

use Drupal\llm_services\Plugin\LLModelProviderManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands to talk with LLM provider (mostly for testing).
 */
final class LlmServicesCommands extends DrushCommands {

  /**
   * Constructs a LlmServicesCommands object.
   */
  public function __construct(
    private readonly LLModelProviderManager $providerManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.llm_services'),
    );
  }

  /**
   * List models from provider.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\llm_services\Exceptions\CommunicationException
   */
  #[CLI\Command(name: 'llm:list:models', aliases: ['llm-list'])]
  #[CLI\Argument(name: 'provider', description: 'Name of the provider (plugin).')]
  #[CLI\Usage(name: 'llm:list:models ollama', description: 'List moduls available ')]
  public function listModels(string $provider): void {
    $provider = $this->providerManager->createInstance($provider);
    $models = $provider->listModels();

    // @todo output more information.
    foreach ($models as $model) {
      $this->writeln($model['name'] . ' (' . $model['modified'] . ')');
    }
  }

  /**
   * Install model in provider.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\llm_services\Exceptions\CommunicationException
   */
  #[CLI\Command(name: 'llm:install:model', aliases: ['llm-install'])]
  #[CLI\Argument(name: 'provider', description: 'Name of the provider (plugin).')]
  #[CLI\Argument(name: 'name', description: 'Name of the model to try and download.')]
  #[CLI\Usage(name: 'llm:install:model ollama llama2', description: 'Install LLama2 modul in Ollama')]
  public function installModel(string $provider, string $name): void {
    $provider = $this->providerManager->createInstance($provider);
    $models = $provider->installModel($name);


  }

}
