<?php

require_once DIR_SYSTEM . 'library/mia/sdk/MiaPosSdk.php';

class ControllerExtensionPaymentMia extends Controller
{
    private $log;
    private $miaSdk;

    protected $p_success = "SUCCESS";
    protected $p_created = "CREATED";
    protected $p_expired = "EXPIRED";
    protected $p_failed = "FAILED";
    protected $p_declined = "DECLINED";
    protected $p_pending = "PENDING";

    public function __construct($registry)
    {
        parent::__construct($registry);

        // Initialize logging
        $this->log = new Log('mia_pos_pay.log');

        // Get configuration settings for SDK initialization
        $baseUrl = $this->config->get('payment_mia_base_url');
        $merchantId = $this->config->get('payment_mia_merchant_id');
        $secretKey = $this->config->get('payment_mia_secret_key');

        // Initialize MiaPosSdk if configuration parameters are valid
        if ($baseUrl && $merchantId && $secretKey) {
            $this->miaSdk = MiaPosSdk::getInstance($baseUrl, $merchantId, $secretKey);
        } else {
            $this->logIfDebug('MIA SDK initialization failed. Missing configuration parameters.', 'error');
        }
    }

    /**
     * Renders the payment method on the checkout page
     *
     * @return string Rendered HTML or error message
     */
    public function index()
    {
        $this->load->language('extension/payment/mia');
        $this->load->model('checkout/order');

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['action'] = $this->url->link('extension/payment/mia/pay', '', true);

        // Check if the order currency is supported
        $supportedCurrencies = ['MDL'];
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!in_array($order_info['currency_code'], $supportedCurrencies)) {
            $error_message = $this->language->get('error_currency_not_supported');
            $this->logIfDebug("Unsupported currency: {$order_info['currency_code']}. Order ID: $order_id", 'error');
            return '<div class="alert alert-danger">' . $error_message . '</div>';
        }

