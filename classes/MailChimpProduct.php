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
class MailChimpProduct extends \ObjectModel
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
     *
     *
     * @param int|null $idShop
     */
    public static function countProducts($idShop = null)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        $sql = new \DbQuery();
        $sql->select('COUNT(ps.`id_product`)');
        $sql->from('product_shop', 'ps');
        $sql->where('ps.`id_shop` = '.(int) $idShop);

        return (int) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Get products
     *
     * @param int|null $idShop
     * @param int      $offset
     * @param int      $limit
     *
     * @return array|false|\mysqli_result|null|\PDOStatement|resource
     * @since 1.1.0
     */
    public static function getProducts($idShop = null, $offset = 0, $limit = 0)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        $idLang = (int) \Configuration::get('PS_LANG_DEFAULT');

        $sql = new \DbQuery();
        $sql->select('ps.*, pl.`name`');
        $sql->from('product_shop', 'ps');
        $sql->innerJoin('product_lang', 'pl', 'pl.`id_product` = ps.`id_product` AND pl.`id_lang` = '.(int) $idLang);
        $sql->where('ps.`id_shop` = '.(int) $idShop);
        if ($offset || $limit) {
            $sql->limit($limit, $offset);
        }

        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }
}
