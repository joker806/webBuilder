<?php
namespace WebBuilder;

use WebBuilder\DataObjects\BlockSet;

interface BlocksLoaderInterface
{
	/**
	 * Return complete blocks structure for given blocks set
	 *
	 * @return array
	 */
	public function loadBlockInstances();
}