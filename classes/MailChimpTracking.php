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

use ObjectModel;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class MailChimpTracking
 *
 * @package MailChimpModule
 *
 * @since 1.1.0
 */
class MailChimpTracking extends ObjectModel
{
    /**
     * @see ObjectModel::$definition
     *
     * @since 1.1.0
     */
    public static $definition = [
        'table'   => 'mailchimp_tracking',
        'primary' => 'id_mailchimp_tracking',
        'fields'  => [
            'id_order'     => ['type' => self::TYPE_INT,    'validate' => 'isInt',    'required' => true,                   'db_type' => 'INT(11) UNSIGNED'],
            'mc_tc'        => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true,  'default' => '', 'db_type' => 'VARCHAR(255)'    ],
            'mc_cid'       => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true,  'default' => '', 'db_type' => 'VARCHAR(255)'    ],
            'landing_site' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => false, 'default' => '', 'db_type' => 'VARCHAR(255)'    ],
        ],
    ];
    // @codingStandardsIgnoreStart
    /** @var int $id_order */
    public $id_order;
    /** @var string $mc_tc */
    public $mc_tc;
    /** @var string $mc_cid */
    public $mc_cid;
    /** @var string $landing_site */
    public $landing_site;
    // @codingStandardsIgnoreEnd
}
