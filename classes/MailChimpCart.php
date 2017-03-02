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
class MailChimpCart extends MailChimpObjectModel
{
    /**
     * @see ObjectModel::$definition
     *
     * @since 1.1.0
     */
    public static $definition = [
        'table'   => 'mailchimp_cart',
        'primary' => 'id_mailchimp_cart',
        'fields'  => [
            'id_cart'     => ['type' => self::TYPE_INT,    'validate' => 'isInt',    'required' => true,                                     'db_type' => 'INT(11) UNSIGNED'],
            'last_synced' => ['type' => self::TYPE_DATE,   'validate' => 'isBool',   'required' => true, 'default' => '1970-01-01 00:00:00', 'db_type' => 'DATETIME'        ],
        ],
    ];
    // @codingStandardsIgnoreStart
    public $id_cart;
    public $last_synced;
    // @codingStandardsIgnoreEnd

    /**
     * Count Carts
     *
     * @param int|null $idShop    Shop ID
     * @param bool     $remaining Remaining carts only
     *
     * @return int
     * @since 1.1.0
     */
    public static function countCarts($idShop = null, $remaining = false)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        $sql = new \DbQuery();
        $sql->select('COUNT(c.`id_cart`)');
        $sql->from('cart', 'c');
        $sql->where('c.`id_shop` = '.(int) $idShop);
        if ($remaining) {
            $sql->leftJoin(bqSQL(self::$definition['table']), 'mc', 'mc.`id_cart` = c.`id_cart`');
            $cartsLastSynced = \Configuration::get(\MailChimp::CARTS_LAST_SYNC, null, null, $idShop);
            if ($cartsLastSynced) {
                $sql->where('mc.`last_synced` IS NULL OR mc.`last_synced` < '.pSQL($cartsLastSynced));
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
     *
     * @param bool     $remaining
     *
     * @return array|false|\mysqli_result|null|\PDOStatement|resource
     * @since 1.1.0
     */
    public static function getCarts($idShop = null, $offset = 0, $limit = 0, $remaining = false)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        $sql = new \DbQuery();
        $sql->select('c.*, cu.`email`, cu.`firstname`, cu.`lastname`, cu.`newsletter`, mc.`last_synced`');
        $sql->from('cart', 'c');
        $sql->innerJoin('customer', 'cu', 'cu.`id_customer` = c.`id_customer`');
        $sql->where('c.`id_shop` = '.(int) $idShop);
        $sql->leftJoin(bqSQL(self::$definition['table']), 'mc', 'mc.`id_cart` = c.`id_cart`');
        if ($remaining) {
            $cartsLastSynced = \Configuration::get(\MailChimp::CARTS_LAST_SYNC, null, null, $idShop);
            if ($cartsLastSynced) {
                $sql->where('mc.`last_synced` IS NULL OR mc.`last_synced` < '.pSQL($cartsLastSynced));
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

            $cartProducts = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

            foreach ($cartProducts as &$cartProduct) {
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
     * @param array $range
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
                'id_cart'     => $item,
                'last_synced' => $now,
            ];
        }

        \Db::getInstance()->delete(
            bqSQL(self::$definition['table']),
            '`id_cart` IN ('.implode(',', $range).')'
        );

        return \Db::getInstance()->insert(
            bqSQL(self::$definition['table']),
            $insert
        );
    }

    /**
     * Adds the indexes as well
     *
     * @param string|null $className
     *
     * @return bool Status
     */
    public static function createDatabase($className = null)
    {
        if (parent::createDatabase($className)) {
            if (!\Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
            SELECT *
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = \''._DB_NAME_.'\'
            AND TABLE_NAME = \''._DB_PREFIX_.bqSQL(self::$definition['table']).'\'
            AND INDEX_NAME = \'mailchimp_cart_id_cart\'')) {
                \Db::getInstance()->execute(
                    'CREATE INDEX `mailchimp_cart_id_cart` ON `'._DB_PREFIX_.bqSQL(self::$definition['table']).'` (`id_cart`)'
                );
            }

            return true;
        }

        return false;
    }
}
