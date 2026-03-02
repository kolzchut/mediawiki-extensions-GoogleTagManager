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

namespace MediaWiki\Extension\GoogleTagManager\Tests\Unit;

use MediaWiki\Config\Config;
use MediaWiki\Extension\GoogleTagManager\Hooks;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;
use Skin;
use User;

/**
 * @covers \MediaWiki\Extension\GoogleTagManager\Hooks
 */
class HooksTest extends MediaWikiUnitTestCase {

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function makeConfig( array $values ): Config {
		$config = $this->createMock( Config::class );
		$config->method( 'get' )
			->willReturnCallback( static function ( $key ) use ( $values ) {
				return $values[$key] ?? null;
			} );
		return $config;
	}

	private function makeDefaultConfig( array $overrides = [] ): Config {
		return $this->makeConfig( array_merge( [
			'GoogleTagManagerContainerID'    => 'GTM-XXXXXX',
			'GoogleTagManagerIgnoreNsIDs'    => [],
			'GoogleTagManagerIgnorePages'    => [],
			'GoogleTagManagerIgnoreSpecials' => [],
			'GoogleTagManagerMediaWikiEvents' => [],
		], $overrides ) );
	}

	private function makeHookContainer(): HookContainer {
		$hookContainer = $this->createMock( HookContainer::class );
		$hookContainer->method( 'run' )->willReturn( true );
		return $hookContainer;
	}

	private function makeHooks( Config $config ): Hooks {
		return new Hooks( $config, $this->makeHookContainer() );
	}

	private function makeMsgStub(): Message {
		$msg = $this->createMock( Message::class );
		$msg->method( 'text' )->willReturn( '' );
		return $msg;
	}

	/**
	 * @param array $options Keys: namespace (int), prefixedText (string),
	 *   isSpecial (callable), isAllowed (bool)
	 */
	private function makeOutputPage( array $options = [] ): OutputPage {
		$out = $this->createMock( OutputPage::class );

		$out->method( 'msg' )->willReturn( $this->makeMsgStub() );

		$user = $this->createMock( User::class );
		$user->method( 'isAllowed' )->willReturn( $options['isAllowed'] ?? false );
		$out->method( 'getUser' )->willReturn( $user );

		$title = $this->createMock( Title::class );
		$title->method( 'getNamespace' )->willReturn( $options['namespace'] ?? 0 );
		$title->method( 'getPrefixedText' )->willReturn( $options['prefixedText'] ?? 'Main_Page' );
		$isSpecialCallback = $options['isSpecial'] ?? static function () {
			return false;
		};
		$title->method( 'isSpecial' )->willReturnCallback( $isSpecialCallback );
		$out->method( 'getTitle' )->willReturn( $title );

		return $out;
	}

	private function makeSkin(): Skin {
		return $this->createMock( Skin::class );
	}

	// -----------------------------------------------------------------------
	// Disabled-path tests
	// -----------------------------------------------------------------------

	public function testDisabledWhenContainerIdIsNull() {
		$config = $this->makeConfig( [
			'GoogleTagManagerContainerID' => null,
		] );
		$hooks = $this->makeHooks( $config );
		$out = $this->makeOutputPage();
		$out->expects( $this->never() )->method( 'addHeadItem' );

		$hooks->onBeforePageDisplay( $out, $this->makeSkin() );
	}

	public function testDisabledForUserWithNoAnalytics() {
		$hooks = $this->makeHooks( $this->makeDefaultConfig() );
		$out = $this->makeOutputPage( [ 'isAllowed' => true ] );
		$out->expects( $this->never() )->method( 'addHeadItem' );

		$hooks->onBeforePageDisplay( $out, $this->makeSkin() );
	}

	public function testDisabledForIgnoredNamespace() {
		$hooks = $this->makeHooks( $this->makeDefaultConfig( [
			'GoogleTagManagerIgnoreNsIDs' => [ NS_FILE ],
		] ) );
		$out = $this->makeOutputPage( [ 'namespace' => NS_FILE ] );
		$out->expects( $this->never() )->method( 'addHeadItem' );

		$hooks->onBeforePageDisplay( $out, $this->makeSkin() );
	}

	public function testDisabledForIgnoredPage() {
		$hooks = $this->makeHooks( $this->makeDefaultConfig( [
			'GoogleTagManagerIgnorePages' => [ 'Secret_Page' ],
		] ) );
		$out = $this->makeOutputPage( [ 'prefixedText' => 'Secret_Page' ] );
		$out->expects( $this->never() )->method( 'addHeadItem' );

		$hooks->onBeforePageDisplay( $out, $this->makeSkin() );
	}

