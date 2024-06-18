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

use Config;
use Exception;
use MediaWiki\MediaWikiServices;
use OutputPage;
use Skin;
use Title;

class Hooks {
	private const EXTENSION_NAME = 'GoogleTagManager';
	/**
	 * @var Config
	 */
	private static Config $config;

	/**
	 * BeforePageDisplay hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 *
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 *
	 * @throws Exception
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		global $wgGoogleTagManagerContainerID;

		/* Check if disabled for site / user / page */
		if ( self::isDisabled( $out ) ) {
			return;
		}

		$dataLayerArray = [];
		$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
		( new Hooks\HookRunner( $hookContainer ) )->onDataLayerSetup( $out, $skin, $dataLayerArray );
		$dataLayerScript = '<script>window.dataLayer = window.dataLayer || [];';
		if ( !empty( $dataLayerArray ) ) {
			$dataLayerScript .= 'window.dataLayer.push( ' . json_encode( $dataLayerArray ) . ' );';
		}
		$dataLayerScript .= '</script>';

		$script = $dataLayerScript;
		$noscript = '';

		// Cast into array, if it's not that already
		$wgGoogleTagManagerContainerID = (array)$wgGoogleTagManagerContainerID;
		foreach ( $wgGoogleTagManagerContainerID as $id ) {

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

		if ( !empty( self::getConfigVar( 'MediaWikiEvents' ) ) ) {
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
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		$vars['wgGoogleTagManagerConfig'] = [
			'MediaWikiEvents' => self::getConfigVar( 'MediaWikiEvents' )
		];
	}

	/**
	 * @param OutputPage $out
	 *
	 * @return false|string
	 * @throws Exception
	 */
	private static function isDisabled( OutputPage $out ) {
		global $wgGoogleTagManagerContainerID;

		if ( $wgGoogleTagManagerContainerID === null ) {
			return self::messageToComment( 'googletagmanager-error-not-configured' );
		}
		if ( $out->getUser()->isAllowed( 'noanalytics' ) ) {
			return self::messageToComment( 'googletagmanager-disabled-for-user' );
		}
		if ( self::isIgnoredPage( $out->getTitle() ) ) {
			return self::messageToComment( 'googletagmanager-disabled-for-page' );
		}

		return false;
	}

	/**
	 * @param Title $title
	 *
	 * @return bool
	 */
	private static function isIgnoredPage( Title $title ): bool {
		global $wgGoogleTagManagerIgnoreNsIDs,
			   $wgGoogleTagManagerIgnorePages,
			   $wgGoogleTagManagerIgnoreSpecials;

		$ignoreSpecials = count( array_filter( $wgGoogleTagManagerIgnoreSpecials,
				static function ( $v ) use ( $title ) {
					return $title->isSpecial( $v );
				} ) ) > 0;

		return (
			$ignoreSpecials
			|| in_array( $title->getNamespace(), $wgGoogleTagManagerIgnoreNsIDs, true )
			|| in_array( $title->getPrefixedText(), $wgGoogleTagManagerIgnorePages, true )
		);
	}

	/**
	 * @param string $messageName
	 *
	 * @return string
	 * @throws Exception
	 */
	protected static function messageToComment( string $messageName = '' ): string {
		if ( empty( $messageName ) ) {
			throw new \MWException( 'missing a message name!' );
		}

		return PHP_EOL . '<!-- ' . wfMessage( $messageName )->text() . ' -->' . PHP_EOL;
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 * @throws \ConfigException
	 */
	protected static function getConfigVar( string $name ) {
		if ( !isset( self::$config ) ) {
			self::$config = MediaWikiServices::getInstance()->getConfigFactory()
							->makeConfig( strtolower( self::EXTENSION_NAME ) );
		}

		return self::$config->get( self::EXTENSION_NAME . $name );
	}
}
