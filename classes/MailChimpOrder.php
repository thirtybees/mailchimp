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
    /** @var int $id_order */
    public $id_order;
    /** @var string $last_synced */
    public $last_synced;
    // @codingStandardsIgnoreEnd

    /**
     * Get orders
     *
     * @param int|null $idShops
     * @param int      $offset
     * @param int      $limit
     * @param bool     $remaining Remaining Orders only
     * @param bool     $count
     *
     * @return array|false|int|\mysqli_result|null|\PDOStatement|resource
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \Adapter_Exception
     * @since 1.1.0
     */
    public static function getOrders($idShops = null, $offset = 0, $limit = 0, $remaining = false, $count = false)
    {
        if (is_int($idShops)) {
            $idShops = [$idShops];
        } elseif (!is_array($idShops) || empty($idShops)) {
            $idShops = \Shop::getContextListShopID(\Shop::SHARE_CUSTOMER);
        }

        $sql = new \DbQuery();
        if ($count) {
            $sql->select('COUNT(*)');
        } else {
            $sql->select('o.`id_order`, o.`date_add`, o.`date_upd`, c.*, mo.`last_synced`, mt.`mc_tc`, mt.`mc_cid`');
            $sql->select('cu.`id_customer`, cu.`email`, cu.`firstname`, cu.`lastname`, cu.`birthday`, cu.`newsletter`');
            $sql->select('l.`language_code`, o.`id_shop`');
        }
        $sql->from('orders', 'o');
        $sql->innerJoin('customer', 'cu', 'cu.`id_customer` = o.`id_customer`');
        $sql->innerJoin('cart', 'c', 'c.`id_cart` = o.`id_cart`');
        $sql->innerJoin('lang', 'l', 'l.`id_lang` = cu.`id_lang`');
        $sql->leftJoin('mailchimp_tracking', 'mt', 'mt.`id_order` = o.`id_order`');
        $sql->where('o.`id_shop` IN ('.implode(',', array_map('intval', $idShops)).')');
        $sql->leftJoin(bqSQL(self::$definition['table']), 'mo', 'mo.`id_order` = o.`id_order`');
        if ($remaining) {
            $sql->where('mo.`last_synced` IS NULL OR (mo.`last_synced` < o.`date_upd` AND mo.`last_synced` > \'2000-01-01 00:00:00\')');
        }
        if ($limit) {
            $sql->limit($limit, $offset);
        }

        try {
            if ($count) {
                return (int) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
            }

            $results = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        } catch (\PrestaShopException $e) {
            \Context::getContext()->controller->errors[] = \Translate::getModuleTranslation('mailchimp', 'Unable to count orders', 'mailchimp');

            return false;
        }

        $mailChimpShop = MailChimpShop::getByShopId($idShops);
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
                false,
                \Db::INSERT_IGNORE
            );
        } catch (\PrestaShopException $e) {
            \Context::getContext()->controller->errors[] = \Translate::getModuleTranslation('mailchimp', 'Unable to set sync status', 'mailchimp');

            return false;
        }
    }

    /**
     * @param int $idShop
     *
     * @return bool
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function resetShop($idShop)
    {
        return \Db::getInstance()->update(
            bqSQL(static::$definition['table']),
            [
                'last_synced' => '1970-01-01 00:00:00',
            ],
            '`id_order` IN (SELECT `id_order` FROM `'._DB_PREFIX_.'orders` WHERE `id_shop` = '.(int) $idShop.')'
        );
    }
}
