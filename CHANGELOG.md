# Release Notes for Lightning

## 5.0.2 - 2026-07-22

### Fixed
- The audit endpoint now rejects unknown `strategy` values instead of forwarding them to Google. Previously an unrecognized strategy could return a success response containing no audit data.
- The audit endpoint now requires a valid `http://` or `https://` URL. Non-web URLs such as `file://` and `javascript:` were previously passed through to the API request.
- An empty strategy is no longer accepted by the plugin settings or the dashboard widget. Yii's `in` validator skips empty values by default, so a blank strategy bypassed the allowed-values check.

### Changed
- The audit endpoint reports an error from whichever strategies were actually run, rather than only checking the `mobile` and `desktop` keys.

### Fixed
- Optimization opportunity and diagnostic descriptions now have their Markdown links (e.g. a trailing "[Learn more](…)") unwrapped to plain text, instead of leaking raw Markdown syntax and URLs into the UI.

## 5.0.0 - 2026-05-02

### Added
- Initial public release for Craft CMS 5.
- Dashboard widget for running Google PageSpeed Insights audits on any URL.
- Entry sidebar panel that automatically appears on entries with public URLs.
- Core Web Vitals reporting: FCP, LCP, TBT, CLS, and Speed Index with color-coded scores.
- Ranked optimization opportunities with estimated time savings.
- Lighthouse diagnostics for additional performance issues.
- Mobile and desktop audit strategies with a tabbed interface.
- Plugin settings screen with environment variable support for the API key.
