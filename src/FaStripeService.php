<?php

namespace Drupal\fa_stripe;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Stripe\StripeClient;

/**
 * FA Stripe Service class.
 */
class FaStripeService {

  /**
   * SDK Source for logging.
   */
  const SDK_SRC = 'SDK';

  /**
   * Webhook Source for logging.
   */
  const WEBHOOK_SRC = 'WEBHOOK';

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Th stripe config.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $stripeConfig;

  /**
   * Constructs a FADocumentBuilderService instance.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory,
      ConfigFactoryInterface $config
  ) {
    $this->loggerFactory = $logger_factory->get('fa_stripe');
    $this->stripeConfig = $config->get('fa_stripe.settings');

  }

  /**
   * Get Stripe Client object.
   */
  public function getStripeClient() {
    // Get Stripe config details.
    // $stripe_env = $this->stripeConfig->get('environment');
    $stripe_secret_key = $this->stripeConfig->get('secret_key');
    //'sk_test_51NjyiJKxJITPWWHaPoUxTlnwF9W74iVDnrE9ySgAS4o2Gdk95uBIa11vYnZzNe4DpLslVTd931rnDwSRcRpOTbke00IPlCqr6p'; //$config['fa_stripe'][$stripe_env]['secret'];
    if (!$stripe_secret_key) {
      $error_log['source'] = self::SDK_SRC;
      $error_log['api_method'] = __FUNCTION__;
      $error_log['message'] = 'Stripe API Secret Key not set.';
      $this->faLogger($error_log, TRUE);
    }
    // Create Stripe client object using the secret key.
    $stripe = new StripeClient($stripe_secret_key);
    return $stripe;
  }

  public function createStripeCustomer($email, $full_name) {
    try {
      $stripe = $this->getStripeClient();
      // Check if the customer already exists with the given email
      $existing_customer = $stripe->customers->all([
          "email" => $email,
          "limit" => 1,
      ]);

      if (count($existing_customer->data)) {
          // Customer already exists, return the customer ID
          return $existing_customer->data[0]->id;
      } else {
          // Create a new customer
          $new_customer = $stripe->customers->create([
              'email' => $email,
              'name' => $full_name,
          ]);

          // Return the customer ID of the newly created customer
          return $new_customer->id;
      }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        // Handle any exceptions that may occur during API request
        $this->faLogger($e->getMessage(), TRUE);
    }
  }
  public function getPaymentIntentStatus($pi) {
    try {
      $stripe = $this->getStripeClient();

      $pi_object = $stripe->paymentIntents->retrieve($pi, []);
      return $pi_object->status;
    }
    catch (\Stripe\Exception\ApiErrorException $e) {
      // Handle any exceptions that may occur during API request
      $this->loggerFactory->error($e->getMessage());
    }
  }
  public function createCheckout($customer_id, $price, $product_name, $sid) {
   try {
      $stripe = $this->getStripeClient();

      $full_domain = \Drupal::request()->getSchemeAndHttpHost();

      $checkout_session = $stripe->checkout->sessions->create([
        'customer' => $customer_id,
        'payment_method_types' => ['card', 'link'],
        'line_items' => [
            [
            'price_data' => [
                'currency' => 'aed',
                'unit_amount_decimal' => $price,
                'product_data' => [
                  'name' => $product_name, 
                ],
            ],
            'quantity' => 1,
            ],
        ],
        'mode' => 'payment',
        'success_url' => $full_domain . $this->stripeConfig->get('success_url'),
        'cancel_url' => $full_domain . '/user',
        'metadata' => [
            'webform_submission_id' => $sid
        ],
        'shipping_address_collection' => [
          'allowed_countries' => ['AE'], // Set the default country to UAE
        ],
      ]);
      // Return the Checkout session URL.
    return $checkout_session->url;
   }
    catch (\Stripe\Exception\ApiErrorException $e) {
      // Handle any exceptions that may occur during API request
      $this->loggerFactory->error($e->getMessage());
    }
 
   }

   /**
    * Log Stripe interactions and error in watchdog.
    */
    public function faLogger($log, $error = NULL) {
      if (!$error) {
        // Convert PHP Array to JSON.
        $json_log =  json_encode($log);
        $this->loggerFactory->notice("{fa_log}", [
          'fa_log' => $json_log,
        ]);
      }
      else {
        // Get Error HTTP Code, Type and Message.
        // if (method_exists($error, getHttpStatus)) {
        //   $log['http_code'] = $error->getHttpStatus();
        // }
        // if (method_exists($error, getError)) {
        //   $log['type'] = $error->getError()->type;
        //   $log['message'] = $error->getError()->message;
        // }
        $log['created'] = time();
        $json_log = json_encode($log);
        $this->loggerFactory->error("{fa_log}",[
          'fa_log' => $json_log,
        ]);
      }
    }

}
