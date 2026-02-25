/*
 * This file is part of the MediaWiki extension GoogleTagManager.
 *
 * GoogleTagManager is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GoogleTagManager is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GoogleTagManager.  If not, see <http://www.gnu.org/licenses/>.
 */

( function () {
	QUnit.module( 'ext.googleTagManager.eventTracking', QUnit.newMwEnvironment( {
		beforeEach: function () {
			// Reset global state
			mw.googleTagManager = mw.googleTagManager || {};
			window.dataLayer = [];

			// Store original functions for restoration
			this.originalConfig = mw.config.get;
			this.originalTrackSubscribe = mw.trackSubscribe;
			this.originalLogWarn = mw.log.warn;

			// Mock mw.config.get
			mw.config.get = function ( key ) {
				if ( key === 'wgGoogleTagManagerConfig' ) {
					return { MediaWikiEvents: [] };
				}
				return {};
			};

			// Mock mw.trackSubscribe to capture subscriptions
			this.subscriptions = {};
			const self = this;
			mw.trackSubscribe = function ( topic, callback ) {
				self.subscriptions[ topic ] = callback;
			};

			// Mock mw.log.warn to capture warnings
			this.warnings = [];
			mw.log.warn = function ( msg ) {
				self.warnings.push( msg );
			};
		},
		afterEach: function () {
			// Restore original functions
			mw.config.get = this.originalConfig;
			mw.trackSubscribe = this.originalTrackSubscribe;
			mw.log.warn = this.originalLogWarn;
		}
	} ) );

	QUnit.test( 'Constructor sanity test', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		assert.ok( ts, 'trackSearch object exists' );
		assert.ok( typeof ts.registerMediaWikiEvents === 'function', 'registerMediaWikiEvents is a function' );
		assert.ok( typeof ts.prepareSubscriptions === 'function', 'prepareSubscriptions is a function' );
		assert.ok( typeof ts.resolveRenamedTopic === 'function', 'resolveRenamedTopic is a function' );
		assert.ok( typeof ts.sendMediaWikiEvent === 'function', 'sendMediaWikiEvent is a function' );
	} );

	QUnit.test( 'prepareSubscriptions with empty config', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		let result = ts.prepareSubscriptions( [] );

		assert.deepEqual( result.subscriptions, [], 'Empty array returns empty subscriptions' );
		assert.deepEqual( result.renames, {}, 'Empty array returns empty renames' );

		result = ts.prepareSubscriptions( {} );
		assert.deepEqual( result.subscriptions, [], 'Empty object returns empty subscriptions' );
		assert.deepEqual( result.renames, {}, 'Empty object returns empty renames' );
	} );

	QUnit.test( 'prepareSubscriptions with array config', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		const config = [ 'survey.accepted', 'survey.rejected', 'user.login' ];
		const result = ts.prepareSubscriptions( config );

		assert.deepEqual(
			result.subscriptions.sort(),
			[ 'survey.accepted', 'survey.rejected', 'user.login' ].sort(),
			'Array items become subscriptions'
		);
		assert.deepEqual( result.renames, {}, 'Array config has no renames' );
	} );

	QUnit.test( 'prepareSubscriptions with mapping config', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		const config = {
			'survey.accepted': 'survey-accepted',
			'survey.rejected': 'survey-rejected',
			'user.login': 'user-login'
		};
		const result = ts.prepareSubscriptions( config );

		assert.deepEqual(
			result.subscriptions.sort(),
			[ 'survey.accepted', 'survey.rejected', 'user.login' ].sort(),
			'Original keys become subscriptions'
		);
		assert.deepEqual( result.renames, {
			'survey.accepted': 'survey-accepted',
			'survey.rejected': 'survey-rejected',
			'user.login': 'user-login'
		}, 'Mapping values become renames' );
	} );

	QUnit.test( 'prepareSubscriptions with mixed array/mapping (PHP-style)', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		const config = {
			0: 'plain.event',
			1: 'another.event',
			'survey.accepted': 'survey-accepted',
			'user.login': 'user-login'
		};
		const result = ts.prepareSubscriptions( config );

		assert.deepEqual(
			result.subscriptions.sort(),
			[ 'plain.event', 'another.event', 'survey.accepted', 'user.login' ].sort(),
			'Both numeric and string keys become subscriptions'
		);
		assert.deepEqual( result.renames, {
			'survey.accepted': 'survey-accepted',
			'user.login': 'user-login'
		}, 'Only non-numeric keys get rename mappings' );
	} );

	QUnit.test( 'prepareSubscriptions filters invalid entries', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		const config = {
			'valid.event': 'valid-event',
			'': 'empty-key',
			'null.value': null,
			'undefined.value': undefined,
			'empty.value': '',
			0: '',
			1: null,
			2: 'valid.numeric'
		};
		const result = ts.prepareSubscriptions( config );

		assert.deepEqual(
			result.subscriptions.sort(),
			[ 'empty.value', 'null.value', 'undefined.value', 'valid.event', 'valid.numeric' ].sort(),
			'Filters out invalid entries (empty keys and numeric keys with invalid values), but keeps valid keys with invalid values'
		);
		assert.deepEqual( result.renames, {
			'valid.event': 'valid-event'
		}, 'Only valid renames are stored' );
	} );

	QUnit.test( 'prepareSubscriptions warns on conflicting renames', function ( assert ) {
		const ts = mw.googleTagManager.trackSearch;
		const config = {
			survey: 'hello',
			'survey.accepted': 'survey-accepted',
			'survey.rejected': 'survey-rejected',
			user: 'person',
			'user.profile': 'profile'
		};

		ts.prepareSubscriptions( config );

		assert.ok( this.warnings.length > 0, 'Warnings were generated' );

		const surveyWarnings = this.warnings.filter( ( warning ) => warning.includes( 'survey' ) && warning.includes( 'conflicting rename rules' ) );
		const userWarnings = this.warnings.filter( ( warning ) => warning.includes( 'user' ) && warning.includes( 'conflicting rename rules' ) );

		assert.ok( surveyWarnings.length > 0, 'Warning generated for survey conflicts' );
		assert.ok( userWarnings.length > 0, 'Warning generated for user conflicts' );
	} );

	QUnit.test( 'resolveRenamedTopic with no renames', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		ts.renames = {};

		assert.strictEqual( ts.resolveRenamedTopic( 'survey.accepted' ), 'survey.accepted', 'Returns original topic when no renames' );
		assert.strictEqual( ts.resolveRenamedTopic( 'user.login' ), 'user.login', 'Returns original topic when no renames' );
	} );

	QUnit.test( 'resolveRenamedTopic with exact match', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		ts.renames = {
			'survey.accepted': 'survey-accepted',
			'user.login': 'user-login'
		};

		assert.strictEqual( ts.resolveRenamedTopic( 'survey.accepted' ), 'survey-accepted', 'Returns exact rename match' );
		assert.strictEqual( ts.resolveRenamedTopic( 'user.login' ), 'user-login', 'Returns exact rename match' );
		assert.strictEqual( ts.resolveRenamedTopic( 'other.event' ), 'other.event', 'Returns original when no match' );
	} );

	QUnit.test( 'resolveRenamedTopic with prefix match', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		ts.renames = {
			survey: 'hello',
			user: 'person'
		};

		assert.strictEqual( ts.resolveRenamedTopic( 'survey.started' ), 'hello.started', 'Applies prefix rename to sub-event' );
		assert.strictEqual( ts.resolveRenamedTopic( 'survey.completed' ), 'hello.completed', 'Applies prefix rename to sub-event' );
		assert.strictEqual( ts.resolveRenamedTopic( 'survey' ), 'hello', 'Applies prefix rename to exact topic' );
		assert.strictEqual( ts.resolveRenamedTopic( 'user.profile' ), 'person.profile', 'Applies prefix rename to sub-event' );
		assert.strictEqual( ts.resolveRenamedTopic( 'surveyother' ), 'surveyother', 'Does not match partial prefix' );
		assert.strictEqual( ts.resolveRenamedTopic( 'other.survey' ), 'other.survey', 'Does not match suffix' );
	} );

	QUnit.test( 'resolveRenamedTopic with longest prefix wins', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		ts.renames = {
			survey: 'hello',
			'survey.user': 'user-survey',
			'survey.user.profile': 'profile'
		};

		assert.strictEqual( ts.resolveRenamedTopic( 'survey.user.profile.edit' ), 'profile.edit', 'Uses longest matching prefix' );
		assert.strictEqual( ts.resolveRenamedTopic( 'survey.user.accepted' ), 'user-survey.accepted', 'Uses second longest when longest doesn\'t match' );
		assert.strictEqual( ts.resolveRenamedTopic( 'survey.other' ), 'hello.other', 'Uses shortest prefix when others don\'t match' );
		assert.strictEqual( ts.resolveRenamedTopic( 'survey.user.profile' ), 'profile', 'Exact match with longest prefix' );
	} );

	QUnit.test( 'resolveRenamedTopic with dash separator replacement', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		ts.renames = {
			'cassandra.tabs': 'tabs-',
			'survey.user': 'user-survey-'
		};

		assert.strictEqual( ts.resolveRenamedTopic( 'cassandra.tabs.open' ), 'tabs-open', 'Replaces dot with dash when rename ends with dash' );
		assert.strictEqual( ts.resolveRenamedTopic( 'cassandra.tabs.close' ), 'tabs-close', 'Replaces dot with dash when rename ends with dash' );
		assert.strictEqual( ts.resolveRenamedTopic( 'survey.user.profile.view' ), 'user-survey-profile-view', 'Replaces dots with dashes in multi-level events' );
		assert.strictEqual( ts.resolveRenamedTopic( 'cassandra.tabs' ), 'tabs-', 'Exact match returns rename as-is' );
	} );

	QUnit.test( 'resolveRenamedTopic with underscore separator replacement', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		ts.renames = {
			'analytics.events': 'events_',
			'user.actions': 'actions_'
		};

		assert.strictEqual( ts.resolveRenamedTopic( 'analytics.events.click' ), 'events_click', 'Replaces dot with underscore when rename ends with underscore' );
		assert.strictEqual( ts.resolveRenamedTopic( 'user.actions.submit.form' ), 'actions_submit_form', 'Replaces dots with underscores in multi-level events' );
	} );

	QUnit.test( 'resolveRenamedTopic preserves dots when rename does not end with separator', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		ts.renames = {
			survey: 'hello',
			user: 'person'
		};

		assert.strictEqual( ts.resolveRenamedTopic( 'survey.started' ), 'hello.started', 'Preserves dot when rename does not end with separator' );
		assert.strictEqual( ts.resolveRenamedTopic( 'user.profile' ), 'person.profile', 'Preserves dot when rename does not end with separator' );
	} );

	QUnit.test( 'sendMediaWikiEvent with no renames', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		ts.renames = {};

		ts.sendMediaWikiEvent( 'survey.accepted', { value: 5, action: 'click' } );

		assert.strictEqual( window.dataLayer.length, 1, 'One event pushed to dataLayer' );
		assert.strictEqual( window.dataLayer[ 0 ].event, 'survey.accepted', 'Original event name used' );
		assert.strictEqual( window.dataLayer[ 0 ].eventData.value, 5, 'Event data preserved' );
		assert.strictEqual( window.dataLayer[ 0 ].eventData.action, 'click', 'Event data preserved' );
		assert.strictEqual( window.dataLayer[ 0 ].eventData.label, null, 'Default null value set' );
	} );

	QUnit.test( 'sendMediaWikiEvent with exact rename', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		ts.renames = {
			'survey.accepted': 'survey-accepted'
		};

		ts.sendMediaWikiEvent( 'survey.accepted', { value: 10 } );

		assert.strictEqual( window.dataLayer[ 0 ].event, 'survey-accepted', 'Renamed event name used' );
		assert.strictEqual( window.dataLayer[ 0 ].eventData.value, 10, 'Event data preserved' );
	} );

	QUnit.test( 'sendMediaWikiEvent with prefix rename', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		ts.renames = {
			survey: 'hello'
		};

		ts.sendMediaWikiEvent( 'survey.started', { action: 'begin' } );

		assert.strictEqual( window.dataLayer[ 0 ].event, 'hello.started', 'Prefix rename applied' );
		assert.strictEqual( window.dataLayer[ 0 ].eventData.action, 'begin', 'Event data preserved' );
	} );

	QUnit.test( 'sendMediaWikiEvent handles missing data', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		ts.renames = {};

		ts.sendMediaWikiEvent( 'test.event' );

		assert.strictEqual( window.dataLayer[ 0 ].event, 'test.event', 'Event name set correctly' );
		assert.deepEqual( window.dataLayer[ 0 ].eventData, {
			action: null,
			label: null,
			value: null
		}, 'Default null values set when no data provided' );
	} );

	QUnit.test( 'registerMediaWikiEvents subscribes to all topics', function ( assert ) {
		const ts = mw.googleTagManager.trackSearch;
		const config = {
			survey: 'hello',
			'survey.accepted': 'survey-accepted',
			'user.login': 'user-login'
		};

		ts.registerMediaWikiEvents( config );

		assert.ok( this.subscriptions.survey, 'Subscribed to survey topic' );
		assert.ok( this.subscriptions[ 'survey.accepted' ], 'Subscribed to survey.accepted topic' );
		assert.ok( this.subscriptions[ 'user.login' ], 'Subscribed to user.login topic' );
		assert.strictEqual( Object.keys( this.subscriptions ).length, 3, 'Subscribed to exactly 3 topics' );
	} );

	QUnit.test( 'registerMediaWikiEvents stores renames', ( assert ) => {
		const ts = mw.googleTagManager.trackSearch;
		const config = {
			survey: 'hello',
			'user.login': 'user-login'
		};

		ts.registerMediaWikiEvents( config );

		assert.deepEqual( ts.renames, {
			survey: 'hello',
			'user.login': 'user-login'
		}, 'Renames stored correctly' );
	} );

	QUnit.test( 'Integration test: complete event flow', function ( assert ) {
		const ts = mw.googleTagManager.trackSearch;
		const config = {
			survey: 'hello',
			'user.login': 'user-login',
			0: 'plain.event'
		};

		// Register events
		ts.registerMediaWikiEvents( config );

		// Simulate events being fired through the subscription callbacks
		this.subscriptions.survey( 'survey.started', { action: 'begin', value: 1 } );
		this.subscriptions[ 'user.login' ]( 'user.login', { userId: 123 } );
		this.subscriptions[ 'plain.event' ]( 'plain.event', { data: 'test' } );

		// Verify dataLayer contents
		assert.strictEqual( window.dataLayer.length, 3, 'Three events in dataLayer' );

		assert.strictEqual( window.dataLayer[ 0 ].event, 'hello.started', 'First event uses prefix rename' );
		assert.strictEqual( window.dataLayer[ 0 ].eventData.action, 'begin', 'First event data preserved' );

		assert.strictEqual( window.dataLayer[ 1 ].event, 'user-login', 'Second event uses exact rename' );
		assert.strictEqual( window.dataLayer[ 1 ].eventData.userId, 123, 'Second event data preserved' );

		assert.strictEqual( window.dataLayer[ 2 ].event, 'plain.event', 'Third event uses original name' );
		assert.strictEqual( window.dataLayer[ 2 ].eventData.data, 'test', 'Third event data preserved' );
	} );

	QUnit.test( 'Integration test: conflict detection in real usage', function ( assert ) {
		const ts = mw.googleTagManager.trackSearch;
		const config = {
			survey: 'hello',
			'survey.accepted': 'accepted',
			'survey.rejected': 'rejected'
		};

		// This should generate warnings
		ts.registerMediaWikiEvents( config );

		// Verify warnings were generated
		assert.ok( this.warnings.length > 0, 'Conflict warnings generated' );

		// Test that most specific rename wins
		this.subscriptions.survey( 'survey.accepted', { test: true } );

		assert.strictEqual( window.dataLayer[ 0 ].event, 'accepted', 'Most specific rename wins over prefix' );
	} );

	QUnit.test( 'Integration test: dash separator replacement', function ( assert ) {
		const ts = mw.googleTagManager.trackSearch;
		const config = {
			'cassandra.tabs': 'tabs-',
			'analytics.events': 'events_',
			'user.profile': 'profile'
		};

		ts.registerMediaWikiEvents( config );

		// Simulate events with dash separator
		this.subscriptions[ 'cassandra.tabs' ]( 'cassandra.tabs.open', { action: 'open' } );
		this.subscriptions[ 'cassandra.tabs' ]( 'cassandra.tabs.close', { action: 'close' } );

		// Simulate events with underscore separator
		this.subscriptions[ 'analytics.events' ]( 'analytics.events.click', { value: 1 } );

		// Simulate events with dot preservation
		this.subscriptions[ 'user.profile' ]( 'user.profile.view', { userId: 123 } );

		assert.strictEqual( window.dataLayer.length, 4, 'Four events in dataLayer' );
		assert.strictEqual( window.dataLayer[ 0 ].event, 'tabs-open', 'Dash separator applied to first event' );
		assert.strictEqual( window.dataLayer[ 1 ].event, 'tabs-close', 'Dash separator applied to second event' );
		assert.strictEqual( window.dataLayer[ 2 ].event, 'events_click', 'Underscore separator applied' );
		assert.strictEqual( window.dataLayer[ 3 ].event, 'profile.view', 'Dot preserved when rename does not end with separator' );
	} );
}() );
