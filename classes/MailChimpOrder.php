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
 * Class MailChimpOrder
 *
 * @package MailChimpModule
 *
 * @since 1.1.0
 */
class MailChimpOrder extends MailChimpObjectModel
{
    /**
     * @see ObjectModel::$definition
     *
     * @since 1.1.0
     */
    public static $definition = [
        'table'   => 'mailchimp_order',
        'primary' => 'id_mailchimp_order',
        'fields'  => [
            'id_order'    => ['type' => self::TYPE_INT,    'validate' => 'isInt',    'required' => true,                                     'db_type' => 'INT(11) UNSIGNED'],
            'last_synced' => ['type' => self::TYPE_DATE,   'validate' => 'isBool',   'required' => true, 'default' => '1970-01-01 00:00:00', 'db_type' => 'DATETIME'        ],
        ],
    ];
    // @codingStandardsIgnoreStart
    public $id_order;
    public $last_synced;
    // @codingStandardsIgnoreEnd

    /**
     * @param int|null $idShop    Shop ID
     * @param bool     $remaining Remaining orders only
     *
     * @return int
     */
    public static function countOrders($idShop = null, $remaining = false)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        $sql = new \DbQuery();
        $sql->select('COUNT(o.`id_order`)');
        $sql->from('orders', 'o');
        $sql->where('o.`id_shop` = '.(int) $idShop);
        if ($remaining) {
            $sql->leftJoin(bqSQL(self::$definition['table']), 'mo', 'mo.`id_order` = o.`id_order`');
            $ordersLastSynced = \Configuration::get(\MailChimp::ORDERS_LAST_SYNC);
            if ($ordersLastSynced) {
                $sql->where('mo.`last_synced` IS NULL OR mo.`last_synced` < '.pSQL($ordersLastSynced));
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
     * @param bool     $remaining Remaining Orders only
     *
     * @return array|false|\mysqli_result|null|\PDOStatement|resource
     * @since 1.1.0
     */
    public static function getOrders($idShop = null, $offset = 0, $limit = 0, $remaining = false)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        $sql = new \DbQuery();
        $sql->select('o.`id_order`, c.*, cu.`email`, cu.`firstname`, cu.`lastname`, cu.`newsletter`');
        $sql->from('orders', 'o');
        $sql->innerJoin('customer', 'cu', 'cu.`id_customer` = o.`id_customer`');
        $sql->innerJoin('cart', 'c', 'c.`id_cart` = o.`id_cart`');
        $sql->where('o.`id_shop` = '.(int) $idShop);
        if ($remaining) {
            $sql->leftJoin(bqSQL(self::$definition['table']), 'mo', 'mo.`id_order` = o.`id_order`');
            $ordersLastSynced = \Configuration::get(\MailChimp::ORDERS_LAST_SYNC);
            if ($ordersLastSynced) {
                $sql->where('mc.`last_synced` IS NULL OR mc.`last_synced` < '.pSQL($ordersLastSynced));
            }
        }
        if ($offset || $limit) {
            $sql->limit($limit, $offset);
        }

        $results = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        $defaultCurrency = \Currency::getDefaultCurrency();
        $defaultCurrencyCode = $defaultCurrency->iso_code;
        foreach ($results as &$cart) {
            $cartObj = new \Cart($cart['id_cart']);

            $cart['currency_code'] = $defaultCurrencyCode;
            $cart['order_total'] = $cartObj->getOrderTotal(true);

            $cart['lines'] = [];
            $sql = new \DbQuery();
            $sql->select('cp.*, ps.`price`');
            $sql->from('cart_product', 'cp');
            $sql->innerJoin('product_shop', 'ps', 'ps.`id_product` = cp.`id_product` AND ps.`id_shop` = '.(int) $idShop);
            $sql->where('cp.`id_cart` = '.(int) $cart['id_cart']);

            $orderProducts = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

            foreach ($orderProducts as &$cartProduct) {
                $cart['lines'][] = [
                    'id'                 => $cartProduct['id_product'],
                    'product_id'         => $cartProduct['id_product'],
                    'product_variant_id' => $cartProduct['id_product'],
                    'quantity'           => (int) $cartProduct['id_product'],
                    'price'              => (float) $cartProduct['price'],
                ];
            }
        }

        return $results;
    }

    /**
     * Set synced
     *
     * @param array    $range
     *
     * @return bool
     * @since 1.1.0
     */
    public static function setSynced($range)
    {
        if (empty($range)) {
            return false;
        }

        $insert = [];
        $now = date('Y-m-d H:i:s');
        foreach ($range as &$item) {
            $insert[] = [
                'id_order'    => $item,
                'last_synced' => $now,
            ];
        }

        \Db::getInstance()->delete(
            bqSQL(self::$definition['table']),
            '`id_order` IN ('.implode(',', $range).')'
        );

        return \Db::getInstance()->insert(
            bqSQL(self::$definition['table']),
            $insert
        );
    }
}
