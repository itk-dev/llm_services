<?php

declare(strict_types=1);

namespace Drupal\llm_services\Commands;

use Drupal\llm_services\Model\Message;
use Drupal\llm_services\Model\MessageRoles;
use Drupal\llm_services\Model\Payload;
use Drupal\llm_services\Plugin\LLModelProviderManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Chat with a model through a provider.
 */
class ModelChatCommand extends Command {

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
      ->setName('llm:model:chat')
      ->setDescription('Chat with model (use ctrl+c to stop chatting)')
      ->addUsage('llm:model:chat ollama llama3')
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
      ->addOption(
        name: 'system-prompt',
        mode: InputOption::VALUE_REQUIRED,
        description: 'System message to instruct the llm have to behave.',
        default: 'Use the following pieces of context to answer the users question. If you don\'t know the answer, just say that you don\'t know, don\'t try to make up an answer.'
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
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $providerName = $input->getArgument('provider');
    $name = $input->getArgument('name');

    $systemPrompt = $input->getOption('system-prompt');
    $temperature = $input->getOption('temperature');
    $topK = $input->getOption('top-k');
    $topP = $input->getOption('top-p');

    $provider = $this->providerManager->createInstance($providerName);

    // Build configuration.
    $payLoad = new Payload();
    $payLoad->model = $name;
    $payLoad->options = [
      'temperature' => $temperature,
      'top_k' => $topK,
      'top_p' => $topP,
    ];
    $msg = new Message();
    $msg->role = MessageRoles::System;
    $msg->content = $systemPrompt;
    $payLoad->messages[] = $msg;

    $helper = $this->getHelper('question');
    $question = new Question('Message: ', '');

    // Keep cheating with the user. Not optimal, but okay for now.
    while (TRUE) {
      // Query the next question.
      $output->write("\n");
      $msg = new Message();
      $msg->role = MessageRoles::User;
      $msg->content = $helper->ask($input, $output, $question);
      $payLoad->messages[] = $msg;
      $output->write("\n");

      $answer = '';
      foreach ($provider->chat($payLoad) as $res) {
        $output->write($res->getContent());
        $answer .= $res->getContent();
      }
      $output->write("\n");

      // Add answer as context to the next question.
      $msg = new Message();
      $msg->role = MessageRoles::Assistant;
      $msg->content = $answer;
      $payLoad->messages[] = $msg;
    }
  }

}
