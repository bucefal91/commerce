<?php

namespace Drupal\commerce_tax\Plugin\Commerce\TaxType;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\RounderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base class for local tax types.
 */
abstract class LocalTaxTypeBase extends TaxTypeBase implements LocalTaxTypeInterface {

  /**
   * The rounder.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * Constructs a new LocalTaxTypeBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The rounder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RounderInterface $rounder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->rounder = $rounder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('commerce_price.rounder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(OrderInterface $order) {
    foreach ($order->getItems() as $order_item) {
      $tax_rates = $this->calculateRates($order_item);
      // Filter out tax rates with no active amounts.
      $tax_rates = array_filter($tax_rates, function ($tax_rate) {
        /** @var \Drupal\commerce_tax\TaxRate $tax_rate */
        return !empty($tax_rate->getAmount());
      });
      if (empty($tax_rates)) {
        continue;
      }

      // @todo Implement tax rate resolving.
      $tax_rate = reset($tax_rates);
      $tax_rate_amount = $tax_rate->getAmount()->getAmount();
      $adjustment_amount = $order_item->getUnitPrice()->multiply($tax_rate_amount);
      if ($this->isDisplayInclusive()) {
        $adjustment_amount = $adjustment_amount->divide(1 + $tax_rate_amount);
      }
      if ($rounding_mode = $this->getRoundingMode()) {
        $adjustment_amount = $this->rounder->round($adjustment_amount, $rounding_mode);
      }

      $order_item->addAdjustment(new Adjustment([
        'type' => 'tax',
        'label' => $this->getGenericLabel(),
        'amount' => $adjustment_amount,
        'source_id' => $this->entityId,
        'included' => $this->isDisplayInclusive(),
      ]));
    }
  }

  /**
   * Calculates tax rates for the given order item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   *
   * @return \Drupal\commerce_tax\TaxRate[]
   *   The tax rates.
   */
  protected function calculateRates(OrderItemInterface $order_item) {
    return [];
  }

  /**
   * Builds the summary of all available tax rates.
   *
   * @return array
   *   The summary form element.
   */
  protected function buildRateSummary() {
    $element = [
      '#type' => 'details',
      '#title' => $this->t('Tax rates'),
      '#markup' => $this->t('The following tax rates are provided:'),
      '#collapsible' => TRUE,
      '#open' => TRUE,
    ];
    $element['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Tax rate'),
        ['data' => $this->t('Amount'), 'colspan' => 2],
      ],
      '#input' => FALSE,
    ];
    foreach ($this->getZones() as $tax_zone) {
      $element['table']['zone-' . $tax_zone->getId()] = [
        '#attributes' => [
          'class' => ['region-title'],
          'no_striping' => TRUE,
        ],
        'label' => [
          '#markup' => $tax_zone->getLabel(),
          '#wrapper_attributes' => ['colspan' => 3],
        ],
      ];
      foreach ($tax_zone->getRates() as $tax_rate) {
        $formatted_amounts = array_map(function ($amount) {
          /** @var \Drupal\commerce_tax\TaxRateAmount $amount */
          return $amount->toString();
        }, $tax_rate->getAmounts());

        $element['table'][] = [
          'tax_rate' => [
            '#markup' => $tax_rate->getLabel(),
          ],
          'amounts' => [
            '#markup' => implode('<br>', $formatted_amounts),
          ],
        ];
      }
    }

    return $element;
  }

}
