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
 *  @author    thirty bees <modules@thirtybees.com>
 *  @copyright 2017-2024 thirty bees
 *  @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * @param $module
 * @return bool
 * @throws PrestaShopDatabaseException
 * @throws PrestaShopException
 */
function upgrade_module_1_2_0($module)
{
    $sql = [];
    $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'mailchimp_promo` (
  `id_mailchimp_promo` INT(11) NOT NULL AUTO_INCREMENT,
  `id_cart_rule`       INT(11) UNSIGNED NOT NULL,
  `enabled`            TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `locked`             TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_mailchimp_promo`),
  KEY `mc_promo_cart_rule` (`id_cart_rule`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1
';

    foreach ($sql as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    $module->registerHook('ActionAdminCartRulesListingFieldsModifier');

    return true;
}
