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

use Adapter_Exception;
use Context;
use Db;
use DbQuery;
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
            'mc_script' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => false,                  'db_type' => 'VARCHAR(255)'],
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
    // @codingStandardsIgnoreEnd

    /**
     * Get shops
     *
     * @param bool $active
     *
     * @return array|false|mysqli_result|null|PDOStatement|resource
     *
     * @since 1.1.0
     * @throws PrestaShopException
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

        try {
            return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        } catch (PrestaShopException $e) {
            return false;
        }
    }

    /**
     * Get MailChimpShop by Shop ID
     *
     * @param int $idShop
     *
     * @return MailChimpShop|false
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getByShopId($idShop)
    {
        $sql = new DbQuery();
        $sql->select('s.`'.bqSQL(Shop::$definition['primary']).'`, ms.*');
        $sql->from('shop', 's');
        $sql->leftJoin(bqSQL(self::$definition['table']), 'ms', 's.`'.bqSQL(Shop::$definition['primary']).'` = ms.`'.bqSQL(Shop::$definition['primary']).'`');
        $sql->where('s.`id_shop` = '.(int) $idShop);

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
        if (!$result) {
            return false;
        }

        $mcs = new self();
        $mcs->hydrate($result);

        return $mcs;
    }

    /**
     * Get MailChimpShop by Shop ID
     *
     * @param int[] $idShops
     * @param bool  $hasList Needs to have a list
     *
     * @return MailChimpShop[]
     *
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getByShopIds($idShops)
    {
        $sql = new DbQuery();
        $sql->select('s.`'.bqSQL(Shop::$definition['primary']).'`, ms.*');
        $sql->from('shop', 's');
        $sql->innerJoin(bqSQL(self::$definition['table']), 'ms', 's.`'.bqSQL(Shop::$definition['primary']).'` = ms.`'.bqSQL(Shop::$definition['primary']).'`');
        $sql->where('s.`'.bqSQL(Shop::$definition['primary']).'` IN ('.implode(',', array_map('intval', $idShops)).')');

        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        if (!$results) {
            return [];
        }

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
     * @return MailChimpShop|false
     *
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getByListId($idList)
    {
        $sql = new DbQuery();
        $sql->select('s.`'.bqSQL(Shop::$definition['primary']).'`, ms.*');
        $sql->from('shop', 's');
        $sql->leftJoin(bqSQL(self::$definition['table']), 'ms', 's.`'.bqSQL(Shop::$definition['primary']).'` = ms.`'.bqSQL(Shop::$definition['primary']).'`');
        $sql->where('s.`id_list` = \''.pSQL($idList).'\'');

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
        if (!$result) {
            return false;
        }

        $mcs = new self();
        $mcs->hydrate($result);

        return $mcs;
    }
}
