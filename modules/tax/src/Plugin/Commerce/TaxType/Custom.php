<?php

namespace Drupal\commerce_tax\Plugin\Commerce\TaxType;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\commerce_tax\TaxZone;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManager;

/**
 * Provides the Custom tax type.
 *
 * @CommerceTaxType(
 *   id = "custom",
 *   label = "Custom",
 * )
 */
class Custom extends LocalTaxTypeBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'display_label' => 'tax',
      'rounding_mode' => self::ROUND_HALF_UP,
      'rates' => [],
      'territories' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['display_label'] = [
      '#type' => 'select',
      '#title' => t('Display label'),
      '#description' => t('Used to identify the applied tax in order summaries.'),
      '#options' => $this->getDisplayLabels(),
      '#default_value' => $this->configuration['display_label'],
    ];
    $form['rounding_mode'] = [
      '#type' => 'select',
      '#title' => t('Rounding mode'),
      '#description' => t("Used to round an order item's tax amount. Sales taxes will generally not round at all, while VAT style taxes will generally round the half up."),
      '#options' => [
        self::ROUND_NONE => $this->t('Do not round at all'),
        self::ROUND_HALF_UP => $this->t('Round the half up'),
        self::ROUND_HALF_DOWN => $this->t('Round the half down'),
        self::ROUND_HALF_EVEN => $this->t('Round the half to the nearest even number'),
        self::ROUND_HALF_ODD => $this->t('Round the half to the nearest odd number'),
      ],
      '#default_value' => $this->configuration['rounding_mode'],
    ];

    $wrapper_id = Html::getUniqueId('tax-type-ajax-wrapper');
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';
    // Ajax callbacks need rates and territories to be in form state.
    if (!$form_state->get('tax_form_initialized')) {
      $rates = $this->configuration['rates'];
      $territories = $this->configuration['territories'];
      // Initialize empty rows in case there's no data yet.
      $rates = $rates ?: [NULL];
      $territories = $territories ?: [NULL];

      $form_state->set('rates', $rates);
      $form_state->set('territories', $territories);
      $form_state->set('tax_form_initialized', TRUE);
    }

    $form['rates'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Tax rate'),
        $this->t('Amount'),
        $this->t('Operations'),
      ],
      '#input' => FALSE,
    ];
    foreach ($form_state->get('rates') as $index => $rate) {
      $rate_form = &$form['rates'][$index];
      $rate_form['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#default_value' => $rate ? $rate['label'] : '',
        '#maxlength' => 255,
        '#required' => TRUE,
      ];
      $rate_form['amount'] = [
        '#type' => 'number',
        '#title' => 'Amount',
        '#default_value' => $rate ? $rate['amount'] * 100 : '',
        '#field_suffix' => $this->t('%'),
        '#min' => 0,
        '#max' => 100,
      ];
      $rate_form['remove'] = [
        '#type' => 'submit',
        '#name' => 'remove_rate' . $index,
        '#value' => $this->t('Remove'),
        '#limit_validation_errors' => [],
        '#submit' => [[get_class($this), 'removeRateSubmit']],
        '#rate_index' => $index,
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxCallback'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }
    $form['rates'][] = [
      'add_rate' => [
        '#type' => 'submit',
        '#value' => $this->t('Add rate'),
        '#submit' => [[get_class($this), 'addRateSubmit']],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxCallback'],
          'wrapper' => $wrapper_id,
        ],
      ],
    ];

    $form['territories'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Territory'),
        $this->t('Operations'),
      ],
      '#input' => FALSE,
      '#prefix' => '<p>' . $this->t('The tax type will be used if both the customer and the store belong to one of the territories.') . '</p>',
    ];
    foreach ($form_state->get('territories') as $index => $territory) {
      $territory_form = &$form['territories'][$index];
      $territory_form['territory'] = [
        '#type' => 'select',
        '#title' => $this->t('Country'),
        '#default_value' => $territory,
        '#options' => CountryManager::getStandardList(),
        '#required' => TRUE,
      ];
      $territory_form['remove'] = [
        '#type' => 'submit',
        '#name' => 'remove_territory' . $index,
        '#value' => $this->t('Remove'),
        '#limit_validation_errors' => [],
        '#submit' => [[get_class($this), 'removeTerritorySubmit']],
        '#territory_index' => $index,
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxCallback'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }
    $form['territories'][] = [
      'add_territory' => [
        '#type' => 'submit',
        '#value' => $this->t('Add territory'),
        '#submit' => [[get_class($this), 'addTerritorySubmit']],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxCallback'],
          'wrapper' => $wrapper_id,
        ],
      ],
    ];

    return $form;
  }

  /**
   * Ajax callback for tax rate and zone territory operations.
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state) {
    return $form['configuration'];
  }

  /**
   * Submit callback for adding a new rate.
   */
  public function addRateSubmit(array $form, FormStateInterface $form_state) {
    $rates = $form_state->get('rates');
    $rates[] = [
      'id' => '',
      'label' => '',
      'amount' => '',
    ];
    $form_state->set('rates', $rates);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for removing a rate.
   */
  public function removeRateSubmit(array $form, FormStateInterface $form_state) {
    $rates = $form_state->get('rates');
    $index = $form_state->getTriggeringElement()['#rate_index'];
    unset($rates[$index]);
    $form_state->set('rates', $rates);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for adding a new territory.
   */
  public function addTerritorySubmit(array $form, FormStateInterface $form_state) {
    $territories = $form_state->get('territories');
    $territories[] = [];
    $form_state->set('territories', $territories);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for removing a territory.
   */
  public function removeTerritorySubmit(array $form, FormStateInterface $form_state) {
    $territories = $form_state->get('territories');
    $index = $form_state->getTriggeringElement()['#territory_index'];
    unset($territories[$index]);
    $form_state->set('territories', $territories);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);
    $values['rates'] = array_filter($values['rates']);
    $values['territories'] = array_filter($values['territories']);
    if (empty($values['rates'])) {
      $form_state->setError($form['rates'], $this->t('Please add at least one rate.'));
    }
    if (empty($values['territories'])) {
      $form_state->setError($form['territories'], $this->t('Please add at least one territory.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $values['rates'] = array_filter($values['rates']);
      $values['territories'] = array_filter($values['territories']);

      $this->configuration['rounding_mode'] = $values['rounding_mode'];
      $this->configuration['rates'] = array_values($values['rates']);
      $this->configuration['territories'] = array_values($values['territories']);
      // Convert the amounts to fractals (90 -> 0.9).
      // @todo Remove once there's a form element that does it.
      foreach ($this->configuration['rates'] as &$rate) {
        $rate['amount'] = $rate['amount'] / 100;
      }
    }
  }

  /**
   * Gets the available display labels.
   *
   * @return array
   *   The display labels, keyed by machine name.
   */
  protected function getDisplayLabels() {
    return [
      'tax' => $this->t('Tax'),
      'vat' => $this->t('VAT'),
      // Australia, New Zealand, Singapore, Hong Kong, India, Malaysia.
      'gst' => $this->t('GST'),
      // Japan.
      'consumption_tax' => $this->t('Consumption tax'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel() {
    $display_labels = $this->getDisplayLabels();
    $display_label_id = $this->configuration['display_label'];
    if (isset($display_labels[$display_label_id])) {
      $display_label = $display_labels[$display_label_id];
    }
    else {
      $display_label = reset($display_labels);
    }
    return $display_label;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoundingMode() {
    return $this->configuration['rounding_mode'];
  }

  /**
   * {@inheritdoc}
   */
  public function applies(StoreInterface $store) {
    $zones = $this->getZones();
    $zone = reset($zones);
    if ($zone->match($store->getAddress())) {
      // The store's address belongs to this zone.
      return TRUE;
    }
    elseif ($this->checkStoreRegistration($zone, $store->tax_registrations->getValue())) {
      // The store is registered to collect tax in this zone.
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function calculateRates(OrderItemInterface $order_item) {
    $zones = $this->getZones();
    $zone = reset($zones);
    $customerZoneMatch = $zone->match($context->getCustomerAddress());
    if ($customerZoneMatch) {
      // The customer and store belong to the same zone.
      $results[] = $taxType;
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getZones() {
    $rates = $this->configuration['rates'];
    // The plugin doesn't support defining multiple amounts with own
    // start/end dates for UX reasons, so a start date is invented here.
    foreach ($rates as &$rate) {
      $rate['amounts'][] = [
        'amount' => $rate['amount'],
        'start_date' => '2000-01-01',
      ];
      unset($rate['amount']);
    }
    // The first defined rate is assumed to be the default.
    $rates[0]['default'] = TRUE;

    $zones = [];
    $zones['default'] = new TaxZone([
      'label' => 'Default',
      'territories' => $this->configuration['territories'],
      'rates' => $rates,
    ]);

    return $zones;
  }

}
