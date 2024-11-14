<?php
/**
 * 2018 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2017-2024 thirty bees
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Utils;
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
    const UUID = 'MAILCHIMP_UUID';
    const API_KEY_VALID = 'MAILCHIMP_API_KEY_VALID';
    const CONFIRMATION_EMAIL = 'MAILCHIMP_CONFIRMATION_EMAIL';
    const UPDATE_EXISTING = 'MAILCHIMP_UPDATE_EXISTING';
    const EXPORT_ALL = 'MAILCHIMP_IMPORT_ALL';
    const EXPORT_OPT_IN = 'MAILCHIMP_IMPORT_OPTED_IN';
    const LAST_EXPORT = 'MAILCHIMP_LAST_IMPORT';
    const LAST_EXPORT_ID = 'MAILCHIMP_LAST_IMPORT_ID';
    const API_TIMEOUT = 20;
    const API_CONCURRENCY = 5;
    const RETRIES = 5;

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
    const DISABLE_POPUP = 'MAILCHIMP_DISABLE_POPUP';

    /**
     * @var string $baseUrl
     */
    public $baseUrl;

    /**
     * @var array $mailChimpLanguages
     */
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
     * @var string $apiKey MailChimp API key
     */
    protected static $apiKey;

    /**
     * @var string $uuid MailChimp account ID
     */
    protected static $uuid;

    /**
     * @var Client $guzzle
     */
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
        $this->version = '1.5.2';
        $this->author = 'thirty bees';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->tb_min_version = '1.5.0';

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
        
        Configuration::updateValue(static::DISABLE_POPUP, true);

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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
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

            return $this->displayMainPage($this->getAdminController());
        }
    }

    /**
     * Get lists
     *
     * @param AdminController $controller
     * @param bool $prepare Prepare for display in e.g. HelperForm
     *
     * @return array|bool
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getLists(AdminController $controller, $prepare = false)
    {
        $client = static::getGuzzle();
        if (!$client) {
            return false;
        }
        try {
            $lists = json_decode((string) $client->get('lists')->getBody(), true);

            if ($prepare) {
                $preparedList = [];
                foreach ($lists['lists'] as $list) {
                    $preparedList[$list['id']] = $list['name'];
                }

                return $preparedList;
            }

            return $lists['lists'];
        } catch (Exception $e) {
            $this->addError($controller, $e->getMessage());
        }

        return false;
    }

    /**
     * Hook to display header
     *
     * @return string
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @since 1.1.0
     *
     * @noinspection PhpUndefinedFieldInspection
     */
    public function hookDisplayHeader()
    {
        // Set MailChimp tracking code
        if (Tools::isSubmit('mc_tc') || Tools::isSubmit('mc_cid')) {
            $cookie = new Cookie('tb_mailchimp');
            if ($cookie->mc_tc != Tools::getValue('mc_tc') || $cookie->mc_cid != Tools::getValue('mc_cid')) {
                $cookie->mc_tc = Tools::getValue('mc_tc');
                $cookie->mc_cid = Tools::getValue('mc_cid');
                $cookie->landing_site = Tools::getShopProtocol()."{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
                $cookie->setExpire(static::COOKIE_LIFETIME);
                $cookie->write();
            }
        }

        if (static::getApiKey() && static::$uuid && !Configuration::get(static::DISABLE_POPUP)) {
            $shop = MailChimpShop::getByShopId($this->context->shop->id);
            if (!Validate::isLoadedObject($shop)) {
                return '';
            }

            $this->context->smarty->assign([
                'mc_script' => static::getMailChimpScript(),
            ]);

            return $this->display(__FILE__, 'popup.tpl');
        }

        return '';
    }

    /**
     * Hook to front office footer
     *
     * @return void
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayBackOfficeHeader()
    {
        $mailChimpShop = MailChimpShop::getByShopId($this->context->shop->id);
        if (strtotime((string)$mailChimpShop->date_upd) < strtotime('-1 day')) {
            MailChimpShop::renewScripts(Shop::getShops(true, null, true));
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
     *
     * @noinspection PhpUndefinedFieldInspection
     */
    public function hookActionValidateOrder($params)
    {
        try {
            $cookie = new Cookie('tb_mailchimp');
            if ($cookie->mc_tc || $cookie->mc_cid) {
                $order = $params['order'];
                if (!($order instanceof Order)) {
                    return;
                }
                $mailChimpTracking = new MailChimpTracking();
                $mailChimpTracking->mc_tc = $cookie->mc_tc;
                $mailChimpTracking->mc_cid = $cookie->mc_cid;
                $mailChimpTracking->id_order = $order->id;
                $mailChimpTracking->landing_site = $cookie->landing_site;

                $mailChimpTracking->save();

                unset($cookie->mc_tc);
                unset($cookie->mc_cid);
                unset($cookie->landing_site);
                $cookie->write();
            }
        } catch (Exception $e) {
            if (isset($params['order']->id) && isset($cookie->mc_tc)) {
                Logger::addLog("Unable to set Mailchimp tracking code $cookie->mc_tc for Order {$params['order']->id}", 2);
            } elseif (isset($params['order']->id) && isset($cookie->mc_cid)) {
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
        return static::$mailChimpLanguages[$iso] ?? 'en';
    }

    /**
     * @param MailChimpSubscriber $subscription
     *
     * @return bool
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionObjectCustomerAddAfter($params)
    {
        $customer = $params['object'];
        if (! ($customer instanceof Customer)) {
            return;
        }
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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionObjectCustomerUpdateAfter($params)
    {
        $customer = $params['object'];
        if (! ($customer instanceof Customer)) {
            return;
        }
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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.0.0
     */
    public function hookActionAdminCustomersControllerSaveAfter($params)
    {
        if (Tools::isSubmit('newsletter')) {
            $object = $params['return'];
            if ($object instanceof Customer) {
                $subscription = (string)Tools::getValue('newsletter')
                    ? MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED
                    : MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
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
    }

    /**
     * @param array $params
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     *
     * @noinspection PhpArrayWriteIsNotUsedInspection
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
                'title'           => $this->l('MailChimp').'<br>'.$this->l('Abandoned').'<br>'.$this->l('cart mails'),
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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getGuzzle()
    {
        if (!static::$guzzle) {
            // Initialize Guzzle and the retry middleware, include the default options
            $apiKey = static::getApiKey();
            $dc = static::getDc();
            if ($apiKey && $dc) {
                $stack = HandlerStack::create(Utils::chooseHandler());
                $stack->push(Middleware::retry(function (
                    $retries,
                    Request $request,
                    Response $response = null,
                    RequestException $exception = null
                ) {
                    // Limit the number of retries to 5
                    if ($retries >= static::RETRIES) {
                        return false;
                    }

                    if ($response) {
                        // Retry on server errors
                        if ($response->getStatusCode() >= 500) {
                            return true;
                        }
                    }

                    return false;
                }, function ($retries) {
                    return $retries * 1000;
                }));
                $guzzle = new Client([
                    'timeout'         => static::API_TIMEOUT,
                    'connect_timeout' => static::API_TIMEOUT,
                    'verify'          => (file_exists(_PS_TOOL_DIR_.'cacert.pem') && filemtime(_PS_TOOL_DIR_.'cacert.pem') > strtotime('-2 years'))
                        ? _PS_TOOL_DIR_.'cacert.pem'
                        : true,
                    'base_uri'        => "https://$dc.api.mailchimp.com/3.0/",
                    'headers'         => [
                        'Accept'        => 'application/json',
                        'Authorization' => 'Basic '.base64_encode("anystring:$apiKey"),
                        'Content-Type'  => 'application/json;charset=UTF-8',
                        'User-Agent'    => static::getUserAgent(),
                    ],
                    'handler'         => $stack,
                ]);

                static::$guzzle = $guzzle;
            }
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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getApiKey()
    {
        if (!static::$apiKey) {
            static::$apiKey = Configuration::get(static::API_KEY, null, 0, 0);
            static::$uuid = Configuration::get(static::UUID, null, 0, 0);
            if (static::$apiKey && !static::$uuid) {
                $apiKey = static::$apiKey;
                $dc = static::getDc();
                try {
                    $response = json_decode((string) (new Client([
                        'timeout'         => static::API_TIMEOUT,
                        'connect_timeout' => static::API_TIMEOUT,
                        'verify'          => (file_exists(_PS_TOOL_DIR_.'cacert.pem') && filemtime(_PS_TOOL_DIR_.'cacert.pem') > strtotime('-2 years'))
                            ?  _PS_TOOL_DIR_.'cacert.pem'
                            : true,
                        'base_uri'        => "https://$dc.api.mailchimp.com/3.0/",
                        'headers'         => [
                            'Accept'        => 'application/json',
                            'Authorization' => 'Basic '.base64_encode("anystring:$apiKey"),
                            'Content-Type'  => 'application/json;charset=UTF-8',
                            'User-Agent'    => static::getUserAgent(),
                        ],
                    ]))->get('', ['timeout' => 2, 'connect_timeout' => 2])->getBody());
                    if (!empty($response->account_id)) {
                        static::$uuid = $response->account_id;
                        Configuration::updateValue(static::UUID, static::$uuid, false, 0, 0);
                    }
                } catch (TransferException $e) {
                }
            }
        }

        return static::$apiKey;
    }

    /**
     * Get data center
     *
     * @return string|false
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getDc()
    {
        $dc = array_pad(explode('-', static::getApiKey()), 2, '')[1];
        if (!preg_match('/^[a-zA-Z][a-zA-Z][0-9]+$/', $dc)) {
            return false;
        }

        return $dc;
    }

    /**
     * @param string $apiKey
     *
     * @return bool
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function setApiKey($apiKey)
    {
        // Reset the internal Guzzle
        static::$guzzle = null;
        // Change the internal API key
        static::$apiKey = $apiKey;
        static::$uuid = '';
        if (static::$apiKey) {
            $apiKey = static::$apiKey;
            $dc = static::getDc();
            try {
                $response = json_decode((string) (new Client([
                    'timeout'         => static::API_TIMEOUT,
                    'connect_timeout' => static::API_TIMEOUT,
                    'verify'          => (file_exists(_PS_TOOL_DIR_.'cacert.pem') && filemtime(_PS_TOOL_DIR_.'cacert.pem') > strtotime('-2 years'))
                        ?  _PS_TOOL_DIR_.'cacert.pem'
                        : true,
                    'base_uri'        => "https://$dc.api.mailchimp.com/3.0/",
                    'headers'         => [
                        'Accept'        => 'application/json',
                        'Authorization' => 'Basic '.base64_encode("anystring:$apiKey"),
                        'Content-Type'  => 'application/json;charset=UTF-8',
                        'User-Agent'    => static::getUserAgent(),
                    ],
                ]))->get('', ['timeout' => 2, 'connect_timeout' => 2])->getBody());
                if (!empty($response->account_id)) {
                    static::$uuid = $response->account_id;
                    Configuration::updateValue(static::UUID, static::$uuid, false, 0, 0);
                }
            } catch (TransferException $e) {
            }
        }

        return Configuration::updateValue(static::API_KEY, $apiKey, false, 0, 0);
    }

    /**
     * Display export modals
     *
     * @return string
     *
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
     * Add confirmation message
     *
     * @param string $message Message
     * @param bool   $private
     */
    public function addConfirmation(AdminController $controller, $message, $private = false)
    {
        if (!Tools::isSubmit('configure')) {
            if (!$private) {
                $controller->confirmations[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
            }
        } else {
            $controller->confirmations[] = $message;
        }
    }

    /**
     * Add error message
     *
     * @param string $message Message
     * @param bool   $private
     */
    public function addError(AdminController $controller, $message, $private = false)
    {
        if (!Tools::isSubmit('configure')) {
            if (!$private) {
                $controller->errors[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
            }
        } else {
            // Do not add error in this case
            // It will break execution of AdminController
            $controller->warnings[] = $message;
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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
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
     * @throws GuzzleException
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
            if ($exportRemaining) {
                $success = $this->exportProducts(0, $idShops, true);
            } else {
                $success = $this->exportProducts(($count - 1) * static::EXPORT_CHUNK_SIZE, $idShops, false);
            }

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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
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

            if ($exportRemaining) {
                $success = $this->exportCarts(0, $idShops, true);
            } else {
                $success = $this->exportCarts(($count - 1) * static::EXPORT_CHUNK_SIZE, $idShops, false);
            }

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
     * @throws GuzzleException
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

            if ($exportRemaining) {
                $success = $this->exportOrders(0, $idShops, true);
            } else {
                $success = $this->exportOrders(($count - 1) * static::EXPORT_CHUNK_SIZE, $idShops, false);
            }
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
    public function processReset($entityType, $idShop, $ajax = false)
    {
        switch ($entityType) {
            case 'products':
                $table = 'product_shop';
                $primary = 'id_product';
                $def = MailChimpProduct::$definition;
                break;
            case 'carts':
                $table = 'cart';
                $primary = 'id_cart';
                $def = MailChimpCart::$definition;
                break;
            case 'orders':
                $def = MailChimpOrder::$definition;
                $table = 'orders';
                $primary = 'id_order';
                break;
            default:
                throw new RuntimeException('Unknown entity type');
        }

        $success = Db::getInstance()->execute(
            'DELETE mo
         FROM `'._DB_PREFIX_.bqSQL($def['table']).'` mo
         INNER JOIN `'._DB_PREFIX_.bqSQL($table).'` o ON o.`'.bqSQL($primary).'` = mo.`'.bqSQL($primary).'`
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
     * @param string $type            `products`, `carts` or `orders`
     * @param int    $idShop
     * @param int    $exportRemaining
     * @param string $submit
     *
     * @return array|false
     * @throws PrestaShopException
     */
    public function cronExport($type, $idShop, $exportRemaining, $submit)
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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function postProcess()
    {
        $controller = $this->getAdminController();
        if (Tools::isSubmit('submitApiKey')) {
            // Check if MailChimp API key is valid
            static::setApiKey(Tools::getValue(static::API_KEY));
            Configuration::updateValue(static::API_KEY_VALID, false, false, 0, 0);
            $this->checkApiKey($controller);
        } elseif (Tools::isSubmit('submitSettings')) {
            // Update all the configuration
            // And check if updates were successful
            $confirmationEmail = (bool) Tools::getvalue(static::CONFIRMATION_EMAIL);
            $importOptedIn = (bool) Tools::getvalue(static::EXPORT_OPT_IN);

            if (Configuration::updateValue(static::CONFIRMATION_EMAIL, $confirmationEmail)
                && Configuration::updateValue(static::EXPORT_OPT_IN, $importOptedIn)
                && Configuration::updateValue(static::GDPR, (bool) Tools::getvalue(static::GDPR, false))
                && Configuration::updateValue(static::DISABLE_POPUP, (bool) Tools::getvalue(static::DISABLE_POPUP))
            ) {
                $this->addConfirmation($controller, $this->l('Settings updated.'));
            } else {
                $this->addError($controller, $this->l('Some of the settings could not be saved.'));
            }
        } elseif (Tools::isSubmit('submitShops')) {
            $shopLists = Tools::getValue('shop_list_id');
            $shopTaxes = Tools::getValue('shop_tax');
            $client = static::getGuzzle();
            if (!$client) {
                return;
            }
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
                                $storeAddress = $shop->getAddress();
                                $shopState = StateCore::getNameById($storeAddress->id_state) ? StateCore::getNameById($storeAddress->id_state) : '';
                                $shopCountryIso = CountryCore::getIsoById($storeAddress->id_country);
                                $client->post(
                                    'ecommerce/stores',
                                    [
                                        'json' => [
                                            'id'            => 'tbstore_'.(int) $idShop,
                                            'list_id'       => (string) $idList,
                                            'name'          => (string) $shop->name,
                                            'domain'        => (string) $shop->domain_ssl,
                                            'email_address' => (string) Configuration::get('PS_SHOP_EMAIL', null, $shop->id_shop_group, $shop->id),
                                            'currency_code' => (string) strtoupper($currency->iso_code),
                                            'money_format'  => (string) $currency->sign,
                                            'platform'      => 'thirty bees',
                                            'address'       => [
                                                'company'  => (string) $shop->getAddress()->company,
                                                'address1' => (string) $shop->getAddress()->address1,
                                                'address2' => (string) (isset($shop->getAddress()->address2) ? $shop->getAddress()->address2 : ''),
                                                'city'     => (string) $shop->getAddress()->city,
                                                'state'    => (string) $shopState,
                                                'country'  => (string) $shopCountryIso,
                                                'zip'      => (string) $storeAddress->postcode,
                                            ],
                                        ],
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

                    $mailChimpShop->save();

                    // Create MailChimp side webhooks
                    if ($mailChimpShop->list_id) {
                        $register = $this->registerWebhookForList($mailChimpShop->list_id);
                        if (!$register) {
                            $this->addError($controller, $this->l('MailChimp webhooks could not be implemented. Please try again.'));
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
            Configuration::updateValue(static::VALID_ORDER_STATUSES, serialize($this->getStatusesValue(static::VALID_ORDER_STATUSES)));
            Configuration::updateValue(static::ORDER_STATUS_PAID, serialize($this->getStatusesValue(static::ORDER_STATUS_PAID)));
            Configuration::updateValue(static::ORDER_STATUS_CANCELED, serialize($this->getStatusesValue(static::ORDER_STATUS_CANCELED)));
            Configuration::updateValue(static::ORDER_STATUS_REFUNDED, serialize($this->getStatusesValue(static::ORDER_STATUS_REFUNDED)));
            Configuration::updateValue(static::ORDER_STATUS_SHIPPED, serialize($this->getStatusesValue(static::ORDER_STATUS_SHIPPED)));
            $date = Tools::getValue(static::DATE_CUTOFF);
            if (!$date) {
                $date = '2018-01-01';
            }
            Configuration::updateValue(static::DATE_CUTOFF, date('Y-m-d', strtotime($date)));
        }
    }

    /**
     * @param string $idList
     *
     * @return bool
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
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

        return Context::getContext()->link->getModuleLink($this->name, 'hook', [], true, $idLang, $idShop, false);
    }

    /**
     * @param int $idList
     * @param string|null $url
     *
     * @return bool
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
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
        if (!$client) {
            return false;
        }
        $promise = $client->postAsync(
            "lists/{$idList}/webhooks",
            [
                'json'   => [
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
                ],
            ]
        );
        $promise->then(function ($response) use (&$success) {
            $success = $response instanceof Response && $response->getStatusCode() === 200;
        });
        $promise->otherwise(function ($reason) use (&$success, $client) {
            if ($reason instanceof ClientException) {
                $response = json_decode((string) $reason->getResponse()->getBody(), true);
                if (!empty($response['errors'][0]['message']) && $response['errors'][0]['message'] !== 'Sorry, you can\'t set up multiple WebHooks for one URL') {
                    $requestBody = (string) $reason->getRequest()->getBody();
                    $responseBody = (string) $reason->getResponse()->getBody();
                    Logger::addLog("MailChimp module client error: {$requestBody} -- {$responseBody}");
                    $success = false;
                }
            }
        });
        GuzzleHttp\Promise\Utils::settle([$promise])->wait();

        return $success;
    }

    /**
     * @param MailChimpSubscriber[] $list
     *
     * @return bool
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.0.0
     */
    protected function importList($list)
    {
        $success = true;
        // Append subscribers to batch operation request using PUT method (to enable update existing)
        $client = static::getGuzzle();
        if (!$client) {
            return false;
        }
        $mailChimpShop = MailChimpShop::getByShopId(Context::getContext()->shop->id);
        $promises = call_user_func(function () use ($list, $client, $mailChimpShop) {
            for ($i = 0; $i < count($list); $i++) {
                $subscriber = $list[$i];
                $hash = md5(mb_strtolower($subscriber->getEmail()));
                yield $client->putAsync(
                    "lists/{$mailChimpShop->list_id}/members/{$hash}",
                    [
                        'json' => $subscriber->getAsArray(),
                    ]
                );
            }
        });

        static::signalSyncStart(Context::getContext()->shop->id);
        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected'    => function ($reason) use (&$success) {
                if ($reason instanceof RequestException) {
                    $responseBody = (string) $reason->getResponse()->getBody();
                    $requestBody = (string) $reason->getRequest()->getBody();
                    Logger::addLog("MailChimp client error: {$requestBody} -- {$responseBody}", 2);
                } elseif ($reason instanceof Exception) {
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                }
                $success = false;
            },
        ]))->promise()->wait();
        static::signalSyncStop(Context::getContext()->shop->id);

        return $success;
    }

    /**
     * Display main page
     *
     * @param AdminController $controller
     * @return string
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function displayMainPage(AdminController $controller)
    {
        $controller->addJS($this->_path.'views/js/sweetalert.min.js');
        $this->loadTabs($controller);

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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @since 1.0.0
     */
    protected function displayApiForm(AdminController $controller)
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
                    'title' => $this->l('API Settings').$this->getConfigurationContext(),
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

        if ($this->checkApiKey($controller)) {
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

            $fields[] = [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Export Settings').$this->getConfigurationContext(Shop::getContextShopID(), Shop::getContextShopGroupID()),
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

            $fields[] = [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Miscellaneous').$this->getConfigurationContext(Shop::getContextShopID(), Shop::getContextShopGroupID()),
                        'icon'  => 'icon-cogs',
                    ],
                    'input'  => [
                        [
                            'type'   => 'switch',
                            'label'  => $this->l('Disable MailChimp script'),
                            'name'   => static::DISABLE_POPUP,
                            'desc'   => $this->l('For privacy purposes you can prevent the popup and other MailChimp tracking scripts from loading on front office pages'),
                            'id'     => 'importOptedIn',
                            'values' => [
                                [
                                    'id'    => static::DISABLE_POPUP.'_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled'),
                                ],
                                [
                                    'id'    => static::DISABLE_POPUP.'_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled'),
                                ],
                            ],
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Save'),
                        'class' => 'btn btn-default pull-right',
                        'name'  => 'submitSettings',
                    ],
                ],
            ];
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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @since 1.0.0
     */
    protected function displayCronForm()
    {
        $context = Context::getContext();
        $token = substr(Tools::encrypt($this->name.'/cron'), 0, 10);

        if (Shop::isFeatureActive()) {
            if (Shop::getContext() === Shop::CONTEXT_ALL) {
                $idShop = 'all';
            } elseif (Shop::getContext() === Shop::CONTEXT_GROUP) {
                $idShop = array_map('intval', Shop::getContextListShopID());
            } else {
                $idShop = $context->shop->id;
            }
        } else {
            $idShop = $context->shop->id;
        }
        $idShopString = is_array($idShop) ? implode(',', $idShop) : (string) $idShop;

        $idLang = array_values(Language::getLanguages(true, false, true));
        if (!empty($idLang)) {
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
                    $context->shop->id,
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
                    $context->shop->id,
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
                    $context->shop->id,
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
                    $context->shop->id,
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
                    $context->shop->id,
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
                    $context->shop->id,
                    false
                ),
                'cron_all_products_cli'       => "php modules/mailchimp/cli.php --shop=$idShopString --action=ExportAllProducts",
                'cron_remaining_products_cli' => "php modules/mailchimp/cli.php --shop=$idShopString --action=ExportRemainingProducts",
                'cron_all_carts_cli'          => "php modules/mailchimp/cli.php --shop=$idShopString --action=ExportAllCarts",
                'cron_remaining_carts_cli'    => "php modules/mailchimp/cli.php --shop=$idShopString --action=ExportRemainingCarts",
                'cron_all_orders_cli'         => "php modules/mailchimp/cli.php --shop=$idShopString --action=ExportAllOrders",
                'cron_remaining_orders_cli'   => "php modules/mailchimp/cli.php --shop=$idShopString --action=ExportRemainingOrders",
            ]
        );

        $fields = [];

        $fieldsForm2 = [
            'form' => [
                'legend'      => [
                    'title' => $this->l('Cron Settings').$this->getConfigurationContext(),
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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.0.0
     */
    protected function getConfigFieldsValues()
    {
        $configFields = [
            static::API_KEY            => static::getApiKey(),
            static::CONFIRMATION_EMAIL => Configuration::get(static::CONFIRMATION_EMAIL),
            static::EXPORT_OPT_IN      => Configuration::get(static::EXPORT_OPT_IN),
            static::DATE_CUTOFF        => date('Y-m-d', strtotime(static::getOrderDateCutoff())),
            static::GDPR               => (bool) Configuration::get(static::GDPR),
            static::DISABLE_POPUP      => (bool) Configuration::get(static::DISABLE_POPUP),
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
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function displayShopsForm(AdminController $controller)
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
            'languages'   => $controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getShopsForm($controller)]);
    }

    /**
     * @param AdminController $controller
     * @return array
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getShopsForm(AdminController $controller)
    {
        $lists = [0 => $this->l('Do not sync')];
        $thisLists = $this->getLists($controller, true);
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
                    'title' => $this->l('Shop settings').$this->getConfigurationContext(),
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
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function displayProductsForm(AdminController $controller)
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
            'languages'   => $controller->getLanguages(),
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
                    'title' => $this->l('Product export').$this->getConfigurationContext(),
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
     * @param AdminController $controller
     * @return string Form HTML
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function displayCartsForm(AdminController $controller)
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
            'languages'   => $controller->getLanguages(),
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
                    'title' => $this->l('Cart export').$this->getConfigurationContext(),
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
     * @param AdminController $controller
     * @return string Form HTML
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function displayOrdersForm(AdminController $controller)
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
            'languages'    => $controller->getLanguages(),
            'id_language'  => $this->context->language->id,
            'fields_value' => $this->getConfigFieldsValues(),
        ];

        return $helper->generateForm($this->getOrdersForm($controller));
    }

    /**
     * @param AdminController $controller
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getOrdersForm(AdminController $controller)
    {
        $controller->addJqueryUI('ui.datepicker');

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
                        'title' => $this->l('Order export').$this->getConfigurationContext(),
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
                        'title' => $this->l('Order settings').$this->getConfigurationContext(Shop::getContextShopID(), Shop::getContextShopGroupID()),
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
     * @param AdminController $controller
     * @return void
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @since 1.0.0
     */
    protected function loadTabs(AdminController $controller)
    {
        $contents = [
            [
                'name'  => $this->l('Export Settings'),
                'icon'  => 'icon-upload',
                'value' => $this->displayApiForm($controller),
                'badge' => false,
            ],
        ];

        if ($this->checkApiKey($controller)) {
            $contents[] = [
                'name'  => $this->l('Shops'),
                'icon'  => 'icon-building',
                'value' => $this->displayShopsForm($controller),
                'badge' => false,
            ];
            $contents[] = [
                'name'  => $this->l('Products'),
                'icon'  => 'icon-archive',
                'value' => $this->displayProductsForm($controller),
                'badge' => false,
            ];
            $contents[] = [
                'name'  => $this->l('Carts'),
                'icon'  => 'icon-shopping-cart',
                'value' => $this->displayCartsForm($controller),
                'badge' => false,
            ];
            $contents[] = [
                'name'  => $this->l('Orders'),
                'icon'  => 'icon-money',
                'value' => $this->displayOrdersForm($controller),
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
        $controller->addCss($this->_path.'/views/css/configtabs.css');
        $controller->addJs($this->_path.'/views/js/configtabs.js');
    }

    /**
     * Check if API key is valid
     *
     * @return bool Indicates whether the API key is valid
     * @throws GuzzleException
     * @throws PrestaShopException
     */
    protected function checkApiKey(AdminController $controller)
    {
        if (static::getApiKey() && Configuration::get(static::API_KEY_VALID, false, 0, 0)) {
            return true;
        }

        // Show settings form only if API key is set and working
        $apiKey = static::getApiKey();
        $dc = static::getDc();
        $validKey = false;
        if (isset($apiKey) && $apiKey) {
            // Check if API key is valid
            try {
                $getLists = json_decode((string) (new Client([
                    'verify'   => (file_exists(_PS_TOOL_DIR_.'cacert.pem') && filemtime(_PS_TOOL_DIR_.'cacert.pem') > strtotime('-2 years'))
                        ?  _PS_TOOL_DIR_.'cacert.pem'
                        : true,
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
                    Configuration::updateValue(static::API_KEY_VALID, true, false, 0 ,0);
                }
            } catch (ClientException $e) {
                $responseBody = (string) $e->getResponse()->getBody();
                $requestBody = (string) $e->getRequest()->getBody();
                $this->addError($controller, "MailChimp client error: {$requestBody} -- {$responseBody}");
            } catch (TransferException $e) {
                $this->addError($controller, "MailChimp connection error: {$e->getMessage()}");
            } catch (Exception $e) {
                $this->addError($controller, "MailChimp generic error: {$e->getMessage()}");
            }
        }

        return $validKey;
    }

    /**
     * Export subscribers
     *
     * @param int $offset
     * @param int[]|int|null $idShops
     *
     * @return bool
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.0.0
     */
    protected function exportSubscribers($offset, $idShops = null)
    {
        if (is_string($idShops) || is_int($idShops)) {
            $idShops = [(int) $idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getContextListShopID(Shop::SHARE_CUSTOMER);
        }

        $mailChimpShops = array_filter(MailChimpShop::getByShopIds($idShops), function ($mcs) {
            return $mcs->list_id;
        });
        $idShops = array_map('intval', array_filter($idShops, function ($idShop) use ($mailChimpShops) {
            return in_array($idShop, array_keys($mailChimpShops));
        }));
        if (empty($mailChimpShops) || empty($idShops)) {
            return false;
        }
        $subscribers = MailChimpSubscriber::getSubscribers($idShops, $offset, static::EXPORT_CHUNK_SIZE);
        if (empty($subscribers)) {
            return false;
        }

        $client = static::getGuzzle();
        if (!$client) {
            return false;
        }
        $promises = call_user_func(function () use (&$subscribers, $mailChimpShops, $client) {
            foreach ($subscribers as $subscriber) {
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
                yield $client->putAsync(
                    "lists/{$mailChimpShops[$subscriber['id_shop']]->list_id}/members/{$subscriberHash}",
                    [
                        'json' => $subscriberBody,
                    ]
                );
            }
        });

        $success = true;
        static::signalSyncStart($idShops);
        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected' => function ($reason) use (&$success) {
                if ($reason instanceof RequestException) {
                    $responseBody = (string) $reason->getResponse()->getBody();
                    $requestBody = (string) $reason->getRequest()->getBody();
                    Logger::addLog("MailChimp client error: {$requestBody} -- {$responseBody}", 2);
                } elseif ($reason instanceof TransferException) {
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                }

                $success = false;
            }
        ]))->promise()->wait();
        static::signalSyncStop($idShops);

        return $success;
    }

    /**
     * Export products
     *
     * @param int $offset
     * @param null $idShops
     * @param bool $remaining
     *
     * @return bool Success
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.0.0
     */
    protected function exportProducts($offset, $idShops = null, $remaining = false)
    {
        if (is_string($idShops) || is_int($idShops)) {
            $idShops = [(int) $idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getContextListShopID(Shop::SHARE_STOCK);
        }

        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $mailChimpShops = array_filter(MailChimpShop::getByShopIds($idShops), function ($mcs) {
            return $mcs->list_id;
        });
        $idShops = array_map('intval', array_filter($idShops, function ($idShop) use ($mailChimpShops) {
            return in_array($idShop, array_keys($mailChimpShops));
        }));
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
        if (!$client) {
            return false;
        }
        $link = Context::getContext()->link;

        $promises = call_user_func(function () use (&$products, $idLang, $client, $link, $taxes) {
            $stockmgmt = (bool) Configuration::get('PS_STOCK_MANAGEMENT');
            $globalOosp = (bool) Configuration::get('PS_ORDER_OUT_OF_STOCK');
            foreach ($products as $product) {
                $allowOosp = $globalOosp && $product['out_of_stock'] == 2 || $product['out_of_stock'] == 1;
                $productObj = new Product();
                $productObj->hydrate($product);
                $allImages = array_values($productObj->getImages($idLang));
                $rate = $taxes[$product['id_shop']];
                $idShop = $product['id_shop'];

                $variants = [];
                $images = [];
                if ($productObj->hasAttributes()) {
                    $allCombinationImages = $productObj->getCombinationImages($idLang);
                    if (!is_array($allCombinationImages)) {
                        $allCombinationImages = [];
                    }
                    $dbCombinations = array_filter($productObj->getAttributeCombinations($idLang), function ($item) {
                        return (bool) $item['id_product_attribute']; // All combinations must have an `id_product_attribute` > 0
                    });
                    if (empty($dbCombinations)) {
                        continue;
                    }
                    $allCombinations = [];
                    foreach ($dbCombinations as $dbCombination) {
                        $allCombinations[$dbCombination['id_product_attribute']] = $dbCombination;
                    }
                    foreach (array_merge(array_keys($allCombinations), [0]) as $idProductAttribute) {
                        if (!array_key_exists($idProductAttribute, $allCombinationImages)) {
                            $allCombinationImages[$idProductAttribute] = $allImages;
                        }
                    }
                    foreach ($allCombinations as $combination) {
                        if (!isset($combination['quantity'])
                            || !isset($combination['reference'])
                            || !$combination['id_product_attribute']
                        ) {
                            continue;
                        }
                        $variant = [
                            'id'                 => "{$product['id_product']}-{$combination['id_product_attribute']}",
                            'title'              => (string) $product['name'],
                            'sku'                => $combination['reference'],
                            // Apply the chosen tax rate here so the proper price shows up in emails
                            'price'              => (float) ($product['price'] * $rate) + (float) ($combination['price'] * $rate),
                            // Add artificial stock when stock mgmt is disabled and/or oos and oos ordering allowed
                            'inventory_quantity' => (int) (!$stockmgmt ? 999 : ($combination['quantity'] ?: ($allowOosp ? 999 : 0))),
                        ];
                        if (isset($allCombinationImages[$combination['id_product_attribute']])
                            && is_array($allCombinationImages[$combination['id_product_attribute']])
                            && count($allCombinationImages[$combination['id_product_attribute']]) > 0
                        ) {
                            $variant['image_url'] = $link->getImageLink('default', "{$product['id_product']}-{$allCombinationImages[$combination['id_product_attribute']][0]['id_image']}");
                            foreach ($allCombinationImages[$combination['id_product_attribute']] as $image) {
                                if (!isset($images[$image['id_image']])) {
                                    $images[$image['id_image']] = [
                                        'id'          => $image['id_image'],
                                        'url'         => $link->getImageLink('default', $image['id_image']),
                                        'variant_ids' => [],
                                    ];

                                }
                                $images[$image['id_image']]['variant_ids'][] = "{$product['id_product']}-{$combination['id_product_attribute']}";
                            }
                        }
                        $variants[] = $variant;
                    }
                } else {
                    $variants[] = [
                        'id'                 => "{$product['id_product']}-0",
                        'title'              => (string) $product['name'],
                        'sku'                => (string) ($product['reference'] ?? ''),
                        // Apply the tax rate here so the proper price shows up in emails
                        'price'              => (float) ($product['price'] * $rate),
                        // Add artificial stock when stock mgmt is disabled and/or oos and oos ordering allowed
                        'inventory_quantity' => (int) (!$stockmgmt ? 999 : (StockAvailable::getQuantityAvailableByProduct($product['id_product'], null, $idShop) ?: ($allowOosp ? 999 : 0))),
                        'image_url'          => !empty($allImages) ? $link->getImageLink('default', "{$product['id_product']}-{$allImages[0]['id_image']}") : '',
                    ];
                    foreach ($allImages as $image) {
                        if (!isset($images[$image['id_image']])) {
                            $images[$image['id_image']] = [
                                'id'          => $image['id_image'],
                                'url'         => $link->getImageLink('default', $image['id_image']),
                                'variant_ids' => [],
                            ];
                        }
                        $images[$image['id_image']]['variant_ids'][] = "{$product['id_product']}-0";
                    }
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
                if (!empty($images)) {
                    $payload['images'] = array_values($images);
                }

                if (!empty($product['last_synced']) && $product['last_synced'] > '2000-01-01 00:00:00') {
                    yield $client->patchAsync(
                        "ecommerce/stores/tbstore_{$idShop}/products/{$product['id_product']}",
                        [
                            'json' => $payload,
                        ]
                    );
                } else {
                    yield $client->postAsync(
                        "ecommerce/stores/tbstore_{$idShop}/products",
                        [
                            'json' => $payload,
                        ]
                    );
                }
                unset($images);
                unset($variants);
                unset($payload);
            }
        });

        static::signalSyncStart($idShops);
        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected' => function ($reason) use ($client) {
                if ($reason instanceof ClientException) {
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
                        } catch (TransferException|Exception $e) {
                            // Second attempt failed, this means an error in the request, continue to make it log the error
                            $reason = $e;
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
                        } catch (TransferException|Exception $e) {
                            $reason = $e;
                            // Second attempt failed, this means an error in the request, continue to make it log the error
                        }
                    }

                    if (method_exists($reason, 'getResponse') && method_exists($reason, 'getRequest')) {
                        $request = $reason->getRequest();
                        $response = $reason->getResponse();
                        if ($request && $response) {
                            $requestBody = (string) $request->getBody();
                            $responseBody = (string) $response->getBody();
                            Logger::addLog(
                                "MailChimp client error: {$requestBody} -- {$responseBody}",
                                2,
                                $reason->getResponse()->getStatusCode(),
                                'MailChimpProduct',
                                json_decode((string) $reason->getRequest()->getBody())->id
                            );

                            return;
                        }
                    }
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                } elseif ($reason instanceof Exception) {
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                }
            },
        ]))->promise()->wait();
        static::signalSyncStop($idShops);

        MailChimpProduct::setSynced(array_column($products, 'id_product'), $idShops);

        return true;
    }

    /**
     * Export products
     *
     * @param int[] $range Product IDs
     * @param null $idShops Shop IDs
     * @param bool $orderDetail Use the order detail table to find the missing pieces
     *
     * @return bool Status
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.0.0
     */
    protected function exportProductRange($range, $idShops = null, $orderDetail = false)
    {
        if (empty($range)) {
            return true;
        }

        if (is_string($idShops) || is_int($idShops)) {
            $idShops = [(int) $idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getContextListShopID(Shop::SHARE_STOCK);
        }

        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $mailChimpShops = array_filter(MailChimpShop::getByShopIds($idShops), function ($mcs) {
            return $mcs->list_id;
        });
        $idShops = array_map('intval', array_filter($idShops, function ($idShop) use ($mailChimpShops) {
            return in_array($idShop, array_keys($mailChimpShops));
        }));
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
        if (!$client) {
            return false;
        }
        $link = Context::getContext()->link;

        $promises = call_user_func(function () use (&$products, $idLang, $client, $link, $taxes) {
            $stockmgmt = (bool) Configuration::get('PS_STOCK_MANAGEMENT');
            $globalOosp = (bool) Configuration::get('PS_ORDER_OUT_OF_STOCK');
            foreach (array_unique(array_map('intval', array_column($products, 'id_product'))) as $idProduct) {
                $product = $products[array_search((int) $idProduct, array_map('intval', array_column($products, 'id_product')))];
                // No order detail means the product could be grabbed from the product table and the product still exists in the store
                if (!isset($product['id_order_detail'])) {
                    $allowOosp = $globalOosp && $product['out_of_stock'] == 2 || $product['out_of_stock'] == 1;
                    $productObj = new Product($idProduct);
                    // Work with an existing product
                    $allImages = array_values($productObj->getImages($idLang));
                    $rate = $taxes[$product['id_shop']];
                    $idShop = $product['id_shop'];

                    $variants = [];
                    $images = [];
                    if ($productObj->hasAttributes()) {
                        $allCombinationImages = $productObj->getCombinationImages($idLang);
                        if (!is_array($allCombinationImages)) {
                            $allCombinationImages = [];
                        }
                        $dbCombinations = array_filter($productObj->getAttributeCombinations($idLang), function ($item) {
                            return $item['id_product_attribute']; // All combinations must have an `id_product_attribute` > 0
                        });
                        if (empty($dbCombinations)) {
                            continue;
                        }
                        $allCombinations = [];
                        foreach ($dbCombinations as $dbCombination) {
                            $allCombinations[$dbCombination['id_product_attribute']] = $dbCombination;
                        }
                        foreach (array_keys($allCombinations) as $idProductAttribute) {
                            if (!array_key_exists($idProductAttribute, $allCombinationImages)) {
                                $allCombinationImages[$idProductAttribute] = $allImages;
                            }
                        }

                        foreach ($allCombinations as $combination) {
                            if (!isset($combination['quantity']) || !isset($combination['reference'])) {
                                continue;
                            }
                            $variant = [
                                'id'                 => "{$product['id_product']}-{$combination['id_product_attribute']}",
                                'title'              => (string) $product['name'],
                                'sku'                => $combination['reference'],
                                // Apply the tax rate here so the proper price shows up in emails
                                'price'              => (float) ($product['price'] * $rate) + (float) ($combination['price'] * $rate),
                                // Add artificial stock when stock mgmt is disabled and/or oos and oos ordering allowed
                                'inventory_quantity' => (int) (!$stockmgmt ? 999 : ($combination['quantity'] ?: ($allowOosp ? 999 : 0))),
                            ];
                            if (isset($allCombinationImages[$combination['id_product_attribute']])
                                && is_array($allCombinationImages[$combination['id_product_attribute']])
                                && count($allCombinationImages[$combination['id_product_attribute']]) > 0
                            ) {
                                $variant['image_url'] = $link->getImageLink('default', "{$product['id_product']}-{$allCombinationImages[$combination['id_product_attribute']][0]['id_image']}");
                                foreach ($allCombinationImages[$combination['id_product_attribute']] as $image) {
                                    if (!isset($images[$image['id_image']])) {
                                        $images[$image['id_image']] = [
                                            'id'          => $image['id_image'],
                                            'url'         => $link->getImageLink('default', $image['id_image']),
                                            'variant_ids' => [],
                                        ];

                                    }
                                    $images[$image['id_image']]['variant_ids'][] = "{$product['id_product']}-{$combination['id_product_attribute']}";
                                }
                            }
                            $variants[] = $variant;
                        }
                    } else {
                        $variants[] = [
                            'id'                 => "{$product['id_product']}-0",
                            'title'              => (string) $product['name'],
                            'sku'                => (string) $product['reference'],
                            // Apply taxes here so the proper price shows up in emails
                            'price'              => (float) ($product['price'] * $rate),
                            // Add artificial stock when stock mgmt is disabled and/or oos and oos ordering allowed
                            'inventory_quantity' => (int) (!$stockmgmt ? 999 : (StockAvailable::getQuantityAvailableByProduct($product['id_product'], null, $idShop) ?: ($allowOosp ? 999 : 0))),
                            'image_url'          => !empty($allImages) ? $link->getImageLink('default', "{$product['id_product']}-{$allImages[0]['id_image']}") : '',
                        ];
                        foreach ($allImages as $image) {
                            if (!isset($images[$image['id_image']])) {
                                $images[$image['id_image']] = [
                                    'id'          => $image['id_image'],
                                    'url'         => $link->getImageLink('default', $image['id_image']),
                                    'variant_ids' => [],
                                ];
                            }
                            $images[$image['id_image']]['variant_ids'][] = "{$product['id_product']}-0";
                        }
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
                    if (!empty($images)) {
                        $payload['images'] = array_values($images);
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
                            'id'                 => "{$orderDetail['id_product']}-{$orderDetail['id_product_attribute']}",
                            'title'              => (string) $orderDetail['name'],
                            'sku'                => (string) $orderDetail['reference'],
                            'price'              => (float) $orderDetail['unit_price_tax_incl'],
                            'inventory_quantity' => 0, // Out of stock because we had to grab it from the order_detail table
                        ];
                    }
                    $product = array_values($orderDetails)[0];
                    $variants[] = [
                            'id'                 => "{$idProduct}-0",
                            'title'              => (string) $product['name'],
                            'sku'                => (string) $product['reference'],
                            'price'              => (float) $product['unit_price_tax_incl'],
                            'inventory_quantity' => 0, // Out of stock because we had to grab it from the order_detail table
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
                            'json' => $payload,
                        ]
                    );
                } else {
                    yield $client->postAsync(
                        "ecommerce/stores/tbstore_{$idShop}/products",
                        [
                            'json' => $payload,
                        ]
                    );
                }
                unset($variants);
                unset($images);
                unset($payload);
            }
        });

        $success = true;
        static::signalSyncStart($idShops);
        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected' => function ($reason) use (&$success, $client) {
                if ($reason instanceof ClientException) {
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
                        } catch (TransferException|Exception $e) {
                            // Second attempt failed, this means an error in the request, continue to make it log the error
                            $reason = $e;
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
                        } catch (TransferException|Exception $e) {
                            // Second attempt failed, this means an error in the request, continue to make it log the error
                            $reason = $e;
                        }
                    }

                    if (method_exists($reason, 'getResponse') && method_exists($reason, 'getRequest')) {
                        $request = $reason->getRequest();
                        $response = $reason->getResponse();
                        if ($request && $response) {
                            $requestBody = (string) $request->getBody();
                            $responseBody = (string) $response->getBody();
                            Logger::addLog(
                                "MailChimp client error: {$requestBody} -- {$responseBody}",
                                2,
                                $reason->getResponse()->getStatusCode(),
                                'MailChimpProduct',
                                json_decode((string) $reason->getRequest()->getBody())->id
                            );

                            return;
                        }
                    }
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                } elseif ($reason instanceof Exception) {
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                }
            },
        ]))->promise()->wait();
        static::signalSyncStop($idShops);

        MailChimpProduct::setSynced(array_column($products, 'id_product'), $idShops);

        return $success;
    }

    /**
     * Export all carts
     *
     * @param int $offset
     * @param int|int[]|null $idShops
     * @param bool $remaining
     *
     * @return bool $success
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.1.0
     */
    protected function exportCarts($offset, $idShops = null, $remaining = false)
    {
        if (is_string($idShops) || is_int($idShops)) {
            $idShops = [(int) $idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getContextListShopID(Shop::SHARE_CUSTOMER);
        }
        $mailChimpShops = array_filter(MailChimpShop::getByShopIds($idShops), function ($mc) {
            return $mc->list_id;
        });
        $idShops = array_map('intval', array_filter($idShops, function ($idShop) use ($mailChimpShops) {
            return in_array($idShop, array_keys($mailChimpShops));
        }));
        if (empty($mailChimpShops) || empty($idShops)) {
            return false;
        }

        $carts = MailChimpCart::getCarts($idShops, $offset, static::EXPORT_CHUNK_SIZE, $remaining);
        if (empty($carts)) {
            return false;
        }

        $client = static::getGuzzle();
        if (!$client) {
            return false;
        }
        $promises = call_user_func(function () use (&$carts, $client, $mailChimpShops, $idShops) {
            foreach ($carts as $cart) {
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
                        'json' => [
                            'email_address' => mb_strtolower($cart['email']),
                            'status_if_new' => MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED,
                            'merge_fields'  => $mergeFields,
                            'language'      => static::getMailChimpLanguageByIso($cart['language_code']),
                        ],
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
                            'json' => $payload,
                        ]
                    );
                } else {
                    yield $client->postAsync(
                        "ecommerce/stores/tbstore_{$cart['id_shop']}/carts",
                        [
                            'json' => $payload,
                        ]
                    );
                }
            }
        });

        static::signalSyncStart($idShops);
        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected' => function ($reason) use (&$success, $client) {
                if ($reason instanceof ClientException) {
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
                        } catch (TransferException|Exception $e) {
                            // Second attempt failed, this means an error in the request, continue to make it log the error
                            $reason = $e;
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
                        } catch (TransferException|Exception $e) {
                            // Second attempt failed, this means an error in the request, continue to make it log the error
                            $reason = $e;
                        }
                    }

                    if (method_exists($reason, 'getResponse') && method_exists($reason, 'getRequest')) {
                        $request = $reason->getRequest();
                        $response = $reason->getResponse();
                        if ($request && $response) {
                            $requestBody = (string) $request->getBody();
                            $responseBody = (string) $response->getBody();
                            Logger::addLog(
                                "MailChimp client error: {$requestBody} -- {$responseBody}",
                                2,
                                $reason->getResponse()->getStatusCode(),
                                'MailChimpProduct',
                                json_decode((string) $reason->getRequest()->getBody())->id
                            );

                            return;
                        }
                    }
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                } elseif ($reason instanceof Exception) {
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
        static::signalSyncStop($idShops);

        MailChimpCart::setSynced(array_column($carts, 'id_cart'));

        return $success;
    }

    /**
     * Export orders
     *
     * @param int $offset
     * @param int|int[]|null $idShops
     * @param bool $exportRemaining
     *
     * @return bool $success
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.1.0
     */
    protected function exportOrders($offset, $idShops = null, $exportRemaining = false)
    {
        if (is_string($idShops) || is_int($idShops)) {
            $idShops = [(int) $idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getContextListShopID(Shop::SHARE_ORDER);
        }
        $idShops = array_map('intval', $idShops);
        $mailChimpShops = array_filter(MailChimpShop::getByShopIds($idShops), function ($mcs) {
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
        if (!$client) {
            return false;
        }
        $promises = call_user_func(function () use (&$orders, $client, $mailChimpShops, $idShops) {
            foreach ($orders as $order) {
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
                        'json'    => [
                            'email_address' => mb_strtolower($order['email']),
                            'status_if_new' => MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED,
                            'merge_fields'  => $mergeFields,
                            'language'      => static::getMailChimpLanguageByIso($order['language_code']),
                        ],
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
                if ($order['landing_site']) {
                    $payload['landing_site'] = $order['landing_site'];
                }

                if (!empty($order['last_synced']) && $order['last_synced'] > '2000-01-01 00:00:00') {
                    yield $client->patchAsync(
                        "ecommerce/stores/tbstore_{$mailChimpShops[$order['id_shop']]->id_shop}/orders/{$order['id_order']}",
                        [
                            'json' => $payload,
                        ]
                    );
                } else {
                    yield $client->postAsync(
                        "ecommerce/stores/tbstore_{$mailChimpShops[$order['id_shop']]->id_shop}/orders",
                        [
                            'json' => $payload,
                        ]
                    );
                    $client->deleteAsync("ecommerce/stores/tbstore_{$mailChimpShops[$order['id_shop']]->id_shop}/carts/{$order['id_cart']}");
                }
            }
        });

        static::signalSyncStart($idShops);
        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected'    => function ($reason) use ($client) {
                if ($reason instanceof ClientException) {
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
                        } catch (TransferException|Exception $e) {
                            // Second attempt failed, this means an error in the request, continue to make it log the error
                            $reason = $e;
                        }
                    } elseif (strtoupper($reason->getRequest()->getMethod()) === 'PATCH'
                        && json_decode((string) $reason->getResponse()->getBody())->title === 'Resource Not Found'
                    ) {
                        try {
                            $client->post("ecommerce/stores/tbstore_{$m['id_shop']}/orders",
                                [
                                    'body' => (string) $reason->getRequest()->getBody(),
                                ]
                            );
                            return;
                        } catch (TransferException|Exception $e) {
                            // Second attempt failed, this means an error in the request, continue to make it log the error
                            $reason = $e;
                        }
                    }

                    if (method_exists($reason, 'getResponse') && method_exists($reason, 'getRequest')) {
                        $request = $reason->getRequest();
                        $response = $reason->getResponse();
                        if ($request && $response) {
                            $requestBody = (string) $request->getBody();
                            $responseBody = (string) $response->getBody();
                            Logger::addLog(
                                "MailChimp client error: {$requestBody} -- {$responseBody}",
                                2,
                                $reason->getResponse()->getStatusCode(),
                                'MailChimpProduct',
                                json_decode((string) $reason->getRequest()->getBody())->id
                            );

                            return;
                        }
                    }
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                } elseif ($reason instanceof Exception) {
                    Logger::addLog("MailChimp connection error: {$reason->getMessage()}", 2);
                }
            },
        ]))->promise()->wait();
        self::signalSyncStop($idShops);

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
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws GuzzleException
     * @since 1.1.0
     */
    protected function checkMergeFields($idList)
    {
        if (!$idList) {
            return false;
        }

        $client = static::getGuzzle();
        if (!$client) {
            return false;
        }

        try {
            $result = json_decode((string) $client->get("lists/{$idList}/merge-fields")->getBody(), true);
        } catch (ClientException $e) {
            $responseBody = (string) $e->getResponse()->getBody();
            $requestBody = (string) $e->getRequest()->getBody();
            Logger::addLog("MailChimp client error while grabbing the merge fields: {$requestBody} -- {$responseBody}");
            return false;
        } catch (TransferException $e) {
            Logger::addLog("MailChimp generic error while grabbing the merge fields: {$e->getMessage()}");
            return false;
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
                'required' => false,
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
                        'json'    => [
                            'tag'      => $tag,
                            'name'     => $field['name'],
                            'type'     => $field['type'],
                            'required' => $field['required'],
                        ],
                    ]
                );
            }
        });

        $idShop = MailChimpShop::getByListId($idList)->id_shop;
        static::signalSyncStart($idShop);
        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
            'rejected'    => function ($reason) use ($client, $idList) {
                if ($reason instanceof ClientException) {
                    if (preg_match("/A Merge Field with the tag \"(?P<tag>.*)?\" already exists for this list./", json_decode((string) $reason->getResponse()->getBody())->detail, $m)) {
                        $request = $reason->getRequest();
                        $client->getAsync("lists/{$idList}/merge-fields")->then(function ($response) use ($client, $idList, $request, $m) {
                            /** @var Response $response */
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
                                } catch (TransferException $e) {
                                    Logger::addLog("MailChimp merge field error: {$e->getMessage()}");
                                }
                            }
                        });
                    }
                }
            },
        ]))->promise()->wait();
        static::signalSyncStop($idShop);

        if (isset($result['id'])) {
            return true;
        }

        return false;
    }

    /**
     * @return string
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
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.1.0
     */
    protected function installDbIndices()
    {
        $this->installDbIndex('ALTER TABLE `'._DB_PREFIX_.'mailchimp_product` ADD INDEX `mc_product_product` (`id_product`, `id_shop`)');
        $this->installDbIndex('ALTER TABLE `'._DB_PREFIX_.'mailchimp_cart` ADD INDEX `mc_cart_cart` (`id_cart`)');
        $this->installDbIndex('ALTER TABLE `'._DB_PREFIX_.'mailchimp_order` ADD INDEX `mc_order_order` (`id_order`)');
        $this->installDbIndex('ALTER TABLE `'._DB_PREFIX_.'mailchimp_shop` ADD INDEX `mc_shop_shop` (`id_shop`)');
        $this->installDbIndex('ALTER TABLE `'._DB_PREFIX_.'mailchimp_tracking` ADD INDEX `mc_tracking_order` (`id_order`)');
    }

    /**
     * Install Db Index
     *
     * @param string $sql
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.1.0
     *
     */
    protected function installDbIndex($sql)
    {
        Db::getInstance()->execute($sql);
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
        $cutoff = Configuration::get(static::DATE_CUTOFF);
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
        $statuses = Configuration::get(static::VALID_ORDER_STATUSES);
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
        $statuses = Configuration::get(static::ORDER_STATUS_PAID);
        if ($statuses === false) {
            return array_column(Db::getInstance(_PS_USE_SQL_SLAVE_)->getArray(
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
        $statuses = Configuration::get(static::ORDER_STATUS_CANCELED);
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
        $statuses = Configuration::get(static::ORDER_STATUS_REFUNDED);
        if ($statuses === false) {
            return [(int) Configuration::get('PS_OS_REFUND')];
        } else {
            $statuses = unserialize($statuses);
        }

        return array_map('intval', $statuses);
    }

    /**
     * Get shRipped order statuses
     *
     * @return int[]
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getOrderShippedStatuses()
    {
        $statuses = Configuration::get(static::ORDER_STATUS_SHIPPED);
        if ($statuses === false) {
            return array_column(Db::getInstance(_PS_USE_SQL_SLAVE_)->getArray(
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
     * @return string
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected static function getMailChimpScript()
    {
        if (Configuration::get(static::DISABLE_POPUP)) {
            return '';
        }
        $idShop = (int) Context::getContext()->shop->id;
        $mcShop = MailChimpShop::getByShopId($idShop);
        if (!Validate::isLoadedObject($mcShop) || !$mcShop->list_id) {
            return '';
        }

        $mcScript = $mcShop->mc_script;
        if (!$mcScript) {
            $guzzle = static::getGuzzle();
            if (!$guzzle) {
                return '';
            }
            try {
                $response = json_decode((string) $guzzle->get("connected-sites/tbstore_{$idShop}")->getBody(), true);
            } catch (TransferException $e ) {
                return '';
            }
            if (!isset($response['site_script']['url'])) {
                return '';
            }
            $mcShop->mc_script = $response['site_script']['url'];
            $mcShop->save();
            try {
                $guzzle->post("connected-sites/tbstore_{$idShop}/actions/verify-script-installation");
            } catch (TransferException $e) {
            }

            $mcScript = $mcShop->mc_script;
        }

        return $mcScript;
    }

    /**
     * @param null|int $idShop
     *
     * @param null|int $idShopGroup
     *
     * @return string
     */
    protected function getConfigurationContext($idShop = null, $idShopGroup = null)
    {
        try {
            if (!Shop::isFeatureActive()) {
                return '';
            }

            if ($idShop) {
                $shop = new Shop($idShop);
                if (Validate::isLoadedObject($shop)) {
                    $idShop = $shop->name;
                }
            } elseif ($idShopGroup) {
                $shops = ShopGroup::getShopsFromGroup($idShopGroup);
                if (is_array($shops) && count($shops)) {
                    $idShop = (new Shop($shops[0]['id_shop']))->name;
                }
            }

            $this->context->smarty->assign([
                'context_shop' => $idShop,
            ]);

            return $this->display(__FILE__, 'views/templates/admin/context-badge.tpl');
        } catch (Exception $e) {
             return '';
        }
    }

    /**
     * Signal that the store is starting the sync
     *
     * @param int|int[] $idShops
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function signalSyncStart($idShops)
    {
        if (empty($idShops)) {
            return;
        }
        $guzzle = static::getGuzzle();
        if (!$guzzle) {
            return;
        }
        if (is_string($idShops) || is_int($idShops)) {
            $idShops = [(int) $idShops];
        } elseif (!is_array($idShops)) {
            return;
        }
        $idShops = array_map('intval', $idShops);

        $promises = call_user_func(function () use ($idShops, $guzzle) {
            foreach ($idShops as $idShop) {
                $guzzle->patchAsync("stores/tbstore_{$idShop}", ['json' => [
                    'is_syncing' => true,
                ]]);
            }
        });

        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
        ]))->promise()->wait();
    }

    /**
     * Signal that the store is stopping the sync
     *
     * @param int|int[] $idShops
     *
     * @return void
     *
     * @throws GuzzleException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function signalSyncStop($idShops)
    {
        if (empty($idShops)) {
            return;
        }
        $guzzle = static::getGuzzle();
        if (!$guzzle) {
            return;
        }
        if (is_string($idShops) || is_int($idShops)) {
            $idShops = [(int) $idShops];
        } elseif (!is_array($idShops)) {
            return;
        }
        $idShops = array_map('intval', $idShops);

        $promises = call_user_func(function () use ($idShops, $guzzle) {
            foreach ($idShops as $idShop) {
                $guzzle->patchAsync("stores/tbstore_{$idShop}", ['json' => [
                    'is_syncing' => false,
                ]]);
            }
        });

        (new EachPromise($promises, [
            'concurrency' => static::API_CONCURRENCY,
        ]))->promise()->wait();
    }

    /**
     * Check if the newsletter table exists and has the required columns
     *
     * @return bool
     * @throws PrestaShopException
     */
    public static function newsletterTableExists()
    {
        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT COUNT(*)
FROM information_schema.COLUMNS
WHERE
  TABLE_SCHEMA = \''._DB_NAME_.'\'
  AND TABLE_NAME = \''._DB_PREFIX_.'newsletter\'
  AND COLUMN_NAME IN(\'email\', \'id_shop\', \'newsletter_date_add\', \'ip_registration_newsletter\', \'active\')
') === 5;
    }

    /**
     * @return AdminController
     */
    protected function getAdminController()
    {
        $controller = $this->context->controller;
        if ($controller instanceof AdminController) {
            return $controller;
        }
        throw new RuntimeException('Invariant: not admin controller');
    }
}
