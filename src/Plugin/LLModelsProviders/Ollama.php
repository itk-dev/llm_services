<?php

namespace Drupal\llm_services\Plugin\LLModelsProviders;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\llm_services\Client\Ollama as ClientOllama;
use Drupal\llm_services\Exceptions\CommunicationException;
use Drupal\llm_services\Exceptions\NotSupportedException;
use Drupal\llm_services\Model\Payload;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Ollama integration provider.
 *
 * @LLModelsProvider(
 *   id = "ollama",
 *   title = @Translation("Ollama"),
 *   description = @Translation("Ollama hosted models.")
 * )
 */
class Ollama extends PluginBase implements LLMProviderInterface, PluginFormInterface, ConfigurableInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function listModels(): array {
    try {
      return $this->getClient()->listLocalModels();
    }
    catch (GuzzleException | \JsonException $exception) {
      throw new CommunicationException(
        message: 'Error in communicating with LLM services',
        previous: $exception,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function installModel(string $modelName): mixed {
    try {
      return $this->getClient()->install($modelName);
    }
    catch (GuzzleException $exception) {
      throw new CommunicationException(
        message: 'Error in communicating with LLM services',
        previous: $exception,
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * @see https://github.com/ollama/ollama/blob/main/docs/api.md#generate-a-completion
   */
  public function completion(Payload $payload): \Generator {
    foreach ($this->getClient()->completion($payload) as $chunk) {
      yield $chunk;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @see https://github.com/ollama/ollama/blob/main/docs/api.md#generate-a-chat-completion
   */
  public function chat(Payload $payload): mixed {
    // TODO: Implement chatCompletions() method.
    throw new NotSupportedException();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): static {
    $this->configuration = $configuration + $this->defaultConfiguration();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'url' => 'http://ollama',
      'port' => '11434',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The URL to connect to the Ollama API.'),
      '#default_value' => $this->configuration['url'],
    ];

    $form['port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The port that Ollama runs on.'),
      '#default_value' => $this->configuration['port'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (filter_var($values['url'], FILTER_VALIDATE_URL) === FALSE) {
      $form_state->setErrorByName('url', $this->t('Invalid URL.'));
    }

    $filter_options = [
      'options' => [
        'min_range' => 1,
        'max_range' => 65535,
      ],
    ];
    if (filter_var($values['port'], FILTER_VALIDATE_INT, $filter_options) === FALSE) {
      $form_state->setErrorByName('port', $this->t('Invalid port range. Should be between 1 and 65535.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValues();
      $configuration = [
        'url' => $values['url'],
        'port' => $values['port'],
      ];
      $this->setConfiguration($configuration);
    }

    // Try to connect to Ollama to test the connection.
    try {
      $this->listModels();
      \Drupal::messenger()->addMessage('Successfully connected to Ollama');
    } catch (\Exception $exception) {
      \Drupal::messenger()->addMessage('Error communication with Ollama: ' . $exception->getMessage(), 'error');
    }
  }

  /**
   * Get a client.
   *
   * @return \Drupal\llm_services\Client\Ollama
   *   Client to communicate with Ollama
   */
  public function getClient(): ClientOllama {
    return new ClientOllama($this->configuration['url'], $this->configuration['port']);
  }

}
