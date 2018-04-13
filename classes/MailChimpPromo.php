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

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class MailChimpPromo
 *
 * @package MailChimpModule
 *
 * @since 1.1.0
 */
class MailChimpPromo extends \ObjectModel
{
    /**
     * @see ObjectModel::$definition
     *
     * @since 1.1.0
     */
    public static $definition = [
        'table'   => 'mailchimp_promo',
        'primary' => 'id_mailchimp_promo',
        'fields'  => [
            'id_cart_rule' => ['type' => self::TYPE_INT, 'validate' => 'isInt',  'required' => true,                    'db_type' => 'INT(11) UNSIGNED'   ],
            'enabled'      => ['type' => self::TYPE_INT, 'validate' => 'isBool', 'required' => true, 'default' => '0', 'db_type' => 'TINYINT(1) UNSIGNED'],
            'locked'       => ['type' => self::TYPE_INT, 'validate' => 'isBool', 'required' => true, 'default' => '0', 'db_type' => 'TINYINT(1) UNSIGNED'],
        ],
    ];
    // @codingStandardsIgnoreStart
    /** @var int $id_cart_rule */
    public $id_cart_rule;
    /** @var bool $enabled */
    public $enabled;
    // @codingStandardsIgnoreEnd
    
    public static function printMailChimpPromoButton($id, $tr)
    {
        if ($tr['mailchimp_locked']) {
            return '--';
        }

        $module = \Module::getInstanceByName('mailchimp');

        \Context::getContext()->smarty->assign([
            'id' => $id,
            'tr' => $tr,
        ]);
        return $module->display(_PS_MODULE_DIR_.'mailchimp.php', '/views/templates/admin/promo_icon.tpl');
    }

    /**
     * Get by Cart Rule
     *
     * @param int $idCartRule
     *
     * @return static
     * @throws \Adapter_Exception
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function getByCartRuleId($idCartRule)
    {
        $promo = new static();
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            (new \DbQuery())
                ->select('*')
                ->from(bqSQL(static::$definition['table']))
                ->where('`id_cart_rule` = '.(int) $idCartRule)
        );
        if (is_array($result) && !empty($result)) {
            $promo->hydrate($result);
        } else {
            $promo->id_cart_rule = $idCartRule;
            $promo->enabled = false;
        }

        return $promo;
    }

    /**
     * @param int $idCartRule
     *
     * @return bool New status
     *
     * @throws \Adapter_Exception
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function toggle($idCartRule)
    {
        $promo = static::getByCartRuleId($idCartRule);
        $promo->enabled = !$promo->enabled;
        $promo->save();

        return $promo->enabled;
    }

    /**
     * Get enabled Cart Rules
     *
     * @return \CartRule[]
     *
     * @throws \Adapter_Exception
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function getCartRules()
    {
        $cartRules = [];
        $results = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new \DbQuery())
                ->select('`id_cart_rule`')
                ->from(bqSQL(static::$definition['table']))
                ->where('`enabled` = 1')
        );

        if (is_array($results) && !empty($results)) {
            foreach ($results as $result) {
                $cartRules[] = new \CartRule($result['id_cart_rule']);
            }
        }

        return $cartRules;
    }

    /**
     * Lock a Cart Rule
     *
     * @param \CartRule $cartRule
     *
     * @return bool
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function lock(\CartRule $cartRule)
    {
        if (\Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            (new \DbQuery())
                ->select('`id_cart_rule`')
                ->from(bqSQL(static::$definition['table']))
                ->where('`id_cart_rule` = '.(int) $cartRule->id)
        )) {
            return \Db::getInstance()->update(
                bqSQL(static::$definition['table']),
                [
                    'locked'  => true,
                    'enabled' => false,
                ],
                '`id_cart_rule` = '.(int) $cartRule->id
            );
        } else {
            return \Db::getInstance()->insert(
                bqSQL(static::$definition['table']),
                [
                    'id_cart_rule' => (int) $cartRule->id,
                    'locked'       => true,
                    'enabled'      => false,
                ],
                '`id_cart_rule` = '.(int) $cartRule->id
            );
        }
    }

    /**
     * Duplicate cart rules for the given customer
     *
     * @param int|\Customer $customer
     *
     * @throws \Adapter_Exception
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function duplicateCartRules($customer)
    {
        if (!$customer instanceof \Customer) {
            $customer = new \Customer($customer);
        }
        $tbRef = MailChimpSubscriber::getTbRef($customer->email);
        foreach (static::getCartRules() as $cartRule) {
            /** @var \CartRule $duplicate */
            try {
                if (!\CartRule::getIdByCode("$tbRef-{$cartRule->code}")) {
                    $duplicate = $cartRule->duplicateObject();
                    $duplicate->id_customer = $customer->id;
                    $duplicate->code = "$tbRef-{$cartRule->code}";
                    $duplicate->save();
                    static::lock($duplicate);
                }
            } catch (\PrestaShopException $e) {
            }
        }
    }
}
