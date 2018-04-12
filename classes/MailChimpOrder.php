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
 * @copyright 2017-2018 thirty bees
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
class MailChimpOrder extends \ObjectModel
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
            'id_order'    => ['type' => self::TYPE_INT,    'validate' => 'isInt',    'required' => true,                                     'db_type' => 'INT(11) UNSIGNED'   ],
            'last_synced' => ['type' => self::TYPE_DATE,   'validate' => 'isDate',   'required' => true, 'default' => '1970-01-01 00:00:00', 'db_type' => 'DATETIME'           ],
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
     * @throws \PrestaShopException
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
                $sql->where('mo.`last_synced` IS NULL OR mo.`last_synced` < o.`date_upd`');
                $sql->where('STR_TO_DATE(o.`date_upd`, \'%Y-%m-%d %H:%i:%s\') IS NOT NULL');
            }
        }

        try {
            return (int) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
        } catch (\PrestaShopException $e) {
            \Context::getContext()->controller->errors[] = \Translate::getModuleTranslation('mailchimp', 'Unable to count orders', 'mailchimp');

            return 0;
        }
    }

    /**
     * Get orders
     *
     * @param int|null $idShop
     * @param int      $offset
     * @param int      $limit
     * @param bool     $remaining Remaining Orders only
     *
     * @return array|false|\mysqli_result|null|\PDOStatement|resource
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \Adapter_Exception
     * @since 1.1.0
     */
    public static function getOrders($idShop = null, $offset = 0, $limit = 0, $remaining = false)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        $sql = new \DbQuery();
        $sql->select('o.`id_order`, o.`date_add`, o.`date_upd`, c.*, mo.`last_synced`, mt.`mc_tc`, mt.`mc_cid`');
        $sql->select('cu.`id_customer`, cu.`email`, cu.`firstname`, cu.`lastname`, cu.`birthday`, cu.`newsletter`');
        $sql->select('l.`language_code`');
        $sql->from('orders', 'o');
        $sql->innerJoin('customer', 'cu', 'cu.`id_customer` = o.`id_customer`');
        $sql->innerJoin('cart', 'c', 'c.`id_cart` = o.`id_cart`');
        $sql->innerJoin('lang', 'l', 'l.`id_lang` = cu.`id_lang`');
        $sql->leftJoin('mailchimp_tracking', 'mt', 'mt.`id_order` = o.`id_order`');
        $sql->where('o.`id_shop` = '.(int) $idShop);
        $sql->leftJoin(bqSQL(self::$definition['table']), 'mo', 'mo.`id_order` = o.`id_order`');
        if ($remaining) {
            $ordersLastSynced = \Configuration::get(\MailChimp::ORDERS_LAST_SYNC);
            if ($ordersLastSynced) {
                $sql->where('mo.`last_synced` IS NULL OR mo.`last_synced` < o.`date_upd`');
                $sql->where('STR_TO_DATE(o.`date_upd`, \'%Y-%m-%d %H:%i:%s\') IS NOT NULL');
            }
        }
        if ($limit) {
            $sql->limit($limit, $offset);
        }

        try {
            $results = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        } catch (\PrestaShopException $e) {
            \Context::getContext()->controller->errors[] = \Translate::getModuleTranslation('mailchimp', 'Unable to count orders', 'mailchimp');

            return false;
        }

        $mailChimpShop = MailChimpShop::getByShopId($idShop);
        if (!\Validate::isLoadedObject($mailChimpShop)) {
            return false;
        }
        $rate = 1;
        $tax = new \Tax($mailChimpShop->id_tax);
        if (\Validate::isLoadedObject($tax) && $tax->active) {
            $rate = 1 + ($tax->rate / 100);
        }

        $defaultCurrency = \Currency::getDefaultCurrency();
        $defaultCurrencyCode = $defaultCurrency->iso_code;
        foreach ($results as &$order) {
            $orderObj = new \Order($order['id_order']);

            $order['currency_code'] = $defaultCurrencyCode;
            $order['order_total'] = $orderObj->getTotalPaid();
            $order['shipping_total'] = (float) ($orderObj->total_shipping_tax_incl * $rate);

            $orderProducts = $orderObj->getOrderDetailList();
            if (!$orderProducts) {
                continue;
            }

            $order['lines'] = [];
            foreach ($orderProducts as &$cartProduct) {
                $line = [
                    'id'                 => (string) $cartProduct['product_id'],
                    'product_id'         => (string) $cartProduct['product_id'],
                    'product_variant_id' => (string) $cartProduct['product_attribute_id'] ? $cartProduct['product_id'].'-'.$cartProduct['product_attribute_id'] : $cartProduct['product_id'],
                    'quantity'           => (int) $cartProduct['product_quantity'],
                    'price'              => (float) ($cartProduct['total_price_tax_incl'] * $rate),
                ];

                $order['lines'][] = $line;
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
     * @throws \PrestaShopException
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

        try {
            \Db::getInstance()->delete(
                bqSQL(self::$definition['table']),
                '`id_order` IN ('.implode(',', $range).')',
                0,
                false
            );
        } catch (\PrestaShopDatabaseException $e) {
            \Context::getContext()->controller->errors[] = \Translate::getModuleTranslation('mailchimp', 'Unable to set sync status', 'mailchimp');

            return false;
        }

        try {
            return \Db::getInstance()->insert(
                bqSQL(self::$definition['table']),
                $insert,
                false,
                false
            );
        } catch (\PrestaShopException $e) {
            \Context::getContext()->controller->errors[] = \Translate::getModuleTranslation('mailchimp', 'Unable to set sync status', 'mailchimp');

            return false;
        }
    }
}
