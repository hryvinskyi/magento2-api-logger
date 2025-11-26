# Advanced API Logger for Adobe Commerce (Magento 2)

API logging solution for Magento 2 with granular endpoint control, secret sanitization, and advanced viewer.

## Features

## Installation

### Install via Composer:
```bash
composer require hryvinskyi/magento2-api-logger
bin/magento module:enable Hryvinskyi_ApiLogger
bin/magento setup:upgrade
bin/magento cache:flush
```

### Install via Manual Download:
1. Download the module from GitHub
2. Place it in `app/code/Hryvinskyi/ApiLogger`
3. Run:
```bash
bin/magento module:enable Hryvinskyi_ApiLogger
bin/magento setup:upgrade
bin/magento cache:flush
```

## Configuration

Navigate to **Stores > Configuration > Hryvinskyi Extensions > API Logger**

### General Settings
- **Enable API Logging**: Master on/off switch
- **Enabled Endpoints**: Select which endpoints to log using the advanced selector
- **Log HTTP Methods**: Choose which HTTP methods to log (GET, POST, etc.)
- **Log Request Headers**: Include HTTP request headers
- **Log Request Body**: Include request payload
- **Log Response Headers**: Include HTTP response headers
- **Log Response Body**: Include response payload

### Security & Sanitization
- **Sanitize Sensitive Data**: Enable automatic secret sanitization
- **Secret Field Names**: Comma-separated list of field patterns to treat as secrets

### Cleanup & Retention
- **Enable Automatic Cleanup**: Run cron job to delete old logs
- **Retention Period**: Days to keep logs (default: 30)

## Usage

### Viewing Logs

1. Navigate to **System -> Tools -> API Logger** in admin menu
2. Use grid filters to find specific logs:
   - Filter by endpoint, method, response code
   - Search by IP address, customer ID
   - Date range filtering
3. Click "View Details" to see full request/response data

### Managing Logs

- **View Details**: Click on any log entry to see formatted JSON
- **Delete Single**: Use delete action in grid or detail view
- **Mass Delete**: Select multiple entries and use mass delete action

## Author

**Volodymyr Hryvinskyi**
- Email: volodymyr@hryvinskyi.com
- GitHub: https://github.com/hryvinskyi

## Support

For issues, feature requests, or questions, please contact the author or submit an issue on GitHub.