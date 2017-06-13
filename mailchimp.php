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

use MailChimpModule\MailChimpCart;
use MailChimpModule\MailChimpOrder;
use MailChimpModule\MailChimpProduct;
use MailChimpModule\MailChimpRegisteredWebhook;
use MailChimpModule\MailChimpShop;
use MailChimpModule\MailChimpSubscriber;
use MailChimpModule\MailChimpTracking;

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/classes/autoload.php';

/**
 * Class MailChimp
 *
 * @since 1.0.0
 */
class MailChimp extends Module
{
    // Always store this key for the first store and the first shop group
    const API_KEY = 'MAILCHIMP_API_KEY';
    const API_KEY_VALID = 'MAILCHIMP_API_KEY_VALID';
    const CONFIRMATION_EMAIL = 'MAILCHIMP_CONFIRMATION_EMAIL';
    const UPDATE_EXISTING = 'MAILCHIMP_UPDATE_EXISTING';
    const IMPORT_ALL = 'MAILCHIMP_IMPORT_ALL';
    const IMPORT_OPTED_IN = 'MAILCHIMP_IMPORT_OPTED_IN';
    const LAST_IMPORT = 'MAILCHIMP_LAST_IMPORT';
    const LAST_IMPORT_ID = 'MAILCHIMP_LAST_IMPORT_ID';
    const API_TIMEOUT = 10;

    const SUBSCRIBERS_SYNC_COUNT = 'MAILCHIMP_SUBSCRIBERS_SYNC_COUNT';
    const SUBSCRIBERS_SYNC_TOTAL = 'MAILCHIMP_SUBSCRIBERS_SYNC_TOTAL';
    const PRODUCTS_SYNC_COUNT = 'MAILCHIMP_PRODUCTS_SYNC_COUNT';
    const PRODUCTS_SYNC_TOTAL = 'MAILCHIMP_PRODUCTS_SYNC_TOTAL';
    const CARTS_SYNC_COUNT = 'MAILCHIMP_CARTS_SYNC_COUNT';
    const CARTS_SYNC_TOTAL = 'MAILCHIMP_CARTS_SYNC_TOTAL';
    const ORDERS_SYNC_COUNT = 'MAILCHIMP_ORDERS_SYNC_COUNT';
    const ORDERS_SYNC_TOTAL = 'MAILCHIMP_ORDERS_SYNC_TOTAL';

    const SUBSCRIBERS_LAST_SYNC = 'PRODUCTS_LAST_SYNC';
    const PRODUCTS_LAST_SYNC = 'PRODUCTS_LAST_SYNC';
    const CARTS_LAST_SYNC = 'CARTS_LAST_SYNC';
    const ORDERS_LAST_SYNC = 'ORDERS_LAST_SYNC';

    const EXPORT_CHUNK_SIZE = 1000;

    const MENU_IMPORT = 1;
    const MENU_SHOPS = 2;
    const MENU_PRODUCTS = 3;
    const MENU_CARTS = 4;
    const MENU_ORDERS = 5;

    const COOKIE_LIFETIME = 259200;

    /** @var string $baseUrl */
    public $baseUrl;
    /** @var \MailChimpModule\MailChimp\MailChimp $mailChimp */
    protected $mailChimp;
    /** @var array $mailChimpLanguages */
    public static $mailChimpLanguages = [
        'en' => 'en',
        'ar' => 'ar',
        'af' => 'af',
        'be' => 'be',
        'bg' => 'bg',
        'ca' => 'ca',
        'zh' => 'zh',
        'hr' => 'hr',
        'cs' => 'cs',
        'da' => 'da',
        'nl' => 'nl',
        'et' => 'et',
        'fa' => 'fa',
        'fi' => 'fi',
        'fr' => 'fr',
        'qc' => 'fr_CA',
        'de' => 'de',
        'el' => 'el',
        'he' => 'he',
        'hi' => 'hi',
        'hu' => 'hu',
        'is' => 'is',
        'id' => 'id',
        'ga' => 'ga',
        'it' => 'it',
        'ja' => 'ja',
        'km' => 'km',
        'ko' => 'ko',
        'lv' => 'lv',
        'lt' => 'lt',
        'mt' => 'mt',
        'ms' => 'ms',
        'mk' => 'mk',
        'no' => 'no',
        'pl' => 'pl',
        'br' => 'pt',
        'pt' => 'pt_PT',
        'ro' => 'ro',
        'ru' => 'ru',
        'sr' => 'sr',
        'sk' => 'sk',
        'si' => 'sl',
        'mx' => 'es',
        'es' => 'es',
        'sw' => 'sw',
        'sv' => 'sv',
        'ta' => 'ta',
        'th' => 'th',
        'tr' => 'tr',
        'uk' => 'uk',
        'vi' => 'vi',
        'gb' => 'en',
    ];

    /**
     * MailChimp constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->name = 'mailchimp';
        $this->tab = 'advertising_marketing';
        $this->version = '1.1.1';
        $this->author = 'thirty bees';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->tb_versions_compliancy = '~1.0.1';

        parent::__construct();

        $this->displayName = $this->l('MailChimp');
        $this->description = $this->l('Synchronize with MailChimp');

        $this->controllers = ['cron', 'hook'];

        if (isset(Context::getContext()->employee->id) && Context::getContext()->employee->id) {
            $this->baseUrl = $this->context->link->getAdminLink('AdminModules', true).'&'.http_build_query([
                'configure' => $this->name,
                'tab_module' => $this->tab,
                'module_name' => $this->name,
            ]);
        }
    }

    /**
     * Install this module
     *
     * @return bool Indicates whether the module was successfully installed
     *
     * @since 1.0.0
     */
    public function install()
    {
        if (!parent::install()
            || !MailChimpRegisteredWebhook::createDatabase()
            || !MailChimpShop::createDatabase()
            || !MailChimpProduct::createDatabase()
            || !MailChimpCart::createDatabase()
            || !MailChimpOrder::createDatabase()
            || !MailChimpTracking::createDatabase()
        ) {
            return false;
        }

        $this->installDbIndices();

        $this->registerHook('displayHeader');
        $this->registerHook('displayBackOfficeHeader');
        $this->registerHook('footer'); // to catch guest newsletter subscription
        $this->registerHook('actionCustomerAccountAdd'); // front office account creation
        $this->registerHook('actionObjectCustomerAddAfter'); // front office account creation
        $this->registerHook('actionObjectCustomerUpdateAfter'); // front office account creation
        $this->registerHook('actionAdminCustomersControllerSaveAfter'); // back office update customer
        $this->registerHook('actionValidateOrder'); // validate order

        return true;
    }

    /**
     * Uninstall this module
     *
     * @return bool Indicates whether this module has been successfully uninstalled
     *
     * @since 1.0.0
     */
    public function uninstall()
    {
        if (!parent::uninstall()
            || !Configuration::deleteByName(self::API_KEY)
            || !Configuration::deleteByName(self::CONFIRMATION_EMAIL)
            || !Configuration::deleteByName(self::IMPORT_OPTED_IN)
            || !Configuration::deleteByName(self::LAST_IMPORT)
            || !Configuration::deleteByName(self::LAST_IMPORT_ID)
            || !MailChimpRegisteredWebhook::dropDatabase()
            || !MailChimpShop::dropDatabase()
            || !MailChimpProduct::dropDatabase()
            || !MailChimpCart::dropDatabase()
            || !MailChimpOrder::dropDatabase()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Module configuration page
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function getContent()
    {
        if (Tools::isSubmit('ajax')) {
            $this->displayAjax();

            return '';
        } else {
            $this->postProcess();

            return $this->displayMainPage();
        }
    }

    /**
     * Get lists
     *
     * @param bool $prepare Prepare for display in e.g. HelperForm
     *
     * @return array|bool
     */
    public function getLists($prepare = false)
    {
        try {
            $mailChimp = new \MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY, null, null, 1));
            $mailChimp->verifySsl = false;
            $lists = $mailChimp->get('lists');

            if ($prepare) {
                $preparedList = [];
                foreach ($lists['lists'] as $list) {
                    $preparedList[$list['id']] = $list['name'];
                }

                return $preparedList;
            }

            return $lists['lists'];
        } catch (Exception $e) {
            $this->addError($e->getMessage());
        }

        return false;
    }

    /**
     * Hook to display header
     *
     * @return void
     *
     * @since 1.1.0
     **/
    public function hookDisplayHeader()
    {
        // Set MailChimp tracking code
        if (Tools::isSubmit('mc_tc') || Tools::isSubmit('mc_cid')) {
            $cookie = new Cookie('tb_mailchimp');
            $cookie->mc_tc = Tools::getValue('mc_tc');
            $cookie->mc_cid = Tools::getValue('mc_cid');
            $cookie->setExpire(self::COOKIE_LIFETIME);
            $cookie->write();
        }
    }

    /**
     * Hook to front office footer
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function hookFooter()
    {
        if (Tools::isSubmit('submitNewsletter') && Tools::isSubmit('email')) {
            if (Validate::isEmail(Tools::getValue('email'))) {
                $iso = Language::getIsoById($this->context->cookie->id_lang);
                $customer = new MailChimpSubscriber(
                    Tools::getValue('email'),
                    MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED,
                    null,
                    null,
                    Tools::getRemoteAddr(),
                    static::getMailChimpLanguageByIso($iso),
                    date('Y-m-d H:i:s')
                );
                if (!$this->addOrUpdateSubscription($customer)) {
                    if (is_object($this->mailChimp)) {
                        Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
                    } else {
                        Logger::addLog('MailChimp customer subscription failed');
                    }
                }
            }
        }
    }

    /**
     * Hook action validate order
     *
     * @param array $params
     *
     * @return void
     *
     * @since 1.1.0
     */
    public function hookActionValidateOrder($params)
    {
        try {
            $cookie = new Cookie('tb_mailchimp');
            if ($cookie->mc_tc || $cookie->mc_cid) {
                /** @var Order $order */
                $order = $params['order'];
                if (!($order instanceof Order)) {
                    return;
                }
                $mailChimpTracking = new MailChimpTracking();
                $mailChimpTracking->mc_tc = $cookie->mc_tc;
                $mailChimpTracking->mc_cid = $cookie->mc_cid;
                $mailChimpTracking->id_order = $order->id;

                $mailChimpTracking->save();

                unset($cookie->mc_tc);
                unset($cookie->mc_cid);
                $cookie->write();
            }
        } catch (Exception $e) {
            if (isset ($cookie->mc_tc) && isset($params['order']->id)) {
                Logger::addLog("Unable to set Mailchimp tracking code $cookie->mc_tc for Order {$params['order']->id}", 3);
            } elseif (isset ($cookie->mc_cid) && isset($params['order']->id)) {
                Logger::addLog("Unable to set Mailchimp tracking code $cookie->mc_cid for Order {$params['order']->id}", 3);
            } else {
                Logger::addLog('Unable to set Mailchimp tracking code for Order', 3);
            }
        }
    }

    /**
     * @param string $iso
     *
     * @return mixed|string
     *
     * @since 1.0.0
     */
    public static function getMailChimpLanguageByIso($iso)
    {
        $lang = static::$mailChimpLanguages[$iso];
        if ($lang == '') {
            $lang = 'en';
        }

        return $lang;
    }

    /**
     * @param MailChimpSubscriber $subscription
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function addOrUpdateSubscription(MailChimpSubscriber $subscription)
    {
        return $this->importList([$subscription]);
    }

    /**
     * @param array $params
     *
     * @since 1.0.0
     */
    public function hookActionCustomerAccountAdd($params)
    {
        // Check if creation is successful
        if (isset($params['newCustomer']) && $params['newCustomer']->email) {
            $subscription = (string) $params['newCustomer']->newsletter ? MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED : MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
            $iso = Language::getIsoById($params['newCustomer']->id_lang);
            $customer = new MailChimpSubscriber(
                $params['newCustomer']->email,
                $subscription,
                $params['newCustomer']->firstname,
                $params['newCustomer']->lastname,
                $params['newCustomer']->ip_registration_newsletter,
                static::getMailChimpLanguageByIso($iso),
                $params['newCustomer']->newsletter_date_add
            );
            if (!$this->addOrUpdateSubscription($customer)) {
                if (is_object($this->mailChimp)) {
                    Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
                } else {
                    Logger::addLog('MailChimp customer subscription failed');
                }
            }
        }
    }

    /**
     * Action update customer after
     *
     * @param array $params
     */
    public function hookActionObjectCustomerUpdateAfter($params)
    {
        /** @var Customer $customer */
        $customer = $params['object'];
        $subscription = (string) $customer->newsletter ? MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED : MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
        $iso = LanguageCore::getIsoById($customer->id_lang);
        $customerMC = new MailChimpSubscriber(
            $customer->email,
            $subscription,
            $customer->firstname,
            $customer->lastname,
            Tools::getRemoteAddr(),
            static::getMailChimpLanguageByIso($iso),
            date('Y-m-d H:i:s')
        );
        if (!$this->addOrUpdateSubscription($customerMC)) {
            if (is_object($this->mailChimp)) {
                Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
            } else {
                Logger::addLog('MailChimp customer subscription failed');
            }
        }

    }

    /**
     * Action add customer after
     *
     * @param array $params
     */
    public function hookActionObjectCustomerAddAfter($params)
    {
        /** @var Customer $customer */
        $customer = $params['object'];

        $subscription = (string) $customer->newsletter ? MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED : MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
        $iso = LanguageCore::getIsoById($customer->id_lang);
        $customerMC = new MailChimpSubscriber(
            $customer->email,
            $subscription,
            $customer->firstname,
            $customer->lastname,
            Tools::getRemoteAddr(),
            static::getMailChimpLanguageByIso($iso),
            date('Y-m-d H:i:s')
        );
        if (!$this->addOrUpdateSubscription($customerMC)) {
            if (is_object($this->mailChimp)) {
                Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
            } else {
                Logger::addLog('MailChimp customer subscription failed');
            }
        }
    }

    /**
     * @param array $params
     *
     * @since 1.0.0
     */
    public function hookActionAdminCustomersControllerSaveAfter($params)
    {
        if (Tools::isSubmit('newsletter')) {
            $object = $params['return'];
            $subscription = (string) Tools::getValue('newsletter') ? MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED : MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
            $iso = Language::getIsoById($object->id_lang);
            $customer = new MailChimpSubscriber(
                $object->email,
                $subscription,
                $object->firstname,
                $object->lastname,
                Tools::getRemoteAddr(),
                $this->getMailChimpLanguageByIso($iso),
                date('Y-m-d H:i:s')
            );
            if (!$this->addOrUpdateSubscription($customer)) {
                if (is_object($this->mailChimp)) {
                    Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
                } else {
                    Logger::addLog('MailChimp customer subscription failed');
                }
            }
        }
    }

    /**
     * Display export modals
     *
     * @return string
     *
     * @since 1.1.0
     */
    public function displayModals()
    {
        $modals = [
            [
                'modal_id'      => 'exportSubscribersProgress',
                'modal_class'   => 'modal-md',
                'modal_title'   => $this->l('Exporting...'),
                'modal_content' => $this->display(__FILE__, 'views/templates/admin/export_subscribers_progress.tpl'),
            ],
            [
                'modal_id'      => 'exportProductsProgress',
                'modal_class'   => 'modal-md',
                'modal_title'   => $this->l('Exporting...'),
                'modal_content' => $this->display(__FILE__, 'views/templates/admin/export_products_progress.tpl'),
            ],
            [
                'modal_id'      => 'exportCartsProgress',
                'modal_class'   => 'modal-md',
                'modal_title'   => $this->l('Exporting...'),
                'modal_content' => $this->display(__FILE__, 'views/templates/admin/export_carts_progress.tpl'),
            ],
            [
                'modal_id'      => 'exportOrdersProgress',
                'modal_class'   => 'modal-md',
                'modal_title'   => $this->l('Exporting...'),
                'modal_content' => $this->display(__FILE__, 'views/templates/admin/export_orders_progress.tpl'),
            ],
        ];

        $this->context->smarty->assign('modals', $modals);

        return $this->display(__FILE__, 'views/templates/admin/modals.tpl');
    }

    /**
     * Add information message
     *
     * @param string $message Message
     * @param bool   $private
     */
    public function addInformation($message, $private = false)
    {
        if (!Tools::isSubmit('configure')) {
            if (!$private) {
                $this->context->controller->informations[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
            }
        } else {
            $this->context->controller->informations[] = $message;
        }
    }

    /**
     * Add confirmation message
     *
     * @param string $message Message
     * @param bool   $private
     */
    public function addConfirmation($message, $private = false)
    {
        if (!Tools::isSubmit('configure')) {
            if (!$private) {
                $this->context->controller->confirmations[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
            }
        } else {
            $this->context->controller->confirmations[] = $message;
        }
    }

    /**
     * Add warning message
     *
     * @param string $message Message
     * @param bool   $private
     */
    public function addWarning($message, $private = false)
    {
        if (!Tools::isSubmit('configure')) {
            if (!$private) {
                $this->context->controller->warnings[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
            }
        } else {
            $this->context->controller->warnings[] = $message;
        }
    }

    /**
     * Add error message
     *
     * @param string $message Message
     * @param bool   $private
     */
    public function addError($message, $private = false)
    {
        if (!Tools::isSubmit('configure')) {
            if (!$private) {
                $this->context->controller->errors[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
            }
        } else {
            // Do not add error in this case
            // It will break execution of AdminController
            $this->context->controller->warnings[] = $message;
        }
    }

    /**
     * Process ajax calls
     *
     * @return void
     *
     * @since 1.1.0
     */
    public function displayAjax()
    {
        $action = ucfirst(Tools::getValue('action'));
        if (in_array($action, [
            'ExportAllSubscribers',
            'ExportAllProducts',
            'ResetProducts',
            'ExportAllCarts',
            'ResetCarts',
            'ExportAllOrders',
            'ResetOrders',
        ])) {
            $this->{'displayAjax'.$action}();
        }
    }

    /**
     * Ajax process export all subscribers
     *
     * @return void
     *
     * @since 1.1.0
     */
    public function displayAjaxExportAllSubscribers()
    {
        $idShop = (int) Tools::getValue('shop');
        if (!$idShop) {
            $idShop = Context::getContext()->shop->id;
        }

        if (Tools::isSubmit('start')) {
            $totalSubscribers = MailChimpSubscriber::countSubscribers($idShop);
            $totalChunks = ceil($totalSubscribers / self::EXPORT_CHUNK_SIZE);

            Configuration::updateValue(self::SUBSCRIBERS_SYNC_COUNT, 0, false, 0, 0);
            Configuration::updateValue(self::SUBSCRIBERS_SYNC_TOTAL, $totalChunks, false, 0, 0);

            die(json_encode([
                'totalChunks'      => $totalChunks,
                'totalSubscribers' => $totalSubscribers,
            ]));
        } elseif (Tools::isSubmit('next')) {
            $count = (int) Configuration::get(self::SUBSCRIBERS_SYNC_COUNT, null, 0, 0) + 1;
            $total = (int) Configuration::get(self::SUBSCRIBERS_SYNC_TOTAL, null, 0, 0);
            Configuration::updateValue(self::SUBSCRIBERS_SYNC_COUNT, $count, null, 0, 0);
            $remaining = $total - $count;

            $idBatch = $this->exportSubscribers(($count - 1) * self::EXPORT_CHUNK_SIZE, $idShop);

            die(json_encode([
                'success'   => true,
                'remaining' => $remaining,
                'batch_id'  => $idBatch,
            ]));
        }
    }

    /**
     * Ajax process export all products
     *
     * @return void
     *
     * @since 1.1.0
     */
    public function displayAjaxExportAllProducts()
    {
        $exportRemaining = (bool) Tools::isSubmit('remaining');
        $idShop = (int) Tools::getValue('shop');
        if (!$idShop) {
            $idShop = Context::getContext()->shop->id;
        }

        if (Tools::isSubmit('start')) {
            $totalProducts = MailChimpProduct::countProducts($idShop, $exportRemaining);
            $totalChunks = ceil($totalProducts / self::EXPORT_CHUNK_SIZE);

            Configuration::updateValue(self::PRODUCTS_SYNC_COUNT, 0, false, 0, 0);
            Configuration::updateValue(self::PRODUCTS_SYNC_TOTAL, $totalChunks, false, 0, 0);

            die(json_encode([
                'totalChunks'   => $totalChunks,
                'totalProducts' => $totalProducts,
            ]));
        } elseif (Tools::isSubmit('next')) {
            $count = (int) Configuration::get(self::PRODUCTS_SYNC_COUNT, null, 0, 0) + 1;
            $total = (int) Configuration::get(self::PRODUCTS_SYNC_TOTAL, null, 0, 0);
            Configuration::updateValue(self::PRODUCTS_SYNC_COUNT, $count, null, 0, 0);
            $remaining = $total - $count;

            $idBatch = $this->exportProducts(($count - 1) * self::EXPORT_CHUNK_SIZE, $idShop, $exportRemaining);

            die(json_encode([
                'success'   => true,
                'remaining' => $remaining,
                'batch_id'  => $idBatch,
            ]));
        }
    }

    /**
     * Ajax process export all carts
     *
     * @return void
     *
     * @since 1.1.0
     */
    public function displayAjaxExportAllCarts()
    {
        $exportRemaining = (bool) Tools::isSubmit('remaining');
        $idShop = (int) Tools::getValue('shop');
        if (!$idShop) {
            $idShop = Context::getContext()->shop->id;
        }

        if (Tools::isSubmit('start')) {
            $totalCarts = MailChimpCart::countCarts($idShop, $exportRemaining);
            $totalChunks = ceil($totalCarts / self::EXPORT_CHUNK_SIZE);

            Configuration::updateValue(self::CARTS_SYNC_COUNT, 0, false, 0, 0);
            Configuration::updateValue(self::CARTS_SYNC_TOTAL, $totalChunks, false, 0, 0);

            die(json_encode([
                'totalChunks' => $totalChunks,
                'totalCarts'  => $totalCarts,
            ]));
        } elseif (Tools::isSubmit('next')) {
            $count = (int) Configuration::get(self::CARTS_SYNC_COUNT, null, 0, 0) + 1;
            $total = (int) Configuration::get(self::CARTS_SYNC_TOTAL, null, 0, 0);
            Configuration::updateValue(self::CARTS_SYNC_COUNT, $count, null, 0, 0);
            $remaining = $total - $count;

            $idBatch = $this->exportCarts(($count - 1) * self::EXPORT_CHUNK_SIZE, $exportRemaining);

            die(json_encode([
                'success'   => true,
                'remaining' => $remaining,
                'batch_id'  => $idBatch,
            ]));
        }
    }

    /**
     * Ajax export all orders
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function displayAjaxExportAllOrders()
    {
        $exportRemaining = (bool) Tools::isSubmit('remaining');
        $idShop = (int) Tools::getValue('shop');
        if (!$idShop) {
            $idShop = Context::getContext()->shop->id;
        }

        if (Tools::isSubmit('start')) {
            $totalOrders = MailChimpOrder::countOrders($idShop, $exportRemaining);
            $totalChunks = ceil($totalOrders / self::EXPORT_CHUNK_SIZE);

            Configuration::updateValue(self::ORDERS_SYNC_COUNT, 0, false, 0, 0);
            Configuration::updateValue(self::ORDERS_SYNC_TOTAL, $totalChunks, false, 0, 0);

            die(json_encode([
                'totalChunks' => $totalChunks,
                'totalOrders' => $totalOrders,
            ]));
        } elseif (Tools::isSubmit('next')) {
            $count = (int) Configuration::get(self::ORDERS_SYNC_COUNT, null, 0, 0) + 1;
            $total = (int) Configuration::get(self::ORDERS_SYNC_TOTAL, null, 0, 0);
            Configuration::updateValue(self::ORDERS_SYNC_COUNT, $count, null, 0, 0);
            $remaining = $total - $count;

            $idBatch = $this->exportOrders(($count - 1) * self::EXPORT_CHUNK_SIZE, $exportRemaining);

            die(json_encode([
                'success'   => true,
                'remaining' => $remaining,
                'batch_id'  => $idBatch,
            ]));
        }
    }

    /**
     * Reset product sync data
     *
     * @return void
     *
     * @since 1.1.0
     */
    public function displayAjaxResetProducts()
    {
        if ($idShop = (int) Tools::getValue('shop')) {
            $this->processResetProducts($idShop, true);
        }

        die(json_encode([
            'success' => false,
        ]));
    }

    /**
     * Reset product sync data
     *
     * @param int  $idShop
     * @param bool $ajax
     *
     * @return bool
     *
     * @since 1.1.0
     */
    public function processResetProducts($idShop, $ajax = false)
    {
        if (Db::getInstance()->delete(
            bqSQL(MailChimpProduct::$definition['table']),
            '`id_shop` = '.(int) $idShop
        )) {
            if ($ajax) {
                die(
                json_encode(
                    [
                        'success' => true,
                    ]
                )
                );
            }

            return true;
        }

        return false;
    }

    /**
     * Reset cart sync data
     *
     * @return void
     *
     * @since 1.1.0
     */
    public function displayAjaxResetCarts()
    {
        if ($idShop = (int) Tools::getValue('shop')) {
            $this->processResetCarts($idShop, true);
        }

        die(json_encode([
            'success' => false,
        ]));
    }

    /**
     * Reset cart sync data
     *
     * @param int  $idShop
     * @param bool $ajax
     *
     * @return bool
     *
     * @since 1.1.0
     */
    public function processResetCarts($idShop, $ajax = false)
    {
        $success = Db::getInstance()->execute(
            'DELETE mc
             FROM `'._DB_PREFIX_.bqSQL(MailChimpCart::$definition['table']).'` mc
             INNER JOIN `'._DB_PREFIX_.'cart` c ON c.`id_cart` = mc.`id_cart`
             WHERE c.`id_shop` = '.(int) $idShop
        );

        if ($ajax) {
            die(json_encode([
                'success' => $success,
            ]));
        }

        return $success;
    }

    /**
     * Reset order sync data
     *
     * @return void
     *
     * @since 1.1.0
     */
    public function displayAjaxResetOrders()
    {
        if ($idShop = (int) Tools::getValue('shop')) {
            $this->processResetOrders($idShop, true);
        }

        die(json_encode([
            'success' => false,
        ]));
    }

    /**
     * Reset order sync data
     *
     * @param int  $idShop
     * @param bool $ajax
     *
     * @return bool
     *
     * @since 1.1.0
     */
    public function processResetOrders($idShop, $ajax = false)
    {
        $success = Db::getInstance()->execute(
            'DELETE mo
             FROM `'._DB_PREFIX_.bqSQL(MailChimpOrder::$definition['table']).'` mo
             INNER JOIN `'._DB_PREFIX_.'orders` o ON o.`id_order` = mo.`id_order`
             WHERE o.`id_shop` = '.(int) $idShop
        );

        if ($ajax) {
            die(json_encode([
                'success' => $success,
            ]));
        }

        return $success;
    }

    /**
     * Cron process export all products
     *
     * @param int    $idShop
     * @param bool   $exportRemaining
     * @param string $submit
     *
     * @return false|array
     * @since 1.1.0
     */
    public function cronExportProducts($idShop, $exportRemaining, $submit)
    {
        if ($submit === 'start') {
            $totalProducts = MailChimpProduct::countProducts($idShop, $exportRemaining);
            $totalChunks = ceil($totalProducts / self::EXPORT_CHUNK_SIZE);

            Configuration::updateValue(self::PRODUCTS_SYNC_COUNT, 0, false, 0, 0);
            Configuration::updateValue(self::PRODUCTS_SYNC_TOTAL, $totalChunks, false, 0, 0);

            return [
                'totalChunks'   => $totalChunks,
                'totalProducts' => $totalProducts,
            ];
        } elseif ($submit === 'next') {
            $count = (int) Configuration::get(self::PRODUCTS_SYNC_COUNT, null, 0, 0) + 1;
            $total = (int) Configuration::get(self::PRODUCTS_SYNC_TOTAL, null, 0, 0);
            Configuration::updateValue(self::PRODUCTS_SYNC_COUNT, $count, null, 0, 0);
            $remaining = $total - $count;

            $idBatch = $this->exportProducts(($count - 1) * self::EXPORT_CHUNK_SIZE, $idShop, $exportRemaining);

            return [
                'success'   => true,
                'remaining' => $remaining,
                'batch_id'  => $idBatch,
            ];
        }

        return false;
    }

    /**
     * Cron process export all carts
     *
     * @param int    $idShop
     * @param bool   $exportRemaining
     * @param string $submit
     *
     * @return array|false
     * @since 1.1.0
     */
    public function cronExportCarts($idShop, $exportRemaining, $submit)
    {
        if ($submit === 'start') {
            $totalCarts = MailChimpCart::countCarts($idShop, $exportRemaining);
            $totalChunks = ceil($totalCarts / self::EXPORT_CHUNK_SIZE);

            Configuration::updateValue(self::CARTS_SYNC_COUNT, 0, false, 0, 0);
            Configuration::updateValue(self::CARTS_SYNC_TOTAL, $totalChunks, false, 0, 0);

            return [
                'totalChunks' => $totalChunks,
                'totalCarts'  => $totalCarts,
            ];
        } elseif ($submit === 'next') {
            $count = (int) Configuration::get(self::CARTS_SYNC_COUNT, null, 0, 0) + 1;
            $total = (int) Configuration::get(self::CARTS_SYNC_TOTAL, null, 0, 0);
            Configuration::updateValue(self::CARTS_SYNC_COUNT, $count, null, 0, 0);
            $remaining = $total - $count;

            $idBatch = $this->exportCarts(($count - 1) * self::EXPORT_CHUNK_SIZE, $exportRemaining);

            return [
                'success'   => true,
                'remaining' => $remaining,
                'batch_id'  => $idBatch,
            ];
        }

        return false;
    }

    /**
     * Cron export all orders
     *
     * @param int    $idShop
     * @param bool   $exportRemaining
     * @param string $submit
     *
     * @return false|array
     *
     * @since 1.0.0
     */
    public function cronExportOrders($idShop, $exportRemaining, $submit)
    {
        if ($submit === 'start') {
            $totalOrders = MailChimpOrder::countOrders($idShop, $exportRemaining);
            $totalChunks = ceil($totalOrders / self::EXPORT_CHUNK_SIZE);

            Configuration::updateValue(self::ORDERS_SYNC_COUNT, 0, false, 0, 0);
            Configuration::updateValue(self::ORDERS_SYNC_TOTAL, $totalChunks, false, 0, 0);

            return [
                'totalChunks' => $totalChunks,
                'totalOrders' => $totalOrders,
            ];
        } elseif ($submit === 'next') {
            $count = (int) Configuration::get(self::ORDERS_SYNC_COUNT, null, 0, 0) + 1;
            $total = (int) Configuration::get(self::ORDERS_SYNC_TOTAL, null, 0, 0);
            Configuration::updateValue(self::ORDERS_SYNC_COUNT, $count, null, 0, 0);
            $remaining = $total - $count;

            $idBatch = $this->exportOrders(($count - 1) * self::EXPORT_CHUNK_SIZE, $exportRemaining);

            return [
                'success'   => true,
                'remaining' => $remaining,
                'batch_id'  => $idBatch,
            ];
        }

        return false;
    }

    /**
     * Process configuration
     */
    protected function postProcess()
    {
        if (Tools::isSubmit('submitApiKey')) {
            // Check if MailChimp API key is valid
            Configuration::updateValue(self::API_KEY, Tools::getValue(self::API_KEY), false, null, 1);
            Configuration::updateValue(self::API_KEY_VALID, false, false, null, 1);
            $this->checkApiKey();
        } elseif (Tools::isSubmit('submitSettings')) {
            // Update all the configuration
            // And check if updates were successful
            $confirmationEmail = (bool) Tools::getvalue(self::CONFIRMATION_EMAIL);
            $importOptedIn = (bool) Configuration::get(self::IMPORT_OPTED_IN);

            if (Configuration::updateValue(self::CONFIRMATION_EMAIL, $confirmationEmail)
                && Configuration::updateValue(self::IMPORT_OPTED_IN, $importOptedIn)
            ) {
                $this->addConfirmation($this->l('Settings updated.'));
            } else {
                $this->addError($this->l('Some of the settings could not be saved.'));
            }
        } elseif (Tools::isSubmit('submitShops')) {
            $shopLists = Tools::getValue('shop_list_id');
            $shopTaxes = Tools::getValue('shop_tax');
            $mailChimp = new \MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY, null, null, 1));
            if (is_array($shopLists)) {
                foreach ($shopLists as $idShop => $idList) {
                    $this->checkMergeFields($idShop, $idList);

                    $shop = new Shop($idShop);
                    $defaultIdCurrency = (int) Configuration::get('PS_CURRENCY_DEFAULT', null, $shop->id_shop_group, $shop->id);
                    $currency = new Currency($defaultIdCurrency);

                    $result = $mailChimp->post(
                        '/ecommerce/stores',
                        [
                            'id'            => 'tbstore_'.(int) $idShop,
                            'list_id'       => $idList,
                            'name'          => $shop->name,
                            'domain'        => $shop->domain_ssl,
                            'email_address' => Configuration::get('PS_SHOP_EMAIL', null, $shop->id_shop_group, $shop->id),
                            'currency_code' => Tools::strtoupper($currency->iso_code),
                        ]
                    );

                    if (!isset($result['id'])) {
                        $mailChimp->patch(
                            '/ecommerce/stores/tbstore_'.(int) $idShop,
                            [
                                'id'            => 'tbstore_'.(int) $idShop,
                                'list_id'       => $idList,
                                'name'          => $shop->name,
                                'domain'        => $shop->domain_ssl,
                                'email_address' => Configuration::get('PS_SHOP_EMAIL', null, $shop->id_shop_group, $shop->id),
                                'currency_code' => Tools::strtoupper($currency->iso_code),
                            ]
                        );
                    }

                    $mailChimpShop = MailChimpShop::getByShopId($idShop);
                    if (!Validate::isLoadedObject($mailChimpShop)) {
                        $mailChimpShop = new MailChimpShop();
                    }
                    $mailChimpShop->list_id = $idList;
                    $mailChimpShop->id_shop = $idShop;
                    $mailChimpShop->id_tax = (int) $shopTaxes[$idShop];
                    $mailChimpShop->synced = true;

                    $mailChimpShop->save();

                    // Create MailChimp side webhooks
                    $register = $this->registerWebhookForList($mailChimpShop->list_id);
                    if (!$register) {
                        $this->addError($this->l('MailChimp webhooks could not be implemented. Please try again.'));
                    }
                }
            }
        } elseif (Tools::isSubmit('submitProducts')) {
            // Everything should have been processed by now
        }
    }

    /**
     * @param string $idList
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function registerWebhookForList($idList)
    {
        $result = true;

        // Fetch previously registered webhooks from database
        $registeredWebhooks = MailChimpRegisteredWebhook::getWebhooks($idList);
        foreach ($registeredWebhooks as &$webhook) {
            $webhook = $webhook['url'];
        }

        // Create a callback url for the webhook
        $callbackUrl = $this->urlForWebhook();

        if (!in_array($callbackUrl, $registeredWebhooks)) {
            $result = $this->registerWebhook($idList, $callbackUrl);
            if ($result) {
                if (!MailChimpRegisteredWebhook::saveWebhook($callbackUrl, $idList)) {
                    Logger::addLog('Could not save webhook to database, List ID: '.$idList.', URL: '.$callbackUrl);
                } else {
                    Logger::addLog('Webhook saved to database, List ID: '.$idList.', URL: '.$callbackUrl);
                }
            } else {
                if (is_object($this->mailChimp)) {
                    Logger::addLog('Could not register webhook for list ID: '.$idList.', Error: '.$this->mailChimp->getLastError());
                } else {
                    Logger::addLog('Could not register webhook for list');
                }
            }
        }

        return $result;
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    protected function urlForWebhook()
    {
        // Take first active language and store ID
        $languages = Language::getLanguages(true);
        $idLang = $languages[key($languages)]['id_lang'];

        $shops = Shop::getShops(true);
        $idShop = $shops[key($shops)]['id_shop'];

        return Context::getContext()->link->getModuleLink($this->name, 'hook', [], $idLang, $idShop, false);
    }

    /**
     * @param int         $idList
     * @param string|null $url
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function registerWebhook($idList, $url = null)
    {
        if (!$url) {
            $url = $this->urlForWebhook();
        }

        $urlWebhooks = "/lists/{$idList}/webhooks";
        $this->mailChimp = new \MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY, null, null, 1));
        $this->mailChimp->verifySsl = false;
        $result = $this->mailChimp->post(
            $urlWebhooks,
            [
                'url'     => $url,
                'events'  => [
                    'subscribe'   => true,
                    'unsubscribe' => true,
                    'profile'     => false,
                    'cleaned'     => true,
                    'upemail'     => true,
                    'campaign'    => false,
                ],
                'sources' => [
                    'user'  => true,
                    'admin' => true,
                    'api'   => false,
                ],
                'list_id' => $idList,
            ]
        );

        return (bool) $result;
    }

    /**
     * @param array $list
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function importList($list)
    {
        // Prepare the request
        try {
            $this->mailChimp = new \MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY, null, null, 1));
        } catch (Exception $e) {
            return false;
        }
        $this->mailChimp->verifySsl = false;

        $batch = $this->mailChimp->newBatch();

        // Append subscribers to batch operation request using PUT method (to enable update existing)
        for ($i = 0; $i < count($list); $i++) {
            $subscriber = $list[$i];
            $hash = $this->mailChimp->subscriberHash($subscriber->getEmail());
            $mailChimpShop = MailChimpShop::getByShopId(Context::getContext()->shop->id);
            $url = sprintf('lists/%s/members/%s', $mailChimpShop->list_id, $hash);
            $batch->put('op'.($i + 1), $url, $subscriber->getAsArray());
        }

        // Execute the batch and check status
        $result = $batch->execute();

        if (!$result) {
            return false;
        } else {
            $batchId = $result['id'];
            Logger::addLog('MailChimp batch operation started with ID: '.$batchId);
            Configuration::updateValue(self::LAST_IMPORT_ID, $batchId);

            return true;
        }
    }

    protected function displayMainPage()
    {
        $this->loadTabs();

        $this->context->smarty->assign([
            'availableShops' => Shop::getShops(true, null, true),
            'exportUrl' => $this->baseUrl,
        ]);

        return $this->displayModals().$this->display(__FILE__, 'views/templates/admin/main.tpl');
    }

    /**
     * Display form
     */
    protected function displayImportForm()
    {
        return $this->generateApiForm();
    }

    /**
     * Display form
     */
    protected function displayCronJobs()
    {
        return $this->generateCronForm();
    }

    protected function displayShopsForm()
    {
        return $this->generateShopsForm();
    }

    protected function displayProductsForm()
    {
        return $this->generateProductsForm();
    }

    protected function displayCartsForm()
    {
        return $this->generateCartsForm();
    }

    protected function displayOrdersForm()
    {
        return $this->generateOrdersForm();
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    protected function generateApiForm()
    {
        $fields = [];

        $inputs1 = [];

        $inputs1[] = [
            'type'  => 'text',
            'label' => $this->l('API Key'),
            'name'  => self::API_KEY,
            'desc'  => $this->l('Please enter your MailChimp API key. This can be found in your MailChimp Dashboard -> Account -> Extras -> API keys.'),
        ];

        $fieldsForm1 = [
            'form' => [
                'legend' => [
                    'title' => $this->l('API Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'input'  => $inputs1,
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                    'name'  => 'submitApiKey',
                ],
            ],
        ];

        $fields[] = $fieldsForm1;

        if ($this->checkApiKey()) {
            $inputs2 = [];

            $inputs2[] = [
                'type'   => 'switch',
                'label'  => $this->l('Confirmation Email'),
                'name'   => self::CONFIRMATION_EMAIL,
                'desc'   => $this->l('If you turn this on, MailChimp will send an email to customers asking them to confirm their subscription.'),
                'values' => [
                    [
                        'id'    => 'confirmationSwitch_on',
                        'value' => 1,
                        'label' => $this->l('Enabled'),
                    ],
                    [
                        'id'    => 'confirmationSwitch_off',
                        'value' => 0,
                        'label' => $this->l('Disabled'),
                    ],
                ],
            ];

            $inputs2[] = [
                'type'   => 'switch',
                'label'  => $this->l('Opted-In Only'),
                'name'   => self::IMPORT_OPTED_IN,
                'desc'   => $this->l('This will only import customers that has opted-in to the newsletter.'),
                'id'     => 'importOptedIn',
                'values' => [
                    [
                        'id'    => 'optedInSwitch_on',
                        'value' => 1,
                        'label' => $this->l('Enabled'),
                    ],
                    [
                        'id'    => 'optedInSwitch_off',
                        'value' => 0,
                        'label' => $this->l('Disabled'),
                    ],
                ],
            ];

            $fieldsForm2 = [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Import Settings'),
                        'icon'  => 'icon-cogs',
                    ],
                    'input'  => $inputs2,
                    'submit' => [
                        'title' => $this->l('Save'),
                        'class' => 'btn btn-default pull-right',
                        'name'  => 'submitSettings',
                    ],
                ],
            ];

            $fields[] = $fieldsForm2;
        }

        $helper = new HelperForm();
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'#mailchimp_tab_'.self::MENU_IMPORT;
        $helper->token = '';
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
        ];

        return $helper->generateForm($fields);
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    protected function generateCronForm()
    {
        $context = Context::getContext();
        $token = Tools::substr(Tools::encrypt($this->name.'/cron'), 0, 10);

        $idShop = array_values(Shop::getShops(true, null, true));
        if (is_array($idShop) && !empty($idShop)) {
            $idShop = $idShop[0];
        } else {
            $idShop = $context->shop->id;
        }

        $idLang = array_values(Language::getLanguages(true, false, true));
        if (is_array($idLang) && !empty($idLang)) {
            $idLang = $idLang[0];
        } else {
            $idLang = $context->language->id;
        }

        $context->smarty->assign(
            [
                'cron_all_products'       => $context->link->getModuleLink($this->name, 'cron', ['action' => 'ExportAllProducts',       'token' => $token, 'id_shop' => $idShop], true, $idLang, $idShop, false),
                'cron_remaining_products' => $context->link->getModuleLink($this->name, 'cron', ['action' => 'ExportRemainingProducts', 'token' => $token, 'id_shop' => $idShop], true, $idLang, $idShop, false),
                'cron_all_carts'          => $context->link->getModuleLink($this->name, 'cron', ['action' => 'ExportAllCarts',          'token' => $token, 'id_shop' => $idShop], true, $idLang, $idShop, false),
                'cron_remaining_carts'    => $context->link->getModuleLink($this->name, 'cron', ['action' => 'ExportRemainingCarts',    'token' => $token, 'id_shop' => $idShop], true, $idLang, $idShop, false),
                'cron_all_orders'         => $context->link->getModuleLink($this->name, 'cron', ['action' => 'ExportAllOrders',         'token' => $token, 'id_shop' => $idShop], true, $idLang, $idShop, false),
                'cron_remaining_orders'   => $context->link->getModuleLink($this->name, 'cron', ['action' => 'ExportRemainingOrders',   'token' => $token, 'id_shop' => $idShop], true, $idLang, $idShop, false),
            ]
        );

        $fields = [];

        $fieldsForm2 = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Cron Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'description' => $this->display(__FILE__, 'views/templates/admin/cron_settings.tpl'),
            ],
        ];

        $fields[] = $fieldsForm2;

        $helper = new HelperForm();
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'#mailchimp_tab_'.self::MENU_IMPORT;
        $helper->token = '';
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
        ];

