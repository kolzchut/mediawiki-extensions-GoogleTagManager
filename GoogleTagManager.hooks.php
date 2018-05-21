<?php

class GoogleTagManagerHooks {
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		global $wgGoogleTagManagerContainerID;

		/* Check if disabled for site / user / page */
		$script = self::isDisabled( $out );
		if ( $script === false ) {
			/* Else: we load the script */
			$script = <<<SCRIPT
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$wgGoogleTagManagerContainerID}');</script>
<!-- End Google Tag Manager -->
SCRIPT;
		}

		$script .= PHP_EOL;

		$out->addHeadItem( 'GoogleTagManager', $script );
	}

	public static function onSkinHelenaBodyStart( Skin $skin ) {
		global $wgGoogleTagManagerContainerID;

		/* Check if disabled for site / user / page */
		$isDisabledReason = self::isDisabled( $skin->getOutput() );
		if ( $isDisabledReason === false ) {
			echo <<<SCRIPT
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$wgGoogleTagManagerContainerID}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
SCRIPT;
		}
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

	protected static function messageToComment( $messageName = '' ) {
		if ( empty( $messageName ) ) {
			( new Exception( 'missing a message name!' ) );
		}

		return PHP_EOL . '<!-- ' . wfMessage( $messageName )->text() . ' -->' . PHP_EOL;

	}
}
