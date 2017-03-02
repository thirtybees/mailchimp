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

if (!defined('_PS_VERSION_') && !defined('_TB_VERSION_')) {
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
    const IMPORT_LIST = 'MAILCHIMP_IMPORT_LIST';
    const CONFIRMATION_EMAIL = 'MAILCHIMP_CONFIRMATION_EMAIL';
    const UPDATE_EXISTING = 'MAILCHIMP_UPDATE_EXISTING';
    const IMPORT_ALL = 'MAILCHIMP_IMPORT_ALL';
    const IMPORT_OPTED_IN = 'MAILCHIMP_IMPORT_OPTED_IN';
    const LAST_IMPORT = 'MAILCHIMP_LAST_IMPORT';
    const LAST_IMPORT_ID = 'MAILCHIMP_LAST_IMPORT_ID';
    const API_TIMEOUT = 10;

    const PRODUCTS_SYNC_COUNT = 'MAILCHIMP_PRODUCTS_SYNC_COUNT';
    const PRODUCTS_SYNC_TOTAL = 'MAILCHIMP_PRODUCTS_SYNC_TOTAL';
    const CARTS_SYNC_COUNT = 'MAILCHIMP_CARTS_SYNC_COUNT';
    const CARTS_SYNC_TOTAL = 'MAILCHIMP_CARTS_SYNC_TOTAL';
    const ORDERS_SYNC_COUNT = 'MAILCHIMP_ORDERS_SYNC_COUNT';
    const ORDERS_SYNC_TOTAL = 'MAILCHIMP_ORDERS_SYNC_TOTAL';

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
    protected $mailChimpLanguages = [
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
        $this->version = '1.1.0';
        $this->author = 'thirty bees';
        $this->need_instance = 0;
        $this->bootstrap = true;

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
            || !$this->registerHook('displayHeader')
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->registerHook('footer') // to catch guest newsletter subscription
            || !$this->registerHook('actionCustomerAccountAdd') // front office account creation
            || !$this->registerHook('actionObjectCustomerAddAfter') // front office account creation
            || !$this->registerHook('actionObjectCustomerUpdateAfter') // front office account creation
            || !$this->registerHook('actionAdminCustomersControllerSaveAfter') // back office update customer
            || !$this->registerHook('actionValidateOrder') // validate order
            || !\MailChimpModule\MailChimpRegisteredWebhook::createDatabase()
            || !\MailChimpModule\MailChimpShop::createDatabase()
            || !\MailChimpModule\MailChimpProduct::createDatabase()
            || !\MailChimpModule\MailChimpCart::createDatabase()
            || !\MailChimpModule\MailChimpOrder::createDatabase()
            || !\MailChimpModule\MailChimpTracking::createDatabase()
        ) {
            return false;
        }

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
            || !Configuration::deleteByName(self::IMPORT_LIST)
            || !Configuration::deleteByName(self::CONFIRMATION_EMAIL)
            || !Configuration::deleteByName(self::IMPORT_OPTED_IN)
            || !Configuration::deleteByName(self::LAST_IMPORT)
            || !Configuration::deleteByName(self::LAST_IMPORT_ID)
            || !\MailChimpModule\MailChimpRegisteredWebhook::dropDatabase()
            || !\MailChimpModule\MailChimpShop::dropDatabase()
            || !\MailChimpModule\MailChimpProduct::dropDatabase()
            || !\MailChimpModule\MailChimpCart::dropDatabase()
            || !\MailChimpModule\MailChimpOrder::dropDatabase()
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
     * Hook to back office header
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function hookDisplayBackOfficeHeader()
    {
        if ($this->context->controller->controller_name) {
            if (Tools::isSubmit('module_name') && Tools::getValue('module_name') == 'mailchimp') {
                $this->context->controller->addJquery();
                $this->context->controller->addJS($this->_path.'views/js/mailchimp.js');
                $this->context->controller->addJS($this->_path.'views/js/jquery.the-modal.js');
            }
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
                $customer = new \MailChimpModule\MailChimpSubscriber(
                    Tools::getValue('email'),
                    \MailChimpModule\MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED,
                    null,
                    null,
                    Tools::getRemoteAddr(),
                    $this->getMailChimpLanguageByIso($iso),
                    date('Y-m-d H:i:s')
                );
                if (!$this->addOrUpdateSubscription($customer)) {
                    Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
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
                $mailChimpTracking = new \MailChimpModule\MailChimpTracking();
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
    public function getMailChimpLanguageByIso($iso)
    {
        $lang = $this->mailChimpLanguages[$iso];
        if ($lang == '') {
            $lang = 'en';
            Logger::addLog('MailChimp language code could not be found for language with ISO: '.$lang);
        }

        return $lang;
    }

    /**
     * @param \MailChimpModule\MailChimpSubscriber $subscription
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function addOrUpdateSubscription(\MailChimpModule\MailChimpSubscriber $subscription)
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
        if (isset($params['newCustomer']) && $params['newCustomer']->id > 0) {
            $subscription = (string) $params['newCustomer']->newsletter ? \MailChimpModule\MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED : \MailChimpModule\MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
            $iso = Language::getIsoById($params['newCustomer']->id_lang);
            $customer = new \MailChimpModule\MailChimpSubscriber(
                $params['newCustomer']->email,
                $subscription,
                $params['newCustomer']->firstname,
                $params['newCustomer']->lastname,
                $params['newCustomer']->ip_registration_newsletter,
                $this->getMailChimpLanguageByIso($iso),
                $params['newCustomer']->newsletter_date_add
            );
            if (!$this->addOrUpdateSubscription($customer)) {
                Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
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
        $subscription = (string) $customer->newsletter ? \MailChimpModule\MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED : \MailChimpModule\MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
        $iso = LanguageCore::getIsoById($customer->id_lang);
        $customerMC = new \MailChimpModule\MailChimpSubscriber(
            $customer->email,
            $subscription,
            $customer->firstname,
            $customer->lastname,
            Tools::getRemoteAddr(),
            $this->getMailChimpLanguageByIso($iso),
            date('Y-m-d H:i:s')
        );
        if (!$this->addOrUpdateSubscription($customerMC)) {
            Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
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

        $subscription = (string) $customer->newsletter ? \MailChimpModule\MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED : \MailChimpModule\MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
        $iso = LanguageCore::getIsoById($customer->id_lang);
        $customerMC = new \MailChimpModule\MailChimpSubscriber(
            $customer->email,
            $subscription,
            $customer->firstname,
            $customer->lastname,
            Tools::getRemoteAddr(),
            $this->getMailChimpLanguageByIso($iso),
            date('Y-m-d H:i:s')
        );
        if (!$this->addOrUpdateSubscription($customerMC)) {
            Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
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
            $subscription = (string) Tools::getValue('newsletter') ? \MailChimpModule\MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED : \MailChimpModule\MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
            $iso = Language::getIsoById($object->id_lang);
            $customer = new \MailChimpModule\MailChimpSubscriber(
                $object->email,
                $subscription,
                $object->firstname,
                $object->lastname,
                Tools::getRemoteAddr(),
                $this->getMailChimpLanguageByIso($iso),
                date('Y-m-d H:i:s')
            );
            if (!$this->addOrUpdateSubscription($customer)) {
                Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
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
            'ExportAllProducts',
            'ExportAllCarts',
            'ExportAllOrders',
        ])) {
            $this->{'displayAjax'.$action}();
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
            $totalProducts = \MailChimpModule\MailChimpProduct::countProducts($idShop, $exportRemaining);
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
            $totalCarts = \MailChimpModule\MailChimpCart::countCarts($idShop, $exportRemaining);
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
            $totalOrders = \MailChimpModule\MailChimpOrder::countOrders($idShop, $exportRemaining);
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
            $importList = Tools::getValue(self::IMPORT_LIST);
            $confirmationEmail = (bool) Tools::getvalue(self::CONFIRMATION_EMAIL);
            $importOptedIn = (bool) Configuration::get(self::IMPORT_OPTED_IN);

            if (Configuration::updateValue(self::IMPORT_LIST, $importList)
                && Configuration::updateValue(self::CONFIRMATION_EMAIL, $confirmationEmail)
                && Configuration::updateValue(self::IMPORT_OPTED_IN, $importOptedIn)
            ) {
                $this->addConfirmation($this->l('Settings updated.'));
                // Create MailChimp side webhooks
                $register = $this->registerWebhookForList(Configuration::get(self::IMPORT_LIST));
                if (!$register) {
                    $this->addError($this->l('MailChimp webhooks could not be implemented. Please try again.'));
                }
            } else {
                $this->addError($this->l('Some of the settings could not be saved.'));
            }
        } elseif (Tools::isSubmit('submitShops')) {
            $shopLists = Tools::getValue('shop_list_id');
            $mailChimp = new \MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY, null, null, 1));
            $batch = $mailChimp->newBatch();
            if (is_array($shopLists)) {
                foreach ($shopLists as $idShop => $idList) {
                    $shop = new Shop($idShop);
                    $defaultIdCurrency = (int) Configuration::get('PS_CURRENCY_DEFAULT', null, $shop->id_shop_group, $shop->id);
                    $currency = new Currency($defaultIdCurrency);

                    $batch->put(
                        'op'.(int) $idShop,
                        'ecommerce/stores',
                        [
                            'id'            => 'tbstore_'.(int) $idShop,
                            'list_id'       => $idList,
                            'name'          => $shop->name,
                            'domain'        => $shop->domain_ssl,
                            'email_address' => Configuration::get('PS_SHOP_EMAIL', null, $shop->id_shop_group, $shop->id),
                            'currency_code' => Tools::strtoupper($currency->iso_code),
                        ]
                    );


                    $mailChimpShop = \MailChimpModule\MailChimpShop::getByShopId($idShop);
                    $mailChimpShop->list_id = $idList;
                    $mailChimpShop->id_shop = $idShop;
                    $mailChimpShop->synced = true;

                    $mailChimpShop->save();
                }

                $batch->execute(self::API_TIMEOUT);
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
        $registeredWebhooks = \MailChimpModule\MailChimpRegisteredWebhook::getWebhooks($idList);
        foreach ($registeredWebhooks as &$webhook) {
            $webhook = $webhook['url'];
        }

        // Create a callback url for the webhook
        $callbackUrl = $this->urlForWebhook();

        if (!in_array($callbackUrl, $registeredWebhooks)) {
            $result = $this->registerWebhook($idList, $callbackUrl);
            if ($result) {
                if (!\MailChimpModule\MailChimpRegisteredWebhook::saveWebhook($callbackUrl, $idList)) {
                    Logger::addLog('Could not save webhook to database, List ID: '.$idList.', URL: '.$callbackUrl);
                } else {
                    Logger::addLog('Webhook saved to database, List ID: '.$idList.', URL: '.$callbackUrl);
                }
            } else {
                Logger::addLog('Could not register webhook for list ID: '.$idList.', Error: '.$this->mailChimp->getLastError());
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

        $urlWebhooks = sprintf('lists/%s/webhooks', $idList);
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
     * @param bool $optedIn
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function getTotalSubscriberList($optedIn = false)
    {
        // Get subscriptions made through Newsletter Block
        $list1 = $this->getNewsletterBlockSubscriptions($optedIn);
        // Get subscriptions made through either registration form or during guest checkout
        $list2 = $this->getCustomerSubscriptions($optedIn);

        return array_merge($list1, $list2);
    }

    /**
     * @param bool $optedIn
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function getNewsletterBlockSubscriptions($optedIn = false)
    {
        $list = [];
        // Check if the module exists
        $moduleNewsletter = \Module::getInstanceByName('blocknewsletter');
        if ($moduleNewsletter) {
            $sql = new DbQuery();
            $sql->select('pn.`email`, pn.`newsletter_date_add`, pn.`ip_registration_newsletter`, pn.`active`');
            $sql->from('newsletter', 'pn');
            $sql->where('pn.`id_shop` = '.(int) $this->context->shop->id);
            if ($optedIn) {
                $sql->where('pn.`active` = 1');
            }
            $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

            if ($result) {
                // If confirmation mail is to be sent, statuses must be post as pending to the MailChimp API
                $subscription = (string) Configuration::get(self::CONFIRMATION_EMAIL) ? \MailChimpModule\MailChimpSubscriber::SUBSCRIPTION_PENDING : \MailChimpModule\MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED;
                // Get default shop language since Newsletter Block registrations don't contain any language info
                $lang = $this->mailChimpLanguages[$this->context->language->iso_code];
                // Safety check
                if ($lang == '') {
                    Logger::addLog('MailChimp language code could not be found for language with ISO: '.$this->context->language->iso_code);
                    $lang = 'en';
                }
                // Create and append subscribers
                foreach ($result as $row) {
                    $list[] = new \MailChimpModule\MailChimpSubscriber(
                        $row['email'],
                        $subscription,
                        null,
                        null,
                        $row['ip_registration_newsletter'],
                        $lang,
                        $row['newsletter_date_add']
                    );
                }
            }
        }

        return $list;
    }

    /**
     * @param bool $optedIn
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function getCustomerSubscriptions($optedIn = false)
    {
        $list = [];
        $sql = new DbQuery();
        $sql->select('pc.`email`, pc.`firstname`, pc.`lastname`, pc.`ip_registration_newsletter`, pc.`newsletter_date_add`, pl.`iso_code`');
        $sql->from('customer', 'pc');
        $sql->leftJoin('lang', 'pl', 'pl.`id_lang` = pc.`id_lang`');
        $sql->where('pc.`id_shop` = '.(int) $this->context->shop->id);

        $sql->where('pc.`newsletter` = 1');
        $sql->where('pc.`deleted` = 0');
        // Opt-in selection is only valid if not all users have been asked
        if ($optedIn) {
            $sql->where('pc.`optin` = 1');
        }
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        if ($result) {
            // If confirmation mail is to be sent, statuses must be post as pending to the MailChimp API
            $subscription = (string) Configuration::get(self::CONFIRMATION_EMAIL) ? \MailChimpModule\MailChimpSubscriber::SUBSCRIPTION_PENDING : \MailChimpModule\MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED;
            // Create an array for non-exist language codes
            $logLang = [];
            // Create and append subscribers
            foreach ($result as $row) {
                $lang = $this->mailChimpLanguages[$row['iso_code']];
                // Safety check
                if ($lang == '') {
                    $logLang[$lang] = true;
                    $lang = 'en';
                }
                $list[] = new \MailChimpModule\MailChimpSubscriber(
                    $row['email'],
                    $subscription,
                    $row['firstname'],
                    $row['lastname'],
                    $row['ip_registration_newsletter'],
                    $lang,
                    $row['newsletter_date_add']
                );
            }
            foreach ($logLang as $lang => $value) {
                Logger::addLog('MailChimp language code could not be found for language with ISO: '.$lang);
            }
        }

        return $list;
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
        $this->mailChimp = new \MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY, null, null, 1));
        $this->mailChimp->verifySsl = false;

        $batch = $this->mailChimp->newBatch();

        // Append subscribers to batch operation request using PUT method (to enable update existing)
        for ($i = 0; $i < count($list); $i++) {
            $subscriber = $list[$i];
            $hash = $this->mailChimp->subscriberHash($subscriber->getEmail());
            $url = sprintf('lists/%s/members/%s', Configuration::get(self::IMPORT_LIST), $hash);
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

        $fieldsForm2 = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Cron Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'description' => $this->display(__FILE__, 'views/templates/admin/cron_settings.tpl'),
            ],
        ];

        $fields[] = $fieldsForm1;
        $fields[] = $fieldsForm2;

        if ($this->checkApiKey()) {
            $inputs2 = [];

            $inputs2[] = [
                'type'   => 'switch',
                'label'  => $this->l('Confirmation Email'),
                'name'   => self::CONFIRMATION_EMAIL,
                'desc'   => $this->l('If you turn this on, Mailchimp will send an email to customers asking them to confirm their subscription.'),
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
     * @return array
     *
     * @since 1.0.0
     */
    protected function getConfigFieldsValues()
    {
        return [
            self::API_KEY            => Configuration::get(self::API_KEY, null, null, 1),
            self::IMPORT_LIST        => Configuration::get(self::IMPORT_LIST),
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
        $helper->table = \MailChimpModule\MailChimpShop::$definition['table'];
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
                        'lists' => [0 => $this->l('Do not sync')] + $this->getLists(true),
                        'shops' => \MailChimpModule\MailChimpShop::getShops(true),
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
        $helper->table = \MailChimpModule\MailChimpShop::$definition['table'];
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
                        'shops' => \MailChimpModule\MailChimpShop::getShops(true),
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
        $helper->table = \MailChimpModule\MailChimpShop::$definition['table'];
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
                        'shops' => \MailChimpModule\MailChimpShop::getShops(true),
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
        $helper->table = \MailChimpModule\MailChimpShop::$definition['table'];
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
                        'shops' => \MailChimpModule\MailChimpShop::getShops(true),
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
        if (Configuration::get(self::API_KEY_VALID)) {
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

        $products = \MailChimpModule\MailChimpProduct::getProducts($idShop, $offset, self::EXPORT_CHUNK_SIZE, $remaining);
        if (empty($products)) {
            return '';
        }

        $mailChimp = new MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY));
        $batch = $mailChimp->newBatch();

        $link = \Context::getContext()->link;

        foreach ($products as &$product) {
            $productObj = new Product();
            $productObj->hydrate($product);

            $allImages = $productObj->getImages($idLang);
            $allCombinations = $productObj->getAttributeCombinations($idLang);
            $allCombinationImages = $productObj->getCombinationImages($idLang);

            $variants = [];
            foreach ($allCombinations as $combination) {
                $variant = [
                    'id' => $combination['id_product_attribute'],
                    'title' => $product['name'],
                    'sku' => $combination['reference'],
                    'price' => (float) $product['price'] + (float) $combination['price'],
                    'inventory_quantity' => $combination['quantity'],
                ];
                if (isset($allCombinationImages[$combination['id_product_attribute']])) {
                    $variant['image_url'] = $link->getImageLink('default', "{$product['id_product']}-{$allCombinationImages[$combination['id_product_attribute']][0]}");
                }
                $variants[] = $variant;
            }

            $payload = [
                'id'       => (string) $product['id_product'],
                'title'    => (string) $product['name'],
                'url'      => $link->getProductLink($product['id_product']),
                'description' => $product['description_short'],
                'vendor' => $product['manufacturer'] ?: '',
                'image_url' => !empty($allImages) ? $link->getImageLink('default', "{$product['id_product']}-{$allImages[0]['id_image']}") : '',
                'variants' => $variants,
            ];
            if ($product['last_synced']) {
                $batch->patch(
                    'op'.(int) $product['id_product'],
                    "ecommerce/stores/tbstore_{$idShop}/products/{$product['id_product']}",
                    $payload
                );
            } else {
                $batch->post(
                    'op'.(int) $product['id_product'],
                    "ecommerce/stores/tbstore_{$idShop}/products",
                    $payload
                );
            }
        }

        $result = $batch->execute(self::API_TIMEOUT);
        if (!empty($result)) {
            if (\MailChimpModule\MailChimpProduct::setSynced(array_column($products, 'id_product'), $idShop)) {
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

        $carts = \MailChimpModule\MailChimpCart::getCarts($idShop, $offset, self::EXPORT_CHUNK_SIZE, $remaining);
        if (empty($carts)) {
            return '';
        }

        $mailChimp = new MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY));
        $batch = $mailChimp->newBatch();

        foreach ($carts as &$cart) {
            $payload = [
                'id'            => (string) $cart['id_cart'],
                'customer'      => [
                    'id'            => (string) $cart['id_customer'],
                    'email_address' => (string) $cart['email'],
                    'first_name'    => (string) $cart['firstname'],
                    'last_name'     => (string) $cart['lastname'],
                    'opt_in_status' => (bool) $cart['newsletter'],
                ],
                'currency_code' => (string) $cart['currency_code'],
                'order_total'   => (string) $cart['order_total'],
                'lines'         => $cart['lines'],
            ];

            if ($cart['last_synced']) {
                $batch->patch(
                    'op'.(int) $cart['id_cart'],
                    "ecommerce/stores/tbstore_{$idShop}/carts/{$cart['id_cart']}",
                    $payload
                );
            } else {
                $batch->post(
                    'op'.(int) $cart['id_cart'],
                    "ecommerce/stores/tbstore_{$idShop}/carts",
                    $payload
                );
            }
        }

        $result = $batch->execute(self::API_TIMEOUT);
        if (!empty($result)) {
            if (\MailChimpModule\MailChimpCart::setSynced(array_column($carts, 'id_cart'))) {
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

        // We use the cart objects
        $carts = \MailChimpModule\MailChimpOrder::getOrders($idShop, $offset, self::EXPORT_CHUNK_SIZE, $exportRemaining);
        if (empty($carts)) {
            return '';
        }

        $mailChimp = new MailChimpModule\MailChimp\MailChimp(Configuration::get(self::API_KEY));
        $batch = $mailChimp->newBatch();

        foreach ($carts as &$cart) {
            if (empty($cart['lines'])) {
                unset($cart);
                continue;
            }

            $payload = [
                'id'            => (string) $cart['id_cart'],
                'customer'      => [
                    'id'            => (string) $cart['id_customer'],
                    'email_address' => (string) $cart['email'],
                    'first_name'    => (string) $cart['firstname'],
                    'last_name'     => (string) $cart['lastname'],
                    'opt_in_status' => (bool) $cart['newsletter'],
                ],
                'currency_code' => (string) $cart['currency_code'],
                'order_total'   => (string) $cart['order_total'],
                'lines'         => $cart['lines'],
                'tracking_code' => $cart['mc_tc'],
            ];

            if ($cart['last_synced']) {
                $batch->patch(
                    'op'.(int) $cart['id_order'],
                    "ecommerce/stores/tbstore_{$idShop}/orders/{$cart['id_order']}",
                    $payload
                );
            } else {
                $batch->post(
                    'op'.(int) $cart['id_cart'],
                    "ecommerce/stores/tbstore_{$idShop}/orders",
                    $payload
                );
            }
        }

        $result = $batch->execute(self::API_TIMEOUT);
        if (!empty($result)) {
            if (\MailChimpModule\MailChimpOrder::setSynced(array_column($carts, 'id_order'))) {
                Configuration::updateValue(self::ORDERS_LAST_SYNC, date('Y-m-d H:i:s'), false, null, $idShop);
            }

            return $result['id'];
        }

        return '';
    }
}
