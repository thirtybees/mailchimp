<?php
/**
 * 2017 Thirty Bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    Thirty Bees <modules@thirtybees.com>
 * @copyright 2017 Thirty Bees
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class AdminCustomersController extends AdminCustomersControllerCore
{
    public function processChangeNewsletterVal()
    {
        $customer = new Customer($this->id_object);
        if (!Validate::isLoadedObject($customer)) {
            $this->errors[] = Tools::displayError('An error occurred while updating customer information.');
        }
        $customer->newsletter = $customer->newsletter ? 0 : 1;
        if (!$customer->update()) {
            $this->errors[] = Tools::displayError('An error occurred while updating customer information.');
        } else {
            // MARK: MailChimp integration
            $mailchimp = Module::getInstanceByName('mailchimp');
            if ($mailchimp && $mailchimp->active) { // Check if mailchimp module is up and running
                require_once _PS_MODULE_DIR_ . 'mailchimp/classes/MailChimpSubscriber.php';
                $subscription = (bool)$customer->newsletter ? SUBSCRIPTION_SUBSCRIBED : SUBSCRIPTION_UNSUBSCRIBED;
                $iso = LanguageCore::getIsoById($customer->id_lang);
                $customerMC = new MailChimpSubscriber(
                    $customer->email,
                    $subscription,
                    $customer->firstname,
                    $customer->lastname,
                    $_SERVER['REMOTE_ADDR'],
                    $mailchimp->getMailchimpLanguageByIso($iso),
                    date('Y-m-d H:i:s')
                );
                if (!$mailchimp->addOrUpdateSubscription($customerMC)) {
                    PrestaShopLogger::addLog('MailChimp customer subscription failed: ' . $mailchimp->_mailchimp->getLastError());
                }
            }
            // //
        }
        Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token);
    }
}