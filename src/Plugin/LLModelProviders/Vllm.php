<?php

namespace Drupal\llm_services\Plugin\LLModelProviders;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\llm_services\Client\Vllm as ClientVllm;
use Drupal\llm_services\Client\VllmChatResponse;
use Drupal\llm_services\Client\VllmCompletionResponse;
use Drupal\llm_services\Model\MessageRoles;
use Drupal\llm_services\Model\Payload;

/**
 * VLLM integration provider.
 *
 * @LLModelProvider(
 *   id = "vllm",
 *   title = @Translation("VLLM"),
 *   description = @Translation("Vllm hosted models.")
 * )
 */
class Vllm extends PluginBase implements LLMProviderInterface, PluginFormInterface, ConfigurableInterface {

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
   */
  public function completion(Payload $payload): \Generator {
    $this->injectFineTuneOptionsToPayload($payload);
    foreach ($this->getClient()->completion($payload) as $chunk) {
      yield new VllmCompletionResponse(
        model: $chunk['model'],
        response: $chunk['choices'][0]['text'] ?? '',
        done: $chunk['done'] ?? FALSE,
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \JsonException
   */
  public function chat(Payload $payload): \Generator {
    $this->injectFineTuneOptionsToPayload($payload);
    foreach ($this->getClient()->chat($payload) as $chunk) {
      if (isset($chunk['choices'][0]['delta']['content'])) {
        yield new VllmChatResponse(
          model: $chunk['model'],
          content: $chunk['choices'][0]['delta']['content'],
          role: MessageRoles::Assistant,
          completed: $chunk['done'] ?? FALSE,
        );
      }
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
      'url' => 'http://vllm',
      'port' => '8000',
      'auth' => [
        'username' => '',
        'password' => '',
      ],
      'timeouts' => [
        'connect' => 10,
        'wait' => 300,
      ],
      'tune' => [
        'max_tokens' => 512,
        'min_p' => 0.1,
        'repetition_penalty' => 1.1,
        'presence_penalty' => 0.0,
        'frequency_penalty' => 0.0,
        'seed' => -1,
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
      '#description' => $this->t('The http(s) URL to connect to the VLLM API.'),
      '#default_value' => $this->configuration['url'],
    ];

    $form['port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Port'),
      '#description' => $this->t('The port that VLLM runs on'),
      '#default_value' => $this->configuration['port'],
    ];

    $form['auth'] = [
      '#type' => 'details',
      '#title' => $this->t('Basic auth'),
      '#description' => $this->t('Basic authentication (if Vllm is placed behind proxy)'),
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
      '#min' => 1,
    ];

    $form['timeouts']['wait'] = [
      '#type' => 'number',
      '#title' => $this->t('Wait timeout'),
      '#description' => $this->t('Seconds to wait for response from LLM.'),
      '#default_value' => $this->configuration['timeouts']['wait'],
      '#min' => 1,
    ];

    $form['tune'] = [
      '#type' => 'details',
      '#title' => $this->t('Fine-tune'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#tree' => TRUE,
    ];

    $form['tune']['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Max tokens'),
      '#description' => $this->t('Maximum number of tokens to generate per output sequence.'),
      '#default_value' => $this->configuration['tune']['max_tokens'],
      '#min' => 10,
      '#max' => 2048,
    ];

    $form['tune']['min_p'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Minimum probability (min_p)'),
      '#description' => $this->t('Float that represents the minimum probability for a token to be considered, relative to the probability of the most likely token. Must be in [0, 1]. Set to 0 to disable this.'),
      '#default_value' => $this->configuration['tune']['min_p'],
    ];

    $form['tune']['repetition_penalty'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Repetition penalty'),
      '#description' => $this->t('Penalizes new tokens based on whether they appear in the prompt and the generated text so far. Values > 1 encourage the model to use new tokens, while values < 1 encourage the model to repeat tokens.'),
      '#default_value' => $this->configuration['tune']['repetition_penalty'],
    ];

    $form['tune']['presence_penalty'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Presence penalty'),
      '#description' => $this->t('Penalizes new tokens based on whether they appear in the generated text so far. Values > 0 encourage the model to use new tokens, while values < 0 encourage the model to repeat tokens.'),
      '#default_value' => $this->configuration['tune']['presence_penalty'],
    ];

    $form['tune']['frequency_penalty'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Frequency penalty'),
      '#description' => $this->t('Penalizes new tokens based on their frequency in the generated text so far. Values > 0 encourage the model to use new tokens, while values < 0 encourage the model to repeat tokens.'),
      '#default_value' => $this->configuration['tune']['frequency_penalty'],
    ];

    $form['tune']['seed'] = [
      '#type' => 'number',
      '#title' => $this->t('Seed'),
      '#description' => $this->t('Random seed to use for the generation.'),
      '#default_value' => $this->configuration['tune']['seed'],
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
        'tune' => [
          "max_tokens" => (int) $values['tune']['max_tokens'],
          "min_p" => (float) $values['tune']['min_p'],
          "repetition_penalty" => (float) $values['tune']['repetition_penalty'],
          "presence_penalty" => (float) $values['tune']['presence_penalty'],
          "frequency_penalty" => (float) $values['tune']['frequency_penalty'],
          "seed" => (int) $values['tune']['seed'],
        ],
      ];

      $this->setConfiguration($configuration);
    }

    // Try to connect to VLLM to test the connection.
    try {
      $this->listModels();
      $this->messenger()->addMessage('Successfully connected to VLLM');
    }
    catch (\Exception $exception) {
      $this->messenger()->addMessage('Error communication with VLLM: ' . $exception->getMessage(), 'error');
    }
  }

  /**
   * Get a client.
   *
   * @return \Drupal\llm_services\Client\Vllm
   *   Client to communicate with VLLM.
   */
  public function getClient(): ClientVllm {
    return new ClientVllm(
      url: $this->configuration['url'],
      port: $this->configuration['port'],
      client:  \Drupal::httpClient(),
      username: $this->configuration['auth']['username'],
      password: $this->configuration['auth']['password'],
    );
  }

  /**
   * Injects fine-tune options to the payload.
   *
   * @param \Drupal\llm_services\Model\Payload $payload
   *   The payload instance to inject options into.
   */
  private function injectFineTuneOptionsToPayload(Payload $payload): void {
    foreach ($this->configuration['tune'] as $options => $value) {
      $payload->addOption($options, $value);
    }
  }

}
