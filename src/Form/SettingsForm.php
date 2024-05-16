<?php

namespace Drupal\llm_services\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\llm_services\Plugin\LLModelProviderManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SettingsForm.
 *
 * This is the settings for the module.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    private readonly LLModelProviderManager $providerManager,
  ) {
    parent::__construct($configFactory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.llm_services')
    );
  }

  /**
   * The name of the configuration setting.
   *
   * @var string
   */
  public static string $configName = 'llm_services.settings';

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::$configName];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'llm_services_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::$configName);

    $plugins = $this->providerManager->getDefinitions();
    ksort($plugins);
    $options = array_map(function ($plugin) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $title */
      $title = $plugin['title'];
      return $title->render();
    }, $plugins);

    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Log provider'),
      '#description' => $this->t('Select the logger provider you which to use'),
      '#options' => $options,
      '#default_value' => $config->get('provider'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $this->config(self::$configName)
      ->set('provider', $form_state->getValue('provider'))
      ->save();
  }

}