        // Render the payment method template
        return $this->load->view('extension/payment/mia', $data);
    }


    public function pay()
    {
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/mia');

        try {
            $salt = uniqid('mia_', true);

            $order_id = $this->session->data['order_id'];
            $this->logIfDebug("Processing payment for order ID: $order_id", 'info');

            $order_info = $this->model_checkout_order->getOrder($order_id);
            if (!$order_info) {
                throw new Exception($this->language->get('error_no_order'));
            }

            // Validate configuration
            if (empty($this->config->get('payment_mia_merchant_id')) ||
                empty($this->config->get('payment_mia_terminal_id'))) {
                throw new Exception($this->language->get('error_configuration'));
            }

            // Ensure the currency is MDL
            if ($order_info['currency_code'] !== 'MDL') {
                throw new Exception($this->language->get('error_currency_not_supported'));
            }

            // Prepare MIA POS SDK
            $sdk = $this->miaSdk;
            if (!$sdk) {
                throw new Exception($this->language->get('error_sdk_initialization'));
            }

            // Build payment request data
            $payment_data = [
                'terminalId' => $this->config->get('payment_mia_terminal_id'),
                'orderId' => (string)$order_id,
                'amount' => (float)number_format($order_info['total'], 2, '.', ''),
                'currency' => 'MDL',
                'language' => $this->config->get('payment_mia_language'),
                'payDescription' => sprintf($this->language->get('text_order_description'), $order_id),
                'paymentType' => $this->config->get('payment_mia_payment_type'),
                'clientName' => substr(trim($order_info['payment_firstname'] . ' ' . $order_info['payment_lastname']), 0, 128),
                'clientPhone' => substr($order_info['telephone'], 0, 40),
                'clientEmail' => $order_info['email'],
                'callbackUrl' => $this->url->link('extension/payment/mia/callback', '', true),
                'successUrl' => $this->url->link("extension/payment/mia/success&orderId=$order_id&salt=$salt", '', true),
                'failUrl' => $this->url->link("extension/payment/mia/fail&orderId=$order_id&salt=$salt", '', true),
            ];

            $this->logIfDebug("Payment request data: " . json_encode($payment_data, JSON_PRETTY_PRINT), 'info');

            // Create payment via SDK
            $response = $sdk->createPayment($payment_data);
            $this->logIfDebug("MIA POS response: " . json_encode($response, JSON_PRETTY_PRINT), 'info');

            if (!isset($response['paymentId']) || !isset($response['checkoutPage'])) {
                throw new Exception($this->language->get('error_invalid_response'));
            }

            // Save payment details in order history
            $this->model_checkout_order->addOrderHistory(
                $order_id,
                $this->config->get('payment_mia_order_pending_status_id'),
                sprintf("mia_payment_id: %s, redirect_salt: %s", $response['paymentId'], $salt),
                false
            );
            $this->session->data['mia_order_id'] = $order_id;

            $this->logIfDebug("Payment initiated successfully for order ID: $order_id. Redirecting to: {$response['checkoutPage']}", 'info');

            // Redirect to payment page
            $this->response->redirect($response['checkoutPage']);
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $this->logIfDebug("Payment error for order ID: $order_id - $error_message", 'error');

            // Add an informative comment to the order history
            $this->model_checkout_order->addOrderHistory(
                $order_id,
                $order_info['order_status_id'], // Keep the current status unchanged
                sprintf($this->language->get('text_payment_failed'), $error_message),
                false
            );

            // Redirect to failure page with error message
            $this->session->data['error'] = $error_message;
            $this->response->redirect($this->url->link('checkout/failure', '', true));
        }
    }

    public function callback()
    {
        $sdk = $this->miaSdk;
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/mia');

        try {
            $rawPost = file_get_contents('php://input');
            $this->logIfDebug("MIA POS callback received raw data: $rawPost");

            $callbackData = json_decode($rawPost, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logIfDebug("MIA POS callback JSON decode error: " . json_last_error_msg(), 'error');
                http_response_code(400);
                echo 'Invalid JSON data';
                return;
            }

            if (!isset(
                $callbackData['result'],
                $callbackData['signature'],
                $callbackData['result']['orderId'],
                $callbackData['result']['status'],
                $callbackData['result']['paymentId'])
            ) {
                $this->logIfDebug("Mia Pos invalid callback data structure: " . json_encode($callbackData, JSON_PRETTY_PRINT), 'error');
                http_response_code(400);
                echo 'Invalid callback data structure';
                return;
            }

            $result = $callbackData['result'];
            $orderId = (int)$result['orderId'];
            $payId = $result['paymentId'];

            $this->logIfDebug("MIA POS callback started for order ID: $orderId, payId: $payId");

            $resultStr = $this->formSignStringByResult($result, $orderId);
            if (!$sdk->verifySignature($resultStr, $callbackData['signature'])) {
                $this->logIfDebug("Mia Pos Invalid signature for callback data: " . json_encode($callbackData, JSON_PRETTY_PRINT), 'error');
                http_response_code(400);
                echo 'Invalid signature';
                return;
            }

            $this->logIfDebug("MIA POS callback signature is valid for order ID: $orderId");

            $orderInfo = $this->model_checkout_order->getOrder($orderId);
            if (!$orderInfo) {
                $this->logIfDebug("MIA POS callback order not found for ID: $orderId", 'error');
                http_response_code(404);
                echo 'Order not found';
                return;
            }


            $currentOrderStatus = $orderInfo['order_status_id'];
            $resultStatus = $result['status'];
            if (!in_array($currentOrderStatus, [$this->config->get('payment_mia_order_pending_status_id'), $this->config->get('payment_mia_order_fail_status_id')])) {
                $this->logIfDebug("MIA POS callback order $orderId is already in a final state [$currentOrderStatus]. Ignoring callback for status $resultStatus.");
                echo 'OK';
                return;
            }

            $type = 'callback';
            switch ($resultStatus) {
                case $this->p_success:
                    $this->processPaymentSuccess($orderId, $payId, $result, $type);
                    break;

                case $this->p_pending:
                case $this->p_created:
                    $this->processPaymentPending($orderId, $payId, $result, $type);
                    break;

                case $this->p_declined:
                case $this->p_failed:
                case $this->p_expired:
                    $this->processPaymentFailure($orderId, $payId, $result, $type);
                    break;

                default:
                    $this->logIfDebug("MIA POS callback unknown payment status received for order ID $orderId: $resultStatus", 'error');
                    http_response_code(400);
                    echo 'Unknown payment status';
                    return;
            }

            $this->logIfDebug("MIA POS callback successfully processed for order ID: $orderId, payId: $payId");
            echo 'OK';
        } catch (Exception $e) {
            $this->logIfDebug("Mia Pos callback processing error, message: " . $e->getMessage(), 'error');
            http_response_code(500);
            echo 'Internal server error';
        }
    }

    public function success()
    {
        $this->processRouteUrl('Success');
    }

    public function fail()
    {
        $this->processRouteUrl('Fail');
    }

    public function processRouteUrl($type)
    {
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/mia');

        $orderId = isset($this->request->get['orderId']) ? (int)$this->request->get['orderId'] : false;
        $receivedSalt = isset($this->request->get['salt']) ? $this->request->get['salt'] : null;

        if (!$orderId || !$receivedSalt) {
            $this->logIfDebug("{$type} URL: Invalid or missing orderId/salt.", 'error');
            $this->session->data['error'] = $this->language->get('error_invalid_response');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        $this->logIfDebug("{$type} URL - Order ID: $orderId, receivedSalt: $receivedSalt", 'info');

        $orderInfo = $this->model_checkout_order->getOrder($orderId);
        if (!$orderInfo) {
            $this->logIfDebug("{$type} URL: Order not found.", 'error');
            $this->session->data['error'] = $this->language->get('error_no_order');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        $paymentDetails = $this->getPaymentDetails($orderId);
        $payId = $paymentDetails['paymentId'];
        $salt = $paymentDetails['salt'];
        $this->logIfDebug("{$type} URL - Payment details by order id $orderId: payId $payId, salt $salt", 'info');

        if (!$salt || $salt !== $receivedSalt) {
            $this->logIfDebug("{$type} URL: Salt mismatch or payment details not found. Received salt: $receivedSalt, Stored salt: $salt", 'error');
            $this->session->data['error'] = $this->language->get('error_invalid_session');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        if (!$payId) {
            $this->logIfDebug("{$type} URL: Payment ID not found for Order ID: $orderId.", 'error');
            $this->session->data['error'] = $this->language->get('error_payment_id_not_found');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }
        $this->logIfDebug("{$type} URL - Mia Payment ID: $payId by orderId: $orderId start check", 'info');

        try {
            // Validate payment status using SDK
            $sdk = $this->miaSdk;
            $paymentStatus = $sdk->getPaymentStatus($payId);
            $this->logIfDebug("Payment status response: " . json_encode($paymentStatus, JSON_PRETTY_PRINT), 'info');

            $status = isset($paymentStatus['status']) ? $paymentStatus['status'] : 'unknown';

            // Process status
            switch ($status) {
                case $this->p_success: // Payment succeeded
                    $this->processPaymentSuccess($orderId, $payId, $paymentStatus, $type);
                    $this->response->redirect($this->url->link('checkout/success', '', true));
                    break;

                case $this->p_pending:
                case $this->p_created: // Payment pending or not completed
                    $this->processPaymentPending($orderId, $payId, $paymentStatus, $type);
                    $this->response->redirect($this->url->link('checkout/checkout', '', true));
                    break;

                default: // Payment failed or other status
                    $this->processPaymentFailure($orderId, $payId, $paymentStatus, $type);
                    $this->session->data['error'] = $this->language->get('error_payment_failed');
                    $this->response->redirect($this->url->link('checkout/failure', '', true));
                    break;
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $this->logIfDebug("{$type} URL - Error processing payment: $error_message", 'error');
            $this->session->data['error'] = $this->language->get('error_payment_verification');

            // Add an informative comment to the order history
            $this->model_checkout_order->addOrderHistory(
                $orderId,
                $orderInfo['order_status_id'], // Keep the current status unchanged
                sprintf($this->language->get('error_check_payment_state'), $error_message),
                false
            );

            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }


    private function processPaymentSuccess($orderId, $payId, $payment, $source)
    {
        $state = $payment['status'];

        $this->logIfDebug("Payment succeeded for Order ID: $orderId, Payment ID: $payId, status: $state, source: $source", 'info');

        $orderStatusId = $this->config->get('payment_mia_order_success_status_id');
        $orderNote = sprintf(
            "Source %s, Success mia_payment_info: %s",
            $source,
            json_encode($payment, JSON_PRETTY_PRINT)
        );

        $this->model_checkout_order->addOrderHistory(
            $orderId,
            $orderStatusId,
            $orderNote,
            true
        );

        $this->logIfDebug("Order updated to success status: $orderStatusId, Order ID: $orderId", 'info');
    }

    private function processPaymentFailure($orderId, $payId, $payment, $source)
    {
        $state = $payment['status'];

        $this->logIfDebug("Payment failed for Order ID: $orderId, Payment ID: $payId, status: $state, source: $source", 'error');

        $orderStatusId = $this->config->get('payment_mia_order_fail_status_id');
        $orderNote = sprintf(
            "Source %s, Fail mia_payment_info: %s",
            $source,
            json_encode($payment, JSON_PRETTY_PRINT)
        );

        $this->model_checkout_order->addOrderHistory(
            $orderId,
            $orderStatusId,
            $orderNote,
            true
        );

        $this->logIfDebug("Order updated to fail status: $orderStatusId, Order ID: $orderId", 'info');
    }

    private function processPaymentPending($orderId, $payId, $payment, $source)
    {
        $state = $payment['status'];

        $this->logIfDebug("Payment pending for Order ID: $orderId, Payment ID: $payId, status: $state, source: $source", 'info');

        $orderNote = sprintf($this->language->get('text_payment_pending'), $payId, $state);

        $orderStatusId = config->get('payment_mia_order_pending_status_id');
        $this->model_checkout_order->addOrderHistory(
            $orderId,
            $this->$orderStatusId,
            $orderNote,
            false
        );

        $this->logIfDebug("Order updated to pending status: $orderStatusId, Order ID: $orderId", 'info');
    }

    private function getPaymentDetails($orderId)
    {
        $query = $this->db->query("SELECT comment FROM " . DB_PREFIX . "order_history WHERE order_id = '" . (int)$orderId . "' AND comment LIKE '%mia_payment_id%' ORDER BY order_id DESC");
        if ($query->num_rows > 0) {
            $comment = $query->row['comment'];

            preg_match('/mia_payment_id: (\S+)/', $comment, $matches);
            preg_match('/mia_payment_id: (\S+), redirect_salt: (\S+)/', $comment, $matches);
            return [
                'paymentId' => isset($matches[1]) ? $matches[1] : null,
                'salt' => isset($matches[2]) ? $matches[2] : null
            ];
        }
        return ['paymentId' => null, 'salt' => null];
    }

    private function formSignStringByResult($result_data, $order_id)
    {
        ksort($result_data);

        $result_str = implode(
            ';',
            array_map(function ($key, $value) {
                if ($key === 'amount') {
                    return number_format($value, 2, '.', '');
                }
                return (string)$value;
            }, array_keys($result_data), $result_data)
        );

        $this->logIfDebug("Mia Pos sign str for order_id: $order_id, signature: $result_str", 'info');
        return $result_str;
    }

    /**
     * Logs messages if debug mode is enabled
     *
     * @param string $message Message to log
     * @param string $type Type of message (info, error, debug)
     */
    private function logIfDebug($message, $type = 'info')
    {
        if ($this->config->get('payment_mia_debug')) {
            $this->log->write("mia $type: $message");
        }
    }
}
