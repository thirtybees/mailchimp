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
use MailChimpModule\MailChimpTools;
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
    const EXPORT_ALL = 'MAILCHIMP_IMPORT_ALL';
    const EXPORT_OPT_IN = 'MAILCHIMP_IMPORT_OPTED_IN';
    const LAST_EXPORT = 'MAILCHIMP_LAST_IMPORT';
    const LAST_EXPORT_ID = 'MAILCHIMP_LAST_IMPORT_ID';
    const API_TIMEOUT = 20;
    const API_CONCURRENCY = 5;

    const EXPORT_CHUNK_SIZE = 10;

    const MENU_EXPORT = 1;
    const MENU_SHOPS = 2;
    const MENU_PRODUCTS = 3;
    const MENU_CARTS = 4;
    const MENU_ORDERS = 5;

    const VALID_ORDER_STATUSES = 'MAILCHIMP_VALID_OSES';
    const ORDER_STATUS_PAID = 'MAILCHIMP_OS_PAID';
    const ORDER_STATUS_REFUNDED = 'MAILCHIMP_OS_REFUNDED';
    const ORDER_STATUS_CANCELED = 'MAILCHIMP_OS_CANCELED';
    const ORDER_STATUS_SHIPPED = 'MAILCHIMP_OS_SHIPPED';
    const DATE_CUTOFF = 'MAILCHIMP_DATE_CUTOFF';

    const COOKIE_LIFETIME = 259200;

    const IP_SERVICE_RATE_LIMIT = 60;
    const IP_SERVICE_REQUESTS = 'MAILCHIMP_IP_REQUESTS';

    const GDPR = 'MAILCHIMP_GDPR';
    const EXPORT_COUNTRY = 'MAILCHIMP_EXPORT_COUNTRY';

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
            || !Configuration::deleteByName(static::EXPORT_OPT_IN)
            || !Configuration::deleteByName(static::LAST_EXPORT)
            || !Configuration::deleteByName(static::LAST_EXPORT_ID)
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
                $this->addOrUpdateSubscription($customer);
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
            $this->addOrUpdateSubscription($customer);
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
        $this->addOrUpdateSubscription($customerMC);
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
        $subscription = (string) $customer->newsletter
            ? MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED
            : MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
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
        $this->addOrUpdateSubscription($customerMC);
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
            $this->addOrUpdateSubscription($customer);
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
        $idShops = Tools::getValue('shops');
        if (Tools::isSubmit('start')) {
            $totalSubscribers = MailChimpSubscriber::getSubscribers($idShops, 0, 0, true, false, true);
            $totalChunks = ceil($totalSubscribers / static::EXPORT_CHUNK_SIZE);

            die(json_encode([
                'totalChunks'      => $totalChunks,
                'totalSubscribers' => $totalSubscribers,
                'count'            => 0,
            ]));
        } elseif (Tools::isSubmit('next')) {
            $count = (int) Tools::getValue('count');
            if (!$count) {
                die(json_encode([
                    'success' => false,
                    'error'   => 'Count param missing',
                ]));
            }
            $success = $this->exportSubscribers(($count - 1) * static::EXPORT_CHUNK_SIZE, $idShops);

            die(json_encode([
                'success' => $success,
                'count'   => ++$count,
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
        $idShops = Tools::getValue('shops');
        $exportRemaining = (bool) Tools::isSubmit('remaining');
        if (Tools::isSubmit('start')) {
            $totalProducts = MailChimpProduct::getProducts($idShops, 0, 0, $exportRemaining, true);
            $totalChunks = ceil($totalProducts / static::EXPORT_CHUNK_SIZE);

            die(json_encode([
                'totalChunks'   => $totalChunks,
                'totalProducts' => $totalProducts,
            ]));
        } elseif (Tools::isSubmit('next')) {
            $count = (int) Tools::getValue('count');
            if (!$count) {
                die(json_encode([
                    'success' => false,
                    'error'   => 'Count param missing',
                ]));
            }
            $success = $this->exportProducts(0, $idShops, $exportRemaining);

            die(json_encode([
                'success' => $success,
                'count'   => ++$count,
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
        $idShops = Tools::getValue('shops');
        $exportRemaining = (bool) Tools::isSubmit('remaining');
        if (Tools::isSubmit('start')) {
            $totalCarts = MailChimpCart::getCarts(null, 0, 0, $exportRemaining, true);
            $totalChunks = ceil($totalCarts / static::EXPORT_CHUNK_SIZE);

            die(json_encode([
                'totalChunks' => $totalChunks,
                'totalCarts'  => $totalCarts,
            ]));
        } elseif (Tools::isSubmit('next')) {
            $count = (int) Tools::getValue('count');

            $success = $this->exportCarts(0, $idShops, $exportRemaining);

            die(json_encode([
                'success' => $success,
                'count'   => ++$count,
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
        $idShops = Tools::getValue('shops');
        $exportRemaining = (bool) Tools::isSubmit('remaining');
        if (Tools::isSubmit('start')) {
            $totalOrders = MailChimpOrder::getOrders($idShops, 0, 0, $exportRemaining, true);
            $totalChunks = ceil($totalOrders / static::EXPORT_CHUNK_SIZE);

            die(json_encode([
                'totalChunks' => $totalChunks,
                'totalOrders' => $totalOrders,
            ]));
        } elseif (Tools::isSubmit('next')) {
            $count = (int) Tools::getValue('count');
            if (!$count) {
                die(json_encode([
                    'success' => false,
                    'error'   => 'Count param missing',
                ]));
            }
            $success = $this->exportOrders(0, $idShops, $exportRemaining);

            die(json_encode([
                'success' => $success,
                'count'   => ++$count,
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
        $success = true;
        if ($idShops = Tools::getValue('shops')) {
            if (is_array($idShops)) {
                foreach ($idShops as $idShop) {
                    $success &= $this->processReset('products', $idShop, true);
                }
            }
        } else {
            $success = false;
        }

        die(json_encode([
            'success' => $success,
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
        $success = true;
        if ($idShops = Tools::getValue('shops')) {
            if (is_array($idShops)) {
                foreach ($idShops as $idShop) {
                    $success &= $this->processReset('carts', $idShop, true);
                }
            }
        } else {
            $success = false;
        }

        die(json_encode([
            'success' => $success,
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
        $success = false;
        if ($idShops = Tools::getValue('shops')) {
            if (is_array($idShops)) {
                foreach ($idShops as $idShop) {
                    $success &= $this->processReset('orders', $idShop, true);
                }
            }
        }

        die(json_encode([
            'success' => $success,
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

        $success = false;
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
            $totalItems = call_user_func('\\MailChimpModule\\MailChimp'.ucfirst(substr($type, 0, strlen($type) - 1)).'::get'.ucfirst($type), $idShop, 0, 0, $exportRemaining, true);
            $totalChunks = ceil($totalItems / static::EXPORT_CHUNK_SIZE);

            return [
                'totalChunks'          => $totalChunks,
                'total'.ucfirst($type) => $totalItems,
            ];
        } elseif ($submit === 'next') {
            $this->{'export'.ucfirst($type)}(0, $idShop, $exportRemaining);

            return [
                'success'   => true,
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
            $importOptedIn = (bool) Tools::getvalue(static::EXPORT_OPT_IN);

            if (Configuration::updateValue(static::CONFIRMATION_EMAIL, $confirmationEmail)
                && Configuration::updateValue(static::EXPORT_OPT_IN, $importOptedIn)
                && Configuration::updateValue(static::GDPR, (bool) Tools::getvalue(static::GDPR))
                && Configuration::updateValue(static::EXPORT_COUNTRY, (bool) Tools::getvalue(static::EXPORT_COUNTRY))
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
                    }
                    $shop = new Shop($idShop);
                    $defaultIdCurrency = (int) Configuration::get('PS_CURRENCY_DEFAULT', null, $shop->id_shop_group, $shop->id);
                    $currency = new Currency($defaultIdCurrency);

                    $mailChimpShop = MailChimpShop::getByShopId($idShop);
                    if (!Validate::isLoadedObject($mailChimpShop)) {
                        $mailChimpShop = new MailChimpShop();
                    }

                    if ($idList && $mailChimpShop->list_id !== $idList || !$idList) {
                        try {
                            $client->delete('ecommerce/stores/tbstore_'.(int) $idShop);
                        } catch (TransferException $e){
                        }
                        if ($idList) {
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
                        }
                        MailChimpProduct::resetShop($idShop);
                        MailChimpCart::resetShop($idShop);
                        MailChimpOrder::resetShop($idShop);
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
                    } else {
                        try {
                            $client->delete('ecommerce/stores/tbstore_'.(int) $idShop);
                        } catch (TransferException $e){
                        }
                    }
                }
            }
        } elseif (Tools::isSubmit('submitOrders')) {
            Configuration::updateValue(static::VALID_ORDER_STATUSES, serialize($this->getStatusesValue(static::VALID_ORDER_STATUSES)), false,0,0);
            Configuration::updateValue(static::ORDER_STATUS_PAID, serialize($this->getStatusesValue(static::ORDER_STATUS_PAID)), false,0,0);
            Configuration::updateValue(static::ORDER_STATUS_CANCELED, serialize($this->getStatusesValue(static::ORDER_STATUS_CANCELED)), false,0,0);
            Configuration::updateValue(static::ORDER_STATUS_REFUNDED, serialize($this->getStatusesValue(static::ORDER_STATUS_REFUNDED)), false,0,0);
            Configuration::updateValue(static::ORDER_STATUS_SHIPPED, serialize($this->getStatusesValue(static::ORDER_STATUS_SHIPPED)), false,0,0);
            $date = Tools::getValue(static::DATE_CUTOFF);
            if (!$date) {
                $date = '2018-01-01';
            }
            Configuration::updateValue(static::DATE_CUTOFF, date('Y-m-d', strtotime($date)), false, 0, 0);
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
                    $responseBody = (string) $reason->getResponse()->getBody();
                    $requestBody = (string) $reason->getRequest()->getBody();
                    Logger::addLog("MailChimp client error: {$requestBody} -- {$responseBody}", 2);
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
                'name'   => static::EXPORT_OPT_IN,
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

            $inputs2[] = [
                'type'   => 'switch',
                'label'  => $this->l('GDPR mode'),
                'name'   => static::GDPR,
                'desc'   => $this->l('This will disable exporting sensitive data such as IP addresses.'),
                'id'     => static::GDPR,
                'values' => [
                    [
                        'id'    => static::GDPR.'_on',
                        'value' => 1,
                        'label' => $this->l('Enabled'),
                    ],
                    [
                        'id'    => static::GDPR.'_off',
                        'value' => 0,
                        'label' => $this->l('Disabled'),
                    ],
                ],
            ];

            $inputs2[] = [
                'type'   => 'switch',
                'label'  => $this->l('Export country code'),
                'name'   => static::EXPORT_COUNTRY,
                'desc'   => $this->l('This will export the user\'s country code in a GDPR compliant way.'),
                'id'     => static::EXPORT_COUNTRY,
                'values' => [
                    [
                        'id'    => static::EXPORT_COUNTRY.'_on',
                        'value' => 1,
                        'label' => $this->l('Enabled'),
                    ],
                    [
                        'id'    => static::EXPORT_COUNTRY.'_off',
                        'value' => 0,
                        'label' => $this->l('Disabled'),
                    ],
                ],
            ];

            $fieldsForm2 = [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Export Settings'),
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
            .'&module_name='.$this->name.'#mailchimp_tab_'.static::MENU_EXPORT;
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
                'cron_all_products_cli'       => "php modules/mailchimp/cli.php --shop=$idShop --action=ExportAllProducts",
                'cron_remaining_products_cli' => "php modules/mailchimp/cli.php --shop=$idShop --action=ExportRemainingProducts",
                'cron_all_carts_cli'          => "php modules/mailchimp/cli.php --shop=$idShop --action=ExportAllCarts",
                'cron_remaining_carts_cli'    => "php modules/mailchimp/cli.php --shop=$idShop --action=ExportRemainingCarts",
                'cron_all_orders_cli'         => "php modules/mailchimp/cli.php --shop=$idShop --action=ExportAllOrders",
                'cron_remaining_orders_cli'   => "php modules/mailchimp/cli.php --shop=$idShop --action=ExportRemainingOrders",
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
            .'&module_name='.$this->name.'#mailchimp_tab_'.static::MENU_EXPORT;
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
        $configFields = [
            static::API_KEY            => static::getApiKey(),
            static::CONFIRMATION_EMAIL => Configuration::get(static::CONFIRMATION_EMAIL),
            static::EXPORT_OPT_IN      => Configuration::get(static::EXPORT_OPT_IN),
            static::DATE_CUTOFF        => date('Y-m-d', strtotime(static::getOrderDateCutoff())),
            static::GDPR               => (bool) Configuration::get(static::GDPR),
            static::EXPORT_COUNTRY     => (bool) Configuration::get(static::EXPORT_COUNTRY),
        ];

        $paidStatuses = [];
        foreach (static::getOrderPaidStatuses() as $conf) {
            $paidStatuses[static::ORDER_STATUS_PAID.'_'.(int) $conf] = true;
        }
        $canceledStatuses = [];
        foreach (static::getOrderCanceledStatuses() as $conf) {
            $canceledStatuses[static::ORDER_STATUS_CANCELED.'_'.(int) $conf] = true;
        }
        $refundedStatuses = [];
        foreach (static::getOrderRefundedStatuses() as $conf) {
            $refundedStatuses[static::ORDER_STATUS_REFUNDED.'_'.(int) $conf] = true;
        }
        $shippedStatuses = [];
        foreach (static::getOrderShippedStatuses() as $conf) {
            $shippedStatuses[static::ORDER_STATUS_SHIPPED.'_'.(int) $conf] = true;
        }
        $exportableStatuses = [];
        foreach (static::getValidOrderStatuses() as $conf) {
            $exportableStatuses[static::VALID_ORDER_STATUSES.'_'.(int) $conf] = true;
        }

        $configFields = array_merge(
            $configFields,
            $paidStatuses,
            $canceledStatuses,
            $refundedStatuses,
            $shippedStatuses,
            $exportableStatuses
        );

        return $configFields;
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
                        'shops' => array_filter(MailChimpShop::getShops(true), function ($shop) {
                            return in_Array($shop['id_shop'], Shop::getContextListShopID());
                        }),
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
                    'title' => $this->l('Product export'),
                    'icon'  => 'icon-archive',
                ],
                'input'  => [
                    [
                        'type'  => 'mailchimp_products',
                        'label' => $this->l('Products to sync'),
                        'name'  => 'mailchimp_products',
                        'shops' => array_filter(MailChimpShop::getShops(true), function ($shop) {
                            return in_array($shop['id_shop'], Shop::getContextListShopID());
                        }),
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
        $helper->submit_action = 'submitCarts';
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
                    'title' => $this->l('Cart export'),
                    'icon'  => 'icon-shopping-cart',
                ],
                'input'  => [
                    [
                        'type'  => 'mailchimp_carts',
                        'label' => $this->l('Carts to sync'),
                        'name'  => 'mailchimp_carts',
                        'shops' => array_filter(MailChimpShop::getShops(true), function ($shop) {
                            return in_array($shop['id_shop'], Shop::getContextListShopID());
                        }),
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
        $helper->submit_action = 'submitOrders';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true)
            .'&configure='.$this->name.'&tab_module='.$this->tab
            .'&module_name='.$this->name.'#mailchimp_tab_'.static::MENU_ORDERS;

        $helper->token = '';

        $helper->tpl_vars = [
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
            'fields_value' => $this->getConfigFieldsValues(),
        ];

        return $helper->generateForm($this->getOrdersForm());
    }

    /**
     * @return array
     * @throws PrestaShopException
     */
    protected function getOrdersForm()
    {
        $this->context->controller->addJqueryUI('ui.datepicker');

        $otherStatuses = array_map(function ($state) {
            $state['name'] = "{$state['id_order_state']}. {$state['name']}";
            return $state;
        }, OrderState::getOrderStates($this->context->language->id));
        usort($otherStatuses, function ($a, $b) {
            return strcmp(
                str_pad($a['id_order_state'], 3, '0', STR_PAD_LEFT),
                str_pad($b['id_order_state'], 3, '0', STR_PAD_LEFT)
            );
        });

        return [
            [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Order export'),
                        'icon'  => 'icon-money',
                    ],
                    'input'  => [
                        [
                            'type'  => 'mailchimp_orders',
                            'label' => $this->l('Orders to sync'),
                            'name'  => 'mailchimp_orders',
                            'shops' => array_filter(MailChimpShop::getShops(true), function ($shop) {
                                return in_array($shop['id_shop'], Shop::getContextListShopID());
                            }),
                        ],
                    ],
                ],

            ],
            [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Order settings'),
                        'icon'  => 'icon-cogs',
                    ],
                    'input'  => [
                        [
                            'type'     => 'date',
                            'label'    => $this->l('Cutoff date'),
                            'name'     => static::DATE_CUTOFF,
                            'size'     => 10,
                            'required' => true,
                            'desc'     => $this->l('Do not process any orders before this date'),
                        ],
                        [
                            'type'     => 'checkbox',
                            'label'    => $this->l('Paid order statuses'),
                            'desc'     => $this->l('Orders with these statuses are marked as paid'),
                            'name'     => static::ORDER_STATUS_PAID,
                            'multiple' => true,
                            'values'   => [
                                'query' => $otherStatuses,
                                'id'    => 'id_order_state',
                                'name'  => 'name',
                            ],
                            'expand'   => (count($otherStatuses) > 20) ? [
                                'print_total' => count($otherStatuses),
                                'default'     => 'show',
                                'show'        => ['text' => $this->l('Show'), 'icon' => 'plus-sign-alt'],
                                'hide'        => ['text' => $this->l('Hide'), 'icon' => 'minus-sign-alt'],
                            ] : null,
                        ],
                        [
                            'type'     => 'checkbox',
                            'label'    => $this->l('Refunded order statuses'),
                            'desc'     => $this->l('Orders with at least one of these statuses are marked as refunded'),
                            'name'     => static::ORDER_STATUS_REFUNDED,
                            'multiple' => true,
                            'values'   => [
                                'query' => $otherStatuses,
                                'id'    => 'id_order_state',
                                'name'  => 'name',
                            ],
                            'expand'   => (count($otherStatuses) > 20) ? [
                                'print_total' => count($otherStatuses),
                                'default'     => 'show',
                                'show'        => ['text' => $this->l('Show'), 'icon' => 'plus-sign-alt'],
                                'hide'        => ['text' => $this->l('Hide'), 'icon' => 'minus-sign-alt'],
                            ] : null,
                        ],
                        [
                            'type'     => 'checkbox',
                            'label'    => $this->l('Canceled order statuses'),
                            'desc'     => $this->l('Orders with at least one of these statuses are marked as canceled'),
                            'name'     => static::ORDER_STATUS_CANCELED,
                            'multiple' => true,
                            'values'   => [
                                'query' => $otherStatuses,
                                'id'    => 'id_order_state',
                                'name'  => 'name',
                            ],
                            'expand'   => (count($otherStatuses) > 20) ? [
                                'print_total' => count($otherStatuses),
                                'default'     => 'show',
                                'show'        => ['text' => $this->l('Show'), 'icon' => 'plus-sign-alt'],
                                'hide'        => ['text' => $this->l('Hide'), 'icon' => 'minus-sign-alt'],
                            ] : null,
                        ],
                        [
                            'type'     => 'checkbox',
                            'label'    => $this->l('Shipped order statuses'),
                            'desc'     => $this->l('Orders with at least one of these statuses are marked as shipped.'),
                            'name'     => static::ORDER_STATUS_SHIPPED,
                            'multiple' => true,
                            'values'   => [
                                'query' => $otherStatuses,
                                'id'    => 'id_order_state',
                                'name'  => 'name',
                            ],
                            'expand'   => (count($otherStatuses) > 20) ? [
                                'print_total' => count($otherStatuses),
                                'default'     => 'show',
                                'show'        => ['text' => $this->l('Show'), 'icon' => 'plus-sign-alt'],
                                'hide'        => ['text' => $this->l('Hide'), 'icon' => 'minus-sign-alt'],
                            ] : null,
                        ],
                        [
                            'type'     => 'checkbox',
                            'label'    => $this->l('Exportable order statuses'),
                            'desc'     => $this->l('Uncheck an order status to ignore it.').'<br>'.$this->l('NOTE: MailChimp can handle unpaid, canceled or refunded orders perfectly well. Only use this option to ignore orders if you really have to.'),
                            'name'     => static::VALID_ORDER_STATUSES,
                            'multiple' => true,
                            'values'   => [
                                'query' => $otherStatuses,
                                'id'    => 'id_order_state',
                                'name'  => 'name',
                            ],
                            'expand'   => (count($otherStatuses) > 20) ? [
                                'print_total' => count($otherStatuses),
                                'default'     => 'show',
                                'show'        => ['text' => $this->l('Show'), 'icon' => 'plus-sign-alt'],
                                'hide'        => ['text' => $this->l('Hide'), 'icon' => 'minus-sign-alt'],
                            ] : null,
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Save'),
                        'class' => 'btn btn-default pull-right',
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
                'name'  => $this->l('Export Settings'),
                'icon'  => 'icon-upload',
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
                'icon'  => 'icon-money',
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
                $responseBody = (string) $e->getResponse()->getBody();
                $requestBody = (string) $e->getRequest()->getBody();
                $this->addError("MailChimp client error: {$requestBody} -- {$responseBody}");
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
     * @param int            $offset
     * @param int[]|int|null $idShops
     *
     * @return string
     *
     * @throws Exception
     * @throws PrestaShopException
     * @since 1.0.0
     */
    protected function exportSubscribers($offset, $idShops = null)
    {
        if (is_int($idShops)) {
            $idShops = [$idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = \Shop::getContextListShopID(\Shop::SHARE_CUSTOMER);
        }

        $mailChimpShops = array_filter(MailChimpShop::getByShopIds($idShops), function ($mcs) {
            /** @var MailChimpShop $mcs */
            return $mcs->list_id;
        });
        $idShops = array_filter($idShops, function ($idShop) use ($mailChimpShops) {
            return in_array($idShop, array_keys($mailChimpShops));
        });
        if (empty($mailChimpShops) || empty($idShops)) {
            return false;
        }
        $subscribers = MailChimpSubscriber::getSubscribers($idShops, $offset, static::EXPORT_CHUNK_SIZE);
        if (empty($subscribers)) {
            return false;
        }

        $client = static::getGuzzle();
        $promises = call_user_func(function () use (&$subscribers, $mailChimpShops, $client) {
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
                $subscriberBody = [
                    'email_address' => mb_strtolower($subscriber['email']),
                    'status_if_new' => $subscriber['subscription'],
                    'merge_fields'  => $mergeFields,
                    'language'      => static::getMailChimpLanguageByIso($subscriber['language_code']),
                ];
                if (!Configuration::get(static::GDPR)) {
                    $subscriberBody['ip_signup'] = (string) ($subscriber['ip_address'] ?: '');
                }
                if (Configuration::get(static::EXPORT_COUNTRY) && $subscriber['ip_address']) {
                    $coords = static::getUserLatLongByIp($subscriber['id_address']);
                    if ($coords) {
                        $subscriberBody['location'] = [
                            'latitude'  => $coords['lat'],
                            'longitude' => $coords['long'],
                        ];
                    }
                }
                yield $client->putAsync(
                    "lists/{$mailChimpShops[$subscriber['id_shop']]->list_id}/members/{$subscriberHash}",
                    [
                        'body' => json_encode($subscriberBody),
                    ]
                );
            }
        });

        $result = true;
        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected' => function ($reason) use (&$success) {
                if ($reason instanceof \GuzzleHttp\Exception\RequestException) {
                    $responseBody = (string) $reason->getResponse()->getBody();
                    $requestBody = (string) $reason->getRequest()->getBody();
                    Logger::addLog("MailChimp client error: {$requestBody} -- {$responseBody}", 2);
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
     * @param int  $idShops
     * @param bool $remaining
     *
     * @return bool Success
     *
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.0.0
     */
    protected function exportProducts($offset, $idShops = null, $remaining = false)
    {
        if (is_int($idShops)) {
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = \Shop::getContextListShopID(\Shop::SHARE_STOCK);
        }

        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $mailChimpShops = array_filter(MailChimpShop::getByShopIds($idShops), function ($mcs) {
            /** @var MailChimpShop $mcs */
            return $mcs->list_id;
        });
        $idShops = array_filter($idShops, function ($idShop) use ($mailChimpShops) {
            return in_array($idShop, array_keys($mailChimpShops));
        });
        if (empty($mailChimpShops) || empty($idShops)) {
            return false;
        }

        $products = MailChimpProduct::getProducts($idShops, $offset, static::EXPORT_CHUNK_SIZE, $remaining);
        if (empty($products)) {
            return false;
        }

        $taxes = [];
        foreach ($mailChimpShops as $mailChimpShop) {
            $rate = 1;
            $tax = new Tax($mailChimpShop->id_tax);
            if (Validate::isLoadedObject($tax) && $tax->active) {
                $rate = 1 + ($tax->rate / 100);
            }
            $taxes[(int) $mailChimpShop->id_shop] = $rate;
        }
        $client = static::getGuzzle();
        $link = \Context::getContext()->link;

        $promises = call_user_func(function () use (&$products, $idLang, $client, $link, $taxes) {
            foreach ($products as &$product) {
                $productObj = new Product();
                $productObj->hydrate($product);
                $allImages = array_values($productObj->getImages($idLang));
                $rate = $taxes[$product['id_shop']];
                $idShop = $product['id_shop'];

                $variants = [];
                if ($productObj->hasAttributes()) {
                    $allCombinations = $productObj->getAttributeCombinations($idLang);
                    $allCombinationImages = $productObj->getCombinationImages($idLang);

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
                }

                $variants[] = [
                    'id'                 => (string) $product['id_product'],
                    'title'              => (string) $product['name'],
                    'sku'                => (string) (isset($product['reference']) ? $product['reference'] : ''),
                    'price'              => (float) ($product['price'] * $rate),
                    'inventory_quantity' => (int) (isset($product['quantity']) ? $product['quantity'] : 1),
                ];

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
                if (!empty($product['last_synced']) && $product['last_synced'] > '2000-01-01 00:00:00') {
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
            'rejected' => function ($reason) use ($client) {
                if ($reason instanceof \GuzzleHttp\Exception\ClientException) {
                    preg_match("/tbstore_(?P<id_shop>[0-9]+)?\//", $reason->getRequest()->getUri(), $m);
                    $idProduct = json_decode((string) $reason->getRequest()->getBody())->id;
                    if (strtoupper($reason->getRequest()->getMethod()) === 'DELETE') {
                        // We don't care about the DELETEs, those are fire-and-forget
                        return;
                    } elseif (strtoupper($reason->getRequest()->getMethod()) === 'POST'
                        && strpos(json_decode((string) $reason->getResponse()->getBody())->detail, 'A product with the provided ID') !== false
                    ) {
                        try {
                            $client->patch("ecommerce/stores/tbstore_{$m['id_shop']}/products/{$idProduct}",
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
                            $client->post("ecommerce/stores/tbstore_{$m['id_shop']}/products",
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
                    $requestBody = (string) $reason->getRequest()->getBody();
                    Logger::addLog(
                        "MailChimp client error: {$requestBody} -- {$responseBody}",
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

        MailChimpProduct::setSynced(array_column($products, 'id_product'), $idShops);

        return true;
    }

    /**
     * Export products
     *
     * @param int[] $range       Product IDs
     * @param int   $idShops     Shop IDs
     * @param bool  $orderDetail Use the order detail table to find the missing pieces
     *
     * @return bool Status
     *
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.0.0
     */
    protected function exportProductRange($range, $idShops = null, $orderDetail = false)
    {
        if (empty($range)) {
            return true;
        }

        if (is_int($idShops)) {
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = \Shop::getContextListShopID(\Shop::SHARE_STOCK);
        }

        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $mailChimpShops = array_filter(MailChimpShop::getByShopIds($idShops), function ($mcs) {
            /** @var MailChimpShop $mcs */
            return $mcs->list_id;
        });
        $idShops = array_filter($idShops, function ($idShop) use ($mailChimpShops) {
            return in_array($idShop, array_keys($mailChimpShops));
        });
        if (empty($mailChimpShops) || empty($idShops)) {
            return false;
        }

        $products = MailChimpProduct::getProductRange($range, $idShops, true, $orderDetail);
        if (empty($products)) {
            return true;
        }

        $taxes = [];
        foreach ($mailChimpShops as $mailChimpShop) {
            $rate = 1;
            $tax = new Tax($mailChimpShop->id_tax);
            if (Validate::isLoadedObject($tax) && $tax->active) {
                $rate = 1 + ($tax->rate / 100);
            }
            $taxes[(int) $mailChimpShop->id_shop] = $rate;
        }
        $client = static::getGuzzle();
        $link = \Context::getContext()->link;

        $promises = call_user_func(function () use (&$products, $idLang, $client, $link, $taxes) {
            foreach (array_unique(array_map('intval', array_column($products, 'id_product'))) as $idProduct) {
                $product = $products[array_search((int) $idProduct, array_map('intval', array_column($products, 'id_product')))];
                if (!isset($product['id_order_detail'])) {
                    $productObj = new Product($idProduct);
                    // Work with an existing product
                    $allImages = array_values($productObj->getImages($idLang));
                    $rate = $taxes[$product['id_shop']];
                    $idShop = $product['id_shop'];

                    $variants = [];
                    if ($productObj->hasAttributes()) {
                        $allCombinations = $productObj->getAttributeCombinations($idLang);
                        $allCombinationImages = $productObj->getCombinationImages($idLang);

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
                    }
                    $variants[] = [
                        'id'                 => (string) $product['id_product'],
                        'title'              => (string) $product['name'],
                        'sku'                => (string) $product['reference'],
                        'price'              => (float) ($product['price'] * $rate),
                        'inventory_quantity' => (int) (isset($product['quantity']) ? $product['quantity'] : 1),
                    ];

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
                } else {
                    // Work with an order detail row
                    $orderDetails = array_filter($products, function ($product) use ($idProduct) {
                        return (int) $product['id_product'] === $idProduct;
                    });
                    $idShop = $product['id_shop'];
                    $variants = [];
                    foreach ($orderDetails as $orderDetail) {
                        if (empty($orderDetail['id_product_attribute']) || in_array($orderDetail['id_product_attribute'], array_filter(array_map(function ($item) {
                                return array_pad(explode('-', $item), 2, null)[1];
                            }, array_column($variants, 'id'))))) {
                            continue;
                        }
                        $variants[] = [
                            'id'                 => (string) $orderDetail['id_product'].'-'.(string) $orderDetail['id_product_attribute'],
                            'title'              => (string) $orderDetail['name'],
                            'sku'                => (string) $orderDetail['reference'],
                            'price'              => (float) $orderDetail['unit_price_tax_incl'],
                            'inventory_quantity' => (int) $orderDetail['quantity'],
                        ];
                    }
                    $product = array_values($orderDetails)[0];
                    $variants[] = [
                            'id'                 => (string) $idProduct,
                            'title'              => (string) $product['name'],
                            'sku'                => (string) $product['reference'],
                            'price'              => (float) $product['unit_price_tax_incl'],
                            'inventory_quantity' => (int) (isset($product['quantity']) ? $product['quantity'] : 1),
                    ];
                    $payload = [
                        'id'          => (string) $idProduct,
                        'title'       => (string) $product['name'],
                        'variants'    => $variants,
                    ];
                }

                if (!empty($product['last_synced']) && $product['last_synced'] > '2000-01-01 00:00:00') {
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

        $success = true;
        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected' => function ($reason) use (&$success, $client) {
                if ($reason instanceof \GuzzleHttp\Exception\ClientException) {
                    preg_match("/tbstore_(?P<id_shop>[0-9]+)?\//", $reason->getRequest()->getUri(), $m);
                    $idProduct = json_decode((string) $reason->getRequest()->getBody())->id;
                    if (strtoupper($reason->getRequest()->getMethod()) === 'DELETE') {
                        // We don't care about the DELETEs, those are fire-and-forget
                        return;
                    } elseif (strtoupper($reason->getRequest()->getMethod()) === 'POST'
                        && strpos(json_decode((string) $reason->getResponse()->getBody())->detail, 'A product with the provided ID') !== false
                    ) {
                        try {
                            $client->patch("ecommerce/stores/tbstore_{$m['id_shop']}/products/{$idProduct}",
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
                            $client->post("ecommerce/stores/tbstore_{$m['id_shop']}/products",
                                [
                                    'body' => (string) $reason->getRequest()->getBody(),
                                ]
                            );
                            return;
                        } catch (\GuzzleHttp\Exception\TransferException $e) {
                            Logger::addLog($e->getMessage());
                        } catch (\Exception $e) {
                            Logger::addLog($e->getMessage());
                        }
                    }

                    $responseBody = (string) $reason->getResponse()->getBody();
                    $requestBody = (string) $reason->getRequest()->getBody();
                    Logger::addLog(
                        "MailChimp client error: {$requestBody} -- {$responseBody}",
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

        MailChimpProduct::setSynced(array_column($products, 'id_product'), $idShops);

        return $success;
    }

    /**
     * Export all carts
     *
     * @param int            $offset
     * @param int|int[]|null $idShops
     * @param bool           $remaining
     *
     * @return bool $success
     *
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.1.0
     */
    protected function exportCarts($offset, $idShops = null, $remaining = false)
    {
        if (is_int($idShops)) {
            $idShops = [$idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = \Shop::getContextListShopID(\Shop::SHARE_CUSTOMER);
        }
        $mailChimpShops = array_filter(MailChimpShop::getByShopIds($idShops), function ($mc) {
            /** @var MailChimpShop $mc */
            return $mc->list_id;
        });
        $idShops = array_filter($idShops, function ($idShop) use ($mailChimpShops) {
            return in_array($idShop, array_keys($mailChimpShops));
        });
        if (empty($mailChimpShops) || empty($idShops)) {
            return false;
        }

        $carts = MailChimpCart::getCarts($idShops, $offset, static::EXPORT_CHUNK_SIZE, $remaining);
        if (empty($carts)) {
            return false;
        }

        $client = static::getGuzzle();
        $promises = call_user_func(function () use (&$carts, $client, $mailChimpShops, $idShops) {
            foreach ($carts as &$cart) {
                if (empty($cart['lines'])) {
                    continue;
                }
                $this->exportProductRange(array_unique(array_column($cart['lines'], 'product_id')), $idShops, true);
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
                    "lists/{$mailChimpShops[$cart['id_shop']]->list_id}/members/{$subscriberHash}",
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

                if (!empty($cart['last_synced']) && $cart['last_synced'] > '2000-01-01 00:00:00') {
                    yield $client->patchAsync(
                        "ecommerce/stores/tbstore_{$cart['id_shop']}/carts/{$cart['id_cart']}",
                        [
                            'body' => json_encode($payload),
                        ]
                    );
                } else {
                    yield $client->postAsync(
                        "ecommerce/stores/tbstore_{$cart['id_shop']}/carts",
                        [
                            'body' => json_encode($payload),
                        ]
                    );
                }
            }
        });

        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected' => function ($reason) use (&$success, $client) {
                if ($reason instanceof \GuzzleHttp\Exception\ClientException) {
                    preg_match("/tbstore_(?P<id_shop>[0-9]+)?\//", $reason->getRequest()->getUri(), $m);
                    if (strtoupper($reason->getRequest()->getMethod()) === 'DELETE') {
                        // We don't care about the DELETEs, those are fire-and-forget
                        return;
                    } elseif (strtoupper($reason->getRequest()->getMethod()) === 'POST'
                        && strpos(json_decode((string) $reason->getResponse()->getBody())->detail, 'A cart with the provided ID') !== false
                    ) {
                        $idCart = json_decode((string) $reason->getRequest()->getBody())->id;
                        try {
                            $response = json_decode($client->patch("ecommerce/stores/tbstore_{$m['id_shop']}/carts/{$idCart}",
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
                            $response = json_decode($client->post("ecommerce/stores/tbstore_{$m['id_shop']}/carts",
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
                    $requestBody = (string) $reason->getRequest()->getBody();
                    Logger::addLog(
                        "MailChimp client error: {$requestBody} -- {$responseBody}",
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

        MailChimpCart::setSynced(array_column($carts, 'id_cart'));

        return $success;
    }

    /**
     * Export orders
     *
     * @param int  $offset
     * @param int|int[]|null $idShops
     * @param bool $exportRemaining
     *
     * @return bool $success
     *
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.1.0
     */
    protected function exportOrders($offset, $idShops = null, $exportRemaining = false)
    {
        if (is_int($idShops)) {
            $idShops = [$idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = \Shop::getContextListShopID(\Shop::SHARE_ORDER);
        }
        $mailChimpShops = array_filter(MailChimpShop::getByShopIds($idShops), function ($mcs) {
            /** @var MailChimpShop $mcs */
            return $mcs->list_id;
        });
        if (empty($mailChimpShops) || empty($idShops)) {
            return false;
        }
        // We use the Order objects
        $orders = MailChimpOrder::getOrders($idShops, $offset, static::EXPORT_CHUNK_SIZE, $exportRemaining);
        if (empty($orders)) {
            return false;
        }
        $client = static::getGuzzle();
        $promises = call_user_func(function () use (&$orders, $client, $mailChimpShops, $idShops) {
            foreach ($orders as &$order) {
                if (empty($order['lines'])) {
                    continue;
                }

                $this->exportProductRange(array_unique(array_column($order['lines'], 'product_id')), $idShops, true);
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
                    "lists/{$mailChimpShops[$order['id_shop']]->list_id}/members/{$subscriberHash}",
                    [
                        'body'    => json_encode([
                            'email_address' => mb_strtolower($order['email']),
                            'status_if_new' => MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED,
                            'merge_fields'  => (object) $mergeFields,
                            'language'      => static::getMailChimpLanguageByIso($order['language_code']),
                        ]),
                    ]
                );

                $payload = [
                    'id'               => (string) $order['id_order'],
                    'customer'         => [
                        'id'            => (string) $order['id_customer'],
                        'email_address' => (string) $order['email'],
                        'first_name'    => (string) $order['firstname'],
                        'last_name'     => (string) $order['lastname'],
                        'opt_in_status' => false,
                    ],
                    'financial_status' => (string) $order['financial_status'],
                    'currency_code'    => (string) $order['currency_code'],
                    'order_total'      => (string) $order['order_total'],
                    'lines'            => $order['lines'],
                ];
                if ($order['shipped']) {
                    $payload['fulfillment_status'] = 'shipped';
                }
                $payload['processed_at_foreign'] = date('c', strtotime($order['date_add']));
                $payload['updated_at_foreign'] = date('c', strtotime($order['date_upd']));
                if ($order['mc_tc'] && ctype_xdigit($order['mc_tc']) && strlen($order['mc_tc']) === 10) {
                    $payload['tracking_code'] = $order['mc_tc'];
                }
                if ($order['mc_cid']) {
                    $payload['campaign_id'] = $order['mc_cid'];
                }

                if (!empty($order['last_synced']) && $order['last_synced'] > '2000-01-01 00:00:00') {
                    yield $client->patchAsync(
                        "ecommerce/stores/tbstore_{$mailChimpShops[$order['id_shop']]->id_shop}/orders/{$order['id_order']}",
                        [
                            'body' => json_encode($payload),
                        ]
                    );
                } else {
                    yield $client->postAsync(
                        "ecommerce/stores/tbstore_{$mailChimpShops[$order['id_shop']]->id_shop}/orders",
                        [
                            'body' => json_encode($payload),
                        ]
                    );
                    $client->deleteAsync("ecommerce/stores/tbstore_{$mailChimpShops[$order['id_shop']]->id_shop}/carts/{$order['id_cart']}");
                }
            }
        });

        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected'    => function ($reason) use ($client) {
                if ($reason instanceof \GuzzleHttp\Exception\ClientException) {
                    preg_match("/tbstore_(?P<id_shop>[0-9]+)?\//", $reason->getRequest()->getUri(), $m);
                    if (strtoupper($reason->getRequest()->getMethod()) === 'DELETE') {
                        // We don't care about the DELETEs, those are fire-and-forget
                        return;
                    } elseif (strtoupper($reason->getRequest()->getMethod()) === 'POST'
                        && strpos(json_decode((string) $reason->getResponse()->getBody())->detail, 'An order with the provided ID') !== false
                    ) {
                        $idOrder = json_decode($reason->getRequest()->getBody())->id;
                        try {
                            $client->patch("ecommerce/stores/tbstore_{$m['id_shop']}/orders/{$idOrder}",
                                [
                                    'body' => (string) $reason->getRequest()->getBody(),
                                ]
                            );
                            return;
                        } catch (\GuzzleHttp\Exception\TransferException $e) {
                        } catch (\Exception $e) {
                        }
                    }
                    elseif (strtoupper($reason->getRequest()->getMethod()) === 'PATCH'
                        && json_decode((string) $reason->getResponse()->getBody())->title === 'Resource Not Found'
                    ) {
                        try {
                            $client->post("ecommerce/stores/tbstore_{$m['id_shop']}/orders",
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
                    $requestBody = (string) $reason->getRequest()->getBody();
                    Logger::addLog(
                        "MailChimp client error: {$requestBody} -- {$responseBody}",
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

        MailChimpOrder::setSynced(array_column($orders, 'id_order'));

        return true;
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
            $responseBody = (string) $e->getResponse()->getBody();
            $requestBody = (string) $e->getRequest()->getBody();
            Logger::addLog("MailChimp client error while grabbing the merge fields: {$requestBody} -- {$responseBody}");
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
        $this->installDbIndex('ALTER TABLE `PREFIX_mailchimp_product` ADD INDEX `mc_product_product` (`id_product`, `id_shop`)');
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

    /**
     * Get all status values from the form.
     *
     * @param $key string The key that is used in the HelperForm
     *
     * @return array Array with statuses
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getStatusesValue($key)
    {
        $statesEnabled = [];
        foreach (OrderState::getOrderStates($this->context->language->id) as $state) {
            if (Tools::isSubmit($key.'_'.$state['id_order_state'])) {
                $statesEnabled[] = $state['id_order_state'];
            }
        }

        return $statesEnabled;
    }

    /**
     * Get Order date cutoff
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    public static function getOrderDateCutoff()
    {
        $cutoff = Configuration::get(static::DATE_CUTOFF, null, 0, 0);
        if ($cutoff === false || !strtotime($cutoff)) {
            $cutoff = '2018-01-01 00:00:00';
        }

        return date('Y-m-d H:i:s', strtotime($cutoff));
    }

    /**
     * Get valid order statuses
     *
     * @return int[]
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getValidOrderStatuses()
    {
        $statuses = Configuration::get(static::VALID_ORDER_STATUSES, null, 0, 0);
        if ($statuses === false) {
            return array_column(OrderState::getOrderStates(Context::getContext()->language->id), 'id_order_state');
        } else {
            $statuses = unserialize($statuses);
        }

        return array_map('intval', $statuses);
    }

    /**
     * Get paid order statuses
     *
     * @return int[]
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getOrderPaidStatuses()
    {
        $statuses = Configuration::get(static::ORDER_STATUS_PAID, null, 0, 0);
        if ($statuses === false) {
            return array_column(Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                (new DbQuery())
                    ->select('`id_order_state`')
                    ->from('order_state')
                    ->where('`paid` = 1')
            ) ?: [], 'id_order_state');
        } else {
            $statuses = unserialize($statuses);
        }

        return array_map('intval', $statuses);
    }

    /**
     * Get canceled order statuses
     *
     * @return int[]
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getOrderCanceledStatuses()
    {
        $statuses = Configuration::get(static::ORDER_STATUS_CANCELED, null, 0, 0);
        if ($statuses === false) {
            return [(int) Configuration::get('PS_OS_CANCELED')];
        } else {
            $statuses = unserialize($statuses);
        }

        return array_map('intval', $statuses);
    }

    /**
     * Get refunded order statuses
     *
     * @return int[]
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getOrderRefundedStatuses()
    {
        $statuses = Configuration::get(static::ORDER_STATUS_REFUNDED, null, 0, 0);
        if ($statuses === false) {
            return [(int) Configuration::get('PS_OS_REFUND')];
        } else {
            $statuses = unserialize($statuses);
        }

        return array_map('intval', $statuses);
    }

    /**
     * Get shipped order statuses
     *
     * @return int[]
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getOrderShippedStatuses()
    {
        $statuses = Configuration::get(static::ORDER_STATUS_SHIPPED, null, 0, 0);
        if ($statuses === false) {
            return array_column(Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                (new DbQuery())
                    ->select('`id_order_state`')
                    ->from('order_state')
                    ->where('`shipped` = 1')
            ) ?: [], 'id_order_state');
        } else {
            $statuses = unserialize($statuses);
        }

        return array_map('intval', $statuses);
    }

    /**
     * Get user lat long by IP
     *
     * @param string $ip
     *
     * @return array|false
     * @throws PrestaShopException
     */
    public static function getUserLatLongByIp($ip)
    {
        static $cache = [];
        $requests = static::getIpRequests();

        if (array_key_exists($ip, $cache)) {
            return $cache[$ip];
        }

        $cc = '';
        if (@filemtime(_PS_GEOIP_DIR_._PS_GEOIP_CITY_FILE_)) {
            $gi = geoip_open(realpath(_PS_GEOIP_DIR_._PS_GEOIP_CITY_FILE_), GEOIP_STANDARD);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $cc = strtoupper(geoip_country_code_by_addr_v6($gi, $ip));
            } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $cc = strtoupper(geoip_country_code_by_addr($gi, $ip));
            }
        } elseif (!$cc && isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $cc = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY']);
        } elseif (!$cc) {
            try {
                while (count(array_filter($requests, function ($date) {
                    return $date > date('Y-m-d H:i:s', strtotime("-1 min"));
                })) > static::IP_SERVICE_RATE_LIMIT) {
                    // Slow down, beautiful. We're going to get banned!
                    sleep(1);
                }
                $requests = array_filter($requests, function ($date) {
                    return $date > date('Y-m-d H:i:s', strtotime("-1 min"));
                });
                $requests[] = date('Y-m-d H:i:s');
                static::saveIpRequests($requests);

                $cc = trim(strtoupper((string) (new Client(['timeout' => 1]))->get("http://ip-api.com/line/{$ip}?fields=countryCode")->getBody()));
            } catch (TransferException $e) {
            }
        }

        if ($cc) {
            $coords = MailChimpTools::getCountryCoordinates();
            $idx = array_search($cc, array_column($coords, 'code'));
            if ($idx === false) {
                return false;
            }

            return $coords[$idx];
        }

        return false;
    }

    /**
     * Get IP service requests
     *
     * @return string[]
     *
     * @throws PrestaShopException
     */
    protected static function getIpRequests()
    {
        $requests = json_decode(Configuration::get(static::IP_SERVICE_REQUESTS, null, 0, 0));
        if (!is_array($requests)) {
            $requests = [];
        }

        return $requests;
    }

    /**
     * Save IP service requests
     *
     * @param string[] $requests
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    protected static function saveIpRequests($requests)
    {
        return Configuration::updateValue(static::IP_SERVICE_REQUESTS, json_encode($requests), false, 0, 0);
    }
}
