<?php

namespace Drupal\dgi_migrate\Plugin\migrate\process\Xml;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\dgi_migrate\Plugin\migrate\process\MissingBehaviorTrait;

/**
 * Query a DOMXPath using a DOMNode context as the source.
 *
 * @MigrateProcessPlugin(
 *   id = "dgi_migrate.process.xml.context_query"
 * )
 *
 * @code
 * sub_context_values:
 *   plugin: dgi_migrate.process.xml.context_query
 *   source: '@some_dom_node'
 *   xpath: '@some_dom_xpath'
 *   query: '/my/query'
 * @endcode
 */
class ContextQuery extends ProcessPluginBase {

  use MissingBehaviorTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->missingBehaviorInit();

    assert(!empty($this->configuration['xpath']));
    assert(!empty($this->configuration['query']));
    $this->xpath = $this->configuration['xpath'];
    $this->query = $this->configuration['query'];
    assert(!isset($this->configuration['method']) || in_array($this->configuration['method'], [
      'query',
      'evaluate',
    ]));
    $this->method = $this->configuration['method'] ?? 'query';
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    $xpath = $row->get($this->xpath);
    if (!($xpath instanceof \DOMXPath)) {
      throw $this->getMissingException(strtr('Requires an ":xpath" parameter that is an instance of :domxpath for :property.', [
        ':xpath' => 'xpath',
        ':domxpath' => 'DOMXPath',
        ':property' => $destination_property,
      ]));
    }
    if (!($value instanceof \DOMNode)) {
      throw $this->getMissingException(strtr('Input should be a DOMNode for :property.', [
        ':property' => $destination_property,
      ]));
    }

    return call_user_func([$xpath, $this->method], $this->query, $value);
  }

}
