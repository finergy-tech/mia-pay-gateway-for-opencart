# MIA POS Payment Gateway for OpenCart

Accept payments in your OpenCart store through the MIA POS payment system.

## Description

This plugin integrates MIA POS as a payment method in your OpenCart store. MIA POS is a payment system provided by Finergy Tech that allows you to accept payments via QR codes and direct payment requests.

### Features

- Accept payments via QR codes
- Support for Request to Pay (RTP) payments
- Automatic order status updates
- Secure payment processing using signature verification
- Multilingual support (RO, RU, EN)
- Easy integration with OpenCart

## Requirements

- Registration on the MIA POS platform
- OpenCart version 3.x
- PHP 7.2 or higher
- Installed SSL certificate
- PHP extensions: _curl_ and _json_ must be enabled

## Installation

1. Download the extension file: **mia-pay-gateway.ocmod.zip**.
2. In the OpenCart admin panel, go to **Extensions > Installer**.
3. Click **Upload** and select the extension file. OpenCart will begin the installation process after the upload is complete.
4. Go to **Extensions > Modifications** and click the **Refresh** button.
5. Navigate to **Extensions > Extensions** and select the **Payments** type. You should see **MIA POS Payment Gateway** in the list.
6. Click **Install**.
7. Click **Edit** to configure the necessary settings.

## Configuration

### Required Parameters

- **Merchant ID**: Your unique merchant identifier (provided by MIA POS).
- **Secret Key**: The secret key for API authentication (provided by MIA POS).
- **Terminal ID**: The identifier for your terminal (provided by MIA POS).
- **API Base URL**: The API endpoint URL for MIA POS. This URL must be obtained from your bank. Ensure to test the plugin in the bank’s test environment first.

### Optional Parameters

- **Order Status Settings: Payment Pending** - The order status when the payment is pending.
- **Order Status Settings: Payment Success** - The order status after successful payment.
- **Order Status Settings: Payment Failed** - The order status when the payment fails.

## Testing

To test the plugin, you must use the bank's test environment and a test MIA POS account. Contact your bank to obtain test credentials.

1. Configure the plugin using test credentials.
2. Perform test purchases to verify the payment process.
3. Check that order statuses are updated correctly.
4. Verify the processing of callback notifications.

## Support

For support and inquiries, please contact:
- Website: [https://finergy.md/](https://finergy.md/)
- Email: [info@finergy.md](mailto:info@finergy.md)