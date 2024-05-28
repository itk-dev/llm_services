<?php

declare(strict_types=1);

namespace Drupal\llm_services\Commands;

use Drupal\llm_services\Model\Message;
use Drupal\llm_services\Model\Payload;
use Drupal\llm_services\Plugin\LLModelProviderManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List models from a given provider.
 */
class ProviderListCommand extends Command {

  /**
   * Default constructor.
   *
   * @param \Drupal\llm_services\Plugin\LLModelProviderManager $providerManager
   *   The provider manager.
   */
  public function __construct(
    private readonly LLModelProviderManager $providerManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritDoc}
   */
  protected function configure(): void {
    $this
      ->setName('llm:provider:list')
      ->setDescription('Install model in provider')
      ->addUsage('llm:install:model ollama llama2')
      ->addArgument(
        name: 'provider',
        mode: InputArgument::REQUIRED,
        description: 'Name of the provider (plugin).'
      );
  }

  /**
   * {@inheritDoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\llm_services\Exceptions\CommunicationException
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $providerName = $input->getArgument('provider');

    $provider = $this->providerManager->createInstance($providerName);
    $models = $provider->listModels();

    foreach ($models as $model) {
      $output->writeln($model['name'] . ' (' . $model['modified'] . ')');
    }

    return Command::SUCCESS;
  }

}
