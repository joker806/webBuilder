<?php
namespace WebBuilder\DataDependency;

use WebBuilder\BlockInstance;
use WebBuilder\DataDependencyInterface;
use WebBuilder\DataIntegrityException;

class InheritedData implements DataDependencyInterface
{
	/**
	 * Data target
	 *
	 * @var \WebBuilder\BlockInstance
	 */
	protected $target;

	/**
	 * Data target property
	 *
	 * @var string
	 */
	protected $targetProperty;

	/**
	 * Data provider
	 *
	 * @var \WebBuilder\BlockInstance
	 */
	protected $provider;

	/**
	 * Data provider property
	 *
	 * @var string
	 */
	protected $providerProperty;

	/**
	 * Constructor
	 *
	 * @param BlockInstance $target
	 * @param string         $targetProperty
	 * @param BlockInstance $provider
	 * @param string         $providerProperty
	 */
	public function __construct( BlockInstance $target, $targetProperty, BlockInstance $provider, $providerProperty )
	{
		$this->target           = $target;
		$this->targetProperty   = $targetProperty;
		$this->provider         = $provider;
		$this->providerProperty = $providerProperty;
	}

	/**
	 * Returns target block instance
	 *
	 * @return BlockInstance
	 */
	public function getTarget()
	{
		return $this->target;
	}

	/**
	 * Returns target block property name
	 *
	 * @return string
	 */
	public function getProperty()
	{
		return $this->targetProperty;
	}

	/**
	 * Returns data provider
	 *
	 * @return \WebBuilder\BlockInstance|null
	 */
	public function getProvider()
	{
		return $this->provider;
	}

	/**
	 * Returns data provider property name
	 *
	 * @return string
	 */
	public function getProviderProperty()
	{
		return $this->providerProperty;
	}

	/**
	 * Returns dependency target data
	 *
	 * TODO grammar validation, more complex grammar
	 *
	 * @return mixed
	 *
	 * @throws DataIntegrityException
	 */
	public function getTargetData()
	{
		$data     = $this->provider->data;
		$path     = explode( '.', $this->providerProperty );
		$property = array_shift( $path );

		if( array_key_exists( $property, $data ) === false ) {
			throw new DataIntegrityException( "'{$this->target}' requires invalid data '{$property}' from '{$this->provider}'" );
		}

		$data = $data[ $property ];

		$propsStack = $property;
		while( $part = array_shift( $path ) ) {
			$methodName  = 'get'.ucfirst( $part );
			$propsStack .= '.'.$part;

			if( is_callable( array( $data, $methodName ) ) === false ) {
				throw new DataIntegrityException( "'{$this->target}' requires invalid data '{$propsStack}' from '{$this->provider}'" );
			}

			$data = $data->$methodName();
		}

		return $data;
	}

	/**
	 * Exports internal data for client-side usage
	 *
	 * @return mixed
	 */
	public function export()
	{
		return array(
			'providerID'       => $this->provider->ID,
			'providerProperty' => $this->providerProperty
		);
	}
}