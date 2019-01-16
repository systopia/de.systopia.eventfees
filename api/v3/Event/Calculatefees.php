<?php
/*-------------------------------------------------------+
| Calculate Event Fees                                   |
| Copyright (C) 2016 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/**
 * Replicating CRM_Event_Form_Registration_Register::buildAmount
 */
function civicrm_api3_event_calculatefees($params) {
  $event_id = (int) $params['event_id'];
  $price_set_id = NULL;
  $is_discount = 1;

  // first check if there is an active discount
  $discount_id = CRM_Core_BAO_Discount::findSet($event_id, 'civicrm_event');
  if ($discount_id) {
    // load discounts, extract price set
    $discounts = CRM_Core_BAO_Discount::getOptionGroup($event_id, 'civicrm_event');
    $price_set_id = $discounts[$discount_id];
  }

  // if no discount found: use the default
  if (empty($price_set_id)) {
    $is_discount = 0;
    $price_set_id = CRM_Price_BAO_PriceSet::getFor('civicrm_event', $event_id);
  }

  // verify that we have a price set
  if (empty($price_set_id)) {
    return civicrm_api3_create_error("No price set found for event [{$event_id}]");
  }

  // then load the valid price set
  $price_set  = CRM_Price_BAO_PriceSet::getSetDetail($price_set_id);

  // add extra data that's not provided by the function above
  $extra_data = civicrm_api3('PriceSet', 'getsingle', array('id' => $price_set_id));
  $title      = $extra_data['title'];

  // option count
  $recordedOptionsCount = CRM_Event_BAO_Participant::priceSetOptionsCount($event_id, []);

  foreach ($price_set[$price_set_id]['fields'] as &$fields) {
    // adding an expiration indicator
    $now = strtotime('now');
    $from = (key_exists("active_on",$fields) ? strtotime($fields['active_on']) : strtotime("-30 years"));
    $till = (key_exists("expire_on",$fields) ? strtotime($fields['expire_on']) : strtotime("+30 years"));
    $fields['is_expired'] = ($now > $from && $now < $till) ? "false" : "true";
    foreach ($fields['options'] as &$field) {
      $field['priceset_title'] = $extra_data['title'];
      $field['priceset_id']    = $extra_data['id'];
      // add the option count as extra data and set an is_full indicator.
      $field['participant_count'] = (key_exists($field['id'],$recordedOptionsCount) ? strval($recordedOptionsCount[$field['id']]) : "");
      $field['is_full'] = (!key_exists($field['id'],$recordedOptionsCount) || !key_exists('max_value',$field) || $field['max_value'] > $recordedOptionsCount[$field['id']] ? "false" : "true");
    }
  }

  // verify that we have a price set
  if (empty($price_set)) {
    return civicrm_api3_create_error("Price set [{price_set_id}] doesn't exist.");
  }

  $dao = NULL;
  return civicrm_api3_create_success($price_set[$price_set_id]['fields'], $params, 'Event', 'calculatefees', $dao, array('is_discount' => $is_discount));
}

/**
 * API3 action specs
 */
function _civicrm_api3_event_calculatefees_spec(&$params) {
  $params['event_id']['api.required'] = 1;
  $params['event_id']['title'] = 'Event ID';
}


