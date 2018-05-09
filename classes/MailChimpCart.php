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
use Cart;
use Context;
use Currency;
use Db;
use DbQuery;
use ObjectModel;
use PrestaShopDatabaseException;
use PrestaShopException;
use Shop;
use Translate;
use Validate;

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
class MailChimpCart extends ObjectModel
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
            'id_cart'     => ['type' => self::TYPE_INT,  'validate' => 'isInt',  'required' => true,                                     'db_type' => 'INT(11) UNSIGNED'],
            'last_synced' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true, 'default' => '1970-01-01 00:00:00', 'db_type' => 'DATETIME'        ],
        ],
    ];
    // @codingStandardsIgnoreStart
    /** @var int $id_cart */
    public $id_cart;
    /** @var string $last_synced */
    public $last_synced;
    // @codingStandardsIgnoreEnd

    /**
     * Get products
     *
     * @param int[]|int|null $idShops
     * @param int            $offset
     * @param int            $limit
     * @param bool           $remaining
     * @param bool           $count     Just count the carts
     *
     * @return array|false|int
     *
     * @since 1.1.0
     * @throws PrestaShopException
     * @throws Adapter_Exception
     */
    public static function getCarts($idShops = null, $offset = 0, $limit = 0, $remaining = false, $count = false)
    {
        if (is_string($idShops) || is_int($idShops)) {
            $idShops = [(int) $idShops];
        } else if (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getContextListShopID(Shop::SHARE_CUSTOMER);
        }
        $idShops = array_map('intval', $idShops);

        $selectOrdersSql = new DbQuery();
        $selectOrdersSql->select('`id_cart`');
        $selectOrdersSql->from('orders');

        $sql = new DbQuery();
        if ($count) {
            $sql->select('COUNT(*)');
        } else {
            $sql->select('c.*, cu.`email`, cu.`firstname`, cu.`lastname`, cu.`birthday`, cu.`newsletter`');
            $sql->select('cu.`id_lang`, mc.`last_synced`, l.`language_code`');
        }
        $sql->from('cart', 'c');
        $sql->innerJoin('customer', 'cu', 'cu.`id_customer` = c.`id_customer`');
        $sql->innerJoin('lang', 'l', 'l.`id_lang` = cu.`id_lang`');
        $sql->leftJoin(bqSQL(self::$definition['table']), 'mc', 'mc.`id_cart` = c.`id_cart`');
        $sql->where('c.`id_shop` IN ('.implode(',', array_map('intval', $idShops)).')');
        $sql->where('c.`date_upd` > \''.date('Y-m-d H:i:s', strtotime('-1 day')).'\'');
        try {
            $sql->where('c.`id_cart` NOT IN ('.$selectOrdersSql->build().')');
        } catch (PrestaShopException $e) {
            Context::getContext()->controller->errors[] = Translate::getModuleTranslation('mailchimp', 'Unable to count carts properly', 'mailchimp');

            return 0;
        }
        if ($remaining) {
            $sql->where('mc.`last_synced` IS NULL OR (mc.`last_synced` < c.`date_upd` AND mc.`last_synced` > \'2000-01-01 00:00:00\')');
        }
        if ($limit) {
            $sql->limit($limit, $offset);
        }

        try {
            if ($count) {

                return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
            }
            $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        } catch (PrestaShopException $e) {
            if ($count) {
                Context::getContext()->controller->errors[] = Translate::getModuleTranslation('mailchimp', 'Unable to count carts properly', 'mailchimp');
            } else {
                Context::getContext()->controller->errors[] = Translate::getModuleTranslation('mailchimp', 'Unable to find carts', 'mailchimp');
            }

            return false;
        }
        if (empty($results)) {
            return [];
        }

        $defaultCurrency = Currency::getDefaultCurrency();
        $defaultCurrencyCode = $defaultCurrency->iso_code;
        foreach ($results as &$cart) {
            $cartObject = new Cart($cart['id_cart']);

            $cart['currency_code'] = $defaultCurrencyCode;
            $cart['order_total'] = (float) $cartObject->getOrderTotal(false);
            $cart['checkout_url'] = Context::getContext()->link->getPageLink(
                'order',
                false,
                (int) $cart['id_lang'],
                'step=3&recover_cart='.$cart['id_cart'].'&token_cart='.md5(_COOKIE_KEY_.'recover_cart_'.$cart['id_cart'])
            );

            $cartProducts = $cartObject->getProducts();

            $cart['lines'] = [];
            foreach ($cartProducts as &$cartProduct) {
                $cart['lines'][] = [
                    'id'                 => (string) $cartProduct['id_product'],
                    'product_id'         => (string) $cartProduct['id_product'],
                    'product_variant_id' => "{$cartProduct['id_product']}-{$cartProduct['id_product_attribute']}",
                    'quantity'           => (int) $cartProduct['cart_quantity'],
                    'price'              => (float) $cartProduct['price_wt'],
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

        try {
            Db::getInstance()->delete(
                bqSQL(self::$definition['table']),
                '`id_cart` IN ('.implode(',', $range).')',
                0,
                false
            );
        } catch (PrestaShopException $e) {
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
            '`id_cart` IN (SELECT `id_cart` FROM `'._DB_PREFIX_.'cart` WHERE `id_shop` = '.(int) $idShop.')'
        );
    }
}
