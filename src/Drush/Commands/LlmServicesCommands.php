<?php

namespace Drupal\llm_services\Drush\Commands;

use Drupal\llm_services\Model\Message;
use Drupal\llm_services\Model\Payload;
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

    // @todo Output more information.
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

    // @todo Stream responses.
    foreach ($provider->installModel($name) as $progress) {
      if (isset($progress['total']) && isset($progress['completed'])) {
        $percent = ($progress['completed'] / $progress['total']) * 100;
        $this->output()->writeln(sprintf('%s (%0.2f%% downloaded)', $progress['status'], $percent));
      }
      else {
        $this->output()->writeln($progress['status']);
      }
    }
    $this->output()->write("\n");
  }

  /**
   * Try out completion with a model.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\llm_services\Exceptions\CommunicationException
   */
  #[CLI\Command(name: 'llm:model:completion', aliases: ['llm-completion'])]
  #[CLI\Argument(name: 'provider', description: 'Name of the provider (plugin).')]
  #[CLI\Argument(name: 'name', description: 'Name of the model to use.')]
  #[CLI\Argument(name: 'prompt', description: 'The prompt to generate a response for.')]
  #[CLI\Usage(name: 'llm:model:completion ollama llama2 "Why is the sky blue?"', description: 'Prompt LLama2')]
  public function completion(string $provider, string $name, string $prompt): void {
    $provider = $this->providerManager->createInstance($provider);

    $payLoad = new Payload();
    $payLoad->model = $name;

    $msg = new Message();
    $msg->content = $prompt;
    $payLoad->messages[] = $msg;

    foreach ($provider->completion($payLoad) as $res) {
      $this->output()->write($res->getResponse());
    }
    $this->output()->write("\n");
  }

}
