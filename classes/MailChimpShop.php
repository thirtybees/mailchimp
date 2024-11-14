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

namespace MailChimpModule;

use Context;
use Db;
use DbQuery;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise\EachPromise;
use mysqli_result;
use ObjectModel;
use PDOStatement;
use PrestaShopDatabaseException;
use PrestaShopException;
use Shop;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class MailChimpShop
 *
 * @package MailChimpModule
 *
 * @since 1.1.0
 */
class MailChimpShop extends ObjectModel
{
    /**
     * @see ObjectModel::$definition
     *
     * @since 1.1.0
     */
    public static $definition = [
        'table'   => 'mailchimp_shop',
        'primary' => 'id_mailchimp_shop',
        'fields'  => [
            'id_shop'   => ['type' => self::TYPE_INT,    'validate' => 'isInt',    'required' => true,                   'db_type' => 'INT(11) UNSIGNED'   ],
            'list_id'   => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true,                   'db_type' => 'VARCHAR(32)'        ],
            'id_tax'    => ['type' => self::TYPE_INT,    'validate' => 'isInt',    'required' => true,                   'db_type' => 'INT(11) UNSIGNED'   ],
            'synced'    => ['type' => self::TYPE_BOOL,   'validate' => 'isBool',   'required' => true, 'default' => '0', 'db_type' => 'TINYINT(1) UNSIGNED'],
            'mc_script' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => false,                  'db_type' => 'VARCHAR(255)'       ],
            'date_add'  => ['type' => self::TYPE_STRING, 'validate' => 'isDate',                                         'db_type' => 'DATETIME'           ],
            'date_upd'  => ['type' => self::TYPE_STRING, 'validate' => 'isDate',                                         'db_type' => 'DATETIME'           ],
        ],
    ];
    // @codingStandardsIgnoreStart
    /** @var int $id_shop */
    public $id_shop;
    /** @var string $list_id */
    public $list_id;
    /** @var int $id_tax */
    public $id_tax;
    /** @var bool $synced */
    public $synced;
    /** @var string $mc_script */
    public $mc_script;
    /** @var string $date_add */
    public $date_add;
    /** @var string $date_upd */
    public $date_upd;
    // @codingStandardsIgnoreEnd

    /**
     * Get shops
     *
     * @param bool $active
     *
     * @return array
     *
     * @throws PrestaShopException
     * @since 1.1.0
     */
    public static function getShops($active = false)
    {
        $sql = new DbQuery();
        $sql->select('s.`'.bqSQL(Shop::$definition['primary']).'`, s.`name`, ms.`list_id`, ms.`id_tax`, ms.`synced`');
        $sql->from('shop', 's');
        $sql->leftJoin(bqSQL(self::$definition['table']), 'ms', 's.`'.bqSQL(Shop::$definition['primary']).'` = ms.`'.bqSQL(Shop::$definition['primary']).'`');
        if ($active) {
            $sql->where('s.`active` = 1');
        }
        if (!Shop::isFeatureActive()) {
            $sql->where('s.`id_shop` = '.(int) Context::getContext()->shop->id);
        }

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getArray($sql);
    }

    /**
     * Get MailChimpShop by Shop ID
     *
     * @param int $idShop
     *
     * @return MailChimpShop
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getByShopId($idShop)
    {
        $sql = new DbQuery();
        $sql->select('s.`'.bqSQL(Shop::$definition['primary']).'`, ms.*');
        $sql->from('shop', 's');
        $sql->leftJoin(bqSQL(static::$definition['table']), 'ms', 's.`'.bqSQL(Shop::$definition['primary']).'` = ms.`'.bqSQL(Shop::$definition['primary']).'`');
        $sql->where('s.`id_shop` = '.(int) $idShop);

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
        $mcs = new static();
        if (is_array($result)) {
            $mcs->hydrate($result);
        }

        return $mcs;
    }

    /**
     * Get MailChimpShop by Shop ID
     *
     * @param int[] $idShops
     *
     * @return MailChimpShop[]
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getByShopIds($idShops)
    {
        $sql = new DbQuery();
        $sql->select('s.`'.bqSQL(Shop::$definition['primary']).'`, ms.*');
        $sql->from('shop', 's');
        $sql->innerJoin(bqSQL(static::$definition['table']), 'ms', 's.`'.bqSQL(Shop::$definition['primary']).'` = ms.`'.bqSQL(Shop::$definition['primary']).'`');
        $sql->where('s.`'.bqSQL(Shop::$definition['primary']).'` IN ('.implode(',', array_map('intval', $idShops)).')');

        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->getArray($sql);
        $mcs = [];
        foreach ($results as $result) {
            $mc = new static();
            $mc->hydrate($result);
            $mcs[(int) $result['id_shop']] = $mc;
        }

        return $mcs;
    }

    /**
     * Get MailChimpShop by List ID
     *
     * @param string $idList
     *
     * @return MailChimpShop
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getByListId($idList)
    {
        $sql = new DbQuery();
        $sql->select('s.`'.bqSQL(Shop::$definition['primary']).'`, ms.*');
        $sql->from('shop', 's');
        $sql->leftJoin(bqSQL(static::$definition['table']), 'ms', 's.`'.bqSQL(Shop::$definition['primary']).'` = ms.`'.bqSQL(Shop::$definition['primary']).'`');
        $sql->where('ms.`list_id` = \''.pSQL($idList).'\'');

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
        $mcs = new static();
        if (is_array($result)) {
            $mcs->hydrate($result);
        }

        return $mcs;
    }

    /**
     * Renew MC scripts
     *
     * @param int|int[]|null $idShops
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws GuzzleException
     */
    public static function renewScripts($idShops = null)
    {
        if (is_string($idShops) || is_int($idShops)) {
            $idShops = [(int) $idShops];
        } else if (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getContextListShopID(Shop::SHARE_CUSTOMER);
        }
        $idShops = array_map('intval', $idShops);
        $mailChimpShops = static::getByShopIds($idShops);

        $guzzle = \MailChimp::getGuzzle();
        if (!$guzzle) {
            return;
        }

        $promises = call_user_func(function () use ($mailChimpShops, $guzzle) {
            foreach ($mailChimpShops as $index => $mailChimpShop) {
                yield $index => $guzzle->getAsync("connected-sites/tbstore_{$mailChimpShop->id_shop}");
            }
        });

        (new EachPromise($promises, [
            'concurrency' => \MailChimp::API_CONCURRENCY,
            'fulfilled' => function ($response, $index) use ($mailChimpShops, $guzzle) {
                if ($response instanceof \GuzzleHttp\Psr7\Response) {
                    $response = json_decode((string) $response->getBody(), true);
                    $mailChimpShop = $mailChimpShops[$index];
                    if (isset($response['site_script']['url'])) {
                        if (!$mailChimpShop->mc_script) {
                            try {
                                $guzzle->post("connected-sites/tbstore_{$mailChimpShop->id_shop}/actions/verify-script-installation");
                            } catch (TransferException $e) {
                            }
                        }
                        $mailChimpShop->mc_script = $response['site_script']['url'];
                        $mailChimpShop->save();
                    }
                }
            }
        ]))->promise()->wait();
    }
}
