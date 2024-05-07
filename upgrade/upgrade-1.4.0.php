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

function upgrade_module_1_4_0()
{
    if (!Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = \''._DB_NAME_.'\'
                AND TABLE_NAME = \''._DB_PREFIX_.'mailchimp_tracking\'
                AND COLUMN_NAME = \'landing_site\'')
    ) {
        Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'mailchimp_tracking` ADD `landing_site` VARCHAR(255) NULL');
    }
    if (!Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = \''._DB_NAME_.'\'
                AND TABLE_NAME = \''._DB_PREFIX_.'mailchimp_shop\'
                AND COLUMN_NAME = \'mc_script\'')
    ) {
        Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'mailchimp_shop` ADD `mc_script` VARCHAR(255) NULL');
    }


    return true;
}
