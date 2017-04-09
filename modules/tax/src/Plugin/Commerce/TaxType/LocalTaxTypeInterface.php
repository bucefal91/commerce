<?php

namespace Drupal\commerce_tax\Plugin\Commerce\TaxType;

/**
 * Defines the interface for local tax type plugins.
 *
 * Local tax types store one or more tax zones with their
 * corresponding tax rates.
 */
interface LocalTaxTypeInterface extends TaxTypeInterface {

  // Rounding modes.
  const ROUND_NONE = 0;
  const ROUND_HALF_UP = PHP_ROUND_HALF_UP;
  const ROUND_HALF_DOWN = PHP_ROUND_HALF_DOWN;
  const ROUND_HALF_EVEN = PHP_ROUND_HALF_EVEN;
  const ROUND_HALF_ODD = PHP_ROUND_HALF_ODD;

  /**
   * Gets the tax type rounding mode.
   *
   * @return int
   *   The tax type rounding mode, a ROUND_ constant.
   */
  public function getRoundingMode();

  /**
   * Gets the tax zones.
   *
   * @return \Drupal\commerce_tax\TaxZone[]
   *   The tax zones.
   */
  public function getZones();

}
