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

use MediaWiki\MediaWikiServices;
use OutputPage;
use Skin;
use Title;

class Hooks {
	const EXTENSION_NAME = 'GoogleTagManager';
	/**
	 * @var \Config
	 */
	private static $config;

	/**
	 * BeforePageDisplay hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 *
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		global $wgGoogleTagManagerContainerID;

		/* Check if disabled for site / user / page */
		$script = self::isDisabled( $out );
		if ( $script === false ) {
			/* Else: we load the script */
			$script = '';

			// Cast into array, if it's not that already
			$wgGoogleTagManagerContainerID = (array) $wgGoogleTagManagerContainerID;
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
			}
		}

		$out->addHeadItem( 'GoogleTagManager', $script );
		$out->addModules( 'ext.googleTagManager.eventTracking' );
	}

	/**
	 * SkinHelenaBodyStart hook handler
	 * This hook is part of Skin:Helena.
	 *
	 * @param Skin $skin
	 */
	public static function onSkinHelenaBodyStart( Skin $skin ) {
		global $wgGoogleTagManagerContainerID;

		/* Check if disabled for site / user / page */
		$isDisabledReason = self::isDisabled( $skin->getOutput() );
		if ( $isDisabledReason === false ) {
			// Cast into array, if it's not that already
			$wgGoogleTagManagerContainerID = (array) $wgGoogleTagManagerContainerID;
			foreach ( $wgGoogleTagManagerContainerID as $id ) {
				echo <<<SCRIPT
<!-- Google Tag Manager (noscript) - ID {$id} -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$id}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
SCRIPT;
				echo PHP_EOL;
			}
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

	private static function isDisabled( OutputPage $out ) {
		global $wgGoogleTagManagerContainerID;

		if ( is_null( $wgGoogleTagManagerContainerID ) ) {
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

	private static function isIgnoredPage( Title $title ) {
		global $wgGoogleTagManagerIgnoreNsIDs,
			   $wgGoogleTagManagerIgnorePages,
			   $wgGoogleTagManagerIgnoreSpecials;

		$ignoreSpecials = count( array_filter( $wgGoogleTagManagerIgnoreSpecials,
				function ( $v ) use ( $title ) {
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
	 */
	protected static function messageToComment( $messageName = '' ) {
		if ( empty( $messageName ) ) {
			( new Exception( 'missing a message name!' ) );
		}

		return PHP_EOL . '<!-- ' . wfMessage( $messageName )->text() . ' -->' . PHP_EOL;
	}

	/**
	 * @param string $name
	 *
	 * @return \Config
	 * @throws \ConfigException
	 */
	protected static function getConfigVar( $name ) {
		if ( !isset( self::$config ) ) {
			self::$config = MediaWikiServices::getInstance()->getConfigFactory()
							->makeConfig( strtolower( self::EXTENSION_NAME ) );
		}

		return self::$config->get( self::EXTENSION_NAME . $name );
	}
}
