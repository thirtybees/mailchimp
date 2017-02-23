<?php
/**
 * Copyright (C) 2017 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2017 thirty bees
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

spl_autoload_register(
    function ($class) {
        if (!in_array($class, [
            'MailChimpModule\\MailChimpRegisteredWebhook',
            'MailChimpModule\\MailChimpSubscriber',
            'MailChimpModule\\MailChimpShop',
            'MailChimpModule\\MailChimpProduct',
            'MailChimpModule\\MailChimpCart',
            'MailChimpModule\\MailChimpObjectModel',
            'MailChimpModule\\MailChimpOrder',
            'MailChimpModule\\MailChimp\\Batch',
            'MailChimpModule\\MailChimp\\MailChimp',
            'MailChimpModule\\MailChimp\\Webhook',
        ])) {
            return;
        }

        // project-specific namespace prefix
        $prefix = 'MailChimpModule\\';

        // base directory for the namespace prefix
        $baseDir = __DIR__.'/';

        // does the class use the namespace prefix?
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            // no, move to the next registered autoloader
            return;
        }

        // get the relative class name
        $relativeClass = substr($class, $len);

        // replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php
        $file = $baseDir.str_replace('\\', '/', $relativeClass).'.php';

        // if the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    }
);
