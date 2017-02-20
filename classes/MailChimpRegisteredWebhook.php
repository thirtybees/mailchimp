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

require_once __DIR__.'/autoload.php';

/**
 * Class StripeTransaction
 */
class MailChimpRegisteredWebhook extends \ObjectModel
{
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'   => 'mailchimp_webhook',
        'primary' => 'id_mailchimp_webhook',
        'fields'  => [
            'url'       => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'default' => '', 'db_type' => 'VARCHAR(1024)'],
            'id_list'   => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'default' => '', 'db_type' => 'VARCHAR(32)'],
            'date_recv' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true, 'default' => '1970-01-01 00:00:00', 'db_type' => 'DATETIME'],
        ],
    ];
    // @codingStandardsIgnoreStart
    /** @var string $url */
    public $url;
    /** @var string $id_list */
    public $id_list;
    /** @var string $date_recv */
    public $date_recv;
    // @codingStandardsIgnoreEnd

    /**
     * Save webhook URL to database
     *
     * @param string $url
     * @param string $idList
     *
     * @return bool
     */
    public static function saveWebhook($url, $idList)
    {
        return \Db::getInstance()->insert(
            bqSQL(self::$definition['table']),
            [
                'url'       => $url,
                'id_list'   => $idList,
                'date_recv' => date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Get webhook by callback URL
     *
     * @param string      $url
     * @param string|null $idList
     *
     * @return bool|MailChimpRegisteredWebhook
     */
    public static function getByCallbackUrl($url, $idList = null)
    {
        $sql = new \DbQuery();
        $sql->select('mwh.`'.bqSQL(self::$definition['primary']).'`, mwh.`url`, mwh.`date_recv`, mwh.`id_list`');
        $sql->from(bqSQL(self::$definition['table']));
        $sql->where('mwh.`url` = \''.pSQL($url).'\'');
        if ($idList) {
            $sql->where('mwh.`id_list` = \''.pSQL($idList).'\'');
        }

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);

        if ($result) {
            $webhook = new self();
            $webhook->hydrate($result);

            return $webhook;
        }

        return false;
    }

    /**
     * Refresh webhook list
     *
     * @param array  $urls
     * @param string $idList
     *
     * @return bool Indicates whether the list was successfully refreshed
     */
    public static function refresh($urls, $idList)
    {
        // List empty, nothing to refresh
        if (!$urls) {
            return true;
        }

        foreach ($urls as &$url) {
            $url = pSQL($url);
        }

        if (!\Db::getInstance()->delete(
            bqSQL(self::$definition['table']),
            'url NOT IN ('.implode(',', $urls).') AND `id_list` = \''.pSQL($idList).'\''
        )
        ) {
            return false;
        }

        foreach (self::getWebhooks() as $item) {
            if (!in_array($item['url'], $urls)) {
                $webhook = new self();
                $webhook->url = $item['url'];
                $webhook->id_list = $idList;

                $webhook->add();
            }
        }

        return true;
    }

    /**
     * Get all webhooks
     *
     * @param string|null $idList
     *
     * @return array|false|\mysqli_result|null|\PDOStatement|resource
     */
    public static function getWebhooks($idList = null)
    {
        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from(bqSQL(self::$definition['table']));
        if ($idList) {
            $sql->where('`id_list` = \''.pSQL($idList).'\'');
        }

        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }
}
