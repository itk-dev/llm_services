<?php

namespace Drupal\llm_services\Plugin\LLModelProviders;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\llm_services\Client\Ollama as ClientOllama;
use Drupal\llm_services\Client\OllamaChatResponse;
use Drupal\llm_services\Client\OllamaCompletionResponse;
use Drupal\llm_services\Model\MessageRoles;
use Drupal\llm_services\Model\Payload;

/**
 * Ollama integration provider.
 *
 * @LLModelProvider(
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
   *
   * @return array<string, mixed>
   *   List of models.
   */
  public function listModels(): array {
    return $this->getClient()->listLocalModels();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \JsonException
   */
  public function installModel(string $modelName): \Generator|string {
    return $this->getClient()->install($modelName);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \JsonException
   *
   * @see https://github.com/ollama/ollama/blob/main/docs/api.md#generate-a-completion
   */
  public function completion(Payload $payload): \Generator {
    foreach ($this->getClient()->completion($payload) as $chunk) {
      yield new OllamaCompletionResponse(
        model: $chunk['model'],
        response: $chunk['response'],
        done: $chunk['done'],
        context: $chunk['context'] ?? [],
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \JsonException
   *
   * @see https://github.com/ollama/ollama/blob/main/docs/api.md#generate-a-chat-completion
   */
  public function chat(Payload $payload): \Generator {
    foreach ($this->getClient()->chat($payload) as $chunk) {
      yield new OllamaChatResponse(
        model: $chunk['model'],
        content: $chunk['message']['content'] ?? '',
        role: $chunk['message']['role'] ? MessageRoles::from($chunk['message']['role']) : MessageRoles::Assistant,
        images: $chunk['message']['images'] ?? [],
        done: $chunk['done'],
      );
    }
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
   *
   * @return array<string, string>
   *   Default configuration array.
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
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
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
      $this->messenger()->addMessage('Successfully connected to Ollama');
    }
    catch (\Exception $exception) {
      $this->messenger()->addMessage('Error communication with Ollama: ' . $exception->getMessage(), 'error');
    }
  }

  /**
   * Get a client.
   *
   * @return \Drupal\llm_services\Client\Ollama
   *   Client to communicate with Ollama.
   */
  public function getClient(): ClientOllama {
    return new ClientOllama($this->configuration['url'], $this->configuration['port'], \Drupal::httpClient());
  }

}
