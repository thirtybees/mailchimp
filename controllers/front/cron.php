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

if (!defined('_TB_VERSION_') && !defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class MailChimpCronModuleFrontController
 *
 * @since 1.1.0
 */
class MailChimpCronModuleFrontController extends ModuleFrontController
{
    /** @var MailChimp $module */
    public $module;

    /**
     * Initialize content
     *
     * @return void
     *
     * @since 1.1.0
     */
    public function initContent()
    {
        $token = Tools::getValue('token');
        if ($token !== Tools::substr(Tools::encrypt($this->module->name.'/cron'), 0, 10)) {
            die('invalid token');
        }

        $action = ucfirst(Tools::getValue('action'));
        $idShop = (int) Tools::getValue('id_shop');
        if (!$idShop) {
            $idShop = (int) Context::getContext()->shop->id;
        }

        if (in_array($action, [
            'ExportAllProducts',
            'ExportAllCarts',
            'ExportAllOrders',
            'ExportRemainingProducts',
            'ExportRemainingCarts',
            'ExportRemainingOrders',
        ])) {
            $this->{'process'.$action}($idShop);
        }

        die('N/A');
    }

    protected function processExportAllProducts($idShop)
    {
        die('ok');
    }

    protected function processExportAllCarts($idShop)
    {
        die('ok');
    }

    protected function processExportAllOrders($idShop)
    {
        die('ok');
    }

    protected function processExportRemainingProducts($idShop)
    {
        die('ok');
    }

    protected function processExportRemainingCarts($idShop)
    {
        die('ok');
    }

    protected function processExportRemainingOrders($idShop)
    {
        die('ok');
    }
}