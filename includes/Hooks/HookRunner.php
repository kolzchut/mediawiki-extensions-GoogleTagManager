<?php

namespace MediaWiki\Extension\GoogleTagManager\Hooks;

use MediaWiki\HookContainer\HookContainer;

/**
 * This is a hook runner class, see docs/Hooks.md in core.
 * @internal
 */
class HookRunner implements	DataLayerSetupHook {
	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function onDataLayerSetup( \OutputPage $output, \Skin $skin, array &$dataLayer ) {
		return $this->hookContainer->run(
			'DataLayerSetup',
			[ $output, $skin, &$dataLayer ]
		);
	}
}
