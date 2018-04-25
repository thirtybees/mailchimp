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

namespace MailChimpModule;

use Configuration;
use Context;
use Db;
use DbQuery;
use Logger;
use MailChimp;
use PrestaShopDatabaseException;
use PrestaShopException;
use Shop;

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

    /** @var string $email */
    private $email;
    /** @var string $subscription */
    private $subscription;
    /** @var string $fname */
    private $fname;
    /** @var string $lname */
    private $lname;
    /** @var string $ipSignup */
    private $ipSignup;
    /** @var string $language */
    private $language;         // example: "de"
    /** @var string $timestampSignup */
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
     * Get subscriber as array
     *
     * @return array
     * @throws PrestaShopException
     */
    public function getAsArray()
    {
        $subscriber = [
            'email_address'    => $this->email,
            'status'           => $this->getSubscriptionStatus($this->subscription),
            'status_if_new'    => $this->getSubscriptionStatus($this->subscription),
            'merge_fields'     => [
                'FNAME' => ($this->fname == '') ? '-' : $this->fname,
                'LNAME' => ($this->lname == '') ? '-' : $this->lname,
                'TBREF' => static::getTbRef($this->email),
            ],
            'language'         => $this->language,
            'timestamp_signup' => $this->timestampSignup,
        ];
        if (!Configuration::get(MailChimp::GDPR)) {
            $subscriber['ip_signup'] = (string) ($this->ipSignup ?: '');
        } else {

        }
        if (Configuration::get(MailChimp::EXPORT_COUNTRY) && !empty($subscriber['ip_signup'])) {
            $coords = MailChimp::getUserLatLongByIp($this->ipSignup);
            if ($coords) {
                $subscriber['location'] = [
                    'latitude'  => $coords['lat'],
                    'longitude' => $coords['long'],
                ];
            }
        }

        return $subscriber;
    }

    /**
     * Get subscriber as json
     *
     * @return string
     * @throws PrestaShopException
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
     * @param int|null $idShops
     * @param int      $offset
     * @param int      $limit
     * @param bool     $customers
     * @param bool     $optedIn
     * @param bool     $count
     *
     * @return array|int|false
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.0.0
     */
    public static function getSubscribers($idShops = null, $offset = 0, $limit = 0, $customers = true, $optedIn = false, $count = false)
    {
        if (is_int($idShops)) {
            $idShops = [$idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getContextListShopID(Shop::SHARE_CUSTOMER);
        }

        $list = [];
        $result = false;
        // Check if the module exists
        $existingMailQuery = new DbQuery();
        $existingMailQuery->select('`email`');
        $existingMailQuery->from('customer', 'c');
        $existingMailQuery->where('c.`newsletter` = 1');
        $existingMailQuery->where('c.`active` = 1');

        $nlQuery = new DbQuery();
        $nlQuery->select('n.`email`, \'\' AS `firstname`, \'\' AS `lastname`');
        $nlQuery->select('\'\' AS `ip_registration_newsletter`, l.`iso_code`, n.`id_shop`');
        $nlQuery->select('n.`newsletter_date_add`, \'\' AS `company`, \'\' AS `website`, \'\' AS `birthday`');
        $nlQuery->from('newsletter', 'n');
        $nlQuery->innerJoin('lang',  'l', 'l.`id_lang` = '.(int) \Configuration::get('PS_LANG_DEFAULT'));
        if ($optedIn) {
            $nlQuery->where('n.`active` = 1');
        }
        $nlQuery->where('n.`id_shop` IN ('.implode(',', array_map('intval', $idShops)).')');
        if ($customers) {
            $nlQuery->where('n.`email` NOT IN ('.$existingMailQuery->build().')');
        }
        $nlQuery->where('n.`email` != \'\'');
        $nlQuery->where('n.`email` IS NOT NULL');

        $customerQuery = new DbQuery();
        if ($count && !MailChimp::newsletterTableExists()) {
            $customerQuery->select('COUNT(*)');
        } else {
            $customerQuery->select('c.`email`, c.`firstname`, c.`lastname`, c.`ip_registration_newsletter`');
            $customerQuery->select('l.`iso_code`, c.`id_shop`, c.`newsletter_date_add`, c.`company`, c.`website`, c.`birthday`');
        }
        $customerQuery->from('customer', 'c');
        $customerQuery->innerJoin('lang', 'l', 'l.`id_lang` = c.`id_lang`');
        $customerQuery->where('c.`id_shop` IN ('.implode(',', array_map('intval', $idShops)).')');
        $customerQuery->where('c.`active` = 1');
        $customerQuery->where('c.`newsletter` = 1');
        $customerQuery->where('c.`email` != \'\'');
        $customerQuery->where('c.`email` IS NOT NULL');
        if ($optedIn) {
            $customerQuery->where('c.`newsletter` = 1');
        }

        // Check if the module exists
        if (MailChimp::newsletterTableExists()) {
            $sql = $count ? 'SELECT COUNT(*) FROM ' : 'SELECT * FROM ';
            $sql .= "({$nlQuery->build()} UNION ALL {$customerQuery->build()}) AS `x` ";
            if ($count) {
                return (int) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
            } else {
                $sql .= ' LIMIT '.(int) $offset;
                $sql .= ', '.(int) $limit;
            }
            try {
                $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
            } catch (PrestaShopException $e) {
                Logger::addLog("MailChimp module error: {$e->getMessage()}");

                $result = false;
            }
        } elseif ($customers) {
            try {
                if ($count) {
                    return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($customerQuery);
                }

                $customerQuery->limit($limit, $offset);
                $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($customerQuery);
            } catch (PrestaShopException $e) {
                Logger::addLog("MailChimp module error: {$e->getMessage()}");

                $result = false;
            }
        }

        if ($result) {
            // If confirmation mail is to be sent, statuses must be post as pending to the MailChimp API
            $subscription = (string) Configuration::get(MailChimp::CONFIRMATION_EMAIL)
                ? MailChimpSubscriber::SUBSCRIPTION_PENDING
                : MailChimpSubscriber::SUBSCRIPTION_SUBSCRIBED;
            // Get default shop language since Newsletter Block registrations don't contain any language info
            // Create and append subscribers
            foreach ($result as $row) {
                $list[] = [
                    'email'               => $row['email'],
                    'subscription'        => $subscription,
                    'firstname'           => $row['firstname'] ?: '',
                    'lastname'            => $row['lastname'] ?: '',
                    'ip_address'          => $row['ip_registration_newsletter'],
                    'language_code'       => MailChimp::getMailChimpLanguageByIso($row['iso_code'] ?: Context::getContext()->language->iso_code),
                    'newsletter_date_add' => $row['newsletter_date_add'],
                    'company'             => $row['company'] ?: '',
                    'website'             => $row['website'] ?: '',
                    'birthday'            => $row['birthday'] ?: '',
                    'tbref'               => static::getTbRef($row['email']),
                    'id_shop'             => $row['id_shop'],
                ];
            }
        }

        return $list;
    }

    /**
     * Get TBREF tag
     *
     * @param string $email
     *
     * @return string
     */
    public static function getTbRef($email)
    {
        return strtoupper(substr(md5($email), 0, 8));
    }
}
