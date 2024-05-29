# Large language model services

This Drupal module is an integration to LLM APIs. It tries to provide a new plugin type to make it possible to "talk"
with models. This module comes with integration with Ollama and a client for Ollama as an example for the usage of this
module.

The module has different Drush commands to test the different parts of a giver provider plugin and also has a "not
supported" exception, which can be used for the parts that a given provider does not support (e.g., model installation).

## Installation

Require the module in Drupal and enable the module, each provider will then be available at
[http://launchpad.local.itkdev.dk/en/admin/config/llm_services/settings](http://launchpad.local.itkdev.dk/en/admin/config/llm_services/settings)
as local menu tabs for configuration of the provider.

```shell
composer require itkdev/llm_services
```
