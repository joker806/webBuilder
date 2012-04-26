<?php
namespace WebBuilder\Persistance;

use WebBuilder\DataDependencies\UndefinedData;
use WebBuilder\DataDependencies\InheritedData;
use WebBuilder\DataDependencies\ConstantData;
use WebBuilder\BlockInstance;
use WebBuilder\DataObjects\BlockSet;
use Inspirio\Database\cDatabase;

class DatabaseUpdater
{
	/**
	 * @var \cDatabase
	 */
	protected $database;

	/**
	 * Constructor
	 *
	 * @param \cDatabase $database
	 */
	public function __construct( cDatabase $database )
	{
		$this->database = $database;
	}

	/**
	 * Saves BlockInstances structure
	 *
	 * @param RequestInterface $request
	 * @return array
	 */
	public function saveBlockInstances( BlockSet $blockSet, array $clientData )
	{
		$blockSetID   = (int)$blockSet->getID();
		$tmpInstances = BlockInstance::import( $clientData );

		$this->clearDataDependencies( $blockSetID );

		$localInstances = $this->saveInstances( $blockSetID, $tmpInstances );

		$instances = array();
		foreach( $tmpInstances as $instance ) {
			$instances[ $instance->ID ] = $instance;
		}

		$this->loadMissingData( $instances );

		$this->saveInstancesData( $localInstances );

		return $instances;
	}

	private function clearDataDependencies( $blockSetID )
	{
		$sql = "
			DELETE FROM dc
			USING blocks_instances_data_constant dc
			JOIN blocks_instances bi ON ( bi.ID = dc.instance_ID )
			WHERE bi.block_set_ID = {$blockSetID}
		";
		$this->database->query( $sql );

		$sql = "
			DELETE FROM di
			USING blocks_instances_data_inherited di
			JOIN blocks_instances bi ON ( bi.ID = di.instance_ID )
			WHERE bi.block_set_ID = {$blockSetID}
		";
		$this->database->query( $sql );
	}

	private function saveInstances( $blockSetID, array $instances )
	{
		$localInstances = array();

		// destroy all parent-child relations for the current blockSet
		// (including the parent blockSet set links, excluding child blockSets links)
		$sql = "
			DELETE FROM bis
			USING blocks_instances_subblocks bis
			INNER JOIN blocks_instances bi ON ( bi.ID = bis.inserted_instance_ID )
			WHERE bi.block_set_ID = {$blockSetID}
		";
		$this->database->query( $sql );

		// update instances
		foreach( $instances as $tmpID => $instance ) {
			// local blockSet instance, save
			if( $this->isInstanceLocal( $blockSetID, $instance) ) {
				$this->saveInstance( $blockSetID, $instance );

				$localInstances[ $instance->ID ] = $instance;

			// parent blockSet instance, do nothing
			} else {
				if( $instance->ID == null ) {
					throw new \Exception( "Missing ID of the inherited block instance" );
				}
			}
		}

		// remove orphaned instances
		$sql = "DELETE FROM blocks_instances WHERE block_set_ID = {$blockSetID}";
		if( sizeof( $localInstances ) > 0 ) {
			$sql .= ' AND ID NOT IN ('. implode( ',', array_keys( $localInstances ) ) .')';
		}
		$this->database->query( $sql );

		// create parent-child links
		foreach( $instances as $instance ) {
			foreach( $instance->slots as $codeName => $children ) {
				$codeName = $this->database->escape( $codeName );

				foreach( $children as $position => $child ) {
					// skip non-local instances
					if( ! isset( $localInstances[ $child->ID ] ) ) {
						continue;
					}

					// create the parent-child link
					$sql = "
						INSERT INTO blocks_instances_subblocks ( parent_instance_ID, parent_slot_ID, position, inserted_instance_ID )
						SELECT {$instance->ID}, ID, {$position}, {$child->ID} FROM blocks_templates_slots
						WHERE template_ID = {$instance->templateID} AND code_name = '{$codeName}'
					";
					$this->database->query( $sql );
				}
			}
		}

		return $localInstances;
	}

	private function isInstanceLocal( $blockSetID, BlockInstance $instance )
	{
		return $instance->blockSetID == null || $instance->blockSetID == $blockSetID;
	}

