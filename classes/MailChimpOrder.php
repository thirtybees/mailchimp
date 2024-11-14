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

use Currency;
use Db;
use DbQuery;
use MailChimp;
use ObjectModel;
use Order;
use PrestaShopDatabaseException;
use PrestaShopException;
use Shop;
use Validate;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class MailChimpOrder
 *
 * @package MailChimpModule
 *
 * @since 1.1.0
 */
class MailChimpOrder extends ObjectModel
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
            'id_order'    => ['type' => self::TYPE_INT,  'validate' => 'isInt',  'required' => true,                                     'db_type' => 'INT(11) UNSIGNED'],
            'last_synced' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true, 'default' => '1970-01-01 00:00:00', 'db_type' => 'DATETIME'        ],
        ],
    ];
    // @codingStandardsIgnoreStart
    /** @var int $id_order */
    public $id_order;
    /** @var string $last_synced */
    public $last_synced;
    // @codingStandardsIgnoreEnd

    /**
     * Get orders
     *
     * @param int[]|int|null $idShops
     * @param int            $offset
     * @param int            $limit
     * @param bool           $remaining Remaining Orders only
     * @param bool           $count
     *
     * @return array|int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @since 1.1.0
     */
    public static function getOrders($idShops = null, $offset = 0, $limit = 0, $remaining = false, $count = false)
    {
        if (is_string($idShops) || is_int($idShops)) {
            $idShops = [(int) $idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getContextListShopID(Shop::SHARE_CUSTOMER);
        }
        $idShops = array_map('intval', $idShops);

        $sql = new DbQuery();
        if ($count) {
            $sql->select('COUNT(*)');
        } else {
            $sql->select('o.`id_order`, o.`date_add`, o.`date_upd`, c.*, mo.`last_synced`, mt.`mc_tc`, mt.`mc_cid`, mt.`landing_site`');
            $sql->select('cu.`id_customer`, cu.`email`, cu.`firstname`, cu.`lastname`, cu.`birthday`, cu.`newsletter`');
            $sql->select('l.`language_code`, o.`id_shop`');
        }
        $sql->from('orders', 'o');
        $sql->innerJoin('customer', 'cu', 'cu.`id_customer` = o.`id_customer`');
        $sql->innerJoin('cart', 'c', 'c.`id_cart` = o.`id_cart`');
        $sql->innerJoin('lang', 'l', 'l.`id_lang` = cu.`id_lang`');
        $sql->leftJoin('mailchimp_tracking', 'mt', 'mt.`id_order` = o.`id_order`');
        $sql->where('o.`id_shop` IN ('.implode(',', array_map('intval', $idShops)).')');
        $sql->where('o.`id_order` IN (SELECT `id_order` FROM `'._DB_PREFIX_.'order_detail`)');
        $sql->where('o.`current_state` IN ('.implode(',', array_map('intval', MailChimp::getValidOrderStatuses())).')');
        $sql->where('o.`date_add` > \''.pSQL(MailChimp::getOrderDateCutoff()).'\'');
        $sql->leftJoin(bqSQL(static::$definition['table']), 'mo', 'mo.`id_order` = o.`id_order`');
        if ($remaining) {
            $sql->where('mo.`last_synced` IS NULL OR (mo.`last_synced` < o.`date_upd` AND mo.`last_synced` > \'2000-01-01 00:00:00\')');
        }
        if ($limit) {
            $sql->limit($limit, $offset);
        }

        if ($count) {
            return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
        }

        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->getArray($sql);
        if (empty($results)) {
            return [];
        }

        $mailChimpShop = MailChimpShop::getByShopId($idShops);
        if (!Validate::isLoadedObject($mailChimpShop)) {
            return [];
        }
        $orderHistories = static::getOrderHistories(array_column($results, 'id_order'));
        $defaultCurrency = Currency::getDefaultCurrency();
        $defaultCurrencyCode = $defaultCurrency->iso_code;
        foreach ($results as &$order) {
            $orderObj = new Order($order['id_order']);
            $orderHistory = isset($orderHistories[$order['id_order']]) ? $orderHistories[$order['id_order']] : [];

            $order['currency_code'] = $defaultCurrencyCode;
            $order['order_total'] = $orderObj->total_paid_tax_incl;
            $order['shipping_total'] = (float) $orderObj->total_shipping_tax_incl;
            $order['tax_total'] = (float) $orderObj->total_paid_tax_incl - $orderObj->total_paid_tax_excl;
            if (count(array_intersect(array_map('intval', array_column($orderHistory, 'id_order_state')), array_map('intval', MailChimp::getOrderRefundedStatuses()))) >= 1) {
                $order['financial_status'] = 'refunded';
            } elseif (count(array_intersect(array_map('intval', array_column($orderHistory, 'id_order_state')), array_map('intval', MailChimp::getOrderCanceledStatuses()))) >= 1) {
                $order['financial_status'] = 'canceled';
            } elseif (count(array_intersect(array_map('intval', array_column($orderHistory, 'id_order_state')), array_map('intval', MailChimp::getOrderPaidStatuses()))) >= 1) {
                $order['financial_status'] = 'paid';
            } else {
                $order['financial_status'] = 'pending';
            }

            $order['shipped'] = count(array_intersect(array_map('intval', array_column($orderHistory, 'id_order_state')), array_map('intval', MailChimp::getOrderShippedStatuses()))) >= 1;

            $orderProducts = $orderObj->getOrderDetailList();
            if (!$orderProducts) {
                continue;
            }

            $order['lines'] = [];
            foreach ($orderProducts as $orderProduct) {
                $line = [
                    'id'                 => (string) $orderProduct['product_id'],
                    'product_id'         => (string) $orderProduct['product_id'],
                    'product_variant_id' => "{$orderProduct['product_id']}-{$orderProduct['product_attribute_id']}",
                    'quantity'           => (int) $orderProduct['product_quantity'],
                    'price'              => (float) $orderProduct['total_price_tax_incl'],
                ];

                $order['lines'][] = $line;
            }
        }

        return $results;
    }

    /**
     * Get order history
     *
     * @param int|int[] $range
     *
     * @return array|bool|null
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected static function getOrderHistories($range)
    {
        if (is_string($range) || is_int($range)) {
            $range = [(int) $range];
        } elseif (!is_array($range) || empty($range)) {
            return false;
        }
        $range = array_map('intval', $range);

        $results =  Db::getInstance(_PS_USE_SQL_SLAVE_)->getArray(
            (new DbQuery())
                ->select('`id_order`, `id_order_state`')
                ->from('order_history')
                ->where('`id_order` IN ('.implode(',', $range).')')
        );

        $histories = [];
        foreach ($results as $result) {
            if (!array_key_exists($result['id_order'], $histories)) {
                $histories[$result['id_order']] = [];
            }
            $histories[$result['id_order']][] = $result;
        }

        return $histories;
    }

    /**
     * Set synced
     *
     * @param int[] $range
     *
     * @return bool
     * @since 1.1.0
     * @throws PrestaShopException
     */
    public static function setSynced($range)
    {
        if (! $range) {
            return false;
        }

        $range = array_map('intval', $range);

        $insert = [];
        $now = date('Y-m-d H:i:s');
        foreach ($range as $item) {
            $insert[] = [
                'id_order'    => $item,
                'last_synced' => $now,
            ];
        }

        Db::getInstance()->delete(
            bqSQL(self::$definition['table']),
            '`id_order` IN ('.implode(',', $range).')',
            0,
            false
        );

        return Db::getInstance()->insert(
            bqSQL(self::$definition['table']),
            $insert,
            false,
            false,
            Db::INSERT_IGNORE
        );
    }

    /**
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
            '`id_order` IN (SELECT `id_order` FROM `'._DB_PREFIX_.'orders` WHERE `id_shop` = '.(int) $idShop.')'
        );
    }
}
