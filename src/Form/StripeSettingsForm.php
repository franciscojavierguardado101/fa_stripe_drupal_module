<?php

namespace Drupal\fa_stripe\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class StripeSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['fa_stripe.settings'];
  }

  public function getFormId() {
    return 'fa_stripe_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('fa_stripe.settings');
    $form['fa_stripe'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('FA Stripe Settings')
    ];
    $form['fa_stripe']['environment'] = [
        '#type' => 'select',
        '#title' => 'Stripe Environment',
        '#options' => ['TEST' => 'Test', 'LIVE' => 'Live'],
        '#default_value' => $config->get('environment')
    ];
    $form['fa_stripe']['secret_key'] = [
      '#type' => 'textfield',
      '#title' => 'Secret Key',
      '#default_value' => $config->get('secret_key')
    ];
    $form['fa_stripe']['webhook_secret'] = [
      '#type' => 'textfield',
      '#title' => 'Webhook Secret Key',
      '#default_value' => $config->get('webhook_secret')
    ];
    $form['fa_stripe']['success_url'] = [
      '#type' => 'textfield',
      '#title' => 'Success URL',
      '#default_value' => $config->get('success_url') ?: '/user'
    ];
    $form['fa_invoice'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('FA Invoice Settings')
    ];
    $form['fa_invoice']['last_invoice_number'] = [
      '#type' => 'textfield',
      '#title' => 'Last Invoice Number',
      '#default_value' => $config->get('last_invoice_number')
    ];
    $form['fa_invoice']['last_invoice_month_year'] = [
      '#type' => 'textfield',
      '#title' => 'Last Invoice Month Year',
      '#default_value' => $config->get('last_invoice_month_year')
    ];
    $form['fa_invoice']['invoice_counter'] = [
      '#type' => 'textfield',
      '#title' => 'Invoice Counter',
      '#default_value' => $config->get('invoice_counter')
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('fa_stripe.settings');
    $config->set('environment', $form_state->getValue('environment'))
        ->set('secret_key', $form_state->getValue('secret_key'))
        ->set('webhook_secret', $form_state->getValue('webhook_secret'))
        ->set('success_url', $form_state->getValue('success_url'))
        ->set('last_invoice_number', $form_state->getValue('last_invoice_number'))
        ->set('last_invoice_month_year', $form_state->getValue('last_invoice_month_year'))
        ->set('invoice_counter', $form_state->getValue('invoice_counter'))
        ->save();
    \Drupal::messenger()->addMessage($this->t('Stripe configuration saved successfully.'));
  }
}
