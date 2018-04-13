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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise\EachPromise;
use MailChimpModule\MailChimpCart;
use MailChimpModule\MailChimpOrder;
use MailChimpModule\MailChimpProduct;
use MailChimpModule\MailChimpPromo;
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
 *
 * Translations:
 * $this->l('Unable to count subscribers');
 * $this->l('Unable to count carts');
 * $this->l('Unable to count orders');
 * $this->l('Unable to set sync status');
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
    const API_TIMEOUT = 20;
    const API_CONCURRENCY = 5;

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

    const EXPORT_CHUNK_SIZE = 10;

    const MENU_IMPORT = 1;
    const MENU_SHOPS = 2;
    const MENU_PRODUCTS = 3;
    const MENU_CARTS = 4;
    const MENU_ORDERS = 5;

    const COOKIE_LIFETIME = 259200;

    /** @var string $baseUrl */
    public $baseUrl;
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
    /** @var string $apiKey */
    protected static $apiKey;
    /** @var Client $guzzle */
    protected static $guzzle;

    /**
     * MailChimp constructor.
     *
     * @since 1.0.0
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'mailchimp';
        $this->tab = 'advertising_marketing';
        $this->version = '1.2.0';
        $this->author = 'thirty bees';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('MailChimp');
        $this->description = $this->l('Synchronize with MailChimp');

        $this->controllers = ['cron', 'hook'];

        if (isset(Context::getContext()->employee->id) && Context::getContext()->employee->id) {
            $this->baseUrl = $this->context->link->getAdminLink('AdminModules', true).'&'.http_build_query([
                    'configure'   => $this->name,
                    'tab_module'  => $this->tab,
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
     * @throws PrestaShopException
     */
    public function install()
    {
        if (!parent::install()
            || !MailChimpRegisteredWebhook::createDatabase()
            || !MailChimpShop::createDatabase()
            || !MailChimpProduct::createDatabase()
            || !MailChimpCart::createDatabase()
            || !MailChimpOrder::createDatabase()
            || !MailChimpPromo::createDatabase()
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
        $this->registerHook('actionAdminCartRulesListingFieldsModifier');

        return true;
    }

    /**
     * Uninstall this module
     *
     * @return bool Indicates whether this module has been successfully uninstalled
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.0.0
     */
    public function uninstall()
    {
        if (!parent::uninstall()
            || !Configuration::deleteByName(static::API_KEY)
            || !Configuration::deleteByName(static::CONFIRMATION_EMAIL)
            || !Configuration::deleteByName(static::IMPORT_OPTED_IN)
            || !Configuration::deleteByName(static::LAST_IMPORT)
            || !Configuration::deleteByName(static::LAST_IMPORT_ID)
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
     * @throws Exception
     * @throws PrestaShopException
     * @throws SmartyException
     * @since 1.0.0
     */
    public function getContent()
    {
        MailChimpPromo::duplicateCartRules(1065);
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
     * @throws PrestaShopException
     */
    public function getLists($prepare = false)
    {
        try {
            $lists = json_decode((string) static::getGuzzle()->get(
                'lists',
                [
                    'headers' => [

                    ],
                ])->getBody(), true);

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
     *
     * @throws PrestaShopException
     */
    public function hookDisplayHeader()
    {
        // Set MailChimp tracking code
        if (Tools::isSubmit('mc_tc') || Tools::isSubmit('mc_cid')) {
            $cookie = new Cookie('tb_mailchimp');
            $cookie->mc_tc = Tools::getValue('mc_tc');
            $cookie->mc_cid = Tools::getValue('mc_cid');
            $cookie->setExpire(static::COOKIE_LIFETIME);
            $cookie->write();
        }
    }

    /**
     * Hook to front office footer
     *
     * @return void
     *
     * @throws Exception
     * @throws PrestaShopException
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
//                    if (is_object($this->mailChimp)) {
//                        Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
//                    } else {
//                        Logger::addLog('MailChimp customer subscription failed');
//                    }
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
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
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
                Logger::addLog("Unable to set Mailchimp tracking code $cookie->mc_tc for Order {$params['order']->id}", 2);
            } elseif (isset ($cookie->mc_cid) && isset($params['order']->id)) {
                Logger::addLog("Unable to set Mailchimp tracking code $cookie->mc_cid for Order {$params['order']->id}", 2);
            } else {
                Logger::addLog('Unable to set Mailchimp tracking code for Order', 2);
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
        if (isset(static::$mailChimpLanguages[$iso])) {
            $lang = static::$mailChimpLanguages[$iso];
        } else {
            $lang = 'en';
        }

        return $lang;
    }

    /**
     * @param MailChimpSubscriber $subscription
     *
     * @return bool
     *
     * @throws Exception
     * @throws PrestaShopException
     * @since 1.0.0
     */
    public function addOrUpdateSubscription(MailChimpSubscriber $subscription)
    {
        return $this->importList([$subscription]);
    }

    /**
     * @param array $params
     *
     * @throws Exception
     * @throws PrestaShopException
     * @since 1.0.0
     */
    public function hookActionCustomerAccountAdd($params)
    {
        // Check if creation is successful
        if (isset($params['newCustomer']) && $params['newCustomer']->email) {
            $subscription = (string) $params['newCustomer']->newsletter
                ? MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED
                : MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
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
//                if (is_object($this->mailChimp)) {
//                    Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
//                } else {
//                    Logger::addLog('MailChimp customer subscription failed');
//                }
            }
        }
    }

    /**
     * Action update customer after
     *
     * @param array $params
     *
     * @throws Exception
     * @throws PrestaShopException
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
//            if (is_object($this->mailChimp)) {
//                Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
//            } else {
//                Logger::addLog('MailChimp customer subscription failed');
//            }
        }

    }

    /**
     * Action add customer after
     *
     * @param array $params
     *
     * @throws Exception
     * @throws PrestaShopException
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
//            if (is_object($this->mailChimp)) {
//                Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
//            } else {
//                Logger::addLog('MailChimp customer subscription failed');
//            }
        }
    }

    /**
     * @param array $params
     *
     * @throws Exception
     * @throws PrestaShopException
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
//                if (is_object($this->mailChimp)) {
//                    Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
//                } else {
//                    Logger::addLog('MailChimp customer subscription failed');
//                }
            }
        }
    }

    /**
     * @param array $params
     *
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionAdminCartRulesListingFieldsModifier($params)
    {
        // Process submits
        if (Tools::isSubmit('statusmailchimp_promo')) {
            MailChimpPromo::toggle(Tools::getValue('id_cart_rule'));
        }

        $params['select'] .= "\n\t\tIF(mcp.`enabled`, 'true', 'false') AS `mailchimp_enabled`, mcp.`locked` AS `mailchimp_locked`";
        $params['join'] .= "\n\t\tLEFT JOIN `"._DB_PREFIX_.bqSQL(MailChimpPromo::$definition['table']).'` mcp ON (mcp.`id_cart_rule` = a.`id_cart_rule`)';
        if (isset($params['fields'])) {
            $params['fields']['mailchimp_enabled'] = [
                'title'           => $this->l('MailChimp'),
                'class'           => 'fixed-width-lg',
                'callback'        => 'printMailChimpPromoButton',
                'callback_object' => 'MailChimpModule\\MailChimpPromo',
                'search'          => false,
                'orderby'         => false,
                'remove_onclick'  => true,
            ];
        }
    }

    /**
     * Get the Guzzle client
     *
     * @return Client
     * @throws PrestaShopException
     * @throws Adapter_Exception
     */
    public static function getGuzzle()
    {
        if (!static::$guzzle) {
            // Initialize Guzzle and the retry middleware, include the default options
            $apiKey = Configuration::get(static::API_KEY, null, null, (int) Configuration::get('PS_SHOP_DEFAULT'));
            $dc = substr($apiKey, -4);
            $guzzle = new Client(array_merge(
                [
                    'timeout'         => static::API_TIMEOUT,
                    'connect_timeout' => static::API_TIMEOUT,
                    'verify'          => _PS_TOOL_DIR_.'cacert.pem',
                    'base_uri'        => "https://$dc.api.mailchimp.com/3.0/",
                    'headers'         => [
                        'Accept'        => 'application/json',
                        'Authorization' => 'Basic '.base64_encode("anystring:$apiKey"),
                        'Content-Type'  => 'application/json;charset=UTF-8',
                        'User-Agent'    => static::getUserAgent(),
                    ],
                ]
            ));

            static::$guzzle = $guzzle;
        }

        return static::$guzzle;
    }

    /**
     * Reset Guzzle
     */
    public static function resetGuzzle()
    {
        static::$guzzle = null;
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public static function getApiKey()
    {
        if (!static::$apiKey) {
            $idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
            $idShopGroup = (int) Shop::getGroupFromShop($idShop, true);
            static::$apiKey = Configuration::get(static::API_KEY, null ,$idShopGroup, $idShop);
        }

        return static::$apiKey;
    }

    /**
     * @param string $apiKey
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function setApiKey($apiKey)
    {
        $idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
        $idShopGroup = (int) Shop::getGroupFromShop($idShop, true);

        // Reset the internal Guzzle
        static::$guzzle = null;
        // Change the internal API key
        static::$apiKey = $apiKey;
        return Configuration::updateValue(static::API_KEY, $apiKey, false, $idShopGroup, $idShop);
    }

    /**
     * Display export modals
     *
     * @return string
     *
     * @throws Exception
     * @throws PrestaShopException
     * @throws SmartyException
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
     * @throws Exception
     * @throws PrestaShopException
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
            $totalChunks = ceil($totalSubscribers / static::EXPORT_CHUNK_SIZE);

            Configuration::updateValue(static::SUBSCRIBERS_SYNC_COUNT, 0, false, 0, 0);
            Configuration::updateValue(static::SUBSCRIBERS_SYNC_TOTAL, $totalChunks, false, 0, 0);

            die(json_encode([
                'totalChunks'      => $totalChunks,
                'totalSubscribers' => $totalSubscribers,
            ]));
        } elseif (Tools::isSubmit('next')) {
            $count = (int) Configuration::get(static::SUBSCRIBERS_SYNC_COUNT, null, 0, 0) + 1;
            $total = (int) Configuration::get(static::SUBSCRIBERS_SYNC_TOTAL, null, 0, 0);
            Configuration::updateValue(static::SUBSCRIBERS_SYNC_COUNT, $count, null, 0, 0);
            $remaining = $total - $count;

            $this->exportSubscribers(($count - 1) * static::EXPORT_CHUNK_SIZE, $idShop);

            die(json_encode([
                'success'   => true,
                'remaining' => $remaining,
            ]));
        }
    }

    /**
     * Ajax process export all products
     *
     * @return void
     *
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
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
            $totalChunks = ceil($totalProducts / static::EXPORT_CHUNK_SIZE);

            Configuration::updateValue(static::PRODUCTS_SYNC_COUNT, 0, false, 0, 0);
            Configuration::updateValue(static::PRODUCTS_SYNC_TOTAL, $totalChunks, false, 0, 0);

            die(json_encode([
                'totalChunks'   => $totalChunks,
                'totalProducts' => $totalProducts,
            ]));
        } elseif (Tools::isSubmit('next')) {
            $count = (int) Configuration::get(static::PRODUCTS_SYNC_COUNT, null, 0, 0) + 1;
            $total = (int) Configuration::get(static::PRODUCTS_SYNC_TOTAL, null, 0, 0);
            Configuration::updateValue(static::PRODUCTS_SYNC_COUNT, $count, null, 0, 0);
            $remaining = $total - $count;

            $this->exportProducts(($count - 1) * static::EXPORT_CHUNK_SIZE, $idShop, $exportRemaining);

            die(json_encode([
                'success'   => true,
                'remaining' => $remaining,
            ]));
        }
    }

    /**
     * Ajax process export all carts
     *
     * @return void
     *
     * @throws Exception
     * @throws PrestaShopException
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
            $totalChunks = ceil($totalCarts / static::EXPORT_CHUNK_SIZE);

            Configuration::updateValue(static::CARTS_SYNC_COUNT, 0, false, 0, 0);
            Configuration::updateValue(static::CARTS_SYNC_TOTAL, $totalChunks, false, 0, 0);

            die(json_encode([
                'totalChunks' => $totalChunks,
                'totalCarts'  => $totalCarts,
            ]));
        } elseif (Tools::isSubmit('next')) {
            $count = (int) Configuration::get(static::CARTS_SYNC_COUNT, null, 0, 0) + 1;
            $total = (int) Configuration::get(static::CARTS_SYNC_TOTAL, null, 0, 0);
            Configuration::updateValue(static::CARTS_SYNC_COUNT, $count, null, 0, 0);
            $remaining = $total - $count;

            $this->exportCarts(($count - 1) * static::EXPORT_CHUNK_SIZE, $exportRemaining);

            die(json_encode([
                'success'   => true,
                'remaining' => $remaining,
            ]));
        }
    }

    /**
     * Ajax export all orders
     *
     * @return void
     *
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
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
            $totalChunks = ceil($totalOrders / static::EXPORT_CHUNK_SIZE);

            Configuration::updateValue(static::ORDERS_SYNC_COUNT, 0, false, 0, 0);
            Configuration::updateValue(static::ORDERS_SYNC_TOTAL, $totalChunks, false, 0, 0);

            die(json_encode([
                'totalChunks' => $totalChunks,
                'totalOrders' => $totalOrders,
            ]));
        } elseif (Tools::isSubmit('next')) {
            $count = (int) Configuration::get(static::ORDERS_SYNC_COUNT, null, 0, 0) + 1;
            $total = (int) Configuration::get(static::ORDERS_SYNC_TOTAL, null, 0, 0);
            Configuration::updateValue(static::ORDERS_SYNC_COUNT, $count, null, 0, 0);
            $remaining = $total - $count;

            $this->exportOrders(($count - 1) * static::EXPORT_CHUNK_SIZE, $exportRemaining);

            die(json_encode([
                'success'   => true,
                'remaining' => $remaining,
            ]));
        }
    }

    /**
     * Reset product sync data
     *
     * @return void
     *
     * @since 1.1.0
     * @throws PrestaShopException
     */
    public function displayAjaxResetProducts()
    {
        if ($idShop = (int) Tools::getValue('shop')) {
            $this->processReset('products', $idShop, true);
        }

        die(json_encode([
            'success' => false,
        ]));
    }

    /**
     * Reset cart sync data
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.1.0
     */
    public function displayAjaxResetCarts()
    {
        if ($idShop = (int) Tools::getValue('shop')) {
            $this->processReset('carts', $idShop, true);
        }

        die(json_encode([
            'success' => false,
        ]));
    }

    /**
     * Reset order sync data
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.1.0
     */
    public function displayAjaxResetOrders()
    {
        if ($idShop = (int) Tools::getValue('shop')) {
            $this->processReset('orders', $idShop, true);
        }

        die(json_encode([
            'success' => false,
        ]));
    }

    /**
     * Reset order sync data
     *
     * @param string $entityType
     * @param int    $idShop
     * @param bool   $ajax
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.1.0
     */
    public function processReset($entityType = 'products', $idShop, $ajax = false)
    {
        switch ($entityType) {
            case 'products':
                $table = 'product_shop';
                $primary = 'id_product';
                break;
            case 'carts':
                $table = 'cart';
                $primary = 'id_cart';
                break;
            case 'orders':
                $table = 'orders';
                $primary = 'id_order';
                break;
        }
        $entity = 'MailChimp'.ucfirst(substr($entityType, 0, strlen($entityType) - 1));
        if (!isset($table) || !isset($primary)) {
            return false;
        }

        try {
            $success = Db::getInstance()->execute(
                'DELETE mo
                 FROM `'._DB_PREFIX_.bqSQL((new ReflectionProperty('\\MailChimpModule\\'.$entity, 'definition'))->getValue()['table']).'` mo
                 INNER JOIN `'._DB_PREFIX_.bqSQL($table).'` o ON o.`'.bqSQL($primary).'` = mo.`'.bqSQL($primary).'`
                 WHERE o.`id_shop` = '.(int) $idShop
            );
        } catch (PrestaShopDatabaseException $e) {
            Logger::addLog("MailChimp module error: {$e->getMessage()}");
        } catch (PrestaShopException $e) {
            Logger::addLog("MailChimp module error: {$e->getMessage()}");
            $success = false;
        } catch (ReflectionException $e) {
            Logger::addLog("MailChimp module error: {$e->getMessage()}");
            $success = false;
        }

        if ($ajax) {
            die(json_encode([
                'success' => $success,
            ]));
        }

        return $success;
    }

    /**
     * @param string $type            `products`, `carts` or `orders`
     * @param int    $idShop
     * @param int    $exportRemaining
     * @param string $submit
     *
     * @return array|false
     * @throws PrestaShopException
     */
    public function cronExport($type = 'products', $idShop, $exportRemaining, $submit)
    {
        if ($submit === 'start') {
            $totalItems = call_user_func('\\MailChimpModule\\MailChimp'.ucfirst(substr($type, 0, strlen($type) - 1)).'::count'.ucfirst($type), $idShop, $exportRemaining);
            $totalChunks = ceil($totalItems / static::EXPORT_CHUNK_SIZE);

            Configuration::updateValue(constant(__CLASS__.'::'.strtoupper($type).'_SYNC_COUNT'), 0, false, 0, 0);
            Configuration::updateValue(constant(__CLASS__.'::'.strtoupper($type).'_SYNC_TOTAL'), $totalChunks, false, 0, 0);

            return [
                'totalChunks'          => $totalChunks,
                'total'.ucfirst($type) => $totalItems,
            ];
        } elseif ($submit === 'next') {
            $count = (int) Configuration::get(constant(__CLASS__.'::'.strtoupper($type).'_SYNC_COUNT'), null, 0, 0) + 1;
            $total = (int) Configuration::get(constant(__CLASS__.'::'.strtoupper($type).'_SYNC_TOTAL'), null, 0, 0);
            Configuration::updateValue(constant(__CLASS__.'::'.strtoupper($type).'_SYNC_COUNT'), $count, null, 0, 0);
            $remaining = $total - $count;

            $this->{'export'.ucfirst($type)}(($count - 1) * static::EXPORT_CHUNK_SIZE, $idShop, $exportRemaining);

            return [
                'success'   => true,
                'remaining' => $remaining,
            ];
        }

        return false;
    }

    /**
     * Process configuration
     *
     * @throws Exception
     */
    protected function postProcess()
    {
        $idShopDefault = (int) Configuration::get('PS_SHOP_DEFAULT');
        if (Tools::isSubmit('submitApiKey')) {
            // Check if MailChimp API key is valid
            static::setApiKey(Tools::getValue(static::API_KEY));
            Configuration::updateValue(static::API_KEY_VALID, false, false, null, $idShopDefault);
            $this->checkApiKey();
        } elseif (Tools::isSubmit('submitSettings')) {
            // Update all the configuration
            // And check if updates were successful
            $confirmationEmail = (bool) Tools::getvalue(static::CONFIRMATION_EMAIL);
            $importOptedIn = (bool) Tools::getvalue(static::IMPORT_OPTED_IN);

            if (Configuration::updateValue(static::CONFIRMATION_EMAIL, $confirmationEmail)
                && Configuration::updateValue(static::IMPORT_OPTED_IN, $importOptedIn)
            ) {
                $this->addConfirmation($this->l('Settings updated.'));
            } else {
                $this->addError($this->l('Some of the settings could not be saved.'));
            }
        } elseif (Tools::isSubmit('submitShops')) {
            $shopLists = Tools::getValue('shop_list_id');
            $shopTaxes = Tools::getValue('shop_tax');
            $client = static::getGuzzle();
            if (is_array($shopLists)) {
                foreach ($shopLists as $idShop => $idList) {
                    if ($idList) {
                        $this->checkMergeFields($idList);
                        $shop = new Shop($idShop);
                        $defaultIdCurrency = (int) Configuration::get('PS_CURRENCY_DEFAULT', null, $shop->id_shop_group, $shop->id);
                        $currency = new Currency($defaultIdCurrency);

                        $mailChimpShop = MailChimpShop::getByShopId($idShop);
                        if (!Validate::isLoadedObject($mailChimpShop)) {
                            $mailChimpShop = new MailChimpShop();
                        }

                        if ($mailChimpShop->list_id && $mailChimpShop->list_id !== $idList) {
                            try {
                                $client->delete('ecommerce/stores/tbstore_'.(int) $idShop);
                            } catch (TransferException $e){
                            }
                            try {
                                $client->post(
                                    'ecommerce/stores',
                                    [
                                        'body' => json_encode([
                                            'id'            => 'tbstore_'.(int) $idShop,
                                            'list_id'       => $idList,
                                            'name'          => $shop->name,
                                            'domain'        => $shop->domain_ssl,
                                            'email_address' => Configuration::get('PS_SHOP_EMAIL', null, $shop->id_shop_group, $shop->id),
                                            'currency_code' => strtoupper($currency->iso_code),
                                        ]),
                                    ]
                                );
                            } catch (ClientException $reason) {
                                $response = (string) $reason->getResponse()->getBody();
                                Logger::addLog("MailChimp store error: {$response}");
                            } catch (TransferException $e) {
                            }
                            MailChimpProduct::resetShop($idShop);
                            MailChimpCart::resetShop($idShop);
                            MailChimpOrder::resetShop($idShop);
                        } elseif (!$mailChimpShop->list_id) {
                            try {
                                $client
                                    ->post(
                                        'ecommerce/stores',
                                        [
                                            'body' => json_encode([
                                                'id'            => 'tbstore_'.(int) $idShop,
                                                'list_id'       => $idList,
                                                'name'          => $shop->name,
                                                'domain'        => $shop->domain_ssl,
                                                'email_address' => Configuration::get('PS_SHOP_EMAIL', null, $shop->id_shop_group, $shop->id),
                                                'currency_code' => strtoupper($currency->iso_code),
                                            ]),
                                        ]
                                    );
                            } catch (ClientException $e) {
                            } catch (TransferException $e) {
                            }
                        }

                        $mailChimpShop->list_id = $idList;
                        $mailChimpShop->id_shop = $idShop;
                        $mailChimpShop->id_tax = (int) $shopTaxes[$idShop];
                        $mailChimpShop->synced = true;

                        try {
                            $mailChimpShop->save();
                        } catch (PrestaShopException $e) {
                            $this->addError($this->l('Shop info could not be saved'));
                        }

                        // Create MailChimp side webhooks
                        if ($mailChimpShop->list_id) {
                            $register = $this->registerWebhookForList($mailChimpShop->list_id);
                            if (!$register) {
                                $this->addError($this->l('MailChimp webhooks could not be implemented. Please try again.'));
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string $idList
     *
     * @return bool
     *
     * @throws Exception
     * @throws PrestaShopException
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
                    Logger::addLog('MailChimp: Could not save webhook to database, List ID: '.$idList.', URL: '.$callbackUrl);
                } else {
                    Logger::addLog('MailChimp: Webhook saved to database, List ID: '.$idList.', URL: '.$callbackUrl);
                }
            } else {
//                if (is_object($this->mailChimp)) {
//                    Logger::addLog('Could not register webhook for list ID: '.$idList.', Error: '.$this->mailChimp->getLastError());
//                } else {
//                    Logger::addLog('Could not register webhook for list');
//                }
            }
        }

        return $result;
    }

    /**
     * @return string
     *
     * @since 1.0.0
     * @throws PrestaShopException
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
     * @throws Exception
     * @throws PrestaShopException
     * @since 1.0.0
     */
    protected function registerWebhook($idList, $url = null)
    {
        if (!$idList) {
            return false;
        }

        if (!$url) {
            $url = $this->urlForWebhook();
        }

        $success = true;
        $client = static::getGuzzle();
        $promise = $client->postAsync(
            "lists/{$idList}/webhooks",
            [
                'body'   => json_encode([
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
                ]),
            ]
        );
        $promise->then(function ($response) use (&$success) {
            $success = $response instanceof \GuzzleHttp\Psr7\Response && $response->getStatusCode() === 200;
        });
        $promise->otherwise(function ($reason) use (&$success, $client) {
            if ($reason instanceof ClientException) {
                $response = json_decode((string) $reason->getResponse()->getBody(), true);
                if (!empty($response['errors'][0]['message']) && $response['errors'][0]['message'] !== 'Sorry, you can\'t set up multiple WebHooks for one URL') {
                    $success = false;
                }
            }
        });
        GuzzleHttp\Promise\settle([$promise])->wait();

        return $success;
    }

    /**
     * @param array $list
     *
     * @return bool
     *
     * @throws Exception
     * @throws PrestaShopException
     * @since 1.0.0
     */
    protected function importList($list)
    {
        $success = true;
        // Append subscribers to batch operation request using PUT method (to enable update existing)
        $client = static::getGuzzle();
        $promises = call_user_func(function () use ($list, $client) {
            for ($i = 0; $i < count($list); $i++) {
                /** @var MailChimpSubscriber $subscriber */
                $subscriber = $list[$i];
                $hash = md5(mb_strtolower($subscriber->getEmail()));
                $mailChimpShop = MailChimpShop::getByShopId(Context::getContext()->shop->id);
                yield $client->putAsync(
                    "lists/{$mailChimpShop->list_id}/members/{$hash}",
                    [
                        'body' => $subscriber->getAsJSON(),
                    ]
                );
            }
        });

        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected'    => function ($reason) use (&$success) {
                if ($reason instanceof \GuzzleHttp\Exception\RequestException) {
                    $body = (string) $reason->getResponse()->getBody();
                    Logger::addLog("MailChimp client error: {$body}", 2);
                } elseif ($reason instanceof Exception || $reason instanceof \GuzzleHttp\Exception\TransferException) {
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                }
                $success = false;
            },
        ]))->promise()->wait();

        return $success;
    }

    /**
     * Display main page
     *
     * @return string
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function displayMainPage()
    {
        $this->context->controller->addJS($this->_path.'views/js/sweetalert.min.js');
        $this->loadTabs();

        $this->context->smarty->assign([
            'availableShops' => Shop::isFeatureActive()
                ? Shop::getShops(true, null, true)
                : [$this->context->shop->id => $this->context->shop->id],
            'exportUrl'      => $this->baseUrl,
        ]);

        return $this->displayModals().$this->display(__FILE__, 'views/templates/admin/main.tpl');
    }

    /**
     * @return string
     *
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @since 1.0.0
     */
    protected function displayApiForm()
    {
        $fields = [];

        $inputs1 = [];

        $inputs1[] = [
            'type'  => 'text',
            'label' => $this->l('API Key'),
            'name'  => static::API_KEY,
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
                'name'   => static::CONFIRMATION_EMAIL,
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
                'name'   => static::IMPORT_OPTED_IN,
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
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true)
            .'&configure='.$this->name.'&tab_module='.$this->tab
            .'&module_name='.$this->name.'#mailchimp_tab_'.static::MENU_IMPORT;
        $helper->token = '';
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
        ];

        return $helper->generateForm($fields);
    }

    /**
     * @return string
     *
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @since 1.0.0
     */
    protected function displayCronForm()
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
                'cron_all_products'           => $context->link->getModuleLink(
                    $this->name,
                    'cron',
                    [
                        'action'  => 'ExportAllProducts',
                        'token'   => $token,
                        'id_shop' => $idShop,
                    ],
                    true,
                    $idLang,
                    $idShop,
                    false
                ),
                'cron_remaining_products'     => $context->link->getModuleLink(
                    $this->name,
                    'cron',
                    [
                        'action'  => 'ExportRemainingProducts',
                        'token'   => $token,
                        'id_shop' => $idShop,
                    ],
                    true,
                    $idLang,
                    $idShop,
                    false
                ),
                'cron_all_carts'              => $context->link->getModuleLink(
                    $this->name,
                    'cron',
                    [
                        'action'  => 'ExportAllCarts',
                        'token'   => $token,
                        'id_shop' => $idShop,
                    ],
                    true,
                    $idLang,
                    $idShop,
                    false
                ),
                'cron_remaining_carts'        => $context->link->getModuleLink(
                    $this->name,
                    'cron',
                    [
                        'action'  => 'ExportRemainingCarts',
                        'token'   => $token,
                        'id_shop' => $idShop,
                    ],
                    true,
                    $idLang,
                    $idShop,
                    false
                ),
                'cron_all_orders'             => $context->link->getModuleLink(
                    $this->name,
                    'cron',
                    [
                        'action'  => 'ExportAllOrders',
                        'token'   => $token,
                        'id_shop' => $idShop,
                    ],
                    true,
                    $idLang,
                    $idShop,
                    false
                ),
                'cron_remaining_orders'       => $context->link->getModuleLink(
                    $this->name,
                    'cron',
                    [
                        'action'  => 'ExportRemainingOrders',
                        'token'   => $token,
                        'id_shop' => $idShop,
                    ],
                    true,
                    $idLang,
                    $idShop,
                    false
                ),
                'cron_all_products_cli'       => PHP_BINARY.' '._PS_MODULE_DIR_."mailchimp/cli.php --shop=$idShop --action=ExportAllProducts",
                'cron_remaining_products_cli' => PHP_BINARY.' '._PS_MODULE_DIR_."mailchimp/cli.php --shop=$idShop --action=ExportRemainingProducts",
                'cron_all_carts_cli'          => PHP_BINARY.' '._PS_MODULE_DIR_."mailchimp/cli.php --shop=$idShop --action=ExportAllCarts",
                'cron_remaining_carts_cli'    => PHP_BINARY.' '._PS_MODULE_DIR_."mailchimp/cli.php --shop=$idShop --action=ExportRemainingCarts",
                'cron_all_orders_cli'         => PHP_BINARY.' '._PS_MODULE_DIR_."mailchimp/cli.php --shop=$idShop --action=ExportAllOrders",
                'cron_remaining_orders_cli'   => PHP_BINARY.' '._PS_MODULE_DIR_."mailchimp/cli.php --shop=$idShop --action=ExportRemainingOrders",
                'id_profile'                  => $this->context->employee->id_profile,
            ]
        );

        $fields = [];

        $fieldsForm2 = [
            'form' => [
                'legend'      => [
                    'title' => $this->l('Cron Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'description' => $this->display(__FILE__, 'views/templates/admin/cron_settings.tpl'),
            ],
        ];

        $fields[] = $fieldsForm2;

        $helper = new HelperForm();
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true)
            .'&configure='.$this->name.'&tab_module='.$this->tab
            .'&module_name='.$this->name.'#mailchimp_tab_'.static::MENU_IMPORT;
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
     * @throws PrestaShopException
     */
    protected function getConfigFieldsValues()
    {
        return [
            static::API_KEY            => static::getApiKey(),
            static::CONFIRMATION_EMAIL => Configuration::get(static::CONFIRMATION_EMAIL),
            static::IMPORT_OPTED_IN    => Configuration::get(static::IMPORT_OPTED_IN),
        ];
    }

    /**
     * Render Customer export form
     *
     * @return string Form HTML
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function displayShopsForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = MailChimpShop::$definition['table'];
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitShops';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true)
            .'&configure='.$this->name.'&tab_module='.$this->tab
            .'&module_name='.$this->name.'#mailchimp_tab_'.static::MENU_SHOPS;

        $helper->token = '';

        $helper->tpl_vars = [
            'languages'   => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getShopsForm()]);
    }

    /**
     * @return array
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
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
                    'icon'  => 'icon-building',
                ],
                'input'  => [
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
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function displayProductsForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = MailChimpShop::$definition['table'];
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProducts';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true)
            .'&configure='.$this->name.'&tab_module='.$this->tab
            .'&module_name='.$this->name.'#mailchimp_tab_'.static::MENU_PRODUCTS;

        $helper->token = '';

        $helper->tpl_vars = [
            'languages'   => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getProductsForm()]);
    }

    /**
     * @return array
     * @throws PrestaShopException
     */
    protected function getProductsForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Product settings'),
                    'icon'  => 'icon-archive',
                ],
                'input'  => [
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
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function displayCartsForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = MailChimpShop::$definition['table'];
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProducts';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true)
            .'&configure='.$this->name.'&tab_module='.$this->tab
            .'&module_name='.$this->name.'#mailchimp_tab_'.static::MENU_CARTS;

        $helper->token = '';

        $helper->tpl_vars = [
            'languages'   => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getCartsForm()]);
    }

    /**
     * @return array
     * @throws PrestaShopException
     */
    protected function getCartsForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Cart settings'),
                    'icon'  => 'icon-shopping-cart',
                ],
                'input'  => [
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
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function displayOrdersForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = MailChimpShop::$definition['table'];
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProducts';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true)
            .'&configure='.$this->name.'&tab_module='.$this->tab
            .'&module_name='.$this->name.'#mailchimp_tab_'.static::MENU_ORDERS;

        $helper->token = '';

        $helper->tpl_vars = [
            'languages'   => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getOrdersForm()]);
    }

    /**
     * @return array
     * @throws PrestaShopException
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
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @since 1.0.0
     */
    protected function loadTabs()
    {
        $contents = [
            [
                'name'  => $this->l('Import Settings'),
                'icon'  => 'icon-download',
                'value' => $this->displayApiForm(),
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
            'value' => $this->displayCronForm(),
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
     * @throws PrestaShopException
     */
    protected function checkApiKey()
    {
        if (static::getApiKey() && Configuration::get(static::API_KEY_VALID)) {
            return true;
        }

        // Show settings form only if API key is set and working
        $apiKey = static::getApiKey();
        $dc = substr($apiKey, -4);
        $validKey = false;
        if (isset($apiKey) && $apiKey) {
            // Check if API key is valid
            try {
                $getLists = json_decode((string) (new Client([
                    'verify'   => _PS_TOOL_DIR_.'cacert.pem',
                    'timeout'  => static::API_TIMEOUT,
                    'base_uri' => "https://$dc.api.mailchimp.com/3.0/",
                ]))->get('lists', [
                    'headers' => [
                        'Accept'        => 'application/json',
                        'Authorization' => 'Basic '.base64_encode("anystring:$apiKey"),
                        'User-Agent'    => static::getUserAgent(),
                    ],
                ])->getBody(), true);
                if ($getLists) {
                    $validKey = true;
                    Configuration::updateValue(static::API_KEY_VALID, true);
                }
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $body = (string) $e->getResponse()->getBody();
                $this->addError("MailChimp client error: {$body}");
            } catch (\GuzzleHttp\Exception\TransferException $e) {
                $this->addError("MailChimp connection error: {$e->getMessage()}");
            } catch (Exception $e) {
                $this->addError("MailChimp generic error: {$e->getMessage()}");
            }
        }

        return $validKey;
    }

    /**
     * Export subscribers
     *
     * @param int $offset
     * @param int $idShop
     *
     * @return string
     * @throws Exception
     * @throws PrestaShopException
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

        $client = static::getGuzzle();
        $promises = call_user_func(function () use (&$subscribers, $mailChimpShop, $client) {
            foreach ($subscribers as &$subscriber) {
                $mergeFields = [
                    'FNAME' => $subscriber['firstname'],
                    'LNAME' => $subscriber['lastname'],
                    'TBREF' => $subscriber['tbref'],
                ];
                if ($subscriber['birthday']) {
                    $mergeFields['BDAY'] = date('m/d', strtotime($subscriber['birthday']));
                }

                $subscriberHash = md5(mb_strtolower($subscriber['email']));
                yield $client->putAsync(
                    "lists/{$mailChimpShop->list_id}/members/{$subscriberHash}",
                    [
                        'body'    => json_encode([
                            'email_address' => mb_strtolower($subscriber['email']),
                            'status_if_new' => $subscriber['subscription'],
                            'merge_fields'  => $mergeFields,
                            'language'      => static::getMailChimpLanguageByIso($subscriber['language_code']),
                            'ip_signup'     => (string) ($subscriber['ip_address'] ?: ''),
                        ]),
                    ]
                );
            }
        });

        $result = true;
        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected' => function ($reason) use (&$success) {
                if ($reason instanceof \GuzzleHttp\Exception\RequestException) {
                    $body = (string) $reason->getResponse()->getBody();
                    Logger::addLog("MailChimp client error: {$body}", 2);
                } elseif ($reason instanceof \GuzzleHttp\Exception\TransferException) {
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                }

                $success = false;
            }
        ]))->promise()->wait();

        return $result;
    }

    /**
     * Export products
     *
     * @param int  $offset
     * @param int  $idShop
     * @param bool $remaining
     *
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.0.0
     */
    protected function exportProducts($offset, $idShop = null, $remaining = false)
    {
        if (!$idShop) {
            $idShop = Context::getContext()->shop->id;
        }

        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $products = MailChimpProduct::getProducts($idShop, $offset, static::EXPORT_CHUNK_SIZE, $remaining);
        if (empty($products)) {
            return;
        }
        $mailChimpShop = MailChimpShop::getByShopId($idShop);
        if (!Validate::isLoadedObject($mailChimpShop)) {
            return;
        }
        $rate = 1;
        $tax = new Tax($mailChimpShop->id_tax);
        if (Validate::isLoadedObject($tax) && $tax->active) {
            $rate = 1 + ($tax->rate / 100);
        }
        $client = static::getGuzzle();
        $link = \Context::getContext()->link;

        $promises = call_user_func(function () use (&$products, $idLang, $client, $idShop, $rate, $link) {
            foreach ($products as &$product) {
                $productObj = new Product();
                $productObj->hydrate($product);
                $allImages = $productObj->getImages($idLang);

                if ($productObj->hasAttributes()) {
                    $allCombinations = $productObj->getAttributeCombinations($idLang);
                    $allCombinationImages = $productObj->getCombinationImages($idLang);

                    $variants = [];
                    foreach ($allCombinations as $combination) {
                        if (!isset($combination['quantity']) || !isset($combination['reference'])) {
                            continue;
                        }
                        $variant = [
                            'id'                 => (string) $product['id_product'].'-'.(string) $combination['id_product_attribute'],
                            'title'              => (string) $product['name'],
                            'sku'                => $combination['reference'],
                            'price'              => (float) ($product['price'] * $rate) + (float) ($combination['price'] * $rate),
                            'inventory_quantity' => (int) $combination['quantity'],
                        ];
                        if (isset($allCombinationImages[$combination['id_product_attribute']])) {
                            $variant['image_url'] = $link->getImageLink('default', "{$product['id_product']}-{$allCombinationImages[$combination['id_product_attribute']][0]['id_image']}");
                        }
                        $variants[] = $variant;
                    }
                } else {
                    $variants = [
                        [
                            'id'                 => (string) $product['id_product'],
                            'title'              => (string) $product['name'],
                            'sku'                => (string) (isset($product['reference']) ? $product['reference'] : ''),
                            'price'              => (float) ($product['price'] * $rate),
                            'inventory_quantity' => (int) (isset($product['quantity']) ? $product['quantity'] : 1),
                        ],
                    ];
                }

                try {
                    $payload = [
                        'id'          => (string) $product['id_product'],
                        'title'       => (string) $product['name'],
                        'url'         => (string) $link->getProductLink($product['id_product']),
                        'description' => (string) $product['description_short'],
                        'vendor'      => (string) $product['manufacturer'] ?: '',
                        'image_url'   => !empty($allImages) ? $link->getImageLink('default', "{$product['id_product']}-{$allImages[0]['id_image']}") : '',
                        'variants'    => $variants,
                    ];
                } catch (PrestaShopException $e) {
                    $this->addError(sprintf($this->l('Unable to generate product link for Product ID %d'), $product['id_product']));

                    continue;
                }
                if ($product['last_synced'] && $product['last_synced'] !== '1970-01-01 00:00:00') {
                    yield $client->patchAsync(
                        "ecommerce/stores/tbstore_{$idShop}/products/{$product['id_product']}",
                        [
                            'body' => json_encode($payload),
                        ]
                    );
                } else {
                    yield $client->postAsync(
                        "ecommerce/stores/tbstore_{$idShop}/products",
                        [
                            'body' => json_encode($payload),
                        ]
                    );
                }
            }
        });

        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected' => function ($reason) use (&$success, $client, $idShop) {
                if ($reason instanceof \GuzzleHttp\Exception\ClientException) {
                    if (strtoupper($reason->getRequest()->getMethod()) === 'DELETE') {
                        // We don't care about the DELETEs, those are fire-and-forget
                        return;
                    } elseif (strtoupper($reason->getRequest()->getMethod()) === 'POST'
                        && strpos(json_decode((string) $reason->getResponse()->getBody())->detail, 'A product with the provided ID') !== false
                    ) {
                        $idProduct = json_decode((string) $reason->getRequest()->getBody())->id;
                        try {
                            $client->patch("ecommerce/stores/tbstore_{$idShop}/products/{$idProduct}",
                                [
                                    'body' => (string) $reason->getRequest()->getBody(),
                                ]
                            );
                            return;
                        } catch (\GuzzleHttp\Exception\TransferException $e) {
                        } catch (\Exception $e) {
                        }
                    } elseif (strtoupper($reason->getRequest()->getMethod()) === 'PATCH'
                        && json_decode((string) $reason->getResponse()->getBody())->title === 'Resource Not Found'
                    ) {
                        try {
                            $client->post("ecommerce/stores/tbstore_{$idShop}/products",
                                [
                                    'body' => (string) $reason->getRequest()->getBody(),
                                ]
                            );
                            return;
                        } catch (\GuzzleHttp\Exception\TransferException $e) {
                        } catch (\Exception $e) {
                        }
                    }

                    $responseBody = (string) $reason->getResponse()->getBody();
                    Logger::addLog(
                        "MailChimp client error: {$responseBody}",
                        2,
                        $reason->getResponse()->getStatusCode(),
                        'MailChimpProduct',
                        json_decode((string) $reason->getRequest()->getBody())->id
                    );
                } elseif ($reason instanceof Exception || $reason instanceof \GuzzleHttp\Exception\TransferException) {
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                }
            },
        ]))->promise()->wait();

        if (MailChimpProduct::setSynced(array_column($products, 'id_product'), $idShop)) {
            Configuration::updateValue(static::PRODUCTS_LAST_SYNC, date('Y-m-d H:i:s'), false, null, $idShop);
        }
    }

    /**
     * Export all carts
     *
     * @param int  $offset
     * @param bool $remaining
     *
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.1.0
     */
    protected function exportCarts($offset, $remaining = false)
    {
        $idShop = Context::getContext()->shop->id;

        $carts = MailChimpCart::getCarts($idShop, $offset, static::EXPORT_CHUNK_SIZE, $remaining);
        if (empty($carts)) {
            return;
        }
        $mailChimpShop = MailChimpShop::getByShopId($idShop);
        if (!Validate::isLoadedObject($mailChimpShop)) {
            return;
        }
        $client = static::getGuzzle();
        $promises = call_user_func(function () use (&$carts, $client, $mailChimpShop, $idShop) {
            foreach ($carts as &$cart) {
                if (empty($cart['lines'])) {
                    continue;
                }
                $subscriberHash = md5(mb_strtolower($cart['email']));
                $mergeFields = [
                    'FNAME' => $cart['firstname'],
                    'LNAME' => $cart['lastname'],
                    'TBREF' => MailChimpSubscriber::getTbRef($cart['email']),
                ];
                if ($cart['birthday'] && date('Y-m-d', strtotime($cart['birthday'])) > '1900-01-01') {
                    $mergeFields['BDAY'] = date('m/d', strtotime($cart['birthday']));
                }

                yield $client->putAsync(
                    "lists/{$mailChimpShop->list_id}/members/{$subscriberHash}",
                    [
                        'body' => json_encode([
                            'email_address' => mb_strtolower($cart['email']),
                            'status_if_new' => MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED,
                            'merge_fields'  => (object) $mergeFields,
                            'language'      => static::getMailChimpLanguageByIso($cart['language_code']),
                        ]),
                    ]
                );
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
                    yield $client->patchAsync(
                        "ecommerce/stores/tbstore_{$idShop}/carts/{$cart['id_cart']}",
                        [
                            'body' => json_encode($payload),
                        ]
                    );
                } else {
                    yield $client->postAsync(
                        "ecommerce/stores/tbstore_{$idShop}/carts",
                        [
                            'body' => json_encode($payload),
                        ]
                    );
                }
            }
        });

        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected' => function ($reason) use (&$success, $client, $idShop) {
                if ($reason instanceof \GuzzleHttp\Exception\ClientException) {
                    if (strtoupper($reason->getRequest()->getMethod()) === 'DELETE') {
                        // We don't care about the DELETEs, those are fire-and-forget
                        return;
                    } elseif (strtoupper($reason->getRequest()->getMethod()) === 'POST'
                        && strpos(json_decode((string) $reason->getResponse()->getBody())->detail, 'A cart with the provided ID') !== false
                    ) {
                        $idCart = json_decode((string) $reason->getRequest()->getBody())->id;
                        try {
                            $response = json_decode($client->patch("ecommerce/stores/tbstore_{$idShop}/carts/{$idCart}",
                                [
                                    'body' => (string) $reason->getRequest()->getBody(),
                                ]
                            )->getBody(), true);
                            if (!empty($response['customer']['id'])) {
                                MailChimpPromo::duplicateCartRules($response['customer']['id']);
                            }
                            return;
                        } catch (\GuzzleHttp\Exception\TransferException $e) {
                        } catch (\Exception $e) {
                        }
                    } elseif (strtoupper($reason->getRequest()->getMethod()) === 'PATCH'
                        && json_decode((string) $reason->getResponse()->getBody())->title === 'Resource Not Found'
                    ) {
                        try {
                            $response = json_decode($client->post("ecommerce/stores/tbstore_{$idShop}/carts",
                                [
                                    'body' => (string) $reason->getRequest()->getBody(),
                                ]
                            )->getBody(), true);
                            if (!empty($response['customer']['id'])) {
                                MailChimpPromo::duplicateCartRules($response['customer']['id']);
                            }
                            return;
                        } catch (\GuzzleHttp\Exception\TransferException $e) {
                        } catch (\Exception $e) {
                        }
                    }

                    $responseBody = (string) $reason->getResponse()->getBody();
                    Logger::addLog(
                        "MailChimp client error: {$responseBody}",
                        2,
                        $reason->getResponse()->getStatusCode(),
                        'MailChimpCart',
                        json_decode((string) $reason->getRequest()->getBody())->id
                    );
                } elseif ($reason instanceof Exception || $reason instanceof \GuzzleHttp\Exception\TransferException) {
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                }
            },
            'fulfilled' => function ($value) {
                $response = json_decode((string) $value->getBody(), true);
                if (!empty($response['customer']['id'])) {
                    MailChimpPromo::duplicateCartRules($response['customer']['id']);
                }
            },
        ]))->promise()->wait();

        if (MailChimpCart::setSynced(array_column($carts, 'id_cart'))) {
            Configuration::updateValue(static::CARTS_LAST_SYNC, date('Y-m-d H:i:s'), false, null, $idShop);
        }
    }

    /**
     * Export orders
     *
     * @param int  $offset
     * @param bool $exportRemaining
     *
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.1.0
     */
    protected function exportOrders($offset, $exportRemaining = false)
    {
        $idShop = Context::getContext()->shop->id;

        // We use the Order objects
        $orders = MailChimpOrder::getOrders($idShop, $offset, static::EXPORT_CHUNK_SIZE, $exportRemaining);
        if (empty($orders)) {
            return;
        }
        $mailChimpShop = MailChimpShop::getByShopId($idShop);
        if (!Validate::isLoadedObject($mailChimpShop)) {
            return;
        }
        $client = static::getGuzzle();
        $promises = call_user_func(function () use (&$orders, $client, $mailChimpShop, $idShop) {
            foreach ($orders as &$order) {
                if (empty($order['lines'])) {
                    continue;
                }
                $subscriberHash = md5(mb_strtolower($order['email']));
                $mergeFields = [
                    'FNAME' => $order['firstname'],
                    'LNAME' => $order['lastname'],
                    'TBREF' => MailChimpSubscriber::getTbRef($order['email']),
                ];
                if ($order['birthday'] && date('Y-m-d', strtotime($order['birthday'])) > '1900-01-01') {
                    $mergeFields['BDAY'] = date('m/d', strtotime($order['birthday']));
                }

                yield $client->putAsync(
                    "lists/{$mailChimpShop->list_id}/members/{$subscriberHash}",
                    [
                        'body'    => json_encode([
                            'email_address' => mb_strtolower($order['email']),
                            'status_if_new' => MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED,
                            'merge_fields'  => (object) $mergeFields,
                            'language'      => static::getMailChimpLanguageByIso($order['language_code']),
                        ]),
                    ]
                );

                if (empty($order['lines'])) {
                    unset($order);
                    continue;
                }

                $payload = [
                    'id'            => (string) $order['id_order'],
                    'customer'      => [
                        'id'            => (string) $order['id_customer'],
                        'email_address' => (string) $order['email'],
                        'first_name'    => (string) $order['firstname'],
                        'last_name'     => (string) $order['lastname'],
                        'opt_in_status' => false,
                    ],
                    'currency_code' => (string) $order['currency_code'],
                    'order_total'   => (string) $order['order_total'],
                    'lines'         => $order['lines'],
                ];

                if (static::validateDate($order['date_add'], 'Y-m-d H:i:s')) {
                    $payload['processed_at_foreign'] = date('m/d', strtotime($order['date_add']));
                }
                if (static::validateDate($order['date_upd'], 'Y-m-d H:i:s')) {
                    $payload['updated_at_foreign'] = date('m/d', strtotime($order['date_add']));
                }

                if ($order['mc_tc'] && ctype_xdigit($order['mc_tc']) && strlen($order['mc_tc']) === 10) {
                    $payload['tracking_code'] = $order['mc_tc'];
                }

                if ($order['last_synced'] && $order['last_synced'] !== '1970-01-01 00:00:00') {
                    yield $client->patchAsync(
                        "ecommerce/stores/tbstore_{$idShop}/orders/{$order['id_order']}",
                        [
                            'body' => json_encode($payload),
                        ]
                    );
                } else {
                    yield $client->postAsync(
                        "ecommerce/stores/tbstore_{$idShop}/orders",
                        [
                            'body' => json_encode($payload),
                        ]
                    );
                    $client->deleteAsync("ecommerce/stores/tbstore_{$idShop}/carts/{$order['id_cart']}");
                }
            }
        });

        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected'    => function ($reason) use (&$success, $idShop, $client) {
                if ($reason instanceof \GuzzleHttp\Exception\ClientException) {
                    if (strtoupper($reason->getRequest()->getMethod()) === 'DELETE') {
                        // We don't care about the DELETEs, those are fire-and-forget
                        return;
                    } elseif (strtoupper($reason->getRequest()->getMethod()) === 'POST'
                        && strpos(json_decode((string) $reason->getResponse()->getBody())->detail, 'An order with the provided ID') !== false
                    ) {
                        $idOrder = json_decode($reason->getRequest()->getBody())->id;
                        try {
                            $client->patch("ecommerce/stores/tbstore_{$idShop}/orders/{$idOrder}",
                                [
                                    'body' => (string) $reason->getRequest()->getBody(),
                                ]
                            );
                            return;
                        } catch (\GuzzleHttp\Exception\TransferException $e) {
                        } catch (\Exception $e) {
                        }
                    } elseif (strtoupper($reason->getRequest()->getMethod()) === 'PATCH'
                        && json_decode((string) $reason->getResponse()->getBody())->title === 'Resource Not Found'
                    ) {
                        try {
                            $client->post("ecommerce/stores/tbstore_{$idShop}/orders",
                                [
                                    'body' => (string) $reason->getRequest()->getBody(),
                                ]
                            );
                            return;
                        } catch (\GuzzleHttp\Exception\TransferException $e) {
                        } catch (\Exception $e) {
                        }
                    }

                    $responseBody = (string) $reason->getResponse()->getBody();
                    Logger::addLog(
                        "MailChimp client error: {$responseBody}",
                        2,
                        $reason->getResponse()->getStatusCode(),
                        'MailChimpOrder',
                        json_decode((string) $reason->getRequest()->getBody())->id
                    );
                } elseif ($reason instanceof Exception || $reason instanceof \GuzzleHttp\Exception\TransferException) {
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                }
            },
        ]))->promise()->wait();

        if (MailChimpOrder::setSynced(array_column($orders, 'id_order'))) {
            Configuration::updateValue(static::ORDERS_LAST_SYNC, date('Y-m-d H:i:s'), false, null, $idShop);
        }
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
     * @param string $idList
     *
     * @return bool
     *
     * @throws Exception
     * @throws PrestaShopException
     * @throws \GuzzleHttp\Exception\ClientException
     * @throws \GuzzleHttp\Exception\TransferException
     * @since 1.1.0
     */
    protected function checkMergeFields($idList)
    {
        if (!$idList) {
            return false;
        }

        $client = static::getGuzzle();
        try {
            $result = json_decode((string) $client->get("lists/{$idList}/merge-fields")->getBody(), true);
        } catch (ClientException $e) {
            $response = (string) $e->getResponse()->getBody();
            Logger::addLog("MailChimp client error while grabbing the merge fields: {$response}");
        } catch (TransferException $e) {
            Logger::addLog("MailChimp generic error while grabbing the merge fields: {$e->getMessage()}");
        }
        $missingFields = [
            'FNAME' => [
                'type'     => 'text',
                'name'     => 'First name',
                'required' => false,
            ],
            'LNAME' => [
                'type'     => 'text',
                'name'     => 'Last name',
                'required' => false,
            ],
            'BDAY'  => [
                'type'     => 'date',
                'name'     => 'Birthday',
                'required' => false,
            ],
            'TBREF' => [
                'type'     => 'text',
                'name'     => 'thirty bees customer reference',
                'required' => true,
            ],
        ];
        foreach ($result['merge_fields'] as $mergeField) {
            if (isset($missingFields[$mergeField['tag']]) && $missingFields[$mergeField['tag']] === $mergeField['type']) {
                unset($missingFields[$mergeField['tag']]);
            }
        }

        $promises = call_user_func(function () use ($missingFields, $client, $idList) {
            foreach ($missingFields as $tag => $field) {
                yield $client->postAsync(
                    "lists/{$idList}/merge-fields",
                    [
                        'body'    => json_encode([
                            'tag'      => $tag,
                            'name'     => $field['name'],
                            'type'     => $field['type'],
                            'required' => $field['required'],
                        ]),
                    ]
                );
            }
        });
        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected'    => function ($reason) use ($client, $idList) {
                if ($reason instanceof ClientException) {
                    if (preg_match("/A Merge Field with the tag \"(?P<tag>.*)?\" already exists for this list./", json_decode((string) $reason->getResponse()->getBody())->detail, $m)) {
                        $request = $reason->getRequest();
                        $client->getAsync("lists/{$idList}/merge-fields")->then(function ($response) use ($client, $idList, $request, $m) {
                            /** @var \GuzzleHttp\Psr7\Response $response */
                            $mergeFields = json_decode((string) $response->getBody(), true);
                            $idMerge = null;
                            foreach ($mergeFields['merge_fields'] as $mergeField) {
                                if ($mergeField['tag'] === $m['tag']) {
                                    $idMerge = $mergeField['merge_id'];
                                    break;
                                }
                            }
                            if ($idMerge) {
                                try {
                                    $client->patch("lists/{$idList}/merge-fields/{$idMerge}", ['body' => (string) $request->getBody()]);
                                } catch (\GuzzleHttp\Exception\TransferException $e) {
                                    Logger::addLog("MailChimp merge field error: {$e->getMessage()}");
                                }
                            }
                        });
                    }
                }
            },
        ]))->promise()->wait();

        if (isset($result['id'])) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getUserAgent()
    {
        $module = Module::getInstanceByName('mailchimp');
        return 'thirty bees '._TB_VERSION_."/MailChimp {$module->version} (github.com/thirtybees/mailchimp)";
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
     *
     * @param string $sql
     */
    protected function installDbIndex($sql)
    {
        try {
            Db::getInstance()->execute($sql);
        } catch (Exception $e) {
        }
    }
}
