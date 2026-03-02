<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */
namespace MediaWiki\Extension\GoogleTagManager;

use MediaWiki\Config\Config;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Output\OutputPage;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\Title\Title;
use MessageLocalizer;
use Skin;

class Hooks implements BeforePageDisplayHook, ResourceLoaderGetConfigVarsHook {

	public function __construct(
		private readonly Config $config,
		private readonly HookContainer $hookContainer,
	) {
	}

	/**
	 * BeforePageDisplay hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		/* Check if disabled for site / user / page */
		if ( $this->isDisabled( $out ) ) {
			return;
		}

		$dataLayerArray = [];
		( new Hooks\HookRunner( $this->hookContainer ) )->onDataLayerSetup( $out, $skin, $dataLayerArray );
		$dataLayerScript = '<script>window.dataLayer = window.dataLayer || [];';
		if ( !empty( $dataLayerArray ) ) {
			$dataLayerScript .= 'window.dataLayer.push( ' . json_encode( $dataLayerArray ) . ' );';
		}
		$dataLayerScript .= '</script>';

		$script = $dataLayerScript;
		$noscript = '';

		// Cast into array, if it's not that already
		$containerID = (array)$this->config->get( 'GoogleTagManagerContainerID' );
		foreach ( $containerID as $id ) {

			$script .= <<<SCRIPT
<!-- Google Tag Manager - ID {$id} -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$id}');</script>
<!-- End Google Tag Manager ({$id}) -->
SCRIPT;
			$script .= PHP_EOL;

			$noscript .= <<<SCRIPT
<!-- Google Tag Manager (noscript) - ID {$id} -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$id}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
SCRIPT;
			$noscript .= PHP_EOL;
		}

		$out->addHeadItem( 'GoogleTagManager', $script );
		$out->addHTML( $noscript );

		if ( !empty( $this->config->get( 'GoogleTagManagerMediaWikiEvents' ) ) ) {
			$out->addModules( 'ext.googleTagManager.eventTracking' );
		}
	}

	/**
	 * Hook: ResourceLoaderGetConfigVars called right before
	 * ResourceLoaderStartUpModule::getConfig returns
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderGetConfigVars
	 *
	 * @param array &$vars array of variables to be added into the output of the startup module.
	 * @param string $skin
	 * @param Config $config
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['wgGoogleTagManagerConfig'] = [
			'MediaWikiEvents' => $this->config->get( 'GoogleTagManagerMediaWikiEvents' )
		];
	}

	/**
	 * @param OutputPage $out
	 *
	 * @return bool|string
	 */
	private function isDisabled( OutputPage $out ): bool|string {
		$containerID = $this->config->get( 'GoogleTagManagerContainerID' );

		if ( $containerID === null ) {
			return $this->messageToComment( $out, 'googletagmanager-error-not-configured' );
		}
		if ( $out->getUser()->isAllowed( 'noanalytics' ) ) {
			return $this->messageToComment( $out, 'googletagmanager-disabled-for-user' );
		}
		if ( $this->isIgnoredPage( $out->getTitle() ) ) {
			return $this->messageToComment( $out, 'googletagmanager-disabled-for-page' );
		}

		return false;
	}

	/**
	 * @param Title $title
	 *
	 * @return bool
	 */
	private function isIgnoredPage( Title $title ): bool {
		$ignoreNsIDs = $this->config->get( 'GoogleTagManagerIgnoreNsIDs' );
		$ignorePages = $this->config->get( 'GoogleTagManagerIgnorePages' );
		$ignoreSpecials = $this->config->get( 'GoogleTagManagerIgnoreSpecials' );

		$isIgnoredSpecial = count( array_filter( $ignoreSpecials,
				static function ( $v ) use ( $title ) {
					return $title->isSpecial( $v );
				} ) ) > 0;

		return (
			$isIgnoredSpecial
			|| in_array( $title->getNamespace(), $ignoreNsIDs, true )
			|| in_array( $title->getPrefixedText(), $ignorePages, true )
		);
	}

	/**
	 * @param MessageLocalizer $context
	 * @param string $messageName
	 *
	 * @return string
	 */
	protected function messageToComment( MessageLocalizer $context, string $messageName ): string {
		if ( empty( $messageName ) ) {
			throw new \MWException( 'missing a message name!' );
		}

		return PHP_EOL . '<!-- ' . $context->msg( $messageName )->text() . ' -->' . PHP_EOL;
	}
}
