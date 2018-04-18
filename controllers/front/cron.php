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
     * @throws PrestaShopException
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

        if (substr($action, -5)  === 'Carts') {
            $entityType = 'carts';
        } elseif (substr($action, -8) === 'Products') {
            $entityType = 'products';
        } elseif (substr($action, -6) === 'Orders') {
            $entityType = 'orders';
        } else {
            die('KO');
        }

        if (strpos($action, 'ExportAll') === 0) {
            $actionType = 'ExportAll';
        } elseif (strpos($action, 'ExportRemaining') === 0) {
            $actionType = 'ExportRemaining';
        } elseif (strpos($action, 'Reset') === 0) {
            $actionType = 'Reset';
        } else {
            die('KO');
        }

        if (in_array($action, [
            'ExportAllProducts',
            'ExportRemainingProducts',
            'ExportAllCarts',
            'ExportRemainingCarts',
            'ExportAllOrders',
            'ExportRemainingOrders',
            'ResetCarts',
            'ResetProducts',
            'ResetOrders',
        ])) {
            $this->processCron($entityType, $actionType, $idShop);
        }

        die('KO');
    }

    /**
     * Handle cron functions
     * 
     * @param string $actionType
     * @param string $entityType
     * @param int    $idShop
     *
     * @throws PrestaShopException
     */
    protected function processCron($entityType, $actionType, $idShop)
    {
        if ($actionType === 'ExportAll') {
            $data = $this->module->cronExport($entityType, $idShop, false, 'start');
            for ($i = 1; $i < (int) $data['totalChunks']; $i++) {
                $this->module->cronExport($entityType, $idShop, false, 'next');
            }

            die('OK');
        } elseif ($actionType === 'ExportRemaining') {
            $data = $this->module->cronExport($entityType, $idShop, true, 'start');
            for ($i = 1; $i < (int) $data['totalChunks']; $i++) {
                $this->module->cronExport($entityType, $idShop, true, 'next');
            }

            die('OK');
        } elseif ($actionType === 'Reset') {
            if ($this->module->processReset($entityType, $idShop, false)) {
                die('OK');
            }

            die('OK');
        }

        die('KO');
    }
}
