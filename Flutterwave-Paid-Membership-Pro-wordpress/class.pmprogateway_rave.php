<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Flutterwave for Paid Membership Pro
 * Plugin URI:        https://github.com/iamtoxima
 * Description:       Secure Flutterwave payment gateway for Paid Memberships Pro. Maintained by Toxima.
 * Version:           2.0.1
 * Author:            Toxima
 * Author URI:        https://toxima.com.ng
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

defined('ABSPATH') || exit;

define('Rave_Flutterwave_PMPro', '2.0.1');

if (!function_exists('KKD_rave_pmp_gateway_load')) {
  add_action('plugins_loaded', 'KKD_rave_pmp_gateway_load', 20);
  define('KKD_RAVEPMP', 'rave-paidmembershipspro');

  function KKD_rave_pmp_gateway_load()
  {
    if (!class_exists('PMProGateway')) {
      return;
    }

    if (!class_exists('PMProGateway_rave')) {
      class PMProGateway_rave extends PMProGateway
      {
        const CHECKOUT_SCRIPT = 'https://checkout.flutterwave.com/v3.js';
        const API_BASE = 'https://api.flutterwave.com/v3';
        const VERIFY_CACHE_TTL = 600;

        function __construct($gateway = null)
        {
          $this->gateway = $gateway;
          $this->gateway_environment = pmpro_getOption('gateway_environment');
          return $this->gateway;
        }

        public static function init()
        {
          add_filter('pmpro_gateways', array(__CLASS__, 'pmpro_gateways'));
          add_filter('pmpro_payment_options', array(__CLASS__, 'pmpro_payment_options'));
          add_filter('pmpro_payment_option_fields', array(__CLASS__, 'pmpro_payment_option_fields'), 10, 2);
          add_action('wp_ajax_kkd_pmpro_rave_ipn', array(__CLASS__, 'kkd_pmpro_rave_ipn'));
          add_action('wp_ajax_nopriv_kkd_pmpro_rave_ipn', array(__CLASS__, 'kkd_pmpro_rave_ipn'));
          add_action('wp_ajax_nopriv_kkd_rave_ipn', array(__CLASS__, 'kkd_pmpro_rave_ipn'));
          add_action('kkd_pmpro_rave_send_checkout_emails', array(__CLASS__, 'send_checkout_emails'), 10, 2);

          if (pmpro_getGateway() === 'rave') {
            add_filter('pmpro_include_billing_address_fields', '__return_false');
            add_filter('pmpro_required_billing_fields', array(__CLASS__, 'pmpro_required_billing_fields'));
            add_filter('pmpro_include_payment_information_fields', '__return_false');
            add_filter('pmpro_checkout_before_change_membership_level', array(__CLASS__, 'pmpro_checkout_before_change_membership_level'), 10, 2);
            add_filter('pmpro_gateways_with_pending_status', array(__CLASS__, 'pmpro_gateways_with_pending_status'));
            add_filter('pmpro_pages_shortcode_checkout', array(__CLASS__, 'pmpro_pages_shortcode_checkout'), 20, 1);
            add_filter('pmpro_checkout_default_submit_button', array(__CLASS__, 'pmpro_checkout_default_submit_button'));
            add_filter('pmpro_pages_shortcode_confirmation', array(__CLASS__, 'pmpro_pages_shortcode_confirmation'), 20, 1);
          }
        }

        public static function plugin_action_links($links, $file)
        {
          if ($file === plugin_basename(__FILE__)) {
            array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=pmpro-paymentsettings')) . '">' . esc_html__('Settings', KKD_RAVEPMP) . '</a>');
          }
          return $links;
        }

        public static function pmpro_gateways($gateways)
        {
          if (empty($gateways['rave'])) {
            $gateways = array_slice($gateways, 0, 1) + array('rave' => __('Flutterwave', KKD_RAVEPMP)) + array_slice($gateways, 1);
          }
          return $gateways;
        }

        public static function getGatewayOptions()
        {
          return array(
            'rave_merchant_logo',
            'rave_payment_method',
            'rave_merchant_country',
            'rave_live_public_key',
            'rave_live_secret_key',
            'rave_test_public_key',
            'rave_test_secret_key',
            'rave_webhook_secret_hash',
            'gateway_environment',
            'currency',
            'tax_state',
            'tax_rate',
          );
        }

        public static function pmpro_payment_options($options)
        {
          return array_merge(self::getGatewayOptions(), $options);
        }

        public static function pmpro_payment_option_fields($values, $gateway)
        {
          $values = wp_parse_args($values, array(
            'rave_merchant_logo' => '',
            'rave_payment_method' => 'both',
            'rave_merchant_country' => 'NG',
            'rave_live_public_key' => '',
            'rave_live_secret_key' => '',
            'rave_test_public_key' => '',
            'rave_test_secret_key' => '',
            'rave_webhook_secret_hash' => '',
          ));
          ?>
          <tr class="pmpro_settings_divider gateway gateway_rave" <?php if ($gateway !== 'rave') { ?>style="display: none;"<?php } ?>>
            <td colspan="2"><?php esc_html_e('Flutterwave Settings', 'paid-memberships-pro'); ?></td>
          </tr>
          <tr class="gateway gateway_rave" <?php if ($gateway !== 'rave') { ?>style="display: none;"<?php } ?>>
            <td colspan="2"><strong><?php esc_html_e('Note', 'paid-memberships-pro'); ?>:</strong> <?php esc_html_e('Use sandbox with test keys, and live mode with live keys. Do not mix modes.', 'paid-memberships-pro'); ?></td>
          </tr>
          <tr class="gateway gateway_rave" <?php if ($gateway !== 'rave') { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top"><label><?php esc_html_e('Webhook URL', 'pmpro'); ?>:</label></th>
            <td><code><?php echo esc_html(admin_url('admin-ajax.php') . '?action=kkd_pmpro_rave_ipn'); ?></code></td>
          </tr>
          <tr class="gateway gateway_rave" <?php if ($gateway !== 'rave') { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top"><label for="rave_webhook_secret_hash"><?php esc_html_e('Webhook Secret Hash', 'paid-memberships-pro'); ?>:</label></th>
            <td><input type="password" id="rave_webhook_secret_hash" name="rave_webhook_secret_hash" size="60" value="<?php echo esc_attr($values['rave_webhook_secret_hash']); ?>" autocomplete="off" /> <small><?php esc_html_e('Must match the Secret Hash in your Flutterwave dashboard.', 'paid-memberships-pro'); ?></small></td>
          </tr>
          <tr class="gateway gateway_rave" <?php if ($gateway !== 'rave') { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top"><label for="rave_merchant_logo"><?php esc_html_e('Merchant Logo', 'paid-memberships-pro'); ?>:</label></th>
            <td><input type="url" id="rave_merchant_logo" name="rave_merchant_logo" size="60" value="<?php echo esc_attr($values['rave_merchant_logo']); ?>" /></td>
          </tr>
          <tr class="gateway gateway_rave" <?php if ($gateway !== 'rave') { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top"><label for="rave_payment_method"><?php esc_html_e('Payment Method', 'paid-memberships-pro'); ?>:</label></th>
            <td><select id="rave_payment_method" name="rave_payment_method">
              <option value="both" <?php selected($values['rave_payment_method'], 'both'); ?>><?php esc_html_e('All', 'paid-memberships-pro'); ?></option>
              <option value="card" <?php selected($values['rave_payment_method'], 'card'); ?>><?php esc_html_e('Card only', 'paid-memberships-pro'); ?></option>
              <option value="account" <?php selected($values['rave_payment_method'], 'account'); ?>><?php esc_html_e('Account only', 'paid-memberships-pro'); ?></option>
              <option value="ussd" <?php selected($values['rave_payment_method'], 'ussd'); ?>><?php esc_html_e('USSD only', 'paid-memberships-pro'); ?></option>
            </select></td>
          </tr>
          <tr class="gateway gateway_rave" <?php if ($gateway !== 'rave') { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top"><label for="rave_merchant_country"><?php esc_html_e('Merchant Country', 'paid-memberships-pro'); ?>:</label></th>
            <td><select id="rave_merchant_country" name="rave_merchant_country">
              <option value="NG" <?php selected($values['rave_merchant_country'], 'NG'); ?>><?php esc_html_e('Nigeria', 'paid-memberships-pro'); ?></option>
              <option value="GH" <?php selected($values['rave_merchant_country'], 'GH'); ?>><?php esc_html_e('Ghana', 'paid-memberships-pro'); ?></option>
              <option value="KE" <?php selected($values['rave_merchant_country'], 'KE'); ?>><?php esc_html_e('Kenya', 'paid-memberships-pro'); ?></option>
              <option value="ZA" <?php selected($values['rave_merchant_country'], 'ZA'); ?>><?php esc_html_e('South Africa', 'paid-memberships-pro'); ?></option>
            </select></td>
          </tr>
          <tr class="gateway gateway_rave" <?php if ($gateway !== 'rave') { ?>style="display: none;"<?php } ?>><th scope="row" valign="top"><label for="rave_live_public_key"><?php esc_html_e('Live Public Key', 'paid-memberships-pro'); ?>:</label></th><td><input type="text" id="rave_live_public_key" name="rave_live_public_key" size="60" value="<?php echo esc_attr($values['rave_live_public_key']); ?>" autocomplete="off" /></td></tr>
          <tr class="gateway gateway_rave" <?php if ($gateway !== 'rave') { ?>style="display: none;"<?php } ?>><th scope="row" valign="top"><label for="rave_live_secret_key"><?php esc_html_e('Live Secret Key', 'paid-memberships-pro'); ?>:</label></th><td><input type="password" id="rave_live_secret_key" name="rave_live_secret_key" size="60" value="<?php echo esc_attr($values['rave_live_secret_key']); ?>" autocomplete="off" /></td></tr>
          <tr class="gateway gateway_rave" <?php if ($gateway !== 'rave') { ?>style="display: none;"<?php } ?>><th scope="row" valign="top"><label for="rave_test_public_key"><?php esc_html_e('Test Public Key', 'paid-memberships-pro'); ?>:</label></th><td><input type="text" id="rave_test_public_key" name="rave_test_public_key" size="60" value="<?php echo esc_attr($values['rave_test_public_key']); ?>" autocomplete="off" /></td></tr>
          <tr class="gateway gateway_rave" <?php if ($gateway !== 'rave') { ?>style="display: none;"<?php } ?>><th scope="row" valign="top"><label for="rave_test_secret_key"><?php esc_html_e('Test Secret Key', 'paid-memberships-pro'); ?>:</label></th><td><input type="password" id="rave_test_secret_key" name="rave_test_secret_key" size="60" value="<?php echo esc_attr($values['rave_test_secret_key']); ?>" autocomplete="off" /></td></tr>
          <?php
        }

        public static function pmpro_required_billing_fields($fields)
        {
          foreach (array('bfirstname', 'blastname', 'baddress1', 'bcity', 'bstate', 'bzipcode', 'bphone', 'bemail', 'bcountry', 'CardType', 'AccountNumber', 'ExpirationMonth', 'ExpirationYear', 'CVV') as $field) {
            unset($fields[$field]);
          }
          return $fields;
        }

        public static function pmpro_gateways_with_pending_status($gateways)
        {
          if (!in_array('rave', $gateways, true)) {
            $gateways[] = 'rave';
          }
          return $gateways;
        }

        public static function pmpro_checkout_default_submit_button($show)
        {
          global $pmpro_requirebilling;
          ?>
          <span id="pmpro_submit_span"><input type="hidden" name="submit-checkout" value="1" /><input type="submit" class="pmpro_btn pmpro_btn-submit-checkout" value="<?php echo esc_attr($pmpro_requirebilling ? __('Check Out with Flutterwave', 'pmpro') : __('Submit and Confirm', 'pmpro')); ?> &raquo;" /></span>
          <?php
          return false;
        }

        public static function pmpro_checkout_before_change_membership_level($user_id, $morder)
        {
          global $wpdb, $discount_code_id;
          if (empty($morder)) {
            return;
          }
          if (empty($morder->code)) {
            $morder->code = $morder->getRandomCode();
          }
          $morder->payment_type = 'flutterwave';
          $morder->status = 'pending';
          $morder->user_id = absint($user_id);
          $morder->saveOrder();

          if (!empty($discount_code_id)) {
            $wpdb->insert($wpdb->pmpro_discount_codes_uses, array(
              'code_id' => absint($discount_code_id),
              'user_id' => absint($user_id),
              'order_id' => absint($morder->id),
              'timestamp' => current_time('mysql'),
            ), array('%d', '%d', '%d', '%s'));
          }

          $morder->Gateway->kkd_pmpro_sendToRave($morder);
        }

        public function kkd_pmpro_sendToRave(&$order)
        {
          global $pmpro_currency;
          if (pmpro_isLevelRecurring($order->membership_level)) {
            self::log('error', 'Recurring Flutterwave checkout blocked until tokenized renewals are implemented.', array('orderId' => (int) $order->id));
            echo self::safe_error(__('Recurring memberships are not enabled for Flutterwave yet. Please contact support.', 'pmpro'));
            exit;
          }

          $credentials = self::credentials();
          if (empty($credentials['public_key']) || empty($credentials['secret_key'])) {
            self::log('error', 'Flutterwave keys are missing.', array('environment' => $credentials['environment']));
            echo self::safe_error(__('Flutterwave is not configured for this checkout mode. Please contact support.', 'pmpro'));
            exit;
          }

          $user = get_userdata($order->user_id);
          if (empty($user)) {
            self::log('error', 'Checkout user not found.', array('orderId' => (int) $order->id));
            echo self::safe_error(__('We could not prepare this checkout. Please sign in again and retry.', 'pmpro'));
            exit;
          }

          $amount = round((float) $order->InitialPayment + (float) $order->getTaxForPrice($order->InitialPayment), 2);
          $checkout = array(
            'public_key' => $credentials['public_key'],
            'tx_ref' => sanitize_text_field($order->code),
            'amount' => $amount,
            'currency' => sanitize_text_field($pmpro_currency),
            'country' => self::sanitize_country(pmpro_getOption('rave_merchant_country')),
            'payment_options' => self::payment_options(pmpro_getOption('rave_payment_method')),
            'redirect_url' => esc_url_raw(pmpro_url('confirmation', '?level=' . absint($order->membership_level->id))),
            'customer' => array(
              'email' => sanitize_email($user->user_email),
              'phone_number' => isset($order->billing->phone) ? sanitize_text_field($order->billing->phone) : '',
              'name' => sanitize_text_field(trim((string) $user->display_name)),
            ),
            'customizations' => array(
              'title' => sanitize_text_field(get_bloginfo('name')),
              'description' => sanitize_text_field(sprintf(__('Payment for membership level: %s', 'pmpro'), $order->membership_level->name)),
              'logo' => esc_url_raw(pmpro_getOption('rave_merchant_logo')),
            ),
            'meta' => array(
              'order_id' => (int) $order->id,
              'membership_id' => (int) $order->membership_level->id,
            ),
          );

          self::log('info', 'Starting Flutterwave checkout.', array('orderId' => (int) $order->id, 'txRef' => $order->code));
          self::render_checkout($checkout);
          exit;
        }

        public static function pmpro_pages_shortcode_checkout($content)
        {
          $morder = new MemberOrder();
          $found = $morder->getLastMemberOrder(get_current_user_id(), apply_filters('pmpro_confirmation_order_status', array('pending')));
          if ($found && !empty($morder->id)) {
            $morder->Gateway->kkd_pmpro_delete($morder);
          }
          if (isset($_REQUEST['error'])) {
            $message = esc_html__('Payment could not be completed. Please retry or contact support if the problem continues.', 'pmpro');
            $content = '<div id="pmpro_message" class="pmpro_message pmpro_error">' . $message . '</div>' . $content;
          }
          return $content;
        }

        public static function pmpro_pages_shortcode_confirmation($content, $reference = null)
        {
          global $pmpro_invoice;
          $tx_ref = $reference ? self::sanitize_tx_ref($reference) : self::request_tx_ref();
          if (empty($tx_ref)) {
            return $content;
          }
          $order = empty($pmpro_invoice) ? new MemberOrder($tx_ref) : $pmpro_invoice;
          if (empty($order) || empty($order->id) || $order->gateway !== 'rave' || $order->code !== $tx_ref) {
            self::log('error', 'Invalid Flutterwave confirmation reference.', array('txRef' => $tx_ref));
            return self::safe_error(__('Invalid transaction reference.', 'pmpro'));
          }
          if (isset($_GET['status']) && sanitize_key(wp_unslash($_GET['status'])) === 'cancelled') {
            return $order->Gateway->kkd_pmpro_failed($order, __('You cancelled the transaction.', 'pmpro'));
          }
          return $order->Gateway->kkd_pmpro_requery($order, self::request_transaction_id(), $tx_ref, false);
        }

        public static function kkd_pmpro_rave_ipn()
        {
          $request_id = self::request_id();
          $body = file_get_contents('php://input');
          $event = json_decode($body);
          if (json_last_error() !== JSON_ERROR_NONE || empty($event)) {
            self::log('error', 'Invalid Flutterwave webhook payload.', array(), $request_id);
            status_header(400);
            exit;
          }

          $local_hash = trim((string) pmpro_getOption('rave_webhook_secret_hash'));
          $remote_hash = isset($_SERVER['HTTP_VERIF_HASH']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_VERIF_HASH'])) : '';
          if (!empty($local_hash) && !hash_equals($local_hash, $remote_hash)) {
            self::log('error', 'Flutterwave webhook hash mismatch.', array(), $request_id);
            status_header(401);
            exit;
          }

          if (!empty($event->event) && $event->event === 'test_assess') {
            wp_send_json(array('status' => 'success', 'message' => 'Webhook Test Successful'), 200);
          }

          $data = isset($event->data) ? $event->data : $event;
          $tx_ref = self::sanitize_tx_ref(self::object_value($data, array('tx_ref', 'txref', 'txRef')));
          if (empty($tx_ref)) {
            self::log('error', 'Flutterwave webhook missing tx_ref.', array(), $request_id);
            status_header(422);
            exit;
          }

          $order = new MemberOrder($tx_ref);
          if (empty($order) || empty($order->id) || $order->gateway !== 'rave') {
            self::log('error', 'Flutterwave webhook order not found.', array('txRef' => $tx_ref), $request_id);
            status_header(404);
            exit;
          }

          $order->Gateway->kkd_pmpro_requery($order, absint(self::object_value($data, array('id', 'transaction_id'))), $tx_ref, true);
          status_header(200);
          exit;
        }

        public function kkd_pmpro_requery(&$order, $transaction_id = 0, $tx_ref = '', $webhook = false)
        {
          $credentials = self::credentials();
          if (empty($credentials['secret_key'])) {
            return $this->kkd_pmpro_failed($order, __('Flutterwave is not configured for this checkout mode.', 'pmpro'));
          }
          $tx_ref = self::sanitize_tx_ref($tx_ref ? $tx_ref : self::request_tx_ref());
          $transaction_id = absint($transaction_id ? $transaction_id : self::request_transaction_id());
          if (empty($tx_ref) && empty($transaction_id)) {
            return $this->kkd_pmpro_failed($order, __('Unable to verify this transaction.', 'pmpro'));
          }

          $cache_key = 'flw_pmpro_verify_' . md5($transaction_id ? 'id:' . $transaction_id : 'ref:' . $tx_ref);
          $data = get_transient($cache_key);
          if (empty($data)) {
            $url = $transaction_id ? self::API_BASE . '/transactions/' . $transaction_id . '/verify' : self::API_BASE . '/transactions/verify_by_reference?tx_ref=' . rawurlencode($tx_ref);
            $response = wp_remote_get($url, array(
              'headers' => array('Authorization' => 'Bearer ' . $credentials['secret_key'], 'Content-Type' => 'application/json'),
              'timeout' => 20,
            ));
            if (is_wp_error($response)) {
              self::log('error', 'Flutterwave verification request failed.', array('orderId' => (int) $order->id, 'message' => $response->get_error_message()));
              return $this->kkd_pmpro_failed($order, __('The payment network timed out while verifying this transaction. Please contact support with your order reference.', 'pmpro'));
            }
            $code = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response));
            if ($code < 200 || $code >= 300 || empty($body) || empty($body->data) || (!empty($body->status) && $body->status !== 'success')) {
              self::log('error', 'Flutterwave verification failed.', array('orderId' => (int) $order->id, 'statusCode' => $code));
              return $this->kkd_pmpro_failed($order, __('Unable to verify this transaction.', 'pmpro'));
            }
            $data = $body->data;
            set_transient($cache_key, $data, self::VERIFY_CACHE_TTL);
          }

          $result = $this->kkd_pmpro_verifyTransaction($order, $data, $webhook);
          if ($order->status === 'success') {
            delete_transient($cache_key);
          }
          return $result;
        }

        public function kkd_pmpro_verifyTransaction(&$order, &$data, $webhook = false)
        {
          global $pmpro_currency;
          $tx_ref = self::sanitize_tx_ref(self::object_value($data, array('tx_ref', 'txref', 'txRef')));
          $status = isset($data->status) ? sanitize_key($data->status) : '';
          $charge_code = (string) self::object_value($data, array('charge_response_code', 'chargecode', 'processor_response_code'));
          $paid_amount = isset($data->amount) ? round((float) $data->amount, 2) : 0.00;
          $expected_amount = round((float) $order->total, 2);
          $paid_currency = isset($data->currency) ? strtoupper(sanitize_text_field($data->currency)) : '';
          $expected_currency = strtoupper(sanitize_text_field($pmpro_currency));

          if ($tx_ref !== $order->code) {
            return $this->kkd_pmpro_failed($order, __('The payment reference does not match this order.', 'pmpro'));
          }
          if ($status !== 'successful') {
            self::log('info', 'Flutterwave transaction is not successful yet.', array('orderId' => (int) $order->id, 'status' => $status));
            if (in_array($status, array('failed', 'cancelled'), true)) {
              return $this->kkd_pmpro_failed($order, __('The payment was not successful.', 'pmpro'));
            }
            return $this->kkd_pmpro_pending($order, __('Payment is still being verified. Please refresh this page in a moment or contact support with your order reference.', 'pmpro'));
          }
          if ($paid_amount < $expected_amount) {
            return $this->kkd_pmpro_failed($order, __('The payment amount was less than the order total.', 'pmpro'));
          }
          if ($paid_currency !== $expected_currency) {
            return $this->kkd_pmpro_failed($order, __('The payment currency does not match this order.', 'pmpro'));
          }

          $level = self::get_pmpro_level($order->membership_id);
          if (empty($level)) {
            return $this->kkd_pmpro_failed($order, __('The membership level could not be found.', 'pmpro'));
          }

          if ($order->status !== 'success') {
            $custom_level = self::build_custom_level($order, $level);
            if (!pmpro_changeMembershipLevel($custom_level, $order->user_id, 'changed')) {
              self::log('error', 'PMPro membership activation failed after verified payment.', array('orderId' => (int) $order->id));
              return $this->kkd_pmpro_failed($order, __('Payment was verified, but the membership could not be activated. Please contact support.', 'pmpro'));
            }
            $order->membership_id = $level->id;
            $order->payment_transaction_id = isset($data->id) ? sanitize_text_field($data->id) : $tx_ref;
            $order->status = 'success';
            $order->saveOrder();
            self::schedule_checkout_emails($order->user_id, $order->id);
          }

          self::log('info', 'Flutterwave payment verified.', array('orderId' => (int) $order->id, 'txRef' => $tx_ref));
          return $webhook ? '' : self::confirmation_content($order, $level);
        }

        public function kkd_pmpro_pending(&$order, $message = '')
        {
          $message = $message ? $message : __('Payment is still being verified.', 'pmpro');
          if (!empty($order) && !empty($order->id)) {
            $order->status = 'pending';
            $order->shorterror = sanitize_text_field($message);
            $order->saveOrder();
          }
          return self::safe_notice(__('Payment Pending', 'pmpro'), $message);
        }
        public function kkd_pmpro_failed(&$order, $message = '')
        {
          $message = $message ? $message : __('Transaction failed.', 'pmpro');
          if (!empty($order) && !empty($order->id)) {
            $order->status = 'cancelled';
            $order->shorterror = sanitize_text_field($message);
            $order->saveOrder();
          }
          return self::safe_error($message);
        }

        public function kkd_pmpro_delete(&$order)
        {
          if (!empty($order) && !empty($order->id) && $order->status === 'pending') {
            $order->updateStatus('cancelled');
          }
        }

        public static function send_checkout_emails($user_id, $order_id)
        {
          $user = get_userdata(absint($user_id));
          $invoice = new MemberOrder(absint($order_id));
          if (empty($user) || empty($invoice) || empty($invoice->id)) {
            self::log('error', 'Unable to send checkout emails.', array('userId' => absint($user_id), 'orderId' => absint($order_id)));
            return;
          }
          $email = new PMProEmail();
          $email->sendCheckoutEmail($user, $invoice);
          $email = new PMProEmail();
          $email->sendCheckoutAdminEmail($user, $invoice);
        }

        private static function credentials()
        {
          $environment = pmpro_getOption('gateway_environment');
          $sandbox = ($environment === 'sandbox' || $environment === 'beta-sandbox');
          return array(
            'environment' => $environment,
            'public_key' => trim((string) pmpro_getOption($sandbox ? 'rave_test_public_key' : 'rave_live_public_key')),
            'secret_key' => trim((string) pmpro_getOption($sandbox ? 'rave_test_secret_key' : 'rave_live_secret_key')),
          );
        }

        private static function render_checkout($checkout)
        {
          $json = wp_json_encode($checkout, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
          ?>
          <!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title><?php esc_html_e('Redirecting to Flutterwave', 'pmpro'); ?></title></head><body><p><?php esc_html_e('Redirecting to Flutterwave...', 'pmpro'); ?></p><script src="<?php echo esc_url(self::CHECKOUT_SCRIPT); ?>"></script><script>document.addEventListener('DOMContentLoaded',function(){var data=<?php echo $json; ?>;if(typeof FlutterwaveCheckout==='function'){FlutterwaveCheckout(data);}else{window.location.href=data.redirect_url+'&error=checkout_unavailable';}});</script></body></html>
          <?php
        }

        private static function get_pmpro_level($membership_id)
        {
          global $wpdb;
          return $wpdb->get_row($wpdb->prepare("SELECT id, name, initial_payment, billing_amount, cycle_number, cycle_period, billing_limit, trial_amount, trial_limit, expiration_number, expiration_period FROM {$wpdb->pmpro_membership_levels} WHERE id = %d LIMIT 1", absint($membership_id)));
        }

        private static function build_custom_level($order, $level)
        {
          $enddate = null;
          if (!empty($level->expiration_number) && !empty($level->expiration_period)) {
            $enddate = date('Y-m-d H:i:s', strtotime('+ ' . absint($level->expiration_number) . ' ' . sanitize_key($level->expiration_period), current_time('timestamp')));
          }
          return array(
            'user_id' => absint($order->user_id),
            'membership_id' => absint($level->id),
            'code_id' => isset($order->discount_code_id) ? absint($order->discount_code_id) : '',
            'initial_payment' => $level->initial_payment,
            'billing_amount' => $level->billing_amount,
            'cycle_number' => $level->cycle_number,
            'cycle_period' => $level->cycle_period,
            'billing_limit' => $level->billing_limit,
            'trial_amount' => $level->trial_amount,
            'trial_limit' => $level->trial_limit,
            'startdate' => current_time('mysql'),
            'enddate' => $enddate,
          );
        }

        private static function confirmation_content($order, $level)
        {
          global $current_user, $pmpro_currency;
          $user = get_userdata($order->user_id);
          if ($user) {
            $current_user = $user;
            $current_user->membership_level = pmpro_getMembershipLevelForUser($user->ID);
          }
          $content = '<ul>';
          $content .= '<li><strong>' . esc_html__('Account:', 'pmpro') . '</strong> ' . esc_html($user ? $user->display_name : '') . ' (' . esc_html($user ? $user->user_email : '') . ')</li>';
          $content .= '<li><strong>' . esc_html__('Order:', 'pmpro') . '</strong> ' . esc_html($order->code) . '</li>';
          $content .= '<li><strong>' . esc_html__('Membership Level:', 'pmpro') . '</strong> ' . esc_html($level->name) . '</li>';
          $content .= '<li><strong>' . esc_html__('Amount Paid:', 'pmpro') . '</strong> ' . esc_html($order->total . ' ' . $pmpro_currency) . '</li>';
          $content .= '</ul>';
          ob_start();
          if (file_exists(get_stylesheet_directory() . '/paid-memberships-pro/pages/confirmation.php')) {
            include(get_stylesheet_directory() . '/paid-memberships-pro/pages/confirmation.php');
          } elseif (defined('PMPRO_DIR') && file_exists(PMPRO_DIR . '/pages/confirmation.php')) {
            include(PMPRO_DIR . '/pages/confirmation.php');
          }
          return $content . ob_get_clean();
        }

        private static function schedule_checkout_emails($user_id, $order_id)
        {
          wp_schedule_single_event(time() + 5, 'kkd_pmpro_rave_send_checkout_emails', array(absint($user_id), absint($order_id)));
        }

        private static function payment_options($method)
        {
          $map = array('card' => 'card', 'account' => 'account,banktransfer', 'ussd' => 'ussd', 'both' => 'card,ussd,account,banktransfer');
          $method = sanitize_key($method);
          return isset($map[$method]) ? $map[$method] : $map['both'];
        }

        private static function sanitize_country($country)
        {
          $country = strtoupper(sanitize_key($country));
          return in_array($country, array('NG', 'GH', 'KE', 'ZA'), true) ? $country : 'NG';
        }

        private static function request_tx_ref()
        {
          foreach (array('tx_ref', 'txref', 'txRef') as $key) {
            if (isset($_GET[$key])) {
              return self::sanitize_tx_ref(wp_unslash($_GET[$key]));
            }
          }
          return '';
        }

        private static function request_transaction_id()
        {
          foreach (array('transaction_id', 'id') as $key) {
            if (isset($_GET[$key])) {
              return absint(wp_unslash($_GET[$key]));
            }
          }
          return 0;
        }

        private static function sanitize_tx_ref($value)
        {
          return preg_replace('/[^A-Za-z0-9_.-]/', '', sanitize_text_field((string) $value));
        }

        private static function object_value($object, $keys)
        {
          foreach ($keys as $key) {
            if (is_object($object) && isset($object->{$key})) {
              return $object->{$key};
            }
          }
          return null;
        }

        private static function safe_error($message)
        {
          return '<div id="pmpro_message" class="pmpro_message pmpro_error"><h2>' . esc_html__('Payment Error', 'pmpro') . '</h2><p>' . esc_html($message) . '</p></div>';
        }

        private static function safe_notice($title, $message)
        {
          return '<div id="pmpro_message" class="pmpro_message pmpro_alert"><h2>' . esc_html($title) . '</h2><p>' . esc_html($message) . '</p></div>';
        }

        private static function request_id()
        {
          return !empty($_SERVER['HTTP_X_REQUEST_ID']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REQUEST_ID'])) : (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('req_', true));
        }

        private static function log($level, $message, $meta = array(), $request_id = '')
        {
          error_log(wp_json_encode(array(
            'timestamp' => gmdate('c'),
            'level' => in_array($level, array('debug', 'info', 'error'), true) ? $level : 'info',
            'requestId' => $request_id ? $request_id : self::request_id(),
            'userId' => get_current_user_id() ?: null,
            'message' => sanitize_text_field($message),
            'meta' => is_array($meta) ? $meta : array(),
          )));
        }
      }
    }

    add_action('init', array('PMProGateway_rave', 'init'));
    add_filter('plugin_action_links', array('PMProGateway_rave', 'plugin_action_links'), 10, 2);
  }
}







