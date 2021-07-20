<?php

namespace Drupal\dgi_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Assemble a date or date range using some EDTF standard pieces.
 *
 * At least one of three properties need to be provided:
 * - 'single_date' for an individual date,
 * - 'range_start' for the start of a date range,
 * - 'range_end' for the end of a date range.
 *
 * The output is handled thus:
 * - If a range_start, or a range_end, or both, are provided and not empty, an
 *   EDTF-style date range will be assembled, and any results from single_date
 *   will be ignored.
 * - If neither a range_start nor a range_end are provided or are empty, but the
 *   single_date is provided and has a value, it is returned.
 * - If no provided property has a value, null will be returned.
 *
 * Use the 'get_values' flag to indicate that 'single_date', 'range_start', and
 * 'range_end' should be pulled from destination values in other fields.
 *
 * Use the 'indicate_open' flag to indicate that a missing part of a found range
 * should use the EDTF 'open' range indicator, i.e., "..". Default behaviour is
 * 'false', which will use an empty string for missing parts of the range.
 *
 * N.B. Returned values are NOT validated against the EDTF standard. Use the
 * dgi_migrate_edtf_validator module to validate output.
 *
 * Example unprocessed:
 * @code
 * process:
 *   - plugin: dgi_migrate.process.assemble_date
 *     single_date: 2001-01-01
 *     range_start: 2002-02-02
 *     range_end: 2003-03-03
 *     indicate_open: false
 *   - plugin: dgi_migrate_edtf_validator
 *     intervals: true
 *     strict: true
 * @endcode
 *
 * Example processed:
 * @code
 * process:
 *   - plugin: dgi_migrate.process.assemble_date
 *     get_values: true
 *     parent_row_key: parent_row
 *     parent_value_key: parent_value
 *     single_date:
 *       - plugin: get
 *         source: thing_to_get
 *   - plugin: dgi_migrate_edtf_validator
 *     intervals: true
 *     strict: true
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dgi_migrate.process.assemble_date"
 * )
 */
class AssembleDate extends ProcessPluginBase {

  use MissingBehaviorTrait;

  /**
   * The string to use for a missing part of a range.
   *
   * @var string
   */
  protected $missing;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->missingBehaviorInit();
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($this->configuration['single_date']) && empty($this->configuration['range_start']) && empty($this->configuration['range_end'])) {
      throw $this->getMissingException(strtr('Requires at least one of the three properties, "single_date", "range_start", or "range_end" to be provided for :property.', [
        ':property' => $destination_property,
      ]));
    }

    $this->missing = (isset($this->configuration['indicate_open']) && $this->configuration['indicate_open']) ? '..' : '';
    $return_value = $this->getDateRange($value, $this->configuration['range_start'], $this->configuration['range_end'], $migrate_executable, $row);
    if (!$return_value) {
      $return_value = (!empty($this->configuration['get_values']) && $this->configuration['get_values']) ?
        $row->get($this->configuration['single_date']) :
        $this->configuration['single_date'];
    }
    return $return_value;
  }

  /**
   * Gets a date range for the input from the range_start and range_end.
   *
   * @param mixed $source
   *   The source value of the row.
   * @param mixed $range_start
   *   The value to use as the range start.
   * @param mixed $range_end
   *   The value to use as the range end.
   * @param \Drupal\migrate\MigrateExecutableInterface $executable
   *   A migrate executable.
   * @param \Drupal\migrate\Row $row
   *   The row being processed.
   *
   * @return string|null
   *   A date range string, or NULL if the start and end could not be
   *   found.
   */
  protected function getDateRange($source, $range_start, $range_end, MigrateExecutableInterface $executable, Row $row) {
    if (!$range_start && !$range_end) {
      return NULL;
    }

    if (!empty($this->configuration['get_values']) && $this->configuration['get_values']) {
      $range_start = $row->get($range_start);
      $range_end = $row->get($range_end);
    }
    $range_start = $range_start ?? $this->missing;
    $range_end = $range_end ?? $this->missing;

    if ($range_start === $this->missing && $range_end === $this->missing) {
      return NULL;
    }

    return "{$range_start}/{$range_end}";
  }

}
