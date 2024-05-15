<?php

namespace Drupal\llm_services\Plugin\LLModels;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Ollama integration provider.
 */
class Ollama extends PluginBase implements LLMProviderInterface, PluginFormInterface, ConfigurableInterface {

  /**
   * {@inheritdoc}
   */
  public function listModels(): array {
    // TODO: Implement listModels() method.
    return [];
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
