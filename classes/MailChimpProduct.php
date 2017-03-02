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

if (!defined('_TB_VERSION_') && !defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class MailChimpProduct
 *
 * @package MailChimpModule
 *
 * @since 1.1.0
 */
class MailChimpProduct extends MailChimpObjectModel
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
            'id_product'  => ['type' => self::TYPE_INT,    'validate' => 'isInt',    'required' => true,                                     'db_type' => 'INT(11) UNSIGNED'],
            'id_shop'     => ['type' => self::TYPE_INT,    'validate' => 'isInt',    'required' => true,                                     'db_type' => 'INT(11) UNSIGNED'],
            'last_synced' => ['type' => self::TYPE_DATE,   'validate' => 'isBool',   'required' => true, 'default' => '1970-01-01 00:00:00', 'db_type' => 'DATETIME'        ],
        ],
    ];
    // @codingStandardsIgnoreStart
    public $id_product;
    public $id_shop;
    public $last_synced;
    // @codingStandardsIgnoreEnd

    /**
     * Count products
     *
     * @param int|null $idShop    Shop ID
     * @param bool     $remaining Unsynched only
     *
     * @return int
     *
     * @since 1.1.0
     */
    public static function countProducts($idShop = null, $remaining = false)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        $sql = new \DbQuery();
        $sql->select('COUNT(ps.`id_product`)');
        $sql->from('product_shop', 'ps');
        $sql->where('ps.`id_shop` = '.(int) $idShop);
        if ($remaining) {
            $sql->leftJoin(bqSQL(self::$definition['table']), 'mp', 'mp.`id_product` = ps.`id_product`');
            $productsLastSynced = \Configuration::get(\MailChimp::PRODUCTS_LAST_SYNC, null, null, $idShop);
            if ($productsLastSynced) {
                $sql->where('mp.`last_synced` IS NULL OR mp.`last_synced` < \''.pSQL($productsLastSynced).'\'');
            }
        }

        return (int) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Get products
     *
     * @param int|null $idShop
     * @param int      $offset
     * @param int      $limit
     * @param bool     $remaining
     *
     * @return array|false|\mysqli_result|null|\PDOStatement|resource
     *
     * @since 1.1.0
     */
    public static function getProducts($idShop = null, $offset = 0, $limit = 0, $remaining = false)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        $idLang = (int) \Configuration::get('PS_LANG_DEFAULT');

        $sql = new \DbQuery();
        $sql->select('ps.*, pl.`name`, pl.`description_short`, m.`name` as `manufacturer`, mp.`last_synced`');
        $sql->from('product_shop', 'ps');
        $sql->innerJoin('product_lang', 'pl', 'pl.`id_product` = ps.`id_product` AND pl.`id_lang` = '.(int) $idLang);
        $sql->innerJoin('product', 'p', 'p.`id_product` = ps.`id_product`');
        $sql->leftJoin('manufacturer', 'm', 'm.`id_manufacturer` = p.`id_manufacturer`');
        $sql->where('ps.`id_shop` = '.(int) $idShop);
        $sql->leftJoin(bqSQL(self::$definition['table']), 'mp', 'mp.`id_product` = ps.`id_product`');
        if ($remaining) {
            $productsLastSynced = \Configuration::get(\MailChimp::PRODUCTS_LAST_SYNC, null, null, $idShop);
            if ($productsLastSynced) {
                $sql->where('mp.`last_synced` IS NULL OR mp.`last_synced` < \''.pSQL($productsLastSynced).'\'');
            }
        }
        if ($offset || $limit) {
            $sql->limit($limit, $offset);
        }

        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * Set synced
     *
     * @param array    $range
     * @param int|null $idShop
     *
     * @return bool
     * @since 1.1.0
     */
    public static function setSynced($range, $idShop = null)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }
        $idShop = (int) $idShop;

        if (empty($range)) {
            return false;
        }

        $insert = [];
        $now = date('Y-m-d H:i:s');
        foreach ($range as &$item) {
            $insert[] = [
                'id_product'  => $item,
                'id_shop'     => $idShop,
                'last_synced' => $now,
            ];
        }

        \Db::getInstance()->delete(
            bqSQL(self::$definition['table']),
            '`id_product` IN ('.implode(',', $range).') AND `id_shop` = '.(int) $idShop
        );

        return \Db::getInstance()->insert(
            bqSQL(self::$definition['table']),
            $insert
        );
    }
}