        return $helper->generateForm($fields);
    }

    /**
     * @return array
     *
     * @since 1.0.0
     */
    protected function getConfigFieldsValues()
    {
        return [
            self::API_KEY            => Configuration::get(self::API_KEY, null, null, 1),
            self::CONFIRMATION_EMAIL => Configuration::get(self::CONFIRMATION_EMAIL),
            self::IMPORT_OPTED_IN    => Configuration::get(self::IMPORT_OPTED_IN),
        ];
    }

    /**
     * Render Customer export form
     *
     * @return string Form HTML
     */
    protected function generateShopsForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = MailChimpShop::$definition['table'];
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitShops';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'#mailchimp_tab_'.self::MENU_SHOPS;

        $helper->token = '';

        $helper->tpl_vars = [
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getShopsForm()]);
    }

    /**
     * @return array
     */
    protected function getShopsForm()
    {
        $lists = [0 => $this->l('Do not sync')];
        $thisLists = $this->getLists(true);
        if (is_array($thisLists)) {
            $lists = array_merge($lists, $thisLists);
        }
        $rawTaxes = Tax::getTaxes($this->context->language->id, true);
        $taxes = [
            0 => $this->l('None'),
        ];
        foreach ($rawTaxes as $tax) {
            $taxes[(int) $tax['id_tax']] = $tax['name'];
        }

        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Shop settings'),
                    'icon' => 'icon-building',
                ],
                'input' => [
                    [
                        'type'  => 'mailchimp_shops',
                        'label' => $this->l('Shops to sync'),
                        'name'  => 'mailchimp_shops',
                        'lists' => $lists,
                        'shops' => MailChimpShop::getShops(true),
                        'taxes' => $taxes,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                    'name'  => 'submitShops',
                ],
            ],
        ];
    }

    /**
     * Render Customer export form
     *
     * @return string Form HTML
     */
    protected function generateProductsForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = MailChimpShop::$definition['table'];
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProducts';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'#mailchimp_tab_'.self::MENU_PRODUCTS;

        $helper->token = '';

        $helper->tpl_vars = [
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getProductsForm()]);
    }

    /**
     * @return array
     */
    protected function getProductsForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Product settings'),
                    'icon' => 'icon-archive',
                ],
                'input' => [
                    [
                        'type'  => 'mailchimp_products',
                        'label' => $this->l('Products to sync'),
                        'name'  => 'mailchimp_products',
                        'shops' => MailChimpShop::getShops(true),
                    ],
                ],
            ],
        ];
    }

    /**
     * Render Customer export form
     *
     * @return string Form HTML
     */
    protected function generateCartsForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = MailChimpShop::$definition['table'];
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProducts';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'#mailchimp_tab_'.self::MENU_CARTS;

        $helper->token = '';

        $helper->tpl_vars = [
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getCartsForm()]);
    }

    /**
     * @return array
     */
    protected function getCartsForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Cart settings'),
                    'icon' => 'icon-shopping-cart',
                ],
                'input' => [
                    [
                        'type'  => 'mailchimp_carts',
                        'label' => $this->l('Carts to sync'),
                        'name'  => 'mailchimp_carts',
                        'shops' => MailChimpShop::getShops(true),
                    ],
                ],
            ],
        ];
    }

    /**
     * Render Customer export form
     *
     * @return string Form HTML
     */
    protected function generateOrdersForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = MailChimpShop::$definition['table'];
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProducts';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'#mailchimp_tab_'.self::MENU_ORDERS;

        $helper->token = '';

        $helper->tpl_vars = [
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getOrdersForm()]);
    }

    /**
     * @return array
     */
    protected function getOrdersForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Order settings'),
                    'icon'  => 'icon-shopping-cart',
                ],
                'input'  => [
                    [
                        'type'  => 'mailchimp_orders',
                        'label' => $this->l('Orders to sync'),
                        'name'  => 'mailchimp_orders',
                        'shops' => MailChimpShop::getShops(true),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    protected function loadTabs()
    {
        $contents = [
            [
                'name'  => $this->l('Import Settings'),
                'icon'  => 'icon-download',
                'value' => $this->displayImportForm(),
                'badge' => false,
            ],
        ];

        if ($this->checkApiKey()) {
            $contents[] = [
                'name'  => $this->l('Shops'),
                'icon'  => 'icon-building',
                'value' => $this->displayShopsForm(),
                'badge' => false,
            ];
            $contents[] = [
                'name'  => $this->l('Products'),
                'icon'  => 'icon-archive',
                'value' => $this->displayProductsForm(),
                'badge' => false,
            ];
            $contents[] = [
                'name'  => $this->l('Carts'),
                'icon'  => 'icon-shopping-cart',
                'value' => $this->displayCartsForm(),
                'badge' => false,
            ];
            $contents[] = [
                'name'  => $this->l('Orders'),
                'icon'  => 'icon-shopping-cart',
                'value' => $this->displayOrdersForm(),
                'badge' => false,
            ];
        }

        $contents[] = [
            'name'  => $this->l('Cron jobs'),
            'icon'  => 'icon-cogs',
            'value' => $this->displayCronJobs(),
            'badge' => false,
        ];

        $tabContents = [
            'title'    => $this->l('MailChimp'),
            'contents' => $contents,
        ];

        $this->context->smarty->assign('tab_contents', $tabContents);
        $this->context->smarty->assign('ps_version', _PS_VERSION_);
        $this->context->smarty->assign('new_base_dir', $this->_path);
        $this->context->controller->addCss($this->_path.'/views/css/configtabs.css');
        $this->context->controller->addJs($this->_path.'/views/js/configtabs.js');
    }

    /**
     * Check if API key is valid
     *
     * @return bool Indicates whether the API key is valid
     */
    protected function checkApiKey()
    {
        if (Configuration::get(self::API_KEY) && Configuration::get(self::API_KEY_VALID)) {
            return true;
        }

        // Show settings form only if API key is set and working
        $apiKey = Configuration::get(self::API_KEY);
        $validKey = false;
        if (isset($apiKey) && $apiKey) {
            // Check if API key is valid
            try {
                $mailchimp = new \MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY, null, null, 1));
                $mailchimp->verifySsl = false;
                $getLists = $mailchimp->get('lists');
                if ($getLists) {
                    $validKey = true;
                    Configuration::updateValue(self::API_KEY_VALID, true);
                }
            } catch (Exception $e) {
                $this->addError($e->getMessage());
            }
        }

        return $validKey;
    }

    /**
     * Export subscribers
     *
     * @param int  $offset
     * @param int  $idShop
     * @param bool $remaining
     *
     * @return string MailChimp Batch ID
     *
     * @since 1.0.0
     */
    protected function exportSubscribers($offset, $idShop = null)
    {
        if (!$idShop) {
            $idShop = Context::getContext()->shop->id;
        }

        $mailChimpShop = MailChimpShop::getByShopId($idShop);

        $subscribers = MailChimpSubscriber::getSubscribers($idShop, $offset, static::EXPORT_CHUNK_SIZE);
        if (empty($subscribers)) {
            return '';
        }

        $mailChimp = new MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY));
        $batch = $mailChimp->newBatch();

        $id = 1;
        foreach ($subscribers as &$subscriber) {
            $mergeFields = [
                'FNAME' => $subscriber['firstname'],
                'LNAME' => $subscriber['lastname'],
            ];
            if ($subscriber['birthday']) {
                $mergeFields['BDAY'] = date('c', strtotime($subscriber['birthday']));
            }

            $subscriberHash = md5(Tools::strtolower($subscriber['email']));
            $batch->put("ms{$id}", "/lists/{$mailChimpShop->list_id}/members/{$subscriberHash}", [
                'email_address' => Tools::strtolower($subscriber['email']),
                'status_if_new' => $subscriber['subscription'],
                'merge_fields'  => (object) $mergeFields,
                'language'      => static::getMailChimpLanguageByIso($subscriber['language_code']),
                'ip_signup'     => $subscriber['ip_address'],
            ]);

            $id++;
        }

        $result = $batch->execute(self::API_TIMEOUT);
        if (!empty($result)) {
            return $result['id'];
        }

        return '';
    }

    /**
     * Export products
     *
     * @param int  $offset
     * @param int  $idShop
     * @param bool $remaining
     *
     * @return string MailChimp Batch ID
     *
     * @since 1.0.0
     */
    protected function exportProducts($offset, $idShop = null, $remaining = false)
    {
        if (!$idShop) {
            $idShop = Context::getContext()->shop->id;
        }

        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $products = MailChimpProduct::getProducts($idShop, $offset, self::EXPORT_CHUNK_SIZE, $remaining);
        if (empty($products)) {
            return '';
        }

        $mailChimpShop = MailChimpShop::getByShopId($idShop);
        if (!Validate::isLoadedObject($mailChimpShop)) {
            return false;
        }
        $rate = 1;
        $tax = new Tax($mailChimpShop->id_tax);
        if (Validate::isLoadedObject($tax) && $tax->active) {
            $rate = 1 + ($tax->rate / 100);
        }

        $mailChimp = new MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY));
        $batch = $mailChimp->newBatch();

        $link = \Context::getContext()->link;

        foreach ($products as &$product) {
            $productObj = new Product();
            $productObj->hydrate($product);
            $allImages = $productObj->getImages($idLang);

            if ($productObj->hasAttributes()) {
                $allCombinations = $productObj->getAttributeCombinations($idLang);
                $allCombinationImages = $productObj->getCombinationImages($idLang);

                $variants = [];
                foreach ($allCombinations as $combination) {
                    $variant = [
                        'id'                 => (string) $product['id_product'].'-'.(string) $combination['id_product_attribute'],
                        'title'              => (string) $product['name'],
                        'sku'                => (string) $combination['reference'],
                        'price'              => (float) ($product['price'] * $rate) + (float) ($combination['price'] * $rate),
                        'inventory_quantity' => (int) $combination['quantity'],
                    ];
                    if (isset($allCombinationImages[$combination['id_product_attribute']])) {
                        $variant['image_url'] = $link->getImageLink('default', "{$product['id_product']}-{$allCombinationImages[$combination['id_product_attribute']][0]}");
                    }
                    $variants[] = $variant;
                }
            } else {
                $variants = [
                    [
                        'id'                 => (string) $product['id_product'],
                        'title'              => (string) $product['name'],
                        'sku'                => (string) $product['reference'],
                        'price'              => (float) ($product['price'] * $rate),
                        'inventory_quantity' => (int) $product['quantity'],
                    ],
                ];
            }

            $payload = [
                'id'          => (string) $product['id_product'],
                'title'       => (string) $product['name'],
                'url'         => (string) $link->getProductLink($product['id_product']),
                'description' => (string) $product['description_short'],
                'vendor'      => (string) $product['manufacturer'] ?: '',
                'image_url'   => !empty($allImages) ? $link->getImageLink('default', "{$product['id_product']}-{$allImages[0]['id_image']}") : '',
                'variants'    => $variants,
            ];
            if ($product['last_synced'] && $product['last_synced'] !== '1970-01-01 00:00:00') {
                $batch->patch(
                    'op'.(int) $product['id_product'],
                    "/ecommerce/stores/tbstore_{$idShop}/products/{$product['id_product']}",
                    $payload
                );
            } else {
                $batch->post(
                    'op'.(int) $product['id_product'],
                    "/ecommerce/stores/tbstore_{$idShop}/products",
                    $payload
                );
            }
        }

        $result = $batch->execute(self::API_TIMEOUT);
        if (!empty($result)) {
            if (MailChimpProduct::setSynced(array_column($products, 'id_product'), $idShop)) {
                Configuration::updateValue(self::PRODUCTS_LAST_SYNC, date('Y-m-d H:i:s'), false, null, $idShop);
            }

            return $result['id'];
        }

        return '';
    }

    /**
     * Export all carts
     *
     * @param int  $offset
     *
     * @param bool $remaining
     *
     * @return string MailChimp Batch ID
     *
     * @since 1.1.0
     */
    protected function exportCarts($offset, $remaining = false)
    {
        $idShop = Context::getContext()->shop->id;

        $carts = MailChimpCart::getCarts($idShop, $offset, self::EXPORT_CHUNK_SIZE, $remaining);
        if (empty($carts)) {
            return '';
        }
        $mailChimpShop = MailChimpShop::getByShopId($idShop);
        if (!Validate::isLoadedObject($mailChimpShop)) {
            return '';
        }

        $mailChimp = new MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY));
        $batch = $mailChimp->newBatch();

        foreach ($carts as &$cart) {
            if (empty($cart['lines'])) {
                continue;
            }
            $subscriberHash = md5(Tools::strtolower($cart['email']));
            $mergeFields = [
                'FNAME' => $cart['firstname'],
                'LNAME' => $cart['lastname'],
            ];
            if ($cart['birthday'] && date('Y-m-d', strtotime($cart['birthday'])) > '1900-01-01') {
                $mergeFields['BDAY'] = date('c', strtotime($cart['birthday']));
            }

            $batch->put("ms{$cart['id_customer']}", "/lists/{$mailChimpShop->list_id}/members/{$subscriberHash}", [
                'email_address' => Tools::strtolower($cart['email']),
                'status_if_new' => false,
                'merge_fields'  => (object) $mergeFields,
                'language'      => static::getMailChimpLanguageByIso($cart['language_code']),
            ]);

            $payload = [
                'id'            => (string) $cart['id_cart'],
                'customer'      => [
                    'id'            => (string) $cart['id_customer'],
                    'email_address' => (string) $cart['email'],
                    'first_name'    => (string) $cart['firstname'],
                    'last_name'     => (string) $cart['lastname'],
                    'opt_in_status' => false,
                ],
                'currency_code' => (string) $cart['currency_code'],
                'order_total'   => (string) $cart['order_total'],
                'checkout_url'  => (string) $cart['checkout_url'],
                'lines'         => $cart['lines'],
            ];

            if ($cart['last_synced'] && $cart['last_synced'] !== '1970-01-01 00:00:00') {
                $batch->patch(
                    'op'.(int) $cart['id_cart'],
                    "/ecommerce/stores/tbstore_{$idShop}/carts/{$cart['id_cart']}",
                    $payload
                );
            } else {
                $batch->post(
                    'op'.(int) $cart['id_cart'],
                    "/ecommerce/stores/tbstore_{$idShop}/carts",
                    $payload
                );
            }
        }

        $result = $batch->execute(self::API_TIMEOUT);
        if ($result && isset($result['id'])) {
            if (MailChimpCart::setSynced(array_column($carts, 'id_cart'))) {
                Configuration::updateValue(self::CARTS_LAST_SYNC, date('Y-m-d H:i:s'), false, null, $idShop);
            }

            return $result['id'];
        }

        return '';
    }

    /**
     * Export orders
     *
     * @param int  $offset
     * @param bool $exportRemaining
     *
     * @return string MailChimp Batch ID
     *
     * @since 1.1.0
     */
    protected function exportOrders($offset, $exportRemaining = false)
    {
        $idShop = Context::getContext()->shop->id;

        // We use the Order objects
        $orders = MailChimpOrder::getOrders($idShop, $offset, self::EXPORT_CHUNK_SIZE, $exportRemaining);
        if (empty($orders)) {
            return '';
        }
        $mailChimpShop = MailChimpShop::getByShopId($idShop);
        if (!Validate::isLoadedObject($mailChimpShop)) {
            return '';
        }

        $mailChimp = new MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY));
        $batch = $mailChimp->newBatch();

        foreach ($orders as &$order) {
            if (empty($order['lines'])) {
                continue;
            }
            $subscriberHash = md5(Tools::strtolower($order['email']));
            $mergeFields = [
                'FNAME' => $order['firstname'],
                'LNAME' => $order['lastname'],
            ];
            if ($order['birthday'] && date('Y-m-d', strtotime($order['birthday'])) > '1900-01-01') {
                $mergeFields['BDAY'] = date('c', strtotime($order['birthday']));
            }

            $batch->put("ms{$order['id_customer']}", "/lists/{$mailChimpShop->list_id}/members/{$subscriberHash}", [
                'email_address' => Tools::strtolower($order['email']),
                'status_if_new' => false,
                'merge_fields'  => (object) $mergeFields,
                'language'      => static::getMailChimpLanguageByIso($order['language_code']),
            ]);

            if (empty($order['lines'])) {
                unset($order);
                continue;
            }

            $payload = [
                'id'                   => (string) $order['id_order'],
                'customer'             => [
                    'id'            => (string) $order['id_customer'],
                    'email_address' => (string) $order['email'],
                    'first_name'    => (string) $order['firstname'],
                    'last_name'     => (string) $order['lastname'],
                    'opt_in_status' => false,
                ],
                'currency_code'        => (string) $order['currency_code'],
                'order_total'          => (string) $order['order_total'],
                'lines'                => $order['lines'],
            ];

            if (self::validateDate($order['date_add'], 'Y-m-d H:i:s')) {
                $payload['processed_at_foreign'] = date('c', strtotime($order['date_add']));
            }
            if (self::validateDate($order['date_upd'], 'Y-m-d H:i:s')) {
                $payload['updated_at_foreign'] = date('c', strtotime($order['date_add']));
            }

            if ($order['mc_tc'] && ctype_xdigit($order['mc_tc']) && strlen($order['mc_tc']) === 10) {
                $payload['tracking_code'] = $order['mc_tc'];
            }

            if ($order['last_synced'] && $order['last_synced'] !== '1970-01-01 00:00:00') {
                $batch->patch(
                    'op'.(int) $order['id_order'],
                    "/ecommerce/stores/tbstore_{$idShop}/orders/{$order['id_order']}",
                    $payload
                );
            } else {
                $batch->post(
                    'op'.(int) $order['id_order'],
                    "/ecommerce/stores/tbstore_{$idShop}/orders",
                    $payload
                );
                $batch->delete(
                    'opcart'.(int) $order['id_cart'],
                    "/ecommerce/stores/tbstore_{$idShop}/carts/{$order['id_cart']}"
                );
            }
        }

        $result = $batch->execute(self::API_TIMEOUT);
        if ($result && isset($result['id'])) {
            if (MailChimpOrder::setSynced(array_column($orders, 'id_order'))) {
                Configuration::updateValue(self::ORDERS_LAST_SYNC, date('Y-m-d H:i:s'), false, null, $idShop);
            }

            return $result['id'];
        }

        return '';
    }

    /**
     * @param string $date
     * @param string $format
     *
     * @return bool
     *
     * @since 1.1.0
     */
    protected static function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) == $date;
    }

    /**
     * Check if the merge fields have been remotely registered
     *
     * @param int    $idShop
     * @param string $idList
     *
     * @return bool
     *
     * @since 1.1.0
     */
    protected function checkMergeFields($idShop, $idList)
    {
        $mailChimp = new \MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY, null, null, 1));
        $result = $mailChimp->get("/lists/{$idList}/merge-fields");

        $missingFields = [
            'FNAME' => 'text',
            'LNAME' => 'text',
            'BDAY' => 'date',
        ];
        foreach ($result['merge_fields'] as $mergeField) {
            if (isset($missingFields[$mergeField['tag']]) && $missingFields[$mergeField['tag']] === $mergeField['type']) {
                unset($missingFields[$mergeField['tag']]);
            }
        }

        $batch = $mailChimp->newBatch();
        foreach ($missingFields as $fieldName => $fieldType) {
            $batch->delete("del{$fieldName}", "/lists/{$idList}/merge-fields/{$fieldName}");
            $batch->post("add{$fieldName}", "/lists/{$idList}/merge-fields", [
                'tag'  => $fieldName,
                'name' => $fieldName,
                'type' => $fieldType,
            ]);
        }

        $result = $batch->execute(10);
        if (isset($result['id'])) {
            return true;
        }

        return false;
    }

    /**
     * Install Db Indices
     *
     * @since 1.1.0
     */
    protected function installDbIndices()
    {
        $this->installDbIndex('ALTER TABLE `PREFIX_mailchimp_product` ADD INDEX `mc_product_product` (`id_product`)');
        $this->installDbIndex('ALTER TABLE `PREFIX_mailchimp_cart` ADD INDEX `mc_cart_cart` (`id_cart`)');
        $this->installDbIndex('ALTER TABLE `PREFIX_mailchimp_order` ADD INDEX `mc_order_order` (`id_order`)');
        $this->installDbIndex('ALTER TABLE `PREFIX_mailchimp_shop` ADD INDEX `mc_shop_shop` (`id_shop`)');
        $this->installDbIndex('ALTER TABLE `PREFIX_mailchimp_tracking` ADD INDEX `mc_tracking_order` (`id_order`)');
    }

    /**
     * Install Db Index
     *
     * @since 1.1.0
     */
    protected function installDbIndex($sql)
    {
        try {
            Db::getInstance()->execute($sql);
        } catch (Exception $e) {
        }
    }
}
