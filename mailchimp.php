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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__).'/lib/autoload.php';

class MailChimp extends Module
{
    const MIN_PHP_VERSION = 50303;

    const SETTINGS = 'MAILCHIMP_SETTINGS';
    const FORM = 'MAILCHIMP_FORM';

    const MENU_SETTINGS = 1;
    const MENU_SUBSCRIBERS = 2;

    protected $apiKey;
    protected $ssl;
    protected $module_path;
    protected $admin_tpl_path;
    protected $front_tpl_path;
    protected $hooks_tpl_path;

    /** @var string $moduleUrl */
    public $moduleUrl;

    /** @var array $message */
    public $message = array();

    public $hooks = array(
        'top',
        'leftColumn',
        'rightColumn',
        'footer',
        'home',
        'leftColumnProduct',
        'rightColumnProduct',
        'footerProduct',
    );

    public static $mailChimpLanguages = array(
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
    );

    /**
     * MailChimp constructor.
     */
    public function __construct()
    {
        $this->name = 'mailchimp';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'Thirty Bees';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.6');

        parent::__construct();

        $this->displayName = $this->l('MailChimp');
        $this->description = $this->l('Synchronize with MailChimp');

        // Paths
        $this->module_path = _PS_MODULE_DIR_.$this->name.'/';
        $this->admin_tpl_path = _PS_MODULE_DIR_.$this->name.'/views/templates/admin/';
        $this->front_tpl_path = _PS_MODULE_DIR_.$this->name.'/views/templates/front/';
        $this->hooks_tpl_path = _PS_MODULE_DIR_.$this->name.'/views/templates/hooks/';

        $this->message = array(
            'text' => false,
            'type' => 'conf',
        );

        // Only check from Back Office
        if (isset(Context::getContext()->employee->id) && Context::getContext()->employee->id) {
            if ($this->active && extension_loaded('curl') == false) {
                $this->context->controller->errors[] = $this->displayName.': '.$this->l('You have to enable the cURL extension on your server in order to use this module');
                $this->disable();

                return;
            }
            if (PHP_VERSION_ID < self::MIN_PHP_VERSION) {
                $this->context->controller->errors[] = $this->displayName.': '.$this->l('Your PHP version is not supported. Please upgrade to PHP 5.3.3 or higher.');
                $this->disable();

                return;
            }
            $this->moduleUrl = Context::getContext()->link->getAdminLink('AdminModules', true).'&'.http_build_query(
                array(
                    'configure' => $this->name,
                    'tab_module' => $this->tab,
                    'module_name' => $this->name,
                )
            );
        }
    }

