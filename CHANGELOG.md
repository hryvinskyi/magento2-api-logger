# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.4] - 2026-05-21

### Fixed
- API requests triggered from CLI commands (`bin/magento <command>`) or Magento cron
  are now logged correctly. `RemoteAddress::getRemoteAddress()` returns `false` in CLI
  context, which raised a `TypeError` against the `?string` `setIpAddress()` signature
  and aborted the log write; the value is now coerced to `null` ([#2]).

## [1.1.3] - 2026-05-20

### Added
- `DateFormatterInterface` / `DateFormatter` service that converts stored UTC log
  timestamps into the configured Magento timezone for admin display.

### Fixed
- API log **Created At** timestamps are now shown in the configured Magento timezone
  (`general/locale/timezone`) instead of the raw UTC database value, across the log
  grid, detail view, related logs and the compare modal ([#1]).
- Log grid **Created At** date-range filter no longer renders a malformed value — the
  custom datetime format that the date picker could not parse has been removed, so the
  column now uses the locale-aware medium date/time format.
- Dashboard request-volume chart now groups requests by calendar day in the configured
  timezone instead of UTC.
- HAR export `startedDateTime` is now emitted as a valid ISO 8601 timestamp.

## [1.1.2] - 2026-02-18

### Fixed
- curl copy button in the log detail view.

## [1.1.1] - 2026-02-17

### Added
- System configuration handler.

## [1.1.0] - 2026-02-17

### Added
- Dashboard, request replay, and an advanced log viewer.

## [1.0.3] - 2025-11-26

### Fixed
- Endpoint matcher.

## [1.0.2] - 2025-11-26

### Added
- "Select all" checkbox to each endpoint group header.

## [1.0.1] - 2025-11-26

### Added
- Cloudflare compatibility.

## [1.0.0] - 2025-11-26

### Added
- Initial release.

[1.1.4]: https://github.com/hryvinskyi/magento2-api-logger/compare/1.1.3...1.1.4
[1.1.3]: https://github.com/hryvinskyi/magento2-api-logger/compare/1.1.2...1.1.3
[1.1.2]: https://github.com/hryvinskyi/magento2-api-logger/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/hryvinskyi/magento2-api-logger/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/hryvinskyi/magento2-api-logger/compare/1.0.3...1.1.0
[1.0.3]: https://github.com/hryvinskyi/magento2-api-logger/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/hryvinskyi/magento2-api-logger/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/hryvinskyi/magento2-api-logger/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/hryvinskyi/magento2-api-logger/releases/tag/1.0.0
[#1]: https://github.com/hryvinskyi/magento2-api-logger/issues/1
[#2]: https://github.com/hryvinskyi/magento2-api-logger/issues/2
