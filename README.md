# Lightning

Google PageSpeed Insights for Craft CMS 5. Get performance scores, Core Web Vitals, and optimization suggestions right inside the control panel.

## Features

- **Dashboard widget** — monitor any URL with a one-click audit
- **Entry sidebar panel** — automatically appears on entries with public URLs
- **Core Web Vitals** — FCP, LCP, TBT, CLS, and Speed Index with color-coded scores
- **Optimization suggestions** — ranked opportunities with estimated time savings
- **Diagnostics** — additional performance issues flagged by Lighthouse
- **Mobile & Desktop** — audit both strategies with a tabbed interface
- **Environment variable support** — store your API key in `.env`

## Requirements

- Craft CMS 5.3.0 or later
- PHP 8.2 or later
- A Google PageSpeed Insights API key

## Installation

Add the repository and require the package with Composer:

```sh
composer require justinholtweb/craft-lightning
```

Then install the plugin:

```sh
php craft plugin/install lightning
```

Or install from the control panel under **Settings > Plugins**.

## Setup

1. Get a free API key from the [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Enable the **PageSpeed Insights API** in your Google Cloud project
3. In the Craft control panel, go to **Settings > Lightning**
4. Enter your API key (or use an environment variable like `$GOOGLE_PSI_API_KEY`)
5. Choose a default strategy (Mobile, Desktop, or Both)

### Using an environment variable

Add to your `.env` file:

```
GOOGLE_PSI_API_KEY=your-key-here
```

Then enter `$GOOGLE_PSI_API_KEY` in the plugin settings.

## Usage

### Dashboard Widget

1. Go to the **Dashboard**
2. Click **New Widget > PageSpeed Insights**
3. Enter the URL to audit and choose a strategy
4. Click **Run Audit** to see results

Add multiple widgets to monitor different pages.

### Entry Sidebar

The sidebar panel appears automatically on any entry that has a public URL. Click **Run Audit** to get the current performance data for that entry's page.

## Scores

Scores follow Google's thresholds:

| Range | Rating |
|-------|--------|
| 90–100 | Good (green) |
| 50–89 | Needs Improvement (orange) |
| 0–49 | Poor (red) |

## API Rate Limits

With an API key, you get approximately 25,000 requests per day. Each "Both" audit uses 2 requests (one mobile, one desktop). Without a key the API will not work — a key is required in plugin settings.

## License

This plugin is licensed under [The Craft License](LICENSE.md).

## Development

The project ships with a [DDEV](https://ddev.com) environment providing PHP 8.2 and
MariaDB 10.11. The integration suite needs the database, so run everything through it:

```sh
ddev start
ddev composer install
cp tests/.env.example tests/.env
ddev exec composer test
```

### Test suites

| Suite | Location | Runner | Needs a database |
|-------|----------|--------|------------------|
| Unit | `tests/unit` | PHPUnit | No |
| Integration | `tests/integration` | Codeception + Craft | Yes |

Unit tests cover the Craft-free response parsing in `ResponseParser`. Integration tests
boot a real Craft installation with the plugin loaded to exercise the plugin lifecycle,
settings, widget, service, and controller.

Run them individually with:

```sh
ddev exec composer test:unit
ddev exec composer test:integration
```

The integration suite installs Craft into the DDEV database and drops all its tables
between runs, so point it at a throwaway database only. Credentials live in `tests/.env`,
which is untracked — copy `tests/.env.example` to create it.

Static analysis and code style checks:

```sh
ddev exec composer phpstan
ddev exec composer check-cs
```
