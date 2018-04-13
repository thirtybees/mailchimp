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
 *  @author    thirty bees <modules@thirtybees.com>
 *  @copyright 2017-2018 thirty bees
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

require_once __DIR__.'/../../config/config.inc.php';
require_once __DIR__.'/../../init.php';
require_once __DIR__.'/mailchimp.php';

if (!defined('_TB_VERSION_') && !defined('_PS_VERSION_') || php_sapi_name() !== 'cli') {
    die('no access');
}

/**
 * Process export all products
 *
 * @param int       $idShop
 * @param MailChimp $module
 *
 * @since 1.1.0
 * @throws PrestaShopException
 */
function processExportAllProducts($idShop, $module)
{
    $data = $module->cronExport('products', $idShop, false, 'start');
    for ($i = 0; $i < (int) $data['totalChunks']; $i++) {
        MailChimp::resetGuzzle();
        $module->cronExport('products', $idShop, false, 'next');
    }

    die('ok');
}

/**
 * Process export remaining products
 *
 * @param int       $idShop
 * @param MailChimp $module
 *
 * @since 1.1.0
 * @throws PrestaShopException
 */
function processExportRemainingProducts($idShop, $module)
{
    $data = $module->cronExport('products', $idShop, true, 'start');
    for ($i = 0; $i < (int) $data['totalChunks']; $i++) {
        MailChimp::resetGuzzle();
        $module->cronExport('products', $idShop, true, 'next');
    }

    die('ok');
}

/**
 * Reset product sync data
 *
 * @param int       $idShop
 * @param MailChimp $module
 *
 * @throws PrestaShopDatabaseException
 * @throws PrestaShopException
 * @since 1.0.0
 */
function processResetProducts($idShop, $module)
{
    if ($module->processReset('products', $idShop, false)) {
        die('ok');
    }

    die('fail');
}

/**
 * Process export all carts
 *
 * @param int       $idShop
 * @param MailChimp $module
 *
 * @since 1.1.0
 * @throws PrestaShopException
 */
function processExportAllCarts($idShop, $module)
{
    $data = $module->cronExport('carts', $idShop, false, 'start');
    for ($i = 0; $i < (int) $data['totalChunks']; $i++) {
        MailChimp::resetGuzzle();
        $module->cronExport('carts', $idShop, false, 'next');
    }

    die('ok');
}

/**
 * Process export remaining carts
 *
 * @param int       $idShop
 * @param MailChimp $module
 *
 * @since 1.1.0
 * @throws PrestaShopException
 */
function processExportRemainingCarts($idShop, $module)
{
    $data = $module->cronExport('carts', $idShop, true, 'start');
    for ($i = 0; $i < (int) $data['totalChunks']; $i++) {
        MailChimp::resetGuzzle();
        $module->cronExport('carts', $idShop, true, 'next');
    }

    die('ok');
}

/**
 * Rest cart sync data
 *
 * @param int       $idShop
 * @param MailChimp $module
 *
 * @throws PrestaShopDatabaseException
 * @throws PrestaShopException
 * @since 1.1.0
 */
function processResetCarts($idShop, $module)
{
    if ($module->processReset('carts', $idShop, false)) {
        die('ok');
    }

    die('fail');
}

/**
 * Process export all orders
 *
 * @param int       $idShop
 * @param MailChimp $module
 *
 * @since 1.1.0
 * @throws PrestaShopException
 */
function processExportAllOrders($idShop, $module)
{
    $data = $module->cronExport('orders', $idShop, false, 'start');
    for ($i = 0; $i < (int) $data['totalChunks']; $i++) {
        MailChimp::resetGuzzle();
        $module->cronExport('orders', $idShop, false, 'next');
    }

    die('ok');
}

/**
 * Process export remaining orders
 *
 * @param int       $idShop
 * @param MailChimp $module
 *
 * @since 1.1.0
 * @throws PrestaShopException
 */
function processExportRemainingOrders($idShop, $module)
{
    $data = $module->cronExport('orders', $idShop, true, 'start');
    for ($i = 0; $i < (int) $data['totalChunks']; $i++) {
        MailChimp::resetGuzzle();
        $module->cronExport('orders', $idShop, true, 'next');
    }

    die('ok');
}

/**
 * Reset order sync data
 *
 * @param int       $idShop
 * @param MailChimp $module
 *
 * @throws PrestaShopDatabaseException
 * @throws PrestaShopException
 * @since 1.1.0
 */
function processResetOrders($idShop, $module)
{
    if ($module->processReset('orders', $idShop, false)) {
        die('ok');
    }

    die('fail');
}

$module = Module::getInstanceByName('mailchimp');

$params = getopt('shop:action:', ['shop:', 'action:']);

if (!array_key_exists('shop', $params) || !array_key_exists('action', $params)) {
    die('fail');
}

$action = $params['action'];
$idShop = $params['shop'];
if (!$idShop) {
    $idShop = (int) Context::getContext()->shop->id;
}

if (in_array($action, [
    'ExportAllProducts',
    'ExportRemainingProducts',
    'ResetProducts',
    'ExportAllCarts',
    'ExportRemainingCarts',
    'ResetCarts',
    'ExportAllOrders',
    'ExportRemainingOrders',
    'ResetOrders',
])) {
    call_user_func('process'.$action, $idShop, $module);
}

die('fail');
