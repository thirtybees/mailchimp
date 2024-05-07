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

use Configuration;
use Context;
use Db;
use DbQuery;
use ObjectModel;
use PrestaShopDatabaseException;
use PrestaShopException;
use Shop;
use Translate;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class MailChimpProduct
 *
 * @package MailChimpModule
 *
 * @since 1.1.0
 */
class MailChimpProduct extends ObjectModel
{
    /**
     * @see ObjectModel::$definition
     *
     * @since 1.1.0
     */
    public static $definition = [
        'table'   => 'mailchimp_product',
        'primary' => 'id_mailchimp_product',
        'fields'  => [
            'id_product'  => ['type' => self::TYPE_INT,  'validate' => 'isInt',  'required' => true,                                     'db_type' => 'INT(11) UNSIGNED'   ],
            'id_shop'     => ['type' => self::TYPE_INT,  'validate' => 'isInt',  'required' => true,                                     'db_type' => 'INT(11) UNSIGNED'   ],
            'last_synced' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true, 'default' => '1970-01-01 00:00:00', 'db_type' => 'DATETIME'           ],
        ],
    ];
    // @codingStandardsIgnoreStart
    /** @var int $id_product */
    public $id_product;
    /** @var int $id_shop */
    public $id_shop;
    /** @var string $last_synced */
    public $last_synced;
    // @codingStandardsIgnoreEnd

    /**
     * Get products
     *
     * @param int|null $idShops
     * @param int      $offset
     * @param int      $limit
     * @param bool     $remaining
     * @param bool     $count
     *
     * @return array|int
     *
     * @since 1.1.0
     * @throws PrestaShopException
     */
    public static function getProducts($idShops = null, $offset = 0, $limit = 0, $remaining = false, $count = false)
    {
        if (is_string($idShops) || is_int($idShops)) {
            $idShops = [(int) $idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getContextListShopID(Shop::SHARE_STOCK);
        }
        $idShops = array_map('intval', $idShops);
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $sql = new DbQuery();
        if ($count) {
            $sql->select('COUNT(*)');
        } else {
            $sql->select('ps.*, pl.`name`, pl.`description_short`, m.`name` as `manufacturer`, mp.`last_synced`');
            $sql->select('p.`out_of_stock`, p.`quantity`');
        }
        $sql->from('product_shop', 'ps');
        $sql->innerJoin('product_lang', 'pl', 'pl.`id_product` = ps.`id_product` AND pl.`id_lang` = '.(int) $idLang.' AND ps.`id_shop` = pl.`id_shop`');
        $sql->innerJoin('product', 'p', 'p.`id_product` = ps.`id_product`');
        $sql->leftJoin('manufacturer', 'm', 'm.`id_manufacturer` = p.`id_manufacturer`');
        $sql->where('ps.`id_shop` IN ('.implode(',', array_map('intval', $idShops)).')');
        $sql->leftJoin(bqSQL(self::$definition['table']), 'mp', 'mp.`id_product` = ps.`id_product` AND mp.`id_shop` = ps.`id_shop`');
        $sql->where('ps.`active` = 1');
        if ($remaining) {
            $sql->where('mp.`last_synced` IS NULL OR (mp.`last_synced` < ps.`date_upd` AND mp.`last_synced` > \'2000-01-01 00:00:00\')');
        }
        if ($limit) {
            $sql->limit($limit, $offset);
        }

        try {
            if ($count) {
                return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
            }

            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
            if (!is_array($result)) {
                $result = [];
            }

            return $result;
        } catch (PrestaShopException $e) {
            Context::getContext()->controller->errors[] = Translate::getModuleTranslation('mailchimp', 'Unable to count products', 'mailchimp');

            return 0;
        }
    }

    /**
     * Get products
     *
     * @param int|int[]      $range       Product IDs
     * @param int|int[]|null $idShops     Shop IDs
     * @param bool           $remaining   Only grab the products that have yet to be synced
     * @param bool           $orderDetail Use the order detail table to find missing pieces
     *
     * @return array
     *
     * @since 1.1.0
     * @throws PrestaShopException
     */
    public static function getProductRange($range, $idShops = null, $remaining = false, $orderDetail = false)
    {
        if (is_string($idShops) || is_int($idShops)) {
            $idShops = [(int) $idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getContextListShopID(Shop::SHARE_STOCK);
        }
        $idShops = array_map('intval', $idShops);
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        if (is_string($range) || is_int($range)) {
            $range = [$range];
        } elseif (!is_array($range) || empty($range)) {
            return [];
        }
        $range = array_map('intval', $range);

        $sql = new DbQuery();
        $sql->select('ps.`id_product`, ps.`id_shop`, ps.`id_tax_rules_group`, ps.`price`, ps.`active`');
        $sql->select('ps.`date_add`, ps.`date_upd`, pl.`name`, pl.`description_short`, m.`name` as `manufacturer`, mp.`last_synced`');
        $sql->select('IFNULL(p.`reference`, p.`supplier_reference`) AS `reference`, p.`out_of_stock`, p.`quantity`');
        $sql->from('product_shop', 'ps');
        $sql->innerJoin('product_lang', 'pl', 'pl.`id_product` = ps.`id_product` AND pl.`id_lang` = '.(int) $idLang.' AND ps.`id_shop` = pl.`id_shop`');
        $sql->innerJoin('product', 'p', 'p.`id_product` = ps.`id_product`');
        $sql->leftJoin('manufacturer', 'm', 'm.`id_manufacturer` = p.`id_manufacturer`');
        $sql->where('ps.`id_shop` IN ('.implode(',', array_map('intval', $idShops)).')');
        $sql->leftJoin(bqSQL(self::$definition['table']), 'mp', 'mp.`id_product` = ps.`id_product` AND mp.`id_shop` = ps.`id_shop`');
        $sql->where('ps.`active` = 1');
        $sql->where('ps.`id_product` IN ('.implode(',', array_map('intval', $range)).')');
        if ($remaining) {
            $sql->where('mp.`last_synced` IS NULL OR (mp.`last_synced` < ps.`date_upd` AND mp.`last_synced` > \'2000-01-01 00:00:00\')');
        }

        try {
            $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
            if ($orderDetail && is_array($results)) {
                $missingIds = array_diff(array_map('intval', $range), array_map('intval', array_column($results, 'id_product')));
                if (!empty($missingIds)) {
                    $orderDetails = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                        (new DbQuery())
                            ->select('od.`product_id` AS `id_product`, od.`product_attribute_id` AS `id_product_attribute`, od.`id_order_detail`')
                            ->select('od.`id_shop`, od.`product_name` AS `name`, od.`product_quantity` AS `quantity`, od.`product_price` AS `price`')
                            ->select('od.`tax_rate`, od.`unit_price_tax_excl`, od.`unit_price_tax_incl`, \'1970-01-01 00:00:00\' AS `last_synced`')
                            ->select('od.`product_reference` AS `reference`, \'0\' AS p.`out_of_stock`, \'0\' AS p.`quantity`')
                            ->from('order_detail', 'od')
                            ->where('od.`product_id` IN ('.implode(',', array_map('intval', $missingIds)).')')
                    );
                    if (!is_array($orderDetails)) {
                        $orderDetails = [];
                    }
                    $results = array_merge($results, $orderDetails);
                }
            } else {
                return [];
            }

            return $results;
        } catch (PrestaShopException $e) {
            Context::getContext()->controller->errors[] = Translate::getModuleTranslation('mailchimp', 'Unable to count products', 'mailchimp');

            return [];
        }
    }

    /**
     * Set synced
     *
     * @param array          $range
     * @param int[]|int|null $idShops
     *
     * @return bool
     * @since 1.1.0
     * @throws PrestaShopException
     */
    public static function setSynced($range, $idShops = null)
    {
        if (is_string($idShops) || is_int($idShops)) {
            $idShops = [(int) $idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getContextListShopID(Shop::SHARE_STOCK);
        }
        $idShops = array_map('intval', $idShops);

        if (empty($range)) {
            return false;
        }

        $insert = [];
        $now = date('Y-m-d H:i:s');
        foreach ($range as &$item) {
            foreach ($idShops as $idShop) {
                $insert[] = [
                    'id_product'  => $item,
                    'id_shop'     => $idShop,
                    'last_synced' => $now,
                ];
            }
        }

        try {
            Db::getInstance()->delete(
                bqSQL(self::$definition['table']),
                '`id_product` IN ('.implode(',', $range).') AND `id_shop` IN ('.implode(',', array_map('intval', $idShops)).')',
                0,
                false
            );
        } catch (PrestaShopDatabaseException $e) {
            Context::getContext()->controller->errors[] = Translate::getModuleTranslation('mailchimp', 'Unable to set sync status', 'mailchimp');

            return false;
        }

        try {
            return Db::getInstance()->insert(
                bqSQL(self::$definition['table']),
                $insert,
                false,
                false,
                Db::INSERT_IGNORE
            );
        } catch (PrestaShopException $e) {
            Context::getContext()->controller->errors[] = Translate::getModuleTranslation('mailchimp', 'Unable to set sync status', 'mailchimp');

            return false;
        }
    }

    /**
     * Reset shop sync data
     *
     * @param int $idShop
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function resetShop($idShop)
    {
        return Db::getInstance()->update(
            bqSQL(static::$definition['table']),
            [
                'last_synced' => '1970-01-01 00:00:00',
            ],
            '`id_shop` = '.(int) $idShop
        );
    }
}
