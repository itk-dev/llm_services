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
    $plugins = $this->providerManager->getDefinitions();
    ksort($plugins);
    $options = array_map(function ($plugin) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $title */
      $title = $plugin['title'];
      return $title->render();
    }, $plugins);

    $form['provider'] = [
      '#type' => 'item',
      '#title' => $this->t('Providers'),
      '#description' => $this->t('This is the list of provider plugins detected. Use the local menu tasks to configure the integrations.'),
      '#markup' => '<ul><li>' . implode('</li><li>', $options) . '</li></ul>',
    ];

    return $form;
  }

}
