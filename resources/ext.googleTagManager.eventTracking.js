( function () {
	mw.googleTagManager = mw.googleTagManager || {};
	const ts = mw.googleTagManager.trackSearch = {
		config: [],
		renames: {},
		registerMediaWikiEvents: function ( eventsConfig ) {
			const prepared = ts.prepareSubscriptions( eventsConfig );

			ts.renames = prepared.renames;
			for ( let i = 0; i < prepared.subscriptions.length; i++ ) {
				mw.trackSubscribe( prepared.subscriptions[ i ], ts.sendMediaWikiEvent );
			}
		},
		prepareSubscriptions: function ( eventsConfig ) {
			const subscriptionsSet = Object.create( null );
			const renames = {};

			function isNumericLikeKey( key ) {
				return /^\d+$/.test( key );
			}

			function isValidValue( val ) {
				return typeof val === 'string' && val.length > 0;
			}

			if ( Array.isArray( eventsConfig ) ) {
				// Simple list of event names (topics), pass-through
				eventsConfig.forEach( ( name ) => {
					if ( isValidValue( name ) ) {
						subscriptionsSet[ name ] = true;
					}
				} );
			} else if ( eventsConfig && typeof eventsConfig === 'object' ) {
				Object.keys( eventsConfig ).forEach( ( key ) => {
					const val = eventsConfig[ key ];
					if ( isNumericLikeKey( key ) ) {
						// Value is a topic to subscribe to as-is (from PHP mixed arrays)
						if ( isValidValue( val ) ) {
							subscriptionsSet[ val ] = true;
						}
						return;
					}
					// For non-numeric keys, only process if key is a non-empty string
					if ( typeof key === 'string' && key.length > 0 ) {
						// Always add the key to subscriptions
						subscriptionsSet[ key ] = true;
						// Only store rename mapping if value is a non-empty string
						if ( isValidValue( val ) ) {
							renames[ key ] = val;
						}
						// If val is null/undefined/empty, we still subscribe but no rename
					}
				} );

				// Warn if a topic and one of its sub-events are both renamed
				const keys = Object.keys( renames );
				keys.forEach( ( a ) => {
					keys.forEach( ( b ) => {
						if ( a === b ) {
							return;
						}
						if ( b.indexOf( a ) === 0 && ( b.length === a.length || b.charAt( a.length ) === '.' ) ) {
							mw.log.warn( 'GoogleTagManager: conflicting rename rules for "' + a + '" and "' + b + '". Avoid registering both a topic and its sub-events with renames; the most specific will apply.' );
						}
					} );
				} );
			}

			return { subscriptions: Object.keys( subscriptionsSet ), renames: renames };
		},
		resolveRenamedTopic: function ( topic ) {
			// Exact match first
			if ( Object.prototype.hasOwnProperty.call( ts.renames, topic ) ) {
				return ts.renames[ topic ];
			}
			// Longest prefix (on dot boundary) wins
			let bestKey = null;
			Object.keys( ts.renames ).forEach( ( key ) => {
				if ( topic.indexOf( key ) === 0 && ( topic.length === key.length || topic.charAt( key.length ) === '.' ) ) {
					if ( bestKey === null || key.length > bestKey.length ) {
						bestKey = key;
					}
				}
			} );
			if ( bestKey ) {
				let remainder = topic.slice( bestKey.length ); // '' or '.suffix'
				let renamed = ts.renames[ bestKey ];
				// If renamed ends with a separator (-, _) and remainder starts with '.',
				// replace all dots with the separator
				if ( remainder.charAt( 0 ) === '.' && /[-_]$/.test( renamed ) ) {
					const separator = renamed.charAt( renamed.length - 1 );
					// Remove trailing separator from renamed, replace all dots in remainder
					renamed = renamed.slice( 0, -1 );
					remainder = remainder.replace( /\./g, separator );
				}
				return renamed + remainder;
			}
			return topic;
		},
		sendMediaWikiEvent: function ( topic, data ) {
			// Ensure data is an object
			data = data || {};
			// Make sure the following are defined, or the dataLayer will keep previous values
			[ 'action', 'label', 'value' ].forEach( ( val ) => {
				data[ val ] = data[ val ] || null;
			} );
			const renamedTopic = ts.resolveRenamedTopic( topic );
			window.dataLayer.push( {
				event: renamedTopic,
				eventData: data
			} );
		}
	};

	function init() {
		window.dataLayer = window.dataLayer || [];
		ts.config = mw.config.get( 'wgGoogleTagManagerConfig' ) || {};
		const eventsConfig = ts.config.MediaWikiEvents || [];
		ts.registerMediaWikiEvents( eventsConfig );
	}

	init();
}() );
