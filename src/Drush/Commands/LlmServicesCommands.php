<?php

namespace Drupal\llm_services\Drush\Commands;

use Drupal\llm_services\Plugin\LLModelProviderManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush commandfile.
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
   * Command description here.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  #[CLI\Command(name: 'llm:list:models', aliases: ['llm-list'])]
  #[CLI\Argument(name: 'provider', description: 'Name of the provider (plugin).')]
  #[CLI\Usage(name: 'llm:list:models foo', description: 'List moduls available ')]
  public function commandName(string $provider): void {
    $provider = $this->providerManager->createInstance('ollama');
    $models = $provider->listModels();

    // @todo output more information.
    foreach ($models as $model) {
      $this->writeln($model['name'] . ' (' . $model['modified'] . ')');
    }
  }

}
