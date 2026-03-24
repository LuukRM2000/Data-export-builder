# Data Export Builder

Create reusable CSV, JSON, and XLSX exports for Craft CMS from the Control Panel.

Data Export Builder gives Craft CMS teams a reusable export workflow for reporting, migrations, operational handoffs, and Commerce data movement. Instead of rebuilding one-off export templates or custom scripts every time a client asks for data, teams can define an export once, save it, and run it again on demand. Small exports run immediately. Larger exports run through the Craft queue and stay available for download from run history.

It is positioned for agencies, freelancers, and in-house Craft teams that repeatedly need clean exports without turning every request into bespoke development work.

## What It Does

- export entries, users, categories, and assets
- export Commerce orders when Commerce is installed and Pro features are enabled
- output CSV, JSON, or XLSX
- save export templates for reuse
- rename and reorder columns
- filter by section, site, and created date where supported
- run immediately or queue large exports
- download completed export files later
- schedule recurring exports in Pro
- deliver exports by email or webhook in Pro
- archive exports to a Craft asset volume in Pro

## Who It Is For

- agencies delivering multiple Craft sites and recurring client reporting
- freelancers who keep rebuilding export utilities for migrations and handoffs
- in-house teams that need repeatable operational exports from the Control Panel
- Commerce teams that need reusable order, catalog, and inventory exports

## What It Is Not

- not a BI suite
- not an iPaaS or full integration platform
- not a spreadsheet replacement

The product wins by replacing repeated export scaffolding with a focused, reliable workflow inside Craft.

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

| Element Type | Standard | Pro |
| --- | --- | --- |
| Entries | Yes | Yes |
| Users | Yes | Yes |
| Categories | Yes | Yes |
| Tags | Yes | Yes |
| Assets | Yes | Yes |
| Commerce Orders | No | Yes |
| Commerce Products | No | Yes |
| Commerce Variants | No | Yes |

## Supported Output Formats

- CSV
- JSON
- XLSX

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

## Automation & Delivery

- Pro only
- configure this per export template in `Settings`
- scheduled exports are queued by running `php craft data-export-builder/scheduler/run`
- when a scheduled run is due, the plugin creates a normal export run for that template
- email delivery sends the exported file as an attachment
- webhook delivery posts the export payload and file to the configured endpoint
- remote storage uploads a copy to the selected Craft asset volume
- `Keep local downloadable copy` keeps the local run file after remote upload
- failed runs stay in run history and can be retried from the Control Panel

## Permissions

- `manageDataExports`
- `runDataExports`
- `downloadDataExports`

## Known Limitations

- V1 supports basic filters only
- Matrix flattening is practical, not exhaustive
- Standard and Pro editions should be set through Craft plugin editions, not environment variables

## Screenshots

Suggested screenshots for release:

1. templates index
2. template builder with field picker
3. run history with queued and completed exports
4. Commerce order export example

See [docs/release-readiness-checklist.md](/Users/luukmolenbeek/Developer/Data-export-builder/docs/release-readiness-checklist.md) for the Phase 1 launch checklist.

## Manual QA Checklist

See [docs/manual-qa-checklist.md](/Users/luukmolenbeek/Developer/Data-export-builder/docs/manual-qa-checklist.md).

## Support

- GitHub issues for reproducible bugs: [github.com/LuukRM2000/Data-export-builder/issues](https://github.com/LuukRM2000/Data-export-builder/issues)
- Commercial support: configure a real support inbox before Plugin Store submission

## Roadmap

- field transformations
- CLI triggers
- richer Commerce mappings

## Pricing

See [docs/pricing-edition-notes.md](/Users/luukmolenbeek/Developer/Data-export-builder/docs/pricing-edition-notes.md).

## Editions

Data Export Builder declares native Craft plugin editions:

- `standard` for general content exports
- `pro` for Commerce-focused workflows and premium operational features

Craft stores the active edition in project config. Change it via `plugins.data-export-builder.edition` when testing edition-gated behavior locally.
