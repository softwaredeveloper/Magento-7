<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Cardstream
 * @package    Hosted
 * @copyright  Copyright (c) 2009 - 2012 Cardstream Limited (http://www.cardstream.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

$installer = $this;

$installer->startSetup();

$tableName = $this->getTable('CardstreamHosted_Trans');

$installer->run("CREATE TABLE IF NOT EXISTS `". $tableName ."` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`customerid` varchar(255) DEFAULT NULL,
`orderid` varchar(255) DEFAULT NULL,
`transactionunique` varchar(255) DEFAULT NULL,
`amount` bigint(20) DEFAULT NULL,
`xref` varchar(255) DEFAULT NULL,
`responsecode` varchar(255) DEFAULT NULL,
`message` varchar(255) DEFAULT NULL,
`ctime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
`mtime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
`ip` varchar(255) DEFAULT NULL,
`threedsenrolled` varchar(255) DEFAULT NULL,
`threedsauthenticated` varchar(255) DEFAULT NULL,
`lastfour` varchar(4) DEFAULT NULL,
`cardtype` varchar(255) DEFAULT NULL,
`quoteid` varchar(255) DEFAULT NULL,
PRIMARY KEY (`id`));");

$installer->endSetup();
