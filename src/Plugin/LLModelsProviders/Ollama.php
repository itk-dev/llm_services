<?php

namespace Drupal\llm_services\Plugin\LLModelsProviders;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\llm_services\Client\Ollama as ClientOllama;
use Drupal\llm_services\Exceptions\NotSupportedException;

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
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function listModels(): array {
    $config = $this->getConfiguration();

    $client = new ClientOllama($config['url'], $config['port']);
    return $client->listLocalModels();
  }

  /**
   * {@inheritdoc}
   */
  public function installModel(): mixed {
    // TODO: Implement installModel() method.
    throw new NotSupportedException();
  }

  /**
   * {@inheritdoc}
   *
   * @see https://github.com/ollama/ollama/blob/main/docs/api.md#generate-a-completion
   */
  public function completion(array $body): mixed {
    // TODO: Implement completions() method.
    throw new NotSupportedException();
  }

  /**
   * {@inheritdoc}
   *
   * @see https://github.com/ollama/ollama/blob/main/docs/api.md#generate-a-chat-completion
   */
  public function chat(array $body): mixed {
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
      'port' => '11434'
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
    // TODO: Implement validateConfigurationForm() method.
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
  }

}
