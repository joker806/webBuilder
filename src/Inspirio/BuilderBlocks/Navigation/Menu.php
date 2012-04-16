<?php
namespace Inspirio\BuilderBlocks\Navigation;

use Inspirio\cWebPage;

use WebBuilder\WebPageInterface;
use Inspirio\Database\cDBFeederBase;

use WebBuilder\WebBlock;

class Menu extends WebBlock
{
	public static function requires()
	{
		return array(
			'webPage' => 'cWebPage'
		);
	}

	public static function provides()
	{
		return array(
			'menuItems' => 'array[ cWebPage ]'
		);
	}

	public function setupData( cWebPage $webPage )
	{
		$webPageFeeder = new cDBFeederBase( '\\Inspirio\\cWebPage', $this->database );
		$webPages      = $webPageFeeder->indexBy( 'ID' )->get();

		$roots    = array();
		$itemsBag = array();

		foreach( $webPages as $webPage ) {
			/* @var $webPage cWebPage */
			$itemID   = $webPage->getID();
			$parentID = $webPage->getParentID();

			if( $parentID == null ) {
				$roots[] = $webPage;

			} else {
				if( isset( $itemsBag[ $parentID ] ) === false ) {
					$itemsBag[ $parentID ] = array();
				}

				$itemsBag[ $parentID ][] = $webPage;
			}
		}

		foreach( $itemsBag as $itemID => $subItems ) {
			$items[ $itemID ]->setChildren( $subItems );
		}

		return array(
			'menuItems' => $roots[0]->getChildren()
		);
	}
}