<?php

declare(strict_types=1);

namespace Drupal\llm_services\Commands;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\llm_services\Model\Message;
use Drupal\llm_services\Model\MessageRoles;
use Drupal\llm_services\Model\Payload;
use Drupal\llm_services\Plugin\LLModelProviderManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List models from a given provider.
 */
class ProviderPerformanceCommand extends Command {

  /**
   * Default constructor.
   *
   * @param \Drupal\llm_services\Plugin\LLModelProviderManager $providerManager
   *   The provider manager.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extensionList
   *   Modules list service used to find the path to this module.
   */
  public function __construct(
    private readonly LLModelProviderManager $providerManager,
    private readonly ModuleExtensionList $extensionList,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritDoc}
   */
  protected function configure(): void {
    $this
      ->setName('llm:provider:pref')
      ->setDescription('Performances test of provider (spawn multi-threads to send request to the provider)')
      ->addUsage('llm:provider:pref ollama')
      ->addArgument(
        name: 'provider',
        mode: InputArgument::REQUIRED,
        description: 'Name of the provider (plugin).'
      )->addArgument(
        name: 'name',
        mode: InputArgument::REQUIRED,
        description: 'Name of the model to use.'
      )->addArgument(
        name: 'threads',
        mode: InputArgument::REQUIRED,
        description: 'Number of threads (chats to run at the same time).'
      );
  }

  /**
   * {@inheritDoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\llm_services\Exceptions\CommunicationException
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $name = $input->getArgument('name');
    $providerName = $input->getArgument('provider');
    $threads = (int) $input->getArgument('threads');

    $provider = $this->providerManager->createInstance($providerName);

    // Build configuration.
    $payLoad = new Payload();
    $payLoad->setModel($name)
      ->addOption('temperature', 0.8)
      ->addOption('top_k', 40)
      ->addOption('top_p', 0.5);
    $msg = new Message();
    $msg->role = MessageRoles::System;
    $msg->content = 'You are a helpfully assistance that will answerer questions in 10 to 15 words and if you do not known the answerer say: "I do not known anything about that"';
    $payLoad->addMessage($msg);

    // Load questions from json data file.
    $path = $this->extensionList->getPath('llm_services');
    $jsonString = file_get_contents($path . '/assert/questions.json');
    $questions = json_decode($jsonString, TRUE);

    $payLoads = [];
    for ($i = 0; $i < $threads; $i++) {
      $pay = clone $payLoad;
      $msg = new Message();
      $msg->role = MessageRoles::User;
      $msg->content = $questions[array_rand($questions)]['prompt_text'];
      $pay->addMessage($msg);
      $payLoads[] = $pay;
    }

    // Spawn new threads base on the number of payloads and collect basic stats
    // for the LLM's responses. Remember that the threads do not share memory
    // with the parent, so there is no communication back with stats. This could
    // have been archived by using socket, but seams as overkill for this setup.
    $pids = [];
    foreach ($payLoads as $i => $pay) {
      $pids[$i] = pcntl_fork();

      if (!$pids[$i]) {
        $timers = [
          'start' => microtime(TRUE),
        ];
        $tokens = 0;

        foreach ($provider->chat($pay) as $chat) {
          if (!isset($timers['first'])) {
            $timers['first'] = microtime(TRUE);
          }
          if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
            $output->writeln('Chat (' . $i . '):' . $chat->getContent());
          }
          elseif ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->write('.');
          }
          $tokens++;
        }
        $timers['end'] = microtime(TRUE);

        $str = sprintf('Tokens: %d in %.2f seconds (throughput %.2f tokens/s). Time to first token: %.2f',
          $tokens,
          $timers['end'] - $timers['start'],
          $tokens / ($timers['end'] - $timers['start']),
          $timers['first'] - $timers['start']
        );
        $output->writeln('');
        $output->writeln($str);

        return Command::SUCCESS;
      }
    }

    foreach ($pids as $i => $pid) {
      if ($pid) {
        pcntl_waitpid($pid, $status);
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
          $output->writeln("Child $i completed");
        }
      }
    }

    return Command::SUCCESS;
  }

}