	public function testDisabledForIgnoredSpecialPage() {
		$hooks = $this->makeHooks( $this->makeDefaultConfig( [
			'GoogleTagManagerIgnoreSpecials' => [ 'Userlogin' ],
		] ) );
		$out = $this->makeOutputPage( [
			'isSpecial' => static function ( $name ) {
				return $name === 'Userlogin';
			},
		] );
		$out->expects( $this->never() )->method( 'addHeadItem' );

		$hooks->onBeforePageDisplay( $out, $this->makeSkin() );
	}

	// -----------------------------------------------------------------------
	// Enabled-path tests
	// -----------------------------------------------------------------------

	public function testInjectsScriptWithSingleContainerId() {
		$hooks = $this->makeHooks( $this->makeDefaultConfig() );
		$out = $this->makeOutputPage();

		$capturedScript = null;
		$out->expects( $this->once() )
			->method( 'addHeadItem' )
			->willReturnCallback( static function ( $name, $html ) use ( &$capturedScript ) {
				$capturedScript = $html;
			} );

		$hooks->onBeforePageDisplay( $out, $this->makeSkin() );

		$this->assertStringContainsString( 'GTM-XXXXXX', $capturedScript );
		$this->assertStringContainsString( 'gtm.js', $capturedScript );
	}

	public function testInjectsNoscriptWithSingleContainerId() {
		$hooks = $this->makeHooks( $this->makeDefaultConfig() );
		$out = $this->makeOutputPage();

		$capturedHtml = null;
		$out->expects( $this->once() )
			->method( 'addHTML' )
			->willReturnCallback( static function ( $html ) use ( &$capturedHtml ) {
				$capturedHtml = $html;
			} );

		$hooks->onBeforePageDisplay( $out, $this->makeSkin() );

		$this->assertStringContainsString( '<noscript>', $capturedHtml );
		$this->assertStringContainsString( 'ns.html', $capturedHtml );
		$this->assertStringContainsString( 'GTM-XXXXXX', $capturedHtml );
	}

	public function testInjectsScriptWithMultipleContainerIds() {
		$hooks = $this->makeHooks( $this->makeDefaultConfig( [
			'GoogleTagManagerContainerID' => [ 'GTM-AAAAA', 'GTM-BBBBB' ],
		] ) );
		$out = $this->makeOutputPage();

		$capturedScript = null;
		$out->expects( $this->once() )
			->method( 'addHeadItem' )
			->willReturnCallback( static function ( $name, $html ) use ( &$capturedScript ) {
				$capturedScript = $html;
			} );

		$hooks->onBeforePageDisplay( $out, $this->makeSkin() );

		$this->assertStringContainsString( 'GTM-AAAAA', $capturedScript );
		$this->assertStringContainsString( 'GTM-BBBBB', $capturedScript );
	}

	public function testAddsEventTrackingModuleWhenMediaWikiEventsConfigured() {
		$hooks = $this->makeHooks( $this->makeDefaultConfig( [
			'GoogleTagManagerMediaWikiEvents' => [ [ 'event' => 'click', 'selector' => '.cta' ] ],
		] ) );
		$out = $this->makeOutputPage();

		$out->expects( $this->once() )
			->method( 'addModules' )
			->with( 'ext.googleTagManager.eventTracking' );

		$hooks->onBeforePageDisplay( $out, $this->makeSkin() );
	}

	public function testDoesNotAddEventTrackingModuleWhenMediaWikiEventsEmpty() {
		$hooks = $this->makeHooks( $this->makeDefaultConfig( [
			'GoogleTagManagerMediaWikiEvents' => [],
		] ) );
		$out = $this->makeOutputPage();
		$out->expects( $this->never() )->method( 'addModules' );

		$hooks->onBeforePageDisplay( $out, $this->makeSkin() );
	}

	// -----------------------------------------------------------------------
	// ResourceLoader config vars test
	// -----------------------------------------------------------------------

	public function testResourceLoaderConfigVarsContainMediaWikiEvents() {
		$events = [ [ 'event' => 'pageview', 'action' => 'track' ] ];
		$hooks = $this->makeHooks( $this->makeDefaultConfig( [
			'GoogleTagManagerMediaWikiEvents' => $events,
		] ) );

		$vars = [];
		$hooks->onResourceLoaderGetConfigVars( $vars, 'vector', $this->createMock( Config::class ) );

		$this->assertArrayHasKey( 'wgGoogleTagManagerConfig', $vars );
		$this->assertSame( $events, $vars['wgGoogleTagManagerConfig']['MediaWikiEvents'] );
	}
}
