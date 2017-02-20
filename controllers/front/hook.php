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
 *  @author    Thirty Bees <modules@thirtybees.com>
 *  @copyright 2017 Thirty Bees
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('TB_VERSION')) {
    exit;
}

use MailChimpModule\MailChimp\Webhook;

require_once __DIR__.'/../../classes/autoload.php';

/**
 * Class StripeHookModuleFrontController
 */
class MailChimpHookModuleFrontController extends ModuleFrontController
{
    /** @var \MailChimp $module */
    public $module;

    public $status = '0';

    /**
     * StripeHookModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->ssl = \Tools::usingSecureMode();

        header('Content-Type: text/plain');
        $this->postProcess();

        die($this->status);
    }

    /**
     * Post process
     */
    public function postProcess()
    {
        Webhook::subscribe('subscribe', [$this, 'processSubscribe']);
        Webhook::subscribe('unsubscribe', [$this, 'processUnsubscribe']);
        Webhook::subscribe('cleaned', [$this, 'processUnsubscribe']);
        Webhook::subscribe('upemail', [$this, 'processEmailChanged']);
    }

    /**
     * Process subscribe list
     *
     * @param array $data
     *
     * @return bool Indicates whether the customer was successfully subscribed
     */
    public function processSubscribe($data)
    {
        Logger::addLog('processSubscribe hook worked, json: '.json_encode($data));
        $this->status = '1';
        // Update customer table
        $customer = \Db::getInstance()->update(
            'customer',
            [
                'newsletter' => 1,
            ],
            'email = \''.pSQL($data['email']).'\''
        );
        // Update newsletter table
        $newsletter = \Db::getInstance()->insert(
            'newsletter',
            [
                'email' => pSQL($data['email']),
                'newsletter_date_add' => date('Y-m-d H:i:s'),
                'ip_registration_newsletter' => $_SERVER['REMOTE_ADDR'],
                'active' => 1,
            ]
        );
        if (!$customer) {
            Logger::addLog('processSubscribe hook failed for customer table.');
        }
        if (!$newsletter) {
            Logger::addLog('processSubscribe hook failed for newsletter table.');
        }

        return $customer && $newsletter;
    }

    /**
     * Process unsubscribe list
     *
     * @param array $data
     *
     * @return bool Indicates whether the customer was successfully unsubscribed
     */
    public function processUnsubscribe($data)
    {
        Logger::addLog('processUnsubscribe hook worked, json: '.json_encode($data));
        $this->status = '1';

        // Update customer table
        $customer = \Db::getInstance()->update(
            'customer',
            [
                'newsletter' => 0,
            ],
            'email = \''.pSQL($data['email']).'\''
        );
        // Update newsletter table
        $newsletter = \Db::getInstance()->update(
            'newsletter',
            [
                'active' => 0,
            ],
            'email = \''.pSQL($data['email']).'\''
        );
        if (!$customer) {
            Logger::addLog('processUnsubscribe hook failed for customer table.');
        }
        if (!$newsletter) {
            Logger::addLog('processUnsubscribe hook failed for newsletter table.');
        }

        return $customer && $newsletter;
    }

    /**
     * Process email changed event
     *
     * @param array $data
     *
     * @return bool Indicates whether the event was successfully processed
     */
    public function processEmailChanged($data)
    {
        Logger::addLog('processEmailChanged hook worked, json: '.json_encode($data));
        // Update customer table
        $customer = \Db::getInstance()->update(
            'customer',
            [
                'newsletter' => 0,
            ],
            'email = \''.pSQL($data['old_email']).'\''
        ) && \Db::getInstance()->update(
            'customer',
            [
                'newsletter' => 1,
            ],
            'email = \''.pSQL($data['new_email']).'\''
        );
        // Update newsletter table
        $newsletter = \Db::getInstance()->update(
            'newsletter',
            [
                'email' => $data['new_email'],
            ],
            'email = \''.pSQL($data['old_email']).'\''
        );
        if (!$customer) {
            Logger::addLog('processUnsubscribe hook failed for customer table.');
        }
        if (!$newsletter) {
            Logger::addLog('processUnsubscribe hook failed for newsletter table.');
        }

        return $customer && $newsletter;
    }
}
