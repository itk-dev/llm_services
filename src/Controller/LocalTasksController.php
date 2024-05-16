<?php

namespace Drupal\llm_services\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to handle local taks tabs callbacks.
 */
class LocalTasksController extends ControllerBase {

  /**
   * Default constructor.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   Form builder object.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   CConfiguration factory.
   */
  public function __construct(
    FormBuilderInterface $formBuilder,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->formBuilder = $formBuilder;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): LocalTasksController|static {
    return new static(
      $container->get('form_builder'),
      $container->get('config.factory'),
    );
  }

  /**
   * Get dynamic tasks.
   *
   * @param string|null $type
   *   The type of form to retrieve. Default to NULL.
   *
   * @return array<string, mixed>
   *   An array containing the form definition.
   */
  public function dynamicTasks(string $type = NULL): array {
    if (empty($type)) {
      return $this->formBuilder->getForm('\Drupal\llm_services\Form\SettingsForm');
    }

    return $this->formBuilder->getForm('\Drupal\llm_services\Form\PluginSettingsForm', $type);
  }

}
