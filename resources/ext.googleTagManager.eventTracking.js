( function () {
	var ts;

	mw.googleTagManager = mw.googleTagManager || {};
	ts = mw.googleTagManager.trackSearch = {
		config: [],
		registerMediaWikiEvents: function ( events ) {
			events.forEach( function ( eventName ) {
				mw.trackSubscribe( eventName, ts.sendMediaWikiEvent );
			} );
		},
		sendMediaWikiEvent: function ( topic, data ) {
			// Make sure the following are defined, or the dataLayer will keep previous values
			[ 'action', 'label', 'value' ].forEach( function ( val ) {
				data[ val ] = data[ val ] || null;
			} );
			window.dataLayer.push( {
				event: topic,
				eventData: data
			} );
		}
	};

	function init() {
		window.dataLayer = window.dataLayer || [];
		ts.config = mw.config.get( 'wgGoogleTagManagerConfig' );
		ts.registerMediaWikiEvents( ts.config.MediaWikiEvents );
	}

	init();
}() );
