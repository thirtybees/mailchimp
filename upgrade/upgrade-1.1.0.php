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
 *  @author    Thirty Bees <modules@thirtybees.com>
 *  @copyright 2017 Thirty Bees
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    $sql = [];
    $sql[] = 'CREATE TABLE `'._DB_PREFIX_.'mailchimp_product` (
  `id_mailchimp_product`    INT(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_product`              INT(11) UNSIGNED    NOT NULL,
  `id_shop`                 INT(11) UNSIGNED    NOT NULL,
  `last_synced`             DATETIME,
  PRIMARY KEY (`id_mailchimp_product`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
';

    $sql[] = 'ALTER TABLE `'._DB_PREFIX_.'mailchimp_product` ADD INDEX `mc_product_product` (`id_product`)';
    $sql[] = 'ALTER TABLE `'._DB_PREFIX_.'mailchimp_product` ADD INDEX `mc_product_shop` (`id_shop`)';

    $sql[] = 'CREATE TABLE `'._DB_PREFIX_.'mailchimp_cart` (
  `id_mailchimp_cart`    INT(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_cart`              INT(11) UNSIGNED    NOT NULL,
  `last_synced`             DATETIME,
  PRIMARY KEY (`id_mailchimp_cart`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
';
    $sql[] = 'ALTER TABLE `'._DB_PREFIX_.'mailchimp_cart` ADD INDEX `mc_cart_cart` (`id_cart`)';

    $sql[] = 'ALTER TABLE `'._DB_PREFIX_.'mailchimp_cart` ADD INDEX `mc_product_product` (`id_product`)';
    $sql[] = 'ALTER TABLE `'._DB_PREFIX_.'mailchimp_cart` ADD INDEX `mc_product_shop` (`id_shop`)';

    $sql[] = 'CREATE TABLE `'._DB_PREFIX_.'mailchimp_order` (
  `id_mailchimp_order`    INT(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_order`              INT(11) UNSIGNED    NOT NULL,
  `last_synced`           DATETIME,
  PRIMARY KEY (`id_mailchimp_order`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
';
    $sql[] = 'ALTER TABLE `'._DB_PREFIX_.'mailchimp_order` ADD INDEX `mc_order_order` (`id_order`)';

    $sql[] = 'CREATE TABLE `'._DB_PREFIX_.'mailchimp_shop` (
  `id_mailchimp_shop`    INT(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_shop`              INT(11) UNSIGNED    NOT NULL,
  `list_id`              VARCHAR(32)         NOT NULL,
  `last_synced`          DATETIME,
  PRIMARY KEY (`id_mailchimp_shop`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
';
    $sql[] = 'ALTER TABLE `'._DB_PREFIX_.'mailchimp_shop` ADD INDEX `mc_shop_shop` (`id_shop`)';

    $sql[] = 'CREATE TABLE `'._DB_PREFIX_.'mailchimp_tracking` (
  `id_mailchimp_tracking` INT(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `id_order`              INT(11) UNSIGNED    NOT NULL,
  `mc_tc`                 VARCHAR(255)        NOT NULL,
  `mc_cid`                VARCHAR(255)        NOT NULL,
  PRIMARY KEY (`id_mailchimp_tracking`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
';
    $sql[] = 'ALTER TABLE `'._DB_PREFIX_.'mailchimp_tracking` ADD INDEX `mc_tracking_order` (`id_order`)';

    foreach ($sql as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    return true;
}
