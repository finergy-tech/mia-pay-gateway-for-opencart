<?php

class ModelExtensionPaymentMia extends Model
{
    /**
     * Get payment method details for the checkout page
     *
     * @param array $address Address details of the customer
     * @param float $total Total amount of the order
     * @return array Payment method details or an empty array if not applicable
     */
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/mia');

        // Check if the customer's address matches the configured geo zone
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone
            WHERE geo_zone_id = '" . (int)$this->config->get('payment_mia_geo_zone_id') . "'
                AND country_id = '" . (int)$address['country_id'] . "'
                AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

        // Determine the availability status of the payment method
        if (!$this->config->get('payment_mia_geo_zone_id')) {
            $status = true; // No geo zone restrictions
        } elseif ($query->num_rows) {
            $status = true; // Geo zone matches
        } else {
            $status = false; // Geo zone does not match
        }

        $method_data = [];

        if ($status) {
            $method_data = [
                'code' => 'mia',
                'title' => $this->config->get('payment_mia_title'),
                'terms' => '',
                'sort_order' => $this->config->get('payment_mia_sort_order')
            ];
        }

        return $method_data;
    }
}
