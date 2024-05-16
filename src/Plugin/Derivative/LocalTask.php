<?php

namespace Drupal\llm_services\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\llm_services\Plugin\LLModelProviderManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic tabs based on plugins available.
 */
class LocalTask extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(
    private readonly LLModelProviderManager $providerManager
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): LocalTask|static {
    return new static(
      $container->get('plugin.manager.llm_services')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ReflectionException
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $plugins = $this->providerManager->getDefinitions();
    ksort($plugins);

    // Sadly, it seems that it is not possible to just invalidate the
    // deriver/menu cache stuff. To get the local tasks menu links. So instead
    // of clearing all caches on settings save to only show selected plugins, we
    // show em all.
    $options = array_map(function ($plugin) {
      // Only the plugins that provide configuration options.
      $reflector = new \ReflectionClass($plugin['class']);
      if ($reflector->implementsInterface('Drupal\Component\Plugin\ConfigurableInterface')) {
        /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $title */
        $title = $plugin['title'];
        return $title->render();
      }
    }, $plugins);

    foreach (['settings' => 'Settings'] + $options as $plugin => $title) {
      $this->derivatives[$plugin] = $base_plugin_definition;
      $this->derivatives[$plugin]['title'] = $title;
      $this->derivatives[$plugin]['route_parameters'] = ['type' => $plugin];
      if ($plugin === 'settings') {
        $this->derivatives[$plugin]['route_parameters']['type'] = '';
      }
    }

    return $this->derivatives;
  }

}
