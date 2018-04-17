<?php
/**
 * 2018 thirty bees
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
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2017-2018 thirty bees
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_TB_VERSION_') && !defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class StripeHookModuleFrontController
 */
class MailChimpHookModuleFrontController extends ModuleFrontController
{
    /** @var \MailChimp $module */
    public $module;

    public $status = '0';

    /**
     * MailChimpHookModuleFrontController constructor.
     *
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        $this->ssl = \Tools::usingSecureMode();

        header('Content-Type: text/plain');
        $this->init();

        die($this->status);
    }

    /**
     * Prevent displaying the maintenance page
     *
     * @return void
     */
    protected function displayMaintenancePage()
    {
    }

    /**
     * Process webhooks
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function init()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        Logger::addLog(json_encode($data));
        switch ($data['type']) {
            case 'subscribe':
                $this->processSubscribe($data);
                break;
            case 'unsubscribe':
            case 'cleaned':
                $this->processSubscribe($data);
                break;
            case 'upemail':
                $this->processEmailChanged($data);
                break;
        }
    }

    /**
     * Process subscribe list
     *
     * @param array $data
     *
     * @return bool Indicates whether the customer was successfully subscribed
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function processSubscribe($data)
    {
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
                'email'                      => pSQL($data['email']),
                'newsletter_date_add'        => date('Y-m-d H:i:s'),
                'ip_registration_newsletter' => $_SERVER['REMOTE_ADDR'],
                'active'                     => 1,
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
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function processUnsubscribe($data)
    {
        $this->status = '1';

        // Update customer table
        $customer = \Db::getInstance()->update(
            'customer',
            [
                'newsletter' => 0,
            ],
            '`email` = \''.pSQL($data['email']).'\''
        );
        // Update newsletter table
        $newsletter = \Db::getInstance()->update(
            'newsletter',
            [
                'active' => 0,
            ],
            '`email` = \''.pSQL($data['email']).'\''
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
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function processEmailChanged($data)
    {
        // Update customer table
        $customer = \Db::getInstance()->update(
            'customer',
            [
                'newsletter' => 0,
            ],
            '`email` = \''.pSQL($data['old_email']).'\''
        ) && \Db::getInstance()->update(
            'customer',
            [
                'newsletter' => 1,
            ],
            '`email` = \''.pSQL($data['new_email']).'\''
        );
        // Update newsletter table
        $newsletter = \Db::getInstance()->update(
            'newsletter',
            [
                'email' => $data['new_email'],
            ],
            '`email` = \''.pSQL($data['old_email']).'\''
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
