# Flutterwave for Paid Membership Pro

A secure Flutterwave payment gateway for [Paid Memberships Pro](https://www.paidmembershipspro.com/) on WordPress.

Maintained by **Toxima**.

- Website: https://toxima.com.ng
- GitHub: https://github.com/iamtoxima

## Features

- Collect one-time membership payments through Flutterwave Checkout.
- Uses current Flutterwave Checkout script: `https://checkout.flutterwave.com/v3.js`.
- Verifies every transaction server-side before activating a membership.
- Validates Flutterwave webhook Secret Hash when configured.
- Sanitizes callback and webhook input before processing.
- Uses targeted database queries instead of broad `SELECT *` reads.
- Keeps failed and pending payments from being marked as completed.
- Leaves pending payments pending until Flutterwave reports `successful`.
- Uses structured JSON logging through a centralized logger method.

## Requirements

- WordPress
- Paid Memberships Pro
- A Flutterwave account
- Flutterwave public and secret API keys

## Installation

1. Download the plugin ZIP from GitHub.
2. In WordPress, go to `Plugins > Add New > Upload Plugin`.
3. Upload and activate the plugin.
4. Go to Paid Memberships Pro payment settings.
5. Select `Flutterwave` as the gateway.
6. Choose the correct environment:
   - Sandbox/test mode: use Flutterwave test keys.
   - Live mode: use Flutterwave live keys.
7. Save settings.

## Webhook Setup

Add this webhook URL in your Flutterwave dashboard:

```text
https://your-domain.com/wp-admin/admin-ajax.php?action=kkd_pmpro_rave_ipn
```

If your Flutterwave dashboard provides a Secret Hash field, create a strong random value and enter the same value in:

- Flutterwave dashboard webhook Secret Hash
- WordPress plugin setting: `Webhook Secret Hash`

The Secret Hash is not your Flutterwave secret key. It is a shared webhook verification string.

## Testing

Use sandbox mode with Flutterwave test keys and Flutterwave's current test card details. Do not mix live keys with test cards or test keys with real cards.

## Payment Verification Rules

Membership activation only happens when Flutterwave verification returns:

```text
status = successful
```

Failed or cancelled payments are cancelled in Paid Memberships Pro. Pending or processing payments remain pending and do not activate membership.

## Recurring Memberships

Recurring memberships are intentionally blocked in this version until tokenized renewals or Flutterwave payment plans are implemented securely.

## Credits

Maintained by Toxima. Based on an earlier GPL Flutterwave PMPro integration.

## License

GPL-2.0+


