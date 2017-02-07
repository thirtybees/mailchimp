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

namespace MailChimpModule;

if (!defined('_TB_VERSION_')) {
    exit;
}

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
     * @param $subscription
     * @param $fname
     * @param $lname
     * @param $ipSignup
     * @param $language
     * @param $timestampSignup
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

    public function getAsArray()
    {
        return [
            'email_address'    => $this->email,
            'status'           => $this->_getSubscriptionStatus($this->subscription),
            'status_if_new'    => $this->_getSubscriptionStatus($this->subscription),
            'merge_fields'     => [
                'FNAME' => ($this->fname == '') ? '-' : $this->fname,
                'LNAME' => ($this->lname == '') ? '-' : $this->lname,
            ],
            'ip_signup'        => ($this->ipSignup == '') ? '' : $this->ipSignup,
            'language'         => $this->language,
            'timestamp_signup' => $this->timestampSignup,
        ];
    }

    public function getAsJSON()
    {
        return json_encode($this->getAsArray());
    }

    private function _getSubscriptionStatus($subscription = null)
    {
        if (!$subscription) {
            $subscription = $this->subscription;
        }
        switch ($subscription) {
            case SUBSCRIPTION_SUBSCRIBED:
                return 'subscribed';
                break;
            case SUBSCRIPTION_UNSUBSCRIBED:
                return 'unsubscribed';
                break;
            case SUBSCRIPTION_PENDING:
                return 'pending';
                break;
            case SUBSCRIPTION_CLEANED:
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

}
