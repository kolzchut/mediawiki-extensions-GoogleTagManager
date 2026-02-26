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
| $wgGoogleTagManagerMediaWikiEvents                 | []    | an array of MediaWiki event names, subscribed through mw.trackEvent(). Events will be pushed as-is to the dataLayer - an `event` name + a `eventData` object. **Can also be a mapping to rename events (see below).**
| $wgGoogleTagManagerIgnoreNsIDs                     | []    | An array of namespaces not to load Tag Manager in, e.g. `[ NS_FILE, NS_PROJECT ]`
| $wgGoogleTagManagerIgnorePages                     | []    | An array of page names no to load Tag Manager in
| $wgGoogleTagManagerIgnoreSpecials                  | `['Userlogin', 'Userlogout', 'Preferences', 'ChangePassword']`      | An array of __special__ pages not to load Tag Manager in  

### MediaWiki Events Configuration

The `$wgGoogleTagManagerMediaWikiEvents` configuration supports two formats:

#### Simple Array Format (legacy)
```php
$wgGoogleTagManagerMediaWikiEvents = [
    'survey.accepted',
    'survey.rejected',
    'user.login'
];
```

#### Mapping Format (new)
You can now use a mapping to rename events before they are sent to the dataLayer:

```php
$wgGoogleTagManagerMediaWikiEvents = [
    // Simple events (no rename)
    'user.logout',
    
    // Individual event renames
    'survey.accepted' => 'survey-accepted',
    'survey.rejected' => 'survey-rejected',
    
    // Topic prefix renames (affects all sub-events)
    'survey' => 'hello',  // 'survey.started' becomes 'hello.started'
];
```

#### How Prefix Renames Work

When you rename a topic prefix like `'survey' => 'hello'`, all events starting with that prefix will be renamed:
- `survey.started` becomes `hello.started`
- `survey.completed` becomes `hello.completed` 
- `survey` (exact match) becomes `hello`

The most specific match wins:
```php
$wgGoogleTagManagerMediaWikiEvents = [
    'survey' => 'hello',
    'survey.user' => 'user-survey'
];
```
- `survey.user.accepted` becomes `user-survey.accepted` (longest match)
- `survey.other` becomes `hello.other` (shorter match)

#### Important Warnings

⚠️ **Avoid conflicting rename rules**: Do not register both a topic and its sub-events with renames. For example, avoid:

```php
// BAD - conflicting rules
$wgGoogleTagManagerMediaWikiEvents = [
    'survey' => 'hello',           // Renames all survey.* events
    'survey.accepted' => 'accepted' // Conflicts with above
];
```

The extension will warn you in the browser console if conflicting rules are detected. The most specific rule will take precedence.

### Exemption from tracking
Users with the "noanalytics" right are exempt from tracking, and this will not be
loaded for them. By default, only bots have this right.

## Testing

The extension includes QUnit tests for the event tracking functionality. You can run them through MediaWiki's test suite or by visiting `Special:JavaScriptTest/qunit` on your wiki.

## Extending the data layer
The extension defines a new hook, `DataLayerSetup`, which allows other extensions to add to the data layer.
See file `includes/Hooks/DataLayerSetupHook` for details.

## Changelog ##
### 0.7.1
- Modernized to use service injection, instance-based hook handlers, and namespaced class imports

### 0.7.0
- MediaWiki events can now be renamed using mapping configuration (including topic prefix renames)
- Add QUnit tests for event tracking functionality
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
