# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1] - 2026-10-22

### Fixed
- With **Add Secret Key to URLs** on, the REST log's View action and the old dashboard and call links
  land on the Activity Log pages instead of the admin start page. The links were built from the
  Activity Log's URL front name, whose secret key the admin router does not accept.

### Changed
- Admin icons from Lucide, rendered inline through the Activity Log's icon renderer.

## [2.0.0] - 2026-10-21

### Breaking
- Requires `hryvinskyi/magento2-activity-log` `^1.0`.
- A REST call is now an activity of the `rest` channel in the Activity Log. The call detail
  (headers, bodies, replay, compare, export) is shown on the activity page. The
  `hryvinskyi_api_log_entry` table links to the activity by `activity_id` and `correlation_id`.
- The "API Logger" admin menu is gone. **REST API Logs** and **REST Logging Settings** are in the
  Activity Log menu.
- The dashboard URL redirects to the Activity Log dashboard. The log view URL redirects to the
  activity page of the call. The old dashboard charts are replaced by a **REST response codes**
  widget on the Activity Log dashboard.
- Retention moved to `api_logger/retention/days` (**Keep REST Calls (days)**). The Activity Log's
  daily retention job applies it. `0` keeps every call. The module's own cleanup cron job
  (`hryvinskyi_api_logger_clean_old_logs`) is removed.
- Secret field names moved to the Activity Log's **Secrets** settings
  (`hryvinskyi_activity_log/security/secret_fields`) and apply to every channel. The settings
  `api_logger/security/*`, `api_logger/cleanup/*` and `api_logger/general/sanitize_secrets` /
  `secret_fields` are removed. The upgrade moves the values: it adds your 1.x secret field names to
  the Activity Log's list, copies the retention days (or sets `0` if cleanup was off), and deletes
  the old settings.
- ACL resources moved under `Hryvinskyi_ActivityLog::activity_log`. `Hryvinskyi_ApiLogger::dashboard`
  and `Hryvinskyi_ApiLogger::config` are removed. `logs`, `replay`, `delete` and
  `config_api_logger` stay. Check your admin roles after the upgrade.
- The endpoint is stored without the REST front name and with its query string:
  `/V1/products?searchCriteria[pageSize]=5`, `/default/V1/products/x`. The path is in `endpoint`
  (cut at 512 characters) and the query string, kept whole, is in the new `query_string` column.
  `LogEntryInterface::getEndpoint()` still returns both joined by `?`. Rows written by 1.x keep
  their `/rest/...` form and are still read correctly.
- Removing calls needs `Hryvinskyi_ActivityLog::delete` as well as `Hryvinskyi_ApiLogger::delete`,
  because a call is removed with its activity. The REST panel on the activity page needs
  `Hryvinskyi_ApiLogger::logs`.
- Removed classes and interfaces: `Api\CleanerInterface`, `Model\Cleaner`, `Cron\CleanLogs`,
  `Api\SanitizerInterface`, `Model\Sanitizer`, `Api\InterceptorInterface`, `Model\Interceptor`,
  `Model\Rest\FakeRequest`, `Controller\Adminhtml\Log\ChartData`, `Stats` and `Related`, and the
  dashboard and log view blocks, templates and layouts.
- Changed interfaces:
  - `ConfigInterface` drops `shouldSanitizeSecrets()`, `getSecretFields()`, `getRetentionDays()` and
    `isCleanupEnabled()`, and adds `getReplayBaseUrl()` and `getReplayToken()`.
  - `EndpointUrlResolverInterface::resolve()` takes a log entry and an optional base URL.
  - `Rest\ExtractorInterface` takes `Magento\Framework\App\Request\Http` and
    `Magento\Framework\Webapi\Rest\Response`; `extractExceptionBody()` is removed.
  - `LogEntryInterface` adds `activity_id` and `correlation_id`; `LogEntryRepositoryInterface`
    adds `getByActivityId()`.

### Security
- The module no longer copies the `CF-Connecting-IP` header into `REMOTE_ADDR` on every request.
  Any client could send that header and set its own IP address for the whole store. Behind
  Cloudflare, configure the client IP header in Magento itself
  (`Magento\Framework\HTTP\PhpEnvironment\RemoteAddress`, argument `alternativeHeaders`).
- Secrets are masked before a call is stored. In 1.x the masking read its settings from the wrong
  config paths, so passwords and tokens were stored in plain text. Masking is always on in 2.0.
- Bodies are masked in JSON, XML and form-encoded form. Other text is masked whole when it
  contains a secret name. A JSON body in which nothing is masked is stored byte for byte.