    /**
     * Install this module
     *
     * @return bool Indicates whether this module has been successfully installed
     */
    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('displayHome') ||
            !$this->registerHook('displayHeader') ||
            !$this->registerHook('displayBackOfficeHeader') ||
            !$this->registerHook('displayAdminHomeQuickLinks') ||
            !Configuration::updateValue(strtoupper($this->name).'_START', 1)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Uninstall this module
     *
     * @return bool Indicates whether this module has been successfully uninstalled
     */
    public function uninstall()
    {
        if (!parent::uninstall() ||
            !Configuration::deleteByName(strtoupper($this->name).'_START') ||
            !Configuration::deleteByName(self::SETTINGS) ||
            !Configuration::deleteByName(self::FORM)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Module configuration page
     */
    public function getContent()
    {
        $languages = Language::getLanguages(false);

        // FIXME: form display
        // smarty for admin
        $smartyArray = array(
            'first_start' => Configuration::get(strtoupper($this->name).'_START'),

            'admin_tpl_path' => $this->admin_tpl_path,
            'front_tpl_path' => $this->front_tpl_path,
            'hooks_tpl_path' => $this->hooks_tpl_path,

            'info' => array(
                'module' => $this->name,
                'name' => Configuration::get('PS_SHOP_NAME'),
                'domain' => Configuration::get('PS_SHOP_DOMAIN'),
                'email' => Configuration::get('PS_SHOP_EMAIL'),
                'version' => $this->version,
                'psVersion' => _PS_VERSION_,
                'server' => $_SERVER['SERVER_SOFTWARE'],
                'php' => phpversion(),
                'mysql' => Db::getInstance()->getVersion(),
                'theme' => _THEME_NAME_,
                'userInfo' => $_SERVER['HTTP_USER_AGENT'],
                'today' => date('Y-m-d'),
                'context' => (Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') == 0) ? 1 : ($this->context->shop->getTotalShops() != 1) ? $this->context->shop->getContext() : 1,
            ),
            'form_action' => 'index.php?tab=AdminModules&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&tab_module='.$this->tab.'&module_name='.$this->name,
            'hooks' => $this->hooks,
            'languages' => $languages,
            'default_lang' => $this->context->language->id,
            'flags' => array(
                'title' => $this->displayFlags($languages, $this->context->language->id, 'title¤form', 'title', true),
                'form' => $this->displayFlags($languages, $this->context->language->id, 'title¤form', 'form', true),
            ),
        );

        // Handling settings
        if (Tools::isSubmit('submitMailchimp')) {
            $this->configureMailchimp();
        }

        // Mailchimp lists
        $settings = unserialize(Configuration::get(self::SETTINGS));
        if (!$settings) {
            $this->message['text'] = $this->l('Before you start using this module you need to configure it below!');
        } else {
            $this->apiKey = $settings['apikey'];
            $this->ssl = $settings['ssl'];

            $this->context->smarty->assign(array(
                'mailchimp_list' => $this->getMailchimpLists(),
            ));
        }

        // Handling Import
        if (Tools::isSubmit('submitImport')) {
            $this->importCustomers();
        }
        // Handling Form
        if (Tools::isSubmit('submitForm')) {
            $this->saveSubscriberForm();
        }

        // Smarty for admin
        $smartyArray['mailchimp'] = $settings;
        $smartyArray['message'] = ($this->message['text']) ? $this->message : false;
        $smartyArray['form'] = unserialize(Configuration::get(self::FORM));
        $this->smarty->assign('minic', $smartyArray);

        // Change first start
        if (Configuration::get(strtoupper($this->name).'_START') == 1) {
            Configuration::updateValue(strtoupper($this->name).'_START', 0);
        }

        return $this->display(__FILE__, 'views/templates/admin/import.tpl');
    }

    /**
     * Initialize navigation
     *
     * @return array Menu items
     */
    protected function initNavigation()
    {
        $menu = array(
            self::MENU_SETTINGS => array(
                'short' => $this->l('Settings'),
                'desc' => $this->l('Module settings'),
                'href' => $this->moduleUrl.'&menu='.self::MENU_SETTINGS,
                'active' => false,
                'icon' => 'icon-gears',
            ),
            self::MENU_SUBSCRIBERS => array(
                'short' => $this->l('Transactions'),
                'desc' => $this->l('Stripe transactions'),
                'href' => $this->moduleUrl.'&menu='.self::MENU_SUBSCRIBERS,
                'active' => false,
                'icon' => 'icon-credit-card',
            ),
        );

        switch (Tools::getValue('menu')) {
            case self::MENU_SUBSCRIBERS:
                $this->menu = self::MENU_SUBSCRIBERS;
                $menu[self::MENU_SUBSCRIBERS]['active'] = true;
                break;
            default:
                $this->menu = self::MENU_SETTINGS;
                $menu[self::MENU_SETTINGS]['active'] = true;
                break;
        }

        return $menu;
    }

    /**
     * Save the subscriber form details
     */
    public function saveSubscriberForm()
    {
        $defLang = $this->context->language->id;
        // Get saved form data
        $formSettings = unserialize(Configuration::get(self::FORM));
        // Prepear hooks array
        $hooks = array(
            'old' => ($formSettings) ? $formSettings['hooks'] : false,
            'new' => (Tools::isSubmit('hooks')) ? Tools::getValue('hooks') : false,
        );
        // Get form
        $form = (Tools::isSubmit('form_'.$defLang)) ? Tools::getValue('form_'.$defLang) : false;

        if (!$hooks['new']) {
            $this->message = array('text' => $this->l('At least a hook is required to show the form.'), 'type' => 'error');

            return;
        }
        if (!$form) {
            $this->message = array('text' => $this->l('The form code is required!'), 'type' => 'error');

            return;
        }

        // Unhook from all possible hooks
        foreach ($this->hooks as $hook) {
            if ($this->isRegisteredInHook('display'.$hook)) {
                $this->unregisterHook('display'.$hook);
            }
        }

        // Hook
        if ($hooks['new']) { // pointless ?
            foreach ($hooks['new'] as $hook) {
                $this->registerHook('display'.$hook);
            }
        }

        // Save
        $data = array();
        $languages = Language::getLanguages(false);
        foreach ($languages as $key => $lang) {
            $title = (Tools::isSubmit('title_'.$lang['id_lang'])) ? Tools::getValue('title_'.$lang['id_lang']) : false;

            $data[$lang['id_lang']] = array(
                'title' => ($title) ? $title : Tools::getValue('title_'.$defLang),
                'form' => ($form) ? htmlspecialchars($form) : htmlspecialchars(Tools::getValue('form_'.$defLang)),
            );
        }

        if (Configuration::updateValue(self::FORM, serialize(array('data' => $data, 'hooks' => $hooks['new'])))) {
            $this->message['text'] = $this->l('Saved!');
        }

    }

    /**
     * Import customers into the selected Mailchimp list
     */
    public function importCustomers()
    {
        // Get List id
        $listId = Tools::getValue('list');
        if (!Tools::isSubmit('list') || !$listId) {
            $this->message = array('text' => $this->l('List is required! Please select one.'), 'type' => 'error');

            return;
        }

        // Get Customers list
        $sql = new DbQuery();
        $sql->select('c.`firstname`, c.`lastname`, c.`email`, c.`id_lang`');
        $sql->from('customer', 'c');
        $sql->where('c.`newsletter` = 1');
        $customers = Db::getInstance(_PS_USE_SQL_SLAVE)->executeS($sql);

        // listBatchSubscribe configuration
//        $optin = (Tools::getValue('optin')) ? true : false; //send optin emails
//        $upExist = (Tools::getValue('update_users')) ? true : false; //update currently subscribed users
//        $replaceInt = true;

        // Get languages
        $languages = array();
        foreach (Language::getLanguages(false) as $language) {
            $languages[$language['id_lang']] = $language['iso_code'];
        }

        // Import customers
        $mailChimp = new \ThirtyBees\MailChimp\MailChimp($this->apiKey);
        /** @var ThirtyBees\MailChimp\Batch $batch */
        $batch = $mailChimp->newBatch();
        $batchId = 0;
        foreach ($customers as $customer) {
            $batch->post((string) $batchId, "lists/{$listId}/members", array(
                'email_address' => $customer['email'],
                'status' => 'subscribed',
                'merge_fields' => array('FNAME' => $customer['firstname'], 'LNAME' => $customer['lastname']),
                'language' => $this->getMailChimpLanguage(isset($languages['id_lang']) ? $languages['id_lang'] : 'en'),
            ));
            $batchId++;
        }
        $result = $batch->execute();

        // Process response
        if (!$result) {
            $this->message = array('text' => $this->l('Mailchimp error code:').' '.$mailChimp->getLastError());

            return;
        } else {
            // FIXME: Error handling is broken
            $this->message['text'] = $this->l('Successfullt imported:').' <b>'.$result['add_count'].'</b><br />';
            $this->message['text'] .= $this->l('Successfullt updated:').' <b>'.$result['update_count'].'</b><br />';
            if ($result['error_count'] > 0) {
                $this->message['text'] .= $this->l('Error occured:').' <b>'.$result['error_count'].'</b><br />';
                foreach ($result['errors'] as $error) {
                    $this->message['text'] .= '<p style="margin-left: 15px;">';
                    $this->message['text'] .= $error['email'].' - '.$error['code'].' - '.$error['message'];
                    $this->message['text'] .= '</p>';
                }
                $this->message['type'] = 'warn';
            }
        }

    }

    /**
     * Get all mailchimp list and the fields belongs to them
     */
    public function getMailchimpLists()
    {
        $mailchimp = new \ThirtyBees\MailChimp\MailChimp($this->apiKey);

        // Get Mailchimp lists
        $listResponse = $mailchimp->get('lists');

        if (!$listResponse) {
            $this->addError = array('text' => $this->l('Mailchimp error:').' '.$mailchimp->getLastError(), 'type' => 'error');

            return false;
        } else {
            return $listResponse['lists'];
        }
    }

    /**
     * Save Mailchimp settings into PS Configuration (api key and ssl)
     */
    public function configureMailchimp()
    {
        $settings = array();
        // Get apikey
        if (!Tools::isSubmit('apikey') || !Tools::getValue('apikey')) {
            $this->message = array('text' => $this->l('API Key is empty!'), 'type' => 'error');

            return;
        }
        // Get ssl
        if (!Tools::getValue('ssl') && Tools::getValue('ssl') != 0) {
            $this->message = array('text' => $this->l('SSL save failed!'), 'type' => 'error');

            return;
        }

        $settings = array(
            'apikey' => Tools::getValue('apikey'),
            'ssl' => ((int) Tools::getValue('ssl') == 1) ? true : false,
        );

        Configuration::updateValue(self::SETTINGS, serialize($settings));

        $this->message['text'] = $this->l('Saved!');
    }

    /**
     * Hook for back office dashboard
     */
    public function hookDisplayAdminHomeQuickLinks()
    {
        $this->context->smarty->assign('mailchimp', $this->name);

        return $this->display(__FILE__, 'views/templates/hooks/quick_links.tpl');
    }

    // FRONT OFFICE HOOKS

    /**
     * <head> Hook
     */
    public function hookDisplayHeader()
    {
        // CSS
        $this->context->controller->addCSS($this->_path.'views/css/'.$this->name.'.css');
        // JS
        $this->context->controller->addJS($this->_path.'views/js/'.$this->name.'.js');
    }

    /**
     * Top of pages hook
     */
    public function hookDisplayTop($params)
    {
        return $this->hookDisplayHome($params, 'top');
    }

    /**
     * Home page hook
     */
    public function hookDisplayHome($params, $class = false)
    {
        $data = unserialize(Configuration::get(self::FORM));
        if (!$data) {
            return;
        }

        $smarty['class'] = ($class) ? $class : 'home';
        $smarty['title'] = $data['data'][$this->context->language->id]['title'];
        $smarty['form'] = $data['data'][$this->context->language->id]['form'];

        $this->smarty->assign(self::FORM, $smarty);

        return $this->display(__FILE__, 'views/templates/hooks/home.tpl');
    }

    /**
     * Left Column Hook
     */
    public function hookDisplayRightColumn($params)
    {
        return $this->hookDisplayHome($params, 'right');
    }

    /**
     * Right Column Hook
     */
    public function hookDisplayLeftColumn($params)
    {
        return $this->hookDisplayHome($params, 'left');
    }

    /**
     * Footer hook
     */
    public function hookDisplayFooter($params)
    {
        return $this->hookDisplayHome($params, 'footer');
    }

    /**
     * Product page hook
     */
    public function hookDisplayLeftColumnProduct($params)
    {
        return $this->hookDisplayHome($params, 'left-product');
    }

    /**
     * Product page hook
     */
    public function hookDisplayRightColumProduct($params)
    {
        return $this->hookDisplayHome($params, 'right-product');
    }

    /**
     * Product page hook
     */
    public function hookDisplayFooterProduct($params)
    {
        return $this->hookDisplayHome($params, 'footer-product');
    }

    protected function getMailChimpLanguage($isoCode)
    {
        if (array_key_exists($isoCode, self::$mailChimpLanguages)) {
            return self::$mailChimpLanguages[$isoCode];
        }

        return 'en';
    }
}
