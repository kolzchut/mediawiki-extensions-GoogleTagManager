Google Tag Manager extension for MediaWiki
==========================================

This extension adds the Google Tag Manager snippet to every page.
Right now this extension will only work with Kol-Zchut's "Helena" skin,
as it requires the skin's unique hook for after-<body> code insertion
(which isn't available out-of-the-box, for some reason).

## Configuration ##

### Configuration options
  
| Variable                                           | Value | Explanation
|----------------------------------------------------|-------|-------------------
| $wgGoogleTagManagerContainerID                     | null  | Container ID or array of IDs
| $wgGoogleTagManagerMediaWikiEvents                 | []    | an array of MediaWiki event names, subscribed through mw.trackEvent(). Events will be pushed as-is to the dataLayer - an `event` name + a `eventData` object.
| $wgGoogleTagManagerIgnoreNsIDs                     | []    | An array of namespaces not to load Tag Manager in, e.g. `[ NS_FILE, NS_PROJECT ]`
| $wgGoogleTagManagerIgnorePages                     | []    | An array of page names no to load Tag Manager in
| $wgGoogleTagManagerIgnoreSpecials                  | `['Userlogin', 'Userlogout', 'Preferences', 'ChangePassword']`      | An array of __special__ pages not to load Tag Manager in  

### Exemption from tracking
Users with the "noanalytics" right are exempt from tracking, and this will not be
loaded for them. By default, only bots have this right.

## Extending the data layer
The extension defines a new hook, `DataLayerSetup`, which allows other extensions to add to the data layer.
See file `includes/Hooks/DataLayerSetupHook` for details.

## Changelog ##
### 0.6.0
- Add a new hook, DataLayerSetup, which allows other extensions to add to the data layer.

### 0.5.0
- Add the <noscript> parts using onBeforePageDisplay() and not a custom hook

### 0.4.0
- $wgGoogleTagManagerContainerID can now contain an array of IDs

### 0.3.0
- Add option to pass MediaWiki events (see `mw.trackSubscribe()` in
  `mediawiki.js` into the dataLayer.

### 0.2.0
Lint the code

### 0.1.0
- Initial version