	private function saveInstance( $blockSetID, BlockInstance $instance )
	{
		// existing instance
		if( $instance->ID ) {

			// TODO handle the template change when some subblock exist
			// new template may not have the same slots as the original one
			// so the subblock may not fit int theri positions anymore
			$sql = "
				SELECT COUNT(*) `count` FROM blocks_instances bi
				JOIN blocks_instances_subblocks bis ON ( bi.ID = bis.parent_instance_ID  )
				WHERE bi.ID = {$instance->ID} AND bi.template_ID != {$instance->templateID}
			";

			$this->database->query( $sql );
			$resultSet = $this->database->fetchArray();
			$result    = reset( $resultSet );
			if( $result['count'] && $result['count'] > 0 ) {
				throw new \Exception('Cannot change the template of block with subblock');
			}

			// update used template file
			$sql = "UPDATE blocks_instances SET template_ID = {$instance->templateID} WHERE ID = {$instance->ID}";
			$this->database->query( $sql );

		// new instance
		} else {
			// create new instance record
			$sql = "INSERT INTO blocks_instances ( block_set_ID, template_ID ) VALUES ( {$blockSetID}, {$instance->templateID} )";
			$this->database->query( $sql );

			$instance->ID = $this->database->getLastInsertedId();
		}

		$instance->blockSetID = $blockSetID;
	}

	private function loadMissingData( array $instances )
	{
		if( sizeof( $instances ) === 0 ) {
			return;
		}

		$instanceIDsStr = implode( ',', array_keys( $instances ) );

		$sql = "
			SELECT
				bi.ID       ID,
				bt.filename template_filename,
				b.ID        block_ID,
				b.code_name block_code_name

			FROM blocks_instances bi
			JOIN blocks_templates bt ON ( bt.ID = bi.template_ID )
			JOIN blocks b ON ( b.ID = bt.block_ID )

			WHERE bi.ID IN ({$instanceIDsStr})
		";
		$this->database->query( $sql );
		$resultSet = $this->database->fetchArray();

		if( sizeof( $resultSet ) !== sizeof( $instances) ) {
			// TODO better exception
			throw new \Exception("Instance count does not match");
		}

		foreach( $resultSet as $resultItem ) {
			$instanceID = (int)$resultItem['ID'];

			if( isset( $instances[ $instanceID ] ) === false ) {
				// TODO better exception
				throw new \Exception("Loaded data does not match instances");
			}

			/* @var $instance BlockInstance */
			$instance = $instances[ $instanceID ];
			$instance->templateFile = $resultItem['template_filename'];
			$instance->blockID      = (int)$resultItem['block_ID'];
			$instance->blockName    = $resultItem['block_code_name'];
		}
	}

	private function saveInstancesData( array $instances )
	{
		// save new data
		foreach( $instances as $instance ) {
			/* @var $instance BlockInstance */

			foreach( $instance->dataDependencies as $dependency ) {
				if( $dependency instanceof ConstantData ) {
					$this->saveInstanceData_constant( $instance, $dependency );

				} elseif( $dependency instanceof InheritedData ) {
					$this->saveInstanceData_inherited( $instance, $dependency );

				} elseif( $dependency instanceof UndefinedData ) {
					// do nothing

				} else {
					// TODO better exception
					throw new \Exception("Invalid data dependency");
				}
			}
		}
	}

	private function saveInstanceData_constant( BlockInstance $instance, ConstantData $data )
	{
		$value    = $this->database->escape( $data->getTargetData() );
		$property = $this->database->escape( $data->getProperty() );

		$sql = "
			INSERT INTO blocks_instances_data_constant ( instance_ID, property_ID, value )
			SELECT {$instance->ID}, ID, '{$value}' FROM blocks_data_requirements
				WHERE block_ID = {$instance->blockID} AND property = '{$property}'
		";
		$this->database->query( $sql );
	}

	private function saveInstanceData_inherited( BlockInstance $instance, InheritedData $data )
	{
		$provider         = $data->getProvider();
		$providerProperty = $this->database->escape( $data->getProviderProperty() );

		$target         = $data->getTarget();
		$targetProperty = $this->database->escape( $data->getProperty() );

		$sql = "
			INSERT INTO blocks_instances_data_inherited ( instance_ID, provider_instance_ID, provider_property_ID )
			SELECT {$instance->ID}, {$provider->ID}, bdrp.ID
			FROM blocks_data_requirements_providers bdrp
			JOIN blocks_data_requirements bdr ON ( bdr.ID = bdrp.required_property_ID )
				WHERE bdrp.provider_ID = {$provider->blockID}
				  AND bdrp.provider_property = '{$providerProperty}'
				  AND bdr.block_ID = {$target->blockID}
				  AND bdr.property = '{$targetProperty}'
		";
		$this->database->query( $sql );
	}
}