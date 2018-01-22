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
 * @copyright 2017-2018 thirty bees
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace MailChimpModule;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class MailChimpSubscriber
 *
 * @package MailChimpModule
 *
 * @since 1.0.0
 */
class MailChimpSubscriber
{
    const SUBSCRIPTION_SUBSCRIBED = 'subscribed';
    const SUBSCRIPTION_UNSUBSCRIBED = 'unsubscribed';
    const SUBSCRIPTION_PENDING = 'pending';
    const SUBSCRIPTION_CLEANED = 'cleaned';

    private $email;
    private $subscription;
    private $fname;
    private $lname;
    private $ipSignup;
    private $language; // example: "de"
    private $timestampSignup;  // example: "2013-01-18T16:48:09+00:00"

    /**
     * MailChimpSubscriber constructor.
     * @param string $email
     * @param string $subscription
     * @param string $fname
     * @param string $lname
     * @param string $ipSignup
     * @param string $language
     * @param string $timestampSignup
     */
    public function __construct(
        $email,
        $subscription,
        $fname = '',
        $lname = '',
        $ipSignup = '',
        $language,
        $timestampSignup
    ) {
        $this->email = $email;
        $this->subscription = $subscription;
        $this->fname = $fname;
        $this->lname = $lname;
        $this->ipSignup = $ipSignup;
        $this->language = $language;
        $this->timestampSignup = $timestampSignup;
    }

    /**
     * @return array
     */
    public function getAsArray()
    {
        return [
            'email_address'    => $this->email,
            'status'           => $this->getSubscriptionStatus($this->subscription),
            'status_if_new'    => $this->getSubscriptionStatus($this->subscription),
            'merge_fields'     => [
                'FNAME' => ($this->fname == '') ? '-' : $this->fname,
                'LNAME' => ($this->lname == '') ? '-' : $this->lname,
            ],
            'ip_signup'        => ($this->ipSignup == '') ? '' : $this->ipSignup,
            'language'         => $this->language,
            'timestamp_signup' => $this->timestampSignup,
        ];
    }

    /**
     * @return string
     */
    public function getAsJSON()
    {
        return json_encode($this->getAsArray());
    }

    /**
     * @param string|null $subscription
     *
     * @return string
     */
    protected function getSubscriptionStatus($subscription = null)
    {
        if (!$subscription) {
            $subscription = $this->subscription;
        }
        switch ($subscription) {
            case self::SUBSCRIPTION_SUBSCRIBED:
                return 'subscribed';
                break;
            case self::SUBSCRIPTION_UNSUBSCRIBED:
                return 'unsubscribed';
                break;
            case self::SUBSCRIPTION_PENDING:
                return 'pending';
                break;
            case self::SUBSCRIPTION_CLEANED:
                return 'cleaned';
                break;
            default:
                return 'pending';
                break;
        }
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @return mixed
     */
    public function getSubscription()
    {
        return $this->subscription;
    }

    /**
     * @param mixed $subscription
     */
    public function setSubscription($subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * @return mixed
     */
    public function getFname()
    {
        return $this->fname;
    }

    /**
     * @param mixed $fname
     */
    public function setFname($fname)
    {
        $this->fname = $fname;
    }

    /**
     * @return mixed
     */
    public function getLname()
    {
        return $this->lname;
    }

    /**
     * @param mixed $lname
     */
    public function setLname($lname)
    {
        $this->lname = $lname;
    }

    /**
     * @return mixed
     */
    public function getIpSignup()
    {
        return $this->ipSignup;
    }

    /**
     * @param mixed $ipSignup
     */
    public function setIpSignup($ipSignup)
    {
        $this->ipSignup = $ipSignup;
    }

    /**
     * @return mixed
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @param mixed $language
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }

    /**
     * @return mixed
     */
    public function getTimestampSignup()
    {
        return $this->timestampSignup;
    }

    /**
     * @param mixed $timestampSignup
     */
    public function setTimestampSignup($timestampSignup)
    {
        $this->timestampSignup = $timestampSignup;
    }

    /**
     * Count products
     *
     * @param int|null $idShop Shop ID
     * @param bool     $customers
     * @param bool     $optedIn
     *
     * @return int
     *
     * @since 1.1.0
     * @throws \PrestaShopException
     */
    public static function countSubscribers($idShop = null, $customers = true, $optedIn = false)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        // Check if the module exists
        if (\Module::isEnabled('blocknewsletter')) {
            $sql = new \DbQuery();
            $sql->select('count(*)');
            $sql->from('newsletter', 'n');
            if ($customers) {
                $sql->innerJoin('customer', 'c', 'c.`email` = n.`email`');
                $sql->innerJoin('lang', 'l', 'l.`id_lang` = c.`id_lang`');
            }
            $sql->where('n.`id_shop` = '.(int) $idShop.($customers ? ' OR c.`id_shop` = 1' : ''));
            if ($optedIn) {
                if ($customers) {
                    $sql->where('n.`active` = 1 OR c.`newsletter` = 1');
                } else {
                    $sql->where('n.`active` = 1');
                }
            }

            try {
                return (int) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
            } catch (\PrestaShopException $e) {
                return 0;
            }
        } elseif ($customers) {
            $sql = new \DbQuery();
            $sql->select('count(*)');
            $sql->from('customer', 'c');
            $sql->innerJoin('lang', 'l', 'l.`id_lang` = c.`id_lang`');
            $sql->where('c.`id_shop` = 1');
            if ($optedIn) {
                $sql->where('c.`newsletter` = 1');
            }

            try {
                return (int) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
            } catch (\PrestaShopException $e) {
                return 0;
            }
        }

        return 0;
    }

    /**
     * @param int|null $idShop
     * @param int      $offset
     * @param int      $limit
     * @param bool     $customers
     * @param bool     $optedIn
     *
     * @return array|false
     *
     * @since 1.0.0
     * @throws \PrestaShopException
     */
    public static function getSubscribers($idShop = null, $offset = 0, $limit = 0, $customers = true, $optedIn = false)
    {
        if (!$idShop) {
            $idShop = (int) \Context::getContext()->shop->id;
        }

        $list = [];
        $result = false;
        // Check if the module exists
        if (\Module::isEnabled('blocknewsletter')) {
            $sql = new \DbQuery();
            if ($customers) {
                $sql->select('c.`email`, c.`firstname`, c.`lastname`, c.`birthday`, c.`company`, c.`website`');
                $sql->select('c.`ip_registration_newsletter`, c.`newsletter_date_add`');
                $sql->select('l.`iso_code`, l.`language_code`');
            } else {
                $sql->select('n.`email`, n.`newsletter_date_add`, n.`ip_registration_newsletter`, n.`active`');
            }
            $sql->from('newsletter', 'n');
            if ($customers) {
                $sql->innerJoin('customer', 'c', 'c.`email` = n.`email`');
                $sql->innerJoin('lang', 'l', 'l.`id_lang` = c.`id_lang`');
            }
            $sql->where('n.`id_shop` = '.(int) $idShop.($customers ? '  OR c.`id_shop` = 1' : ''));
            if ($optedIn) {
                if ($customers) {
                    $sql->where('n.`active` = 1 OR c.`newsletter` = 1');
                } else {
                    $sql->where('n.`active` = 1');
                }
            }
            if ($limit) {
                $sql->limit($limit, $offset);
            }
            try {
                $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
            } catch (\PrestaShopException $e) {
                return false;
            }
        } elseif ($customers) {
            $sql = new \DbQuery();
            $sql->select('c.`email`, c.`firstname`, c.`lastname`, c.`birthday`, c.`company`, c.`website`, c.`ip_registration_newsletter`, c.`newsletter_date_add`, l.`iso_code`');
            $sql->from('customer', 'c');
            $sql->innerJoin('lang', 'l', 'l.`id_lang` = c.`id_lang`');
            $sql->where('c.`id_shop` = 1');
            if ($optedIn) {
                $sql->where('c.`newsletter` = 1');
            }
            if ($limit) {
                $sql->limit($limit, $offset);
            }
            try {
                $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
            } catch (\PrestaShopException $e) {
                return false;
            }
        }

        if ($result) {
            // If confirmation mail is to be sent, statuses must be post as pending to the MailChimp API
            $subscription = (string) \Configuration::get(\MailChimp::CONFIRMATION_EMAIL) ? MailChimpSubscriber::SUBSCRIPTION_PENDING : MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED;
            // Get default shop language since Newsletter Block registrations don't contain any language info
            $lang = \MailChimp::getMailChimpLanguageByIso(\Context::getContext()->language->iso_code);
            // Create and append subscribers
            foreach ($result as $row) {
                $list[] = [
                    'email'               => $row['email'],
                    'subscription'        => $subscription,
                    'firstname'           => isset($row['firstname']) ? $row['firstname'] : '',
                    'lastname'            => isset($row['lastname']) ? $row['lastname'] : '',
                    'ip_address'          => $row['ip_registration_newsletter'],
                    'language_code'       => $lang,
                    'newsletter_date_add' => $row['newsletter_date_add'],
                    'company'             => isset($row['company']) ? $row['company'] : '',
                    'website'             => isset($row['website']) ? $row['website'] : '',
                    'birthday'            => isset($row['birthday']) ? $row['birthday'] : '',
                ];
            }
        }

        return $list;
    }
}
