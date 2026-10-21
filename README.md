# API Logger for Adobe Commerce (Magento 2)

Logs the calls made to your store's REST API. Each call is journaled in the Activity Log. Its request, response, headers and timing are stored next to it, with secrets masked.

[![Latest Stable Version](https://poser.pugx.org/hryvinskyi/magento2-api-logger/v/stable)](https://packagist.org/packages/hryvinskyi/magento2-api-logger)
[![Total Downloads](https://poser.pugx.org/hryvinskyi/magento2-api-logger/downloads)](https://packagist.org/packages/hryvinskyi/magento2-api-logger)
[![PayPal donate button](https://img.shields.io/badge/paypal-donate-yellow.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=volodymyr%40hryvinskyi%2ecom&lc=UA&item_name=Magento%202%20Advanced%20API%20Logger&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted "Donate once-off to this project using Paypal")
[![Latest Unstable Version](https://poser.pugx.org/hryvinskyi/magento2-api-logger/v/unstable)](https://packagist.org/packages/hryvinskyi/magento2-api-logger)
[![License](https://poser.pugx.org/hryvinskyi/magento2-api-logger/license)](https://packagist.org/packages/hryvinskyi/magento2-api-logger)

## What it does

- Logs the REST calls you select, by endpoint and HTTP method, and optionally only some response codes.
- Shows each call on its Activity Log page: request and response headers, and a paged body viewer.
- Masks secret values before anything is stored.
- Exports a call as HAR, raw HTTP or a cURL command.
- Replays a call against this store or another one.
- Compares a call with another call to the same endpoint.
- Adds a **REST response codes** widget to the Activity Log dashboard.
- Removes old calls with the Activity Log's retention job.

## Requirements

- PHP 8.1 or higher
- Magento 2.4.x (Open Source or Adobe Commerce)
- `hryvinskyi/magento2-activity-log` `^1.0`
- `hryvinskyi/magento2-base` `^2.2`

## Installation

### Via Composer (recommended)
```bash
composer require hryvinskyi/magento2-api-logger
bin/magento module:enable Hryvinskyi_ApiLogger
bin/magento setup:upgrade
bin/magento cache:flush
```

### Manual Installation
1. Download the module from GitHub.
2. Place it in `app/code/Hryvinskyi/ApiLogger`.
3. Run:
```bash
bin/magento module:enable Hryvinskyi_ApiLogger
bin/magento setup:upgrade
bin/magento cache:flush
```

## Where things are in the admin

| What | Where |
|------|-------|
| List of logged calls | **Activity Log > REST API Logs** |
| One call | **View** on a row of the list, or the call's page in **Activity Log > All Activity** |
| Response codes widget | **Activity Log > Dashboard** |
| Settings | **Stores > Configuration > Hryvinskyi Extensions > REST API Logging**. The Activity Log pages link to it as **REST Logging Settings** |
| Secret field names | **Stores > Configuration > Hryvinskyi Extensions > Activity Log > Secrets** |

## Configuration

Go to **Stores > Configuration > Hryvinskyi Extensions > REST API Logging**.

Nothing is logged until you switch logging on and select endpoints. The Activity Log's **Record Activity** setting must be on as well. When it is off, no call is stored.

### General Settings
These settings can be set per store. A call is logged with the settings of the store in its path (`/rest/fr/V1/...` uses store `fr`). A path without a store code uses the store Magento resolves for the request.

| Option | Description | Default |
|--------|-------------|---------|
| Enable API Logging | Master switch | No |
| Enabled Endpoints | The endpoints to log, in a grouped selector with search. A stored selection with no checkbox, such as a `*` pattern or a route of a disabled module, is kept when you save | None |
| Enabled Response Codes | Log only calls with these response codes. Leave empty for all codes | All |
| Log Request Headers | Store the request headers | Yes |
| Log Request Body | Store the request body | Yes |
| Log Response Headers | Store the response headers | Yes |
| Log Response Body | Store the response body | Yes |

An async or bulk call (`/async/V1/...`, `/async/bulk/V1/...`) is logged when its synchronous endpoint is selected. Selecting `POST /V1/products` also logs `POST /async/V1/products` and `POST /async/bulk/V1/products/bySku`.

### Retention
| Option | Description | Default |
|--------|-------------|---------|
| Keep REST Calls (days) | Calls older than this are removed from the journal and from this log. `0` keeps them | 30 |

The Activity Log's retention job applies it.

### Replay
| Option | Description | Default |
|--------|-------------|---------|
| Replay Against | Base URL of the store replayed calls are sent to, for example `https://staging.example.com/`. Empty replays against this store | Empty |
| Token for That Store | Integration or admin token sent with replays to that URL | Empty |

## Usage

### Finding a call

Open **Activity Log > REST API Logs**. Filter by endpoint, method, response code, exception, IP address or date. The **Request Headers** and **Response Headers** columns are hidden by default. Turn them on from the grid's **Columns** control to filter by a header value, such as an order reference or a trace ID.

Click **View** to open the call's page in the Activity Log.

### Reading a call

The page has a **Request** tab and a **Response** tab. Each shows the URL, the stored headers and the body.

- The body is shown 500 lines at a time. **Load next 500 lines** shows more.
- The search box looks through the whole body, not only the lines on screen, and reports the number of hits.
- **Copy** copies the body. **Download body** saves it as a file.
- Masked values appear as `••••••`.

### Export

**Export** saves the call as one of:

- **HAR (.har)**, which browser dev tools can open.
- **Raw HTTP (.txt)**
- **cURL command**

The file is sent straight from memory. Nothing is written to `var/`. Its name uses the log entry id, for example `rest-call-42.har`. The URL in the file is built from the store's secure base URL, not from the stored `Host` or `X-Forwarded-*` headers.

### Replay

**Replay** sends the stored request again and shows the status and duration of the original and of the replay, with the replay's response body.

- It asks for confirmation first and shows the exact URL it will call. For a write request (POST, PUT, PATCH, DELETE) it warns that data may be created or changed.
- Without **Replay Against**, the request goes to the secure base URL of the store the call was made to. It is sent as the admin who replays it, with a token issued for that admin, not as the original caller.
- With **Replay Against**, it goes to that URL with the token from **Token for That Store**.
- The target comes from configuration and the call's store only. The stored `Host` and `X-Forwarded-*` headers never decide where a replay goes.
- `Host`, `Cookie`, `Accept-Encoding`, `Content-Length`, `Transfer-Encoding`, `Connection` and `Authorization` are not sent, and neither is any masked header.
- A call whose URL holds a masked secret cannot be replayed.
- The **Replay** button is disabled while a replay is running, so it cannot fire twice.
- A replay sent to this store is a REST call like any other. It is logged as a new call if its endpoint is selected.

### Compare

**Compare** lists other calls to the same endpoint with the same method, including calls with a different query string. Search them by a three-digit status or the start of a correlation ID. Pick one to see a line diff of the two requests and of the two responses. Each body is compared up to 256 KB. When a body was cut and the compared parts match, the dialog says "Identical in the first 256 KB".

### Removing calls

Use **Delete** on a row or the mass action in the list. The call and its activity are removed together, so you need both `Hryvinskyi_ApiLogger::delete` and `Hryvinskyi_ActivityLog::delete`. Without both, the row's **Delete** action is hidden, and the mass action shows an error and removes nothing.

## Secrets

Before a call is stored, secrets are replaced with `••••••`. A name is secret when it contains one of the secret field names. Names are compared in lower case with `-` and spaces read as `_`, so `X-Api-Key` matches `api_key` and `Set-Cookie` matches `cookie`. The list is the Activity Log's **Secret Field Names** setting and applies to every channel. The Activity Log also treats `password`, `token`, `secret`, `authorization`, `cookie`, `api_key`, `apikey` and `private_key` as secret whatever the setting says.

What is masked:

- **Headers.** The value of every secret header, such as `Authorization`, `Cookie`, `Set-Cookie` and `X-Api-Key`.
- **Bodies.** JSON, XML and form-encoded bodies are masked by field name. Any other text is masked whole when it contains a secret name. A JSON body in which nothing is masked is stored byte for byte.
- **Query string.** The value of each query key with a secret name, for example `access_token`.
- **Path.** A path segment whose route parameter is a secret field, such as the reset token in `GET /V1/customers/42/password/resetLinkToken/<token>`, is masked (`.../resetLinkToken/••••••`) in the stored endpoint and in the activity's action. This works when a named pattern selected the call. A call selected only by a `*` pattern keeps its path.
- **Whole responses.** The response of a token endpoint (a route ending in `/token` or `/tokens`, with or without a trailing `/`) is stored fully masked. So is the response of the two-factor authentication routes that issue an admin token or reveal a TOTP secret (`authenticate`, `authenticate-onetouch`, `verify` and `configure` under `/V1/tfa/provider/<provider>/`).
- **Error messages.** A secret parameter of a REST error stays a placeholder in the activity's message.

## ACL Resources

| Resource | Description |
|----------|-------------|
| `Hryvinskyi_ApiLogger::api_logger` | REST API Logs, under the Activity Log |
| `Hryvinskyi_ApiLogger::logs` | View logged calls, compare, download and export. The REST panel on the activity page needs it |
| `Hryvinskyi_ApiLogger::replay` | Replay calls |
| `Hryvinskyi_ApiLogger::delete` | Delete calls. Also needs `Hryvinskyi_ActivityLog::delete` |
| `Hryvinskyi_ApiLogger::config_api_logger` | REST API Logging settings |

Opening the call's page also needs `Hryvinskyi_ActivityLog::view`.

## Upgrading from 1.x

Run `bin/magento setup:upgrade`. Install `hryvinskyi/magento2-activity-log` first. The full list is in the [CHANGELOG](CHANGELOG.md). In short:

- Calls are journaled as activities of the `rest` channel. The detail is on the activity page.
- The "API Logger" menu is gone. The dashboard and the log view are in the Activity Log. The old dashboard and log view URLs redirect there.
- The cleanup cron job and its settings are replaced by **Keep REST Calls (days)**. The upgrade copies your retention days, or sets `0` if cleanup was off.
- Secret field names moved to the Activity Log's **Secrets** settings. The upgrade adds your 1.x names to the Activity Log's list and removes the old settings.
- ACL resources moved under the Activity Log. Check your admin roles.
- The module no longer replaces `REMOTE_ADDR` with the `CF-Connecting-IP` header. Behind Cloudflare, set the client IP header in Magento's `RemoteAddress` (`alternativeHeaders`).
- Classes removed: `CleanerInterface`, `Cleaner`, `CleanLogs`, `SanitizerInterface`, `Sanitizer`, `InterceptorInterface`, `Interceptor`, `FakeRequest`, and the `ChartData`, `Stats` and `Related` controllers. `ConfigInterface`, `EndpointUrlResolverInterface` and `Rest\ExtractorInterface` changed.

The upgrade also:

- Adds the calls 1.x stored to the journal. They get the actor "unknown (logged before 2.0)" and keep their original time. Their endpoint keeps the `/rest/...` form it was stored in. The upgrade works in batches of 500 rows.
- Masks the stored headers and bodies of those calls, using your 1.x secret names and the Activity Log's. 1.x did not mask anything because it read its secret settings from the wrong paths. This runs even when **Record Activity** is off.
- Masks the paths and query strings of those calls against the selected endpoints. A path secret is masked when a named pattern matches the call.
- Writes the Activity Log's secret list only when your 1.x names add a name to it. Otherwise that setting is left alone.
- Deletes the export files 1.x left in `var/`: `api_log_<id>.har` and `api_log_<id>.txt`. They held calls in plain text.

## Technical Details

### How It Works

A plugin on `Magento\Webapi\Controller\Rest::dispatch` records REST calls.

1. Before dispatch, it reads the endpoint, method, headers and body. The endpoint is the path after the REST front name, with the store code and the query string kept: `/V1/products?searchCriteria[pageSize]=5`, `/default/V1/products/x`. A dispatch inside another dispatch is recorded as its own call, with its own response.
2. After dispatch, it reads the response code, headers and body, and measures the duration.
3. It checks the call against the enabled endpoints and response codes of the call's store.
4. It records an activity in the `rest` channel of the Activity Log. The action is the method and the route without the store code, for example `GET /V1/products/x`. An async call has the route of the synchronous call. The message of an error response has its parameters filled in.
5. It stores the call detail, masked, in `hryvinskyi_api_log_entry`, linked to the activity.

A failure while logging is written to the Magento log and never changes the response of the REST call.

### Database Table

**Table:** `hryvinskyi_api_log_entry`

| Column | Type | Description |
|--------|------|-------------|
| entity_id | INT (PK) | Auto-increment primary key |
| endpoint | VARCHAR(512) | Path after the REST front name, with the store code, cut at 512 characters |
| query_string | TEXT, nullable | Query string, kept whole |
| method | VARCHAR(10) | HTTP method |
| request_headers | MEDIUMTEXT | JSON-encoded request headers, masked |
| request_body | MEDIUMTEXT | Request payload, masked |
| response_headers | MEDIUMTEXT | JSON-encoded response headers, masked |
| response_body | MEDIUMTEXT | Response payload, masked |
| response_code | SMALLINT | HTTP response status code |
| duration | DECIMAL(10,4) | Request duration in milliseconds |
| is_exception | BOOLEAN | Whether the response was an error |
| store_id | INT | Store of the call |
| ip_address | VARCHAR(45) | Client IP address (IPv4/IPv6) |
| user_agent | VARCHAR(512) | Client user agent string |
| created_at | TIMESTAMP | Entry creation timestamp |
| activity_id | INT | The call's activity. Unique. The row is deleted with the activity |
| correlation_id | VARCHAR(64) | Correlation ID of the activity |

The model's `getEndpoint()` returns the path and the query string joined by `?`. The **Endpoint** column of the REST API Logs grid shows the path only.

Indexed columns: `endpoint`, `method`, `response_code`, `is_exception`, `created_at`, `store_id`, `correlation_id`, and `activity_id` (unique).

## Limitations

- A store whose code is `rest` makes a call stored by 1.x ambiguous: `/rest/V1/x` is read as the front name followed by the route. A store whose code is `async` is read as the async prefix. Avoid these codes.
- Calls stored by 1.x that could not be journaled, because the activity log was off during the upgrade, are masked but have no activity page. Remove them from the REST API Logs grid.
- A secret in the path is masked only when a named pattern selected the call.
- Only REST calls handled by `Magento\Webapi\Controller\Rest` are logged. SOAP and GraphQL calls are not.

## Author

**Volodymyr Hryvinskyi**
- Email: volodymyr@hryvinskyi.com
- GitHub: https://github.com/hryvinskyi

## License

This module is licensed under the [MIT License](LICENSE).

## Support

For issues, feature requests, or questions, please contact the author or submit an issue on GitHub.
