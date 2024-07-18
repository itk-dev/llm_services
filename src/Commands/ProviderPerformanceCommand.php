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
use Symfony\Component\Console\Input\InputOption;
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
      )->addOption(
        name: 'min-words',
        mode: InputArgument::OPTIONAL,
        description: 'Minimal number of words to ask LLM for.',
        default: 20
      )->addOption(
        name: 'max-words',
        mode: InputArgument::OPTIONAL,
        description: 'Maximum number of words to ask LLM for.',
        default: 100
      )->addOption(
        name: 'csv',
        mode: InputOption::VALUE_NONE,
        description: 'Generate a CSV file with stats.',
      )->addOption(
        name: 'temperature',
        mode: InputOption::VALUE_REQUIRED,
        description: 'The temperature of the model. Increasing the temperature will make the model answer more creatively.',
        default: 0.2
      )->addOption(
        name: 'top-k',
        mode: InputOption::VALUE_REQUIRED,
        description: 'Reduces the probability of generating nonsense. A higher value (e.g. 100) will give more diverse answers.',
        default: 40
      )->addOption(
        name: 'top-p',
        mode: InputOption::VALUE_REQUIRED,
        description: 'A higher value (e.g., 0.95) will lead to more diverse text, while a lower value (e.g., 0.5) will generate more focused and conservative text.',
        default: 0.5
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

    $csvFile = $input->getOption('csv');
    $minWords = $input->getOption('min-words');
    $maxWords = $input->getOption('max-words');
    $temperature = (float) $input->getOption('temperature');
    $topK = (int) $input->getOption('top-k');
    $topP = (float) $input->getOption('top-p');

    // Load questions from json data file.
    $path = $this->extensionList->getPath('llm_services');
    $jsonString = file_get_contents($path . '/assert/questions.json');
    $questions = json_decode($jsonString, TRUE);

    // Check if CVS output is selected.
    if ($csvFile) {
      $csvFile = fopen($path . '/output.csv', 'a');
      fputcsv($csvFile, ['Tokens', 'Total seconds', 'Throughput (tokens/s)', 'Time to first token']);
    }

    // Get provider plugin.
    $provider = $this->providerManager->createInstance($providerName);

    // Build configuration.
    $payLoad = new Payload();
    $payLoad->setModel($name)
      ->addOption('temperature', $temperature)
      ->addOption('top_k', $topK)
      ->addOption('top_p', $topP);
    $msg = new Message();
    $msg->role = MessageRoles::System;
    $msg->content = 'You are a helpfully assistance that will answerer questions in ' . $minWords . ' to ' . $maxWords . ' words and if you do not known the answerer say: "I do not known anything about that"';
    $payLoad->addMessage($msg);

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

        // Write stats to the output file.
        if ($csvFile) {
          fputcsv($csvFile, [
            $tokens, $timers['end'] - $timers['start'],
            $tokens / ($timers['end'] - $timers['start']),
            $timers['first'] - $timers['start'],
          ]);
        }

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

    // Close the output file if it exists.
    if ($csvFile) {
      fclose($csvFile);
    }

    return Command::SUCCESS;
  }

}
