<?php

namespace MediaWiki\Extension\GoogleTagManager\Hooks;


use OutputPage;
use Skin;

/**
* @ingroup Hooks
*/

interface DataLayerSetupHook {

	/**
	 * Use this hook to override add stuff to the dataLayer.
	 *
	 * @param OutputPage $output Context output
	 * @param Skin $skin
	 * @param array &$dataLayer
	 * @return void
	 */
	public function onDataLayerSetup( OutputPage $output, Skin $skin, array &$dataLayer );
}
