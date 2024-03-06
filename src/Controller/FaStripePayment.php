<?php
/**
 * @file
 * Contains \Drupal\fa_stripe\Controller\FaStripeController.
 */

namespace Drupal\fa_stripe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * FaStripePayment Controller class.
 */
class FaStripePayment extends ControllerBase {

   /**
    * FA Stripe module 'fa_stripe.settings' configuration.
    * @var \Drupal\Core\Config\ImmutableConfig
    */
   protected $stripeConfig;

   /**
    * Logger Interface. 
    * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
    */
   protected $logger;

   /**
    * Constructs a FaStripePayment Object.
    */
    public function __construct(
        ConfigFactoryInterface $config,
        LoggerChannelFactoryInterface $logger
    ) {
        $this->stripeConfig = $config->get('fa_stripe.settings');
        $this->logger = $logger->get('fa_stripe');
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('config.factory'),
            $container->get('logger.factory')
        );
    }

   public function createCheckout($sid) {
    $stripeSecretKey = $this->stripeConfig->get('secret_key');
    
    $webform_submission = WebformSubmission::load($sid);
    $webform_type = $webform_submission->getWebform()->id();
    $webform = \Drupal\webform\Entity\Webform::load($webform_type);
    // Get the webform title.
    $webform_title = $webform->label();
    $common_service = \Drupal::service('fa.common_service');
    $common_service->processWebformSubmission($webform_type, $sid);
    
    $data = $webform_submission->getData();
    $pricing_package_name = $data['pricing_package'];
    if ($pricing_package_name == "") {
      $response = new RedirectResponse('/');
      \Drupal::messenger()->addError(t('Please select a package'));
      return $response->send();
    }
    $user_email = $data['e_mail_address'];
    $full_name = $data['your_name'];
    // Get total price for the given submission.
    $total_price = $data[$data['pricing_package']];
    foreach ($data['additional_documents_v2'] as $additional_doc) {
      $total_price += $data[$additional_doc . '_price'];
    }

    // Add VAT @5%;
    $total_price += 0.05 * $total_price;
    // Add Admin Fee @ 2.9%
    $total_price += 0.029 * $total_price;

    // Convert underscores to spaces
    $pricing_package_name = str_replace('_', ' ', $pricing_package_name);

    // Capitalize the first letter of each word
    $pricing_package_name = ucwords($pricing_package_name);
    $stripe_payment = \Drupal::service('fa_stripe.service');
    $stripe_cid = $stripe_payment->createStripeCustomer($user_email, $full_name);
    $user = user_load_by_mail($user_email);
    $user->set('field_stripe_customer_id', $stripe_cid);
    $user->save();
    $stripe_checkout_link = $stripe_payment->createCheckout($stripe_cid, round($total_price, 2) * 100, $webform_title . ' ' . '-' . ' ' . $pricing_package_name . ' ' . 'Package', $sid);

    // Redirect the user to the Checkout session URL.
    return new TrustedRedirectResponse($stripe_checkout_link);
   }
}


