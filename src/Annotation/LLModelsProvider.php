<?php

namespace Drupal\llm_services\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a LLModelsProvider annotation object.
 *
 * @see plugin_api
 *
 * @Annotation
 */
class LLModelsProvider extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The human-readable name of the LLM provider.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public Translation $title;

  /**
   * A brief description of the provider.
   *
   * @var \Drupal\Core\Annotation\Translation|string
   *
   * @ingroup plugin_translatable
   */
  public Translation|string $description = '';

}
