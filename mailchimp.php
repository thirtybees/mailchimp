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

use MailChimpModule\MailChimpRegisteredWebhook;
use MailChimpModule\MailChimpSubscriber;

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
    const KEY_API_KEY = 'MAILCHIMP_API_KEY';
    const KEY_IMPORT_LIST = 'MAILCHIMP_IMPORT_LIST';
    const KEY_CONFIRMATION_EMAIL = 'MAILCHIMP_CONFIRMATION_EMAIL';
    const KEY_UPDATE_EXISTING = 'MAILCHIMP_UPDATE_EXISTING';
    const KEY_IMPORT_ALL = 'MAILCHIMP_IMPORT_ALL';
    const KEY_IMPORT_OPTED_IN = 'MAILCHIMP_IMPORT_OPTED_IN';
    const KEY_LAST_IMPORT = 'MAILCHIMP_LAST_IMPORT';
    const KEY_LAST_IMPORT_ID = 'MAILCHIMP_LAST_IMPORT_ID';
    protected $html = '';
    protected $idShop;
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
        $this->version = '1.0.0';
        $this->author = 'thirty bees';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('MailChimp');
        $this->description = $this->l('Synchronize with MailChimp');

        // TODO: This can be asked (i.e. whether to import all shops)
        $this->idShop = (int) Context::getContext()->shop->id;
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
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->registerHook('footer') // to catch guest newsletter subscription
            || !$this->registerHook('actionCustomerAccountAdd') // front office account creation
            || !$this->registerHook('actionObjectCustomerAddAfter') // front office account creation
            || !$this->registerHook('actionObjectCustomerUpdateAfter') // front office account creation
            || !$this->registerHook('actionAdminCustomersControllerSaveAfter') // back office update customer
            || !MailChimpRegisteredWebhook::createDatabase()
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
            || !Configuration::deleteByName('KEY_API_KEY')
            || !Configuration::deleteByName('KEY_IMPORT_LIST')
            || !Configuration::deleteByName('KEY_CONFIRMATION_EMAIL')
            || !Configuration::deleteByName('KEY_UPDATE_EXISTING')
            || !Configuration::deleteByName('KEY_IMPORT_ALL')
            || !Configuration::deleteByName('KEY_IMPORT_OPTED_IN')
            || !Configuration::deleteByName('KEY_LAST_IMPORT')
            || !Configuration::deleteByName('KEY_LAST_IMPORT_ID')
            || !MailChimpRegisteredWebhook::dropDatabase()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Module configuration page
     *
     * @return string
     */
    public function getContent()
    {
        $this->postProcess();
        $this->displayForm();

        return $this->html;
    }

    /**
     * Process configuration
     */
    protected function postProcess()
    {
        if (Tools::isSubmit('submitApiKey')) {
            // Check if MailChimp API key is valid
            try {
                // TODO: Find a different way to validate API key rather than making a request
                $mailchimp = new \MailChimpModule\MailChimp\MailChimp(Tools::getValue('mailchimpApiKey'));
                $mailchimp->verifySsl = false;
                $getLists = $mailchimp->get('lists');
                $update = Configuration::updateValue('KEY_API_KEY', Tools::getValue('mailchimpApiKey'));
                if (!$getLists) {
                    $this->html .= $this->displayError($this->l('An error occurred. Please check your API key.'));
                } else {
                    if ($update) {
                        $this->html .= $this->displayConfirmation($this->l('You have successfully updated your MailChimp API key.'));
                    } else {
                        $this->html .= $this->displayError($this->l('An error occurred while saving API key.'));
                    }
                }
            } catch (Exception $e) {
                // Remove existing value
                Configuration::deleteByName('KEY_API_KEY');
                $this->html .= $this->displayError($e->getMessage());
            }
        } else {
            if (Tools::isSubmit('submitSettings')) {
                // Update all the configuration
                // And check if updates were successful
                if (Configuration::updateValue('KEY_IMPORT_LIST', Tools::getValue('importList'))
                    && Configuration::updateValue('KEY_CONFIRMATION_EMAIL', Tools::getValue('confirmationEmail'))
                    && Configuration::updateValue('KEY_UPDATE_EXISTING', Tools::getValue('updateExisting'))
                    && Configuration::updateValue('KEY_IMPORT_ALL', Tools::getValue('importAll'))
                    && Configuration::updateValue('KEY_IMPORT_OPTED_IN', Tools::getValue('importOptedIn'))
                ) {
                    $this->html .= $this->displayConfirmation($this->l('Settings updated.'));
                    // Create MailChimp side webhooks
                    // TODO: Check if the hooks have been defined before (over DB or API calls)
                    $register = $this->registerWebhookForList(Configuration::get('KEY_IMPORT_LIST'));
                    if (!$register) {
                        $this->html .= $this->displayError($this->l('MailChimp webhooks could not be implemented. Please try again.'));
                    }

                    // Check if asked for a manual import
                    if (Tools::isSubmit('manualImport_0') && (bool) Tools::getValue('manualImport_0')) {
                        // Get subscribers list from Prestashop
                        $all = (bool) Configuration::get('KEY_IMPORT_ALL');
                        $optIn = (bool) Configuration::get('KEY_IMPORT_OPTED_IN');
                        $list = $this->getFinalSubscribersList($all, $optIn);
                        // //
                        $import = $this->importList($list);
                        if ($import) {
                            // Inform the user
                            $this->html .= $this->displayConfirmation($this->l('Import started. Please note that it might take a while to complete process.'));
                            // Save the last import
                            Configuration::updateValue('KEY_LAST_IMPORT', time());
                        } else {
                            $this->html .= $this->displayError($this->mailChimp->getLastError());
                        }
                    }
                } else {
                    $this->html .= $this->displayError($this->l('Some of the settings could not be saved.'));
                }
            }
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
                // TODO: Save this to database
                if (!MailChimpRegisteredWebhook::saveWebhook($callbackUrl, $idList)) {
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
        // TODO: Make sure this is the right way
        // Take first active language and store ID
        $languages = Language::getLanguages(true);
        $idLang = $languages[key($languages)]['id_lang'];
        // //

        // TODO: Make sure this is the right way
        $shops = Shop::getShops(true);
        $idShop = $shops[key($shops)]['id_shop'];

        // //

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
        $this->mailChimp = new \MailChimpModule\MailChimp\MailChimp(Configuration::get('KEY_API_KEY'));
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
     * @param bool $all
     * @param bool $optedIn
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function getFinalSubscribersList($all = false, $optedIn = false)
    {
        // Get subscriptions made through Newsletter Block
        $list1 = $this->getNewsletterBlockSubscriptions($optedIn);
        // Get subscriptions made through either registration form or during guest checkout
        $list2 = $this->getCustomerSubscriptions($all, $optedIn);

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
            // TODO: Use helper methods to generate the query
            $sql = '
                SELECT pn.`email`, pn.`newsletter_date_add`, 
                pn.`ip_registration_newsletter`, pn.`active` 
                FROM `'._DB_PREFIX_.'newsletter` pn
                WHERE 1 
            ';
            // MARK: Loop through shop IDs if need be
            $sql .= 'AND pn.`id_shop` = '.$this->idShop.' ';
            if ($optedIn) {
                $sql .= 'AND pn.`active` = 1 ';
            }

            $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS($sql);

            if ($result) {
                // If confirmation mail is to be sent, statuses must be post as pending to the MailChimp API
                $subscription = (bool) Configuration::get('KEY_CONFIRMATION_EMAIL') ? MailChimpSubscriber::SUBSCRIPTION_PENDING : MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED;
                // Get default shop language since Newsletter Block registrations don't contain any language info
                $lang = $this->mailChimpLanguages[$this->context->language->iso_code];
                // Safety check
                if ($lang == '') {
                    Logger::addLog('MailChimp language code could not be found for language with ISO: '.$this->context->language->iso_code);
                    $lang = 'en';
                }
                // Create and append subscribers
                foreach ($result as $row) {
                    $list[] = new MailChimpSubscriber(
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
     * @param bool $all
     * @param bool $optedIn
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function getCustomerSubscriptions($all = false, $optedIn = false)
    {
        $list = [];
        // TODO: Use helper methods to generate the query
        $sql = '
            SELECT pc.`email`, pc.`firstname`, pc.`lastname`,
            pc.`ip_registration_newsletter`, pc.`newsletter_date_add`, pl.`iso_code`
            FROM `'._DB_PREFIX_.'customer` pc
            LEFT JOIN `'._DB_PREFIX_.'lang` pl ON pl.`id_lang` = pc.`id_lang`
            WHERE 1 
        ';
        // MARK: Loop through shop IDs if need be
        $sql .= 'AND pc.`id_shop` = '.$this->idShop.' ';

        if (!$all) {
            $sql .= 'AND pc.`newsletter` = 1 ';
            $sql .= 'AND pc.`deleted` = 0 ';
            // Opt-in selection is only valid if not all users have been asked
            if ($optedIn) {
                $sql .= 'AND pc.`optin` = 1 ';
            }
        }

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        if ($result) {
            // If confirmation mail is to be sent, statuses must be post as pending to the MailChimp API
            $subscription = (bool) Configuration::get('KEY_CONFIRMATION_EMAIL') ? MailChimpSubscriber::SUBSCRIPTION_PENDING : MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED;
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
                $list[] = new MailChimpSubscriber(
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
        $this->mailChimp = new \MailChimpModule\MailChimp\MailChimp(Configuration::get('KEY_API_KEY'));
        $this->mailChimp->verifySsl = false;

        $batch = $this->mailChimp->newBatch();

        // Append subscribers to batch operation request using PUT method (to enable update existing)
        for ($i = 0; $i < count($list); $i++) {
            $subscriber = $list[$i];
            $hash = $this->mailChimp->subscriberHash($subscriber->getEmail());
            $url = sprintf('lists/%s/members/%s', Configuration::get('KEY_IMPORT_LIST'), $hash);
            $batch->put('op'.($i + 1), $url, $subscriber->getAsArray());
        }

        // Execute the batch and check status
        $result = $batch->execute();

        if (!$result) {
            return false;
        } else {
            $batchId = $result['id'];
            Logger::addLog('MailChimp batch operation started with ID: '.$batchId);
            Configuration::updateValue('KEY_LAST_IMPORT_ID', $batchId);

            return true;
        }
    }

    /**
     * Display form
     */
    protected function displayForm()
    {
        $this->html .= $this->generateForm();
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    protected function generateForm()
    {
        $fields = [];

        $inputs1 = [];

        $inputs1[] = [
            'type'  => 'text',
            'label' => $this->l('API Key'),
            'name'  => 'mailchimpApiKey',
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

        // Show settings form only if API key is set and working
        $apiKey = Configuration::get('KEY_API_KEY');
        $validKey = false;
        $lists = [];
        if (isset($apiKey) && $apiKey != '') {
            // Check if API key is valid
            try {
                $mailchimp = new \MailChimpModule\MailChimp\MailChimp(Configuration::get('KEY_API_KEY'));
                $mailchimp->verifySsl = false;
                $getLists = $mailchimp->get('lists');
                if ($getLists) {
                    $lists = $getLists['lists'];
                    $validKey = true;
                }
            } catch (Exception $e) {
                $this->html .= $this->displayError($e->getMessage());
            }
        }

        if ($validKey) {
            $inputs2 = [];

            $inputs2[] = [
                'type'    => 'select',
                'label'   => $this->l('Import to List'),
                'name'    => 'importList',
                'desc'    => $this->l('Please select a MailChimp list to import subscriptions to.'),
                'options' => [
                    'query' => $lists,
                    'id'    => 'id',
                    'name'  => 'name',
                ],
            ];

            $inputs2[] = [
                'type'   => 'switch',
                'label'  => $this->l('Confirmation Email'),
                'name'   => 'confirmationEmail',
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
                'label'  => $this->l('Update if exists'),
                'name'   => 'updateExisting',
                'desc'   => $this->l('Do you wish to update the subscriber details if they already exist?'),
                'values' => [
                    [
                        'id'    => 'updateSwitch_on',
                        'value' => 1,
                        'label' => $this->l('Enabled'),
                    ],
                    [
                        'id'    => 'updateSwitch_off',
                        'value' => 0,
                        'label' => $this->l('Disabled'),
                    ],
                ],
            ];

            $inputs2[] = [
                'type'   => 'switch',
                'label'  => $this->l('Import All Customers'),
                'name'   => 'importAll',
                'desc'   => $this->l('Turn this on if you wish to import all of the users. This means that the module ignores the customer\'s subscription choice.'),
                'id'     => 'importAll',
                'values' => [
                    [
                        'id'    => 'importSwitch_on',
                        'value' => 1,
                        'label' => $this->l('Enabled'),
                    ],
                    [
                        'id'    => 'importSwitch_off',
                        'value' => 0,
                        'label' => $this->l('Disabled'),
                    ],
                ],
            ];

            $inputs2[] = [
                'type'   => 'switch',
                'label'  => $this->l('Opted-In Only'),
                'name'   => 'importOptedIn',
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

            $lastImport = Configuration::get('KEY_LAST_IMPORT');
            $lastImport = $lastImport == '' ? $this->l('No previous import has been found.') : date('Y-m-d H:i', $lastImport);
            $inputs2[] = [
                'type'   => 'checkbox',
                'label'  => $this->l('Manual Import'),
                'name'   => 'manualImport',
                'desc'   => $this->l('Check this if you want Prestashop to do a manual import after you hit the Save button. Last import: '.$lastImport),
                'values' => [
                    'query' => [
                        [
                            'id_option' => 0,
                            'name'      => $this->l('Import Now'),
                        ],
                    ],
                    'id'    => 'id_option',
                    'name'  => 'name',
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
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
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
            'mailchimpApiKey'   => Configuration::get('KEY_API_KEY'),
            'importList'        => Configuration::get('KEY_IMPORT_LIST'),
            'confirmationEmail' => Configuration::get('KEY_CONFIRMATION_EMAIL'),
            'updateExisting'    => Configuration::get('KEY_UPDATE_EXISTING'),
            'importAll'         => Configuration::get('KEY_IMPORT_ALL'),
            'importOptedIn'     => Configuration::get('KEY_IMPORT_OPTED_IN'),
        ];
    }

    /**
     *
     * @since 1.0.0
     */
    public function hookDisplayBackOfficeHeader()
    {
        if ($this->context->controller->controller_name) {
            if (Tools::isSubmit('module_name') && Tools::getValue('module_name') == 'mailchimp') {
                $this->context->controller->addJS($this->_path.'views/js/mailchimp.js');
            }
        }
    }

    /**
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
                    $_SERVER['REMOTE_ADDR'],
                    $this->getMailchimpLanguageByIso($iso),
                    date('Y-m-d H:i:s')
                );
                if (!$this->addOrUpdateSubscription($customer)) {
                    Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
                }
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
    public function getMailchimpLanguageByIso($iso)
    {
        $lang = $this->mailChimpLanguages[$iso];
        if ($lang == '') {
            $lang = 'en';
            Logger::addLog('MailChimp language code could not be found for language with ISO: '.$lang);
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
        if (isset($params['newCustomer']) && $params['newCustomer']->id > 0) {
            $subscription = (bool) $params['newCustomer']->newsletter ? MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED : MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
            $iso = Language::getIsoById($params['newCustomer']->id_lang);
            $customer = new MailChimpSubscriber(
                $params['newCustomer']->email,
                $subscription,
                $params['newCustomer']->firstname,
                $params['newCustomer']->lastname,
                $params['newCustomer']->ip_registration_newsletter,
                $this->getMailchimpLanguageByIso($iso),
                $params['newCustomer']->newsletter_date_add
            );
            if (!$this->addOrUpdateSubscription($customer)) {
                Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
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
        /** @var Customer $custmer */
        $customer = $params['object'];

        $subscription = (bool) $customer->newsletter ? MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED : MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
        $iso = LanguageCore::getIsoById($customer->id_lang);
        $customerMC = new MailChimpSubscriber(
            $customer->email,
            $subscription,
            $customer->firstname,
            $customer->lastname,
            Tools::getRemoteAddr(),
            $this->getMailchimpLanguageByIso($iso),
            date('Y-m-d H:i:s')
        );
        if (!$this->addOrUpdateSubscription($customerMC)) {
            Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
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
        $subscription = (bool) $customer->newsletter ? MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED : MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
        $iso = LanguageCore::getIsoById($customer->id_lang);
        $customerMC = new MailChimpSubscriber(
            $customer->email,
            $subscription,
            $customer->firstname,
            $customer->lastname,
            Tools::getRemoteAddr(),
            $this->getMailchimpLanguageByIso($iso),
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
            // TODO: Make sure this is bulletproof
            $object = $params['return'];
            $subscription = (bool) Tools::getValue('newsletter') ? MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED : MailChimpSubscriber::SUBSCRIPTION_UNSUBSCRIBED;
            $iso = Language::getIsoById($object->id_lang);
            $customer = new MailChimpSubscriber(
                $object->email,
                $subscription,
                $object->firstname,
                $object->lastname,
                $_SERVER['REMOTE_ADDR'],
                $this->getMailchimpLanguageByIso($iso),
                date('Y-m-d H:i:s')
            );
            if (!$this->addOrUpdateSubscription($customer)) {
                Logger::addLog('MailChimp customer subscription failed: '.$this->mailChimp->getLastError());
            }
        }
    }
}
