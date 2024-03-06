<?php

namespace Drupal\fa_stripe\Controller;

use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Stripe\Stripe;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Config\ConfigFactory;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\node\Entity\Node;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * StripeWebhookController class.
 */
class StripeWebhookController extends ControllerBase {
    protected $logger;
    protected $requestStack;
    protected $configFactory;

    /**
     * Creates a new instance.
     */
    public function __construct(LoggerChannelFactoryInterface $logger,
    RequestStack $request_stack,
    ConfigFactory $config_factory
    ) {
        $this->logger = $logger->get('fa_stripe');
        $this->requestStack = $request_stack;
        $this->configFactory = $config_factory;
    }

    /**
     * {@inheritDoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('logger.factory'),
            $container->get('request_stack'),
            $container->get('config.factory'),
        );
    }

    /**
     * Handle Webhook.
     */
    public function handle(Request $request) {
        $config = $this->configFactory->get('fa_stripe.settings');
        $stripe_secret = $config->get('secret_key');
        // $stripe_secret = 'sk_test_51NjyiJKxJITPWWHaPoUxTlnwF9W74iVDnrE9ySgAS4o2Gdk95uBIa11vYnZzNe4DpLslVTd931rnDwSRcRpOTbke00IPlCqr6p';
        $webhook_secret = $config->get('webhook_secret');
        //'whsec_958bd226e5d693d8b5a6f4a4ef24efb4b146bfe6cab116eea9ae695653783332';
        Stripe::setApiKey($stripe_secret);

        // Get the live or test envrionment.
        $environment = $config->get('environment');
        // Get the payload.
        $payload = @file_get_contents("php://input");
        // Get the stripe signature.
        $signature = $request->server->get('HTTP_STRIPE_SIGNATURE');
        // Get the webhook secret.
        $endpoint_secret = $webhook_secret; //$config->get("apikey.$environment.webhook");
        try {
            // Construct the event to validate the webhook signature.
            $event = Webhook::constructEvent(
            $payload, $signature, $endpoint_secret
            );
            // Handle events.
            switch ($event->type) {
                case 'customer.created': 
                case 'customer. updated':
                    // Log the webhook event.
                    $stripe_log['event_id'] = $event->id;
                    $stripe_log['type'] = $event->type;
                    $stripe_log['cid'] = $event->data->object->id;
                    $stripe_log['created'] = $event->created;
                    // $this->subscriptionService->nPlusLogger ($stripe_log, FALSE);
                    $this->logger->info('<pre>' . print_r($stripe_log, TRUE) . '</pre>');
                    break;
                case 'customer.deleted':
                    // Log the webhook event.
                    break;
                case 'payment_intent.succeeded':
                    $cid = $event->customer;
                    break;
                case 'checkout.session.completed':
                    $this->logger->info('<pre>' . print_r($event, TRUE) . '</pre>');
                    $invoice_nid =  $event->data->object->metadata->nid;
                    if ($invoice_nid) {
                        $invoice_node = Node::load($invoice_nid);
                        $invoice_node->set('field_paid', TRUE);
                        $current_date = new DrupalDateTime('now');

                        // Set the current date to the date field.
                        $invoice_node->set('field_payment_date', $current_date->format('Y-m-d'));
                        $invoice_node->set('field_payment_channel', 'stripe');
                        $invoice_node->save();
                        $data = \Drupal::service('fa.common_service')->emailInvoice($invoice_nid);
                        $email_subject = 'Invoice Generated' . ' ' . $data[2];
                        $attachment_path = [];
                        \Drupal::service('fa.common_service')->sendEmailWithAttachment($data[1], $email_subject, $data[0], $attachment_path);               
                    }
                    $webform_submission_id = $event->data->object->metadata->webform_submission_id;

                    if ($webform_submission_id) {
                        $pi = $event->data->object->payment_intent;
                        $stripe_service = \Drupal::service('fa_stripe.service');
                        $payment_status = $stripe_service->getPaymentIntentStatus($pi);
                         $this->logger->info('<pre>' . print_r($payment_status, TRUE) . '</pre>');

                        $webform_submission = WebformSubmission::load($webform_submission_id);
                        if ($payment_status === 'succeeded') {
                            $webform_submission->setElementData('payment_status', 'Paid');
                        }
                        else {
                            $webform_submission->setElementData('payment_status', 'Pending');
                        }
                        $webform_submission->save();

                    }
                    $this->logger->info('Checkout session complete');
                    // $this->logger->error($event);
                    break;
                default:
                break;
            }
        }
        catch (\UnexpectedValueException $e) {
            return new Response('Bad Request', Response::HTTP_BAD_REQUEST);
        }
        catch (SignatureVerificationException $e) {
            return new Response('Bad Request', Response::HTTP_BAD_REQUEST);
        }

        return new Response("OK", Response::HTTP_OK);
        
    }
}
