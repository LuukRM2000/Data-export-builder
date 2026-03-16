# Data Export Builder

Create reusable CSV and JSON exports for Craft CMS from the Control Panel.

Data Export Builder lets you choose an element type, pick fields, rename columns, apply basic filters, save the template, and run exports on demand. Small exports run immediately. Larger exports run through the Craft queue and stay available for download from run history.

## What It Does

- export entries, users, categories, and assets
- export Commerce orders when Commerce is installed and Pro features are enabled
- output CSV or JSON
- save export templates for reuse
- rename and reorder columns
- filter by section, site, and created date where supported
- run immediately or queue large exports
- download completed export files later

## Requirements

- PHP 8.2+
- Craft CMS 5.0+
- Craft queue configured for larger exports
- Craft Commerce optional for order exports

## Installation

```bash
composer require luremo/craft-data-export-builder
php craft plugin/install data-export-builder
```

Then grant the plugin permissions to the right user groups.

## Supported Element Types

| Element Type | Lite | Pro |
| --- | --- | --- |
| Entries | Yes | Yes |
| Users | Yes | Yes |
| Categories | Yes | Yes |
| Assets | Yes | Yes |
| Commerce Orders | No | Yes |

## Supported Output Formats

- CSV
- JSON

## Quick Start

1. Open `Exports` in the Craft Control Panel.
2. Create a new export template.
3. Choose an element type.
4. Add the fields you want to export.
5. Rename and reorder the selected columns.
6. Apply filters if needed.
7. Save the template.
8. Run the export.
9. Download the completed file from run history.

## Field Support

The field picker includes:

- native element attributes
- common meta values like title, slug, uri, status, and dates
- custom fields
- relation fields
- practical Matrix sub-field paths

Dates are normalized to `Y-m-d H:i:s`.

CSV output uses native `fputcsv()` escaping for commas, quotes, and multiline values.

Relation values export as:

- CSV: comma-separated readable values
- JSON: arrays where practical

## Queue Behavior

- each template has a queue threshold
- exports at or below the threshold run immediately
- larger exports create a queued export run
- completed runs remain downloadable from the template screen
- failed runs store an error message

## Permissions

- `manageDataExports`
- `runDataExports`
- `downloadDataExports`

## Known Limitations

- V1 supports basic filters only
- Matrix flattening is practical, not exhaustive
- files are stored locally in V1
- scheduled exports, remote delivery, transformations, and XLSX are not included in V1

## Screenshots

Suggested screenshots for release:

1. templates index
2. template builder with field picker
3. run history with queued and completed exports
4. Commerce order export example

## Manual QA Checklist

See [docs/manual-qa-checklist.md](/Users/luukmolenbeek/Developer/Data-export-builder/docs/manual-qa-checklist.md).

## Support

- GitHub issues for reproducible bugs
- support email placeholder: `support@example.com`

## Roadmap

- scheduled exports
- email delivery
- remote storage
- webhooks
- field transformations
- CLI triggers
- richer Commerce mappings
- XLSX export

## Pricing

See [docs/pricing-edition-notes.md](/Users/luukmolenbeek/Developer/Data-export-builder/docs/pricing-edition-notes.md).
