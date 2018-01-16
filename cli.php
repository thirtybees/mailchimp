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
 *  @copyright 2017-2018 thirty bees
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

require_once __DIR__.'/../../config/config.inc.php';
require_once __DIR__.'/../../init.php';

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
 */
function processExportAllProducts($idShop, $module)
{
    $data = $module->cronExportProducts($idShop, false, 'start');
    for ($i = 0; $i < (int) $data['totalChunks']; $i++) {
        $module->cronExportProducts($idShop, false, 'next');
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
 */
function processExportRemainingProducts($idShop, $module)
{
    $data = $module->cronExportProducts($idShop, true, 'start');
    for ($i = 0; $i < (int) $data['totalChunks']; $i++) {
        $module->cronExportProducts($idShop, true, 'next');
    }

    die('ok');
}

/**
 * Reset product sync data
 *
 * @param int       $idShop
 * @param MailChimp $module
 *
 * @since 1.0.0
 */
function processResetProducts($idShop, $module)
{
    if ($module->processResetProducts($idShop, false)) {
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
 */
function processExportAllCarts($idShop, $module)
{
    $data = $module->cronExportCarts($idShop, false, 'start');
    for ($i = 0; $i < (int) $data['totalChunks']; $i++) {
        $module->cronExportCarts($idShop, false, 'next');
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
 */
function processExportRemainingCarts($idShop, $module)
{
    $data = $module->cronExportCarts($idShop, true, 'start');
    for ($i = 0; $i < (int) $data['totalChunks']; $i++) {
        $module->cronExportCarts($idShop, true, 'next');
    }

    die('ok');
}

/**
 * Rest cart sync data
 *
 * @param int       $idShop
 * @param MailChimp $module
 *
 * @since 1.1.0
 */
function processResetCarts($idShop, $module)
{
    if ($module->processResetCarts($idShop, false)) {
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
 */
function processExportAllOrders($idShop, $module)
{
    $data = $module->cronExportOrders($idShop, false, 'start');
    for ($i = 0; $i < (int) $data['totalChunks']; $i++) {
        $module->cronExportOrders($idShop, false, 'next');
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
 */
function processExportRemainingOrders($idShop, $module)
{
    $data = $module->cronExportOrders($idShop, true, 'start');
    for ($i = 0; $i < (int) $data['totalChunks']; $i++) {
        $module->cronExportOrders($idShop, true, 'next');
    }

    die('ok');
}

/**
 * Reset order sync data
 *
 * @param int       $idShop
 * @param MailChimp $module
 *
 * @since 1.1.0
 */
function processResetOrders($idShop, $module)
{
    if ($module->processResetOrders($idShop, false)) {
        die('ok');
    }

    die('fail');
}

$module = Module::getInstanceByName('mailchimp');

$params = getopt('shop:action:', ['shop:', 'action:']);

if (!array_key_exists('shop', $params) || !array_key_exists('shop', $params)) {
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
