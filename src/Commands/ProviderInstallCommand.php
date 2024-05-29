<?php

declare(strict_types=1);

namespace Drupal\llm_services\Commands;

use Drupal\llm_services\Plugin\LLModelProviderManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Install model in provider (if supported).
 */
class ProviderInstallCommand extends Command {

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
      ->setName('llm:provider:install')
      ->setDescription('Install model in provider')
      ->addUsage('llm:provider:install ollama llama3')
      ->addArgument(
        name: 'provider',
        mode: InputArgument::REQUIRED,
        description: 'Name of the provider (plugin).'
      )
      ->addArgument(
        name: 'name',
        mode: InputArgument::REQUIRED,
        description: 'Name of the model to use.'
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
    $name = $input->getArgument('name');

    $provider = $this->providerManager->createInstance($providerName);

    foreach ($provider->installModel($name) as $progress) {
      if (isset($progress['total']) && isset($progress['completed'])) {
        $percent = ($progress['completed'] / $progress['total']) * 100;
        $output->writeln(sprintf('%s (%0.2f%% downloaded)', $progress['status'], $percent));
      }
      else {
        $output->writeln($progress['status']);
      }
    }
    $output->write("\n");

    return Command::SUCCESS;
  }

}
