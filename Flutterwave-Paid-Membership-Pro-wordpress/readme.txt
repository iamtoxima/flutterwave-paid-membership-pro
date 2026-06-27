=== Flutterwave for Paid Membership Pro ===
Contributors: iamtoxima
Tags: flutterwave, payment gateway, paid memberships pro, pmpro, wordpress, membership payments
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 2.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Secure Flutterwave payment gateway for Paid Memberships Pro, maintained by Toxima.

== Description ==

This plugin adds Flutterwave Checkout as a payment gateway for Paid Memberships Pro.

Maintainer: Toxima
Website: https://toxima.com.ng
GitHub: https://github.com/iamtoxima

The plugin verifies every Flutterwave transaction server-side before activating a membership. It also validates webhook Secret Hash when configured, sanitizes callback/webhook input, avoids broad database reads, and prevents failed or pending payments from being marked as completed.

== Features ==

* Flutterwave Checkout support for one-time memberships.
* Server-side transaction verification before membership activation.
* Webhook Secret Hash validation when configured.
* Failed/cancelled payments stay failed.
* Pending/processing payments stay pending.
* Targeted database reads instead of SELECT *.
* Structured JSON logging through one logger method.

== Installation ==

1. Upload and activate the plugin.
2. Make sure Paid Memberships Pro is installed and active.
3. Go to Paid Memberships Pro payment settings.
4. Select Flutterwave as the gateway.
5. Enter matching Flutterwave keys for the selected environment.
6. Save settings.

== Webhook Setup ==

Add this webhook URL in Flutterwave:

https://your-domain.com/wp-admin/admin-ajax.php?action=kkd_pmpro_rave_ipn

If Flutterwave provides a Secret Hash field, create a strong random value and enter the same value in Flutterwave and in the plugin settings.

== Frequently Asked Questions ==

= Why does my card show Invalid Transaction? =

Usually because the checkout mode and card type do not match. Sandbox mode needs test keys and Flutterwave test cards. Live mode needs live keys and a real valid card.

= Are recurring memberships supported? =

Not in this version. Recurring memberships are blocked until tokenized renewals or Flutterwave payment plans are implemented securely.

= When is membership activated? =

Only after Flutterwave verification returns status = successful.

= What happens to pending payments? =

They remain pending and do not activate membership.

== Credits ==

This plugin is a modernized and secured fork of the legacy Rave Flutterwave gateway for Paid Memberships Pro. The current maintained version is by Toxima.

== Changelog ==

= 2.0.1 =
* Updated public author metadata for Toxima.
* Added GitHub-ready README documentation.
* Documented payment verification behavior and webhook setup.

= 2.0.0 =
* Migrated from legacy Rave checkout URLs to Flutterwave Checkout v3.
* Added server-side transaction verification before activating membership.
* Added webhook Secret Hash validation.
* Sanitized confirmation and webhook inputs.
* Replaced broad level queries with targeted column selection.
* Replaced order deletion with pending-order cancellation.
* Moved checkout emails to a scheduled WordPress event.
* Added 10-minute verification response cache with invalidation after successful activation.