- The masking is done by the Activity Log's sanitizer, which gains `SanitizerInterface::sanitizeBody()`
  for JSON, XML, form-encoded and plain-text bodies. It compares names normalised, and it always
  treats `password`, `token`, `secret`, `authorization`, `cookie`, `api_key`, `apikey` and
  `private_key` as secret, whatever the configured list says. `cookie` is in the default list.
- `Cookie`, `Set-Cookie` and `X-Api-Key` are masked. Secret names are compared with `-` and `_`
  read the same.
- Query-string values with secret names are masked.
- A path segment whose route parameter is a secret field, such as a password-reset token, is
  masked in the stored endpoint and in the activity's action. This needs a named pattern: a call
  selected only by a `*` pattern keeps its path.
- The response of a token endpoint (a route ending in `/token` or `/tokens`, with or without a
  trailing `/`) is stored fully masked. So is the response of the two-factor authentication routes
  that issue an admin token or reveal a TOTP secret.
- A secret parameter of a REST error stays a placeholder in the activity's message.
- A replay goes to the configured **Replay Against** URL, or to the secure base URL of the call's
  store. The stored `Host` and `X-Forwarded-*` headers are never used for a URL, in a replay, an
  export or the panel, so a caller could not point a replay, and the admin token with it, at another
  host. The replay dialog shows the exact target URL.
- A replay does not send `Host`, `Cookie`, `Accept-Encoding`, `Content-Length`,
  `Transfer-Encoding`, `Connection`, `Authorization` or any masked header. A call whose URL holds a
  masked secret cannot be replayed. The **Replay** button cannot fire twice.
- Downloads and exports are sent from memory. Nothing is written to `var/`. Export file names use
  the log entry id.
- The upgrade masks the rows 1.x stored, including their paths and query strings, checked against
  the selected endpoints. It does this even when **Record Activity** is off, using the merged secret
  list (your 1.x names plus the Activity Log's).
- The upgrade deletes the plain-text export files 1.x left in `var/` (`api_log_<id>.har` and
  `api_log_<id>.txt`).

### Fixed
- The query string is kept whole in its own `query_string` column. The **Endpoint** column of the
  REST API Logs grid shows the path only.
- Calls with a store code in the path (`/rest/default/V1/...`) use the logging settings of that
  store. 1.x read them before Magento applied the store code, so it used the default store's.
- Async and bulk calls (`/async/V1/...`, `/async/bulk/V1/...`) are logged under the synchronous
  endpoint they wrap. Selecting `POST /V1/products` also logs them.
- REST error messages show their parameters instead of `%placeholders`.
- Exports no longer leave a copy of the call in `var/`.
- A REST dispatch inside another one records each call with its own response.
- The endpoint selector keeps stored selections that have no checkbox, such as `*` patterns or the
  routes of a disabled module, when you save.
- The settings upgrade writes the Activity Log's secret list only when your 1.x names add to it.
  A fresh install keeps following the Activity Log's default.
- Compare says "Identical in the first 256 KB" when a body was cut.

### Added
- Paged body viewer (500 lines per page) with a search over the whole body, copy and download.
- Export as a cURL command, beside HAR and raw HTTP.
- Replay against another base URL with its own token (**Replay Against**, **Token for That
  Store**). A replay asks for confirmation first and warns when the call is a write request.
- Compare picks another call to the same endpoint, by status or correlation id, and shows both
  bodies as a line diff. Each body is compared up to 256 KB.
- **REST response codes** widget on the Activity Log dashboard.
- On upgrade, the calls 1.x stored are added to the journal as activities of the `rest` channel
  with the actor "unknown (logged before 2.0)".

## [1.2.1] - 2026-06-01

### Fixed
- PHP 8.4/8.5: declared explicit nullable types for the optional `$resource` and
  `$resourceCollection` constructor parameters of `Model\Config\Backend\EndpointList`
  (`Type $x = null` -> `?Type $x = null`), removing the "Implicitly marking parameter
  as nullable is deprecated" notices.

## [1.2.0] - 2026-05-29

### Added
- **Request Headers** and **Response Headers** columns on the API log grid, hidden by
  default and toggleable from the grid's "Columns" control. Each cell renders the headers
  as structured `Key: value` rows with an inline "Show all / Show less" read-more toggle
  (only the first few rows are shown until expanded), and both columns are text-filterable
  so operators can find calls by a header value (e.g. an order reference, tracking number
  or trace ID) that travels in the headers ([#3]).

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

[2.0.0]: https://github.com/hryvinskyi/magento2-api-logger/compare/1.2.1...2.0.0
[1.2.1]: https://github.com/hryvinskyi/magento2-api-logger/compare/1.2.0...1.2.1
[1.2.0]: https://github.com/hryvinskyi/magento2-api-logger/compare/1.1.4...1.2.0
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
[#3]: https://github.com/hryvinskyi/magento2-api-logger/issues/3
