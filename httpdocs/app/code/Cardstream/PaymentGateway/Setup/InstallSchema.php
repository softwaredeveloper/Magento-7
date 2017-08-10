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
 * @package    PaymentGateway
 * @copyright  Copyright (c) 2017 Cardstream
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Cardstream\PaymentGateway\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * @codeCoverageIgnore
 */
class InstallSchema implements InstallSchemaInterface
{

	/**
	 * {@inheritdoc}
	 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
	 */
	public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
	{
		$installer = $setup;

		$installer->startSetup();

		/**
		 * Create table 'Cardstream_Trans'
		 */
		if (!$installer->getConnection()->isTableExists($installer->getTable('cardstream_trans'))) {
			$table = $installer->getConnection()->newTable(
				$installer->getTable('cardstream_trans')
			)->addColumn(
				'id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				null,
				['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
				'ID'
			)->addColumn(
				'customerid',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Customer Id'
			)->addColumn(
				'orderid',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Order Id'
			)->addColumn(
				'transactionunique',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Transaction Unique'
			)->addColumn(
				'amount',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'20,6',
				['nullable' => false, 'default' => '0.000000'],
				'Amount'
			)->addColumn(
				'xref',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Xref'
			)->addColumn(
				'responsecode',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Response Code'
			)->addColumn(
				'message',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Response Message'
			)->addColumn(
				'ctime',
				\Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
				null,
				['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
				'Created Time'
			)->addColumn(
				'mtime',
				\Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
				null,
				['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
				'Modified Time'
			)->addColumn(
				'ip',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'IP Address'
			)->addColumn(
				'threedsenrolled',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Threedsenrolled'
			)->addColumn(
				'threedsauthenticated',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Threedsauthenticated'
			)->addColumn(
				'lastfour',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Lastfour'
			)->addColumn(
				'cardtype',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Card Type'
			)->addColumn(
				'quoteid',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Quote Id'
			);
			$installer->getConnection()->createTable($table);
		}
		$installer->endSetup();

	}
}
