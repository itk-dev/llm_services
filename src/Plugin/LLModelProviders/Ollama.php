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
        completed: $chunk['done'],
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
      'auth' => [
        'username' => '',
        'password' => '',
      ],
      'timeouts' => [
        'connect' => 10,
        'wait' => 300,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#description' => $this->t('The http(s) URL to connect to the Ollama API.'),
      '#default_value' => $this->configuration['url'],
    ];

    $form['port'] = [
      '#type' => 'number',
      '#title' => $this->t('Port'),
      '#description' => $this->t('The port that Ollama runs on'),
      '#default_value' => $this->configuration['port'],
    ];

    $form['auth'] = [
      '#type' => 'details',
      '#title' => $this->t('Basic auth'),
      '#description' => $this->t('Basic authentication (if Ollama is placed behind proxy)'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#tree' => TRUE,
    ];

    $form['auth']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username.'),
      '#default_value' => $this->configuration['auth']['username'],
    ];

    $form['auth']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('password'),
      '#default_value' => $this->configuration['auth']['password'],
    ];

    $form['timeouts'] = [
      '#type' => 'details',
      '#title' => $this->t('Timeouts'),
      '#description' => $this->t('Timeouts when communicate with the API.'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#tree' => TRUE,
    ];

    $form['timeouts']['connect'] = [
      '#type' => 'number',
      '#title' => $this->t('Connection timeout'),
      '#description' => $this->t('Seconds to wait for connection with the LLM.'),
      '#default_value' => $this->configuration['timeouts']['connect'],
    ];

    $form['timeouts']['wait'] = [
      '#type' => 'number',
      '#title' => $this->t('Wait timeout'),
      '#description' => $this->t('Seconds to wait for response from LLM.'),
      '#default_value' => $this->configuration['timeouts']['wait'],
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

    if ($values['auth']['username'] && !$values['auth']['password']) {
      $form_state->setErrorByName('auth][password', $this->t('Password is required when username is provided.'));
    }
    if (!$values['auth']['username'] && $values['auth']['password']) {
      $form_state->setErrorByName('auth][username', $this->t('Username is required when password is provided.'));
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
        'auth' => [
          'username' => $values['auth']['username'],
          'password' => $values['auth']['password'],
        ],
        'timeouts' => [
          'connect' => $values['timeouts']['connect'],
          'wait' => $values['timeouts']['wait'],
        ],
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
    return new ClientOllama(
      url: $this->configuration['url'],
      port: $this->configuration['port'],
      client:  \Drupal::httpClient(),
      username: $this->configuration['auth']['username'],
      password: $this->configuration['auth']['password'],
    );
  }

}
