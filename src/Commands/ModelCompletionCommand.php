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
 * Make a completion request against a provider model.
 */
class ModelCompletionCommand extends Command {

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
      ->setName('llm:model:completion')
      ->setDescription('Make a completion request to a model')
      ->addUsage('llm:model:completion ollama llama3 "Why is the sky blue?')
      ->addArgument(
        name: 'provider',
        mode: InputArgument::REQUIRED,
        description: 'Name of the provider (plugin).'
      )
      ->addArgument(
        name: 'name',
        mode: InputArgument::REQUIRED,
        description: 'Name of the model to use.'
      )
      ->addArgument(
        name: 'prompt',
        mode: InputArgument::REQUIRED,
        description: 'The prompt to generate a response for.'
      )
      ->addOption(
        name: 'temperature',
        mode: InputOption::VALUE_REQUIRED,
        description: 'The temperature of the model. Increasing the temperature will make the model answer more creatively.',
        default: '0.8'
      )
      ->addOption(
        name: 'top-k',
        mode: InputOption::VALUE_REQUIRED,
        description: 'Reduces the probability of generating nonsense. A higher value (e.g. 100) will give more diverse answers.',
        default: '40'
      )
      ->addOption(
        name: 'top-p',
        mode: InputOption::VALUE_REQUIRED,
        description: 'A higher value (e.g., 0.95) will lead to more diverse text, while a lower value (e.g., 0.5) will generate more focused and conservative text.',
        default: '0.9'
      );
  }

  /**
   * {@inheritDoc}
   *
   * @throws \Drupal\llm_services\Exceptions\CommunicationException
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $providerName = $input->getArgument('provider');
    $name = $input->getArgument('name');
    $prompt = $input->getArgument('prompt');
    $temperature = $input->getOption('temperature');
    $topK = $input->getOption('top-k');
    $topP = $input->getOption('top-p');

    if (!is_numeric($temperature) || !is_numeric($topK) || !is_numeric($topP)) {
      $output->writeln('Invalid input. Temperature, top-k, and top-p must be numeric values.');

      return Command::FAILURE;
    }

    // Build configuration.
    $provider = $this->providerManager->createInstance($providerName);
    $payLoad = new Payload();
    $payLoad->setModel($name)
      ->addOption('temperature', $temperature)
      ->addOption('top_k', $topK)
      ->addOption('top_p', $topP);

    // Create a completion message.
    $msg = new Message();
    $msg->content = $prompt;
    $payLoad->addMessage($msg);

    foreach ($provider->completion($payLoad) as $res) {
      $output->write($res->getResponse());
    }
    $output->write("\n");

    return Command::SUCCESS;
  }

}
