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

require_once dirname(__FILE__).'/../../lib/autoload.php';

use \ThirtyBees\MailChimp\Webhook;

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
        PrestaShopLogger::addLog('Hook worked, json: ' . json_encode($_POST));
        Webhook::subscribe('subscribe', array($this, 'processSubscribe'));
        Webhook::subscribe('unsubscribe', array($this, 'processUnsubscribe'));
        Webhook::subscribe('cleaned', array($this, 'processUnsubscribe'));
        Webhook::subscribe('upemail', array($this, 'processEmailChanged'));

        die($this->status);
    }

    /**
     * Process subscribe list
     *
     * @param $data
     *
     * @return bool Indicates whether the customer was successfully subscribed
     */
    public function processSubscribe($data)
    {
        \PrestaShopLogger::addLog(Tools::jsonEncode($data));
        $this->status = '1';

        return \Db::getInstance()->update(
            'customer',
            array(
                'newsletter' => 1,
            ),
            'email = \''.pSQL($data['email']).'\''
        );
    }

    /**
     * Process unsubscribe list
     *
     * @param $data
     *
     * @return bool Indicates whether the customer was successfully unsubscribed
     */
    public function processUnsubscribe($data)
    {
        \PrestaShopLogger::addLog(Tools::jsonEncode($data));
        $this->status = '1';

        return \Db::getInstance()->update(
            'customer',
            array(
                'newsletter' => 0,
            ),
            'email = \''.pSQL($data['email']).'\''
        );
    }

    /**
     * Process email changed event
     *
     * @param $data
     *
     * @return bool Indicates whether the event was successfully processed
     */
    public function processEmailChanged($data)
    {
        return \Db::getInstance()->update(
            'customer',
            array(
                'newsletter' => 0,
            ),
            'email = \''.pSQL($data['old_email']).'\''
        ) && \Db::getInstance()->update(
            'customer',
            array(
                'newsletter' => 1,
            ),
            'email = \''.pSQL($data['new_email']).'\''
        );
    }
}
