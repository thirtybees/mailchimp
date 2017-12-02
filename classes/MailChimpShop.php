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
 * Class MailChimpShop
 *
 * @package MailChimpModule
 *
 * @since 1.1.0
 */
class MailChimpShop extends \ObjectModel
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
            'id_shop' => ['type' => self::TYPE_INT,    'validate' => 'isInt',    'required' => true,                   'db_type' => 'INT(11) UNSIGNED'   ],
            'list_id' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true,                   'db_type' => 'VARCHAR(32)'        ],
            'id_tax'  => ['type' => self::TYPE_INT,    'validate' => 'isInt',    'required' => true,                   'db_type' => 'INT(11) UNSIGNED'   ],
            'synced'  => ['type' => self::TYPE_BOOL,   'validate' => 'isBool',   'required' => true, 'default' => '0', 'db_type' => 'TINYINT(1) UNSIGNED'],
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
    // @codingStandardsIgnoreEnd

    /**
     * Get shops
     *
     * @param bool $active
     *
     * @return array|false|\mysqli_result|null|\PDOStatement|resource
     *
     * @since 1.1.0
     */
    public static function getShops($active = false)
    {
        $sql = new \DbQuery();
        $sql->select('s.`'.bqSQL(\Shop::$definition['primary']).'`, s.`name`, ms.`list_id`, ms.`id_tax`, ms.`synced`');
        $sql->from('shop', 's');
        $sql->leftJoin(bqSQL(self::$definition['table']), 'ms', 's.`'.bqSQL(\Shop::$definition['primary']).'` = ms.`'.bqSQL(\Shop::$definition['primary']).'`');
        if ($active) {
            $sql->where('s.`active` = 1');
        }

        try {
            return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        } catch (\PrestaShopException $e) {
            return false;
        }
    }

    /**
     * Get MailChimpShop by Shop ID
     *
     * @param int $idShop
     *
     * @return MailChimpShop|false
     */
    public static function getByShopId($idShop)
    {
        $sql = new \DbQuery();
        $sql->select('s.`'.bqSQL(\Shop::$definition['primary']).'`, ms.*');
        $sql->from('shop', 's');
        $sql->leftJoin(bqSQL(self::$definition['table']), 'ms', 's.`'.bqSQL(\Shop::$definition['primary']).'` = ms.`'.bqSQL(\Shop::$definition['primary']).'`');
        $sql->where('s.`id_shop` = '.(int) $idShop);

        try {
            $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
        } catch (\PrestaShopException $e) {
            return false;
        }
        if (!$result) {
            return false;
        }

        $mcs = new self();
        $mcs->hydrate($result);

        return $mcs;
    }
}
