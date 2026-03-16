# Data Export Builder

Build reusable CSV and JSON exports for Craft CMS directly from the Control Panel.

Data Export Builder is designed for agencies, freelancers, and in-house Craft teams who keep rebuilding the same export logic for ERP feeds, accounting handoffs, CRM prep, reporting, migration work, and client operations. Instead of writing one-off Twig templates or project-specific scripts every time, teams can create and reuse export templates in the Craft CP.

## Plugin Store Short Description

Create reusable CSV and JSON exports for Craft CMS entries, users, categories, assets, and Commerce orders without custom Twig templates.

## Plugin Store Long Description

Data Export Builder turns repetitive export work into a reusable Control Panel workflow.

Choose an element type, pick the fields you need, rename columns, add basic filters, and export to CSV or JSON. Save templates for recurring operational exports, queue larger jobs safely, and let editors or developers download completed files when they are ready.

This plugin is built for the kinds of jobs agencies and in-house teams repeat across projects:

- ERP and accounting exports
- CRM sync preparation
- product and catalog feeds
- client reporting
- internal operations exports
- migration prep and content handoff tooling

V1 is intentionally focused. It solves the repeated export problem well, stays close to Craft-native UX, and leaves clean extension points for scheduled delivery, remote storage, richer transformations, and more advanced data mapping in future editions.

## Who It Is For

- Craft CMS agencies standardizing delivery across multiple client projects
- freelance Craft developers who want to stop rebuilding export logic from scratch
- in-house teams running operational or reporting exports without custom deployment work
- Craft Commerce teams that need order exports when Pro edition features are enabled

## Why Agencies Pay For This

- It replaces repeated billable-but-boring export setup with a reusable admin workflow.
- It shortens client handoff time because templates live in the Craft CP.
- It reduces maintenance risk compared with project-specific scripts.
- It creates a consistent export pattern across multiple projects and retainers.

## Requirements

- PHP 8.2+
- Craft CMS 5.0+
- Craft queue configured for larger exports
- Craft Commerce optional for order exports

## Installation

1. Open your Craft project.
2. Require the package with Composer:

```bash
composer require luremo/craft-data-export-builder
```

3. Install the plugin in the Craft Control Panel or with Craft CLI:

```bash
php craft plugin/install data-export-builder
```

4. Give the relevant user groups the plugin permissions.

## What V1 Includes

- Entries, users, categories, and assets export templates
- Commerce order exports when Commerce is installed and Pro edition features are enabled
- CSV and JSON output
- saved export templates
- column renaming
- drag-free reorder controls built for CP simplicity
- section, site, and created-date filters
- immediate exports for smaller datasets
- Craft queue support for larger datasets
- download history and run status tracking
- CP permissions for template management, execution, and downloads

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

## Usage Walkthrough

1. Go to `Exports` in the Craft Control Panel.
2. Create a new export template.
3. Choose an element type.
4. Search and add export fields.
5. Rename columns and reorder them.
6. Apply section, site, or created-date filters if needed.
7. Save the template.
8. Run the export immediately or let the queue process larger jobs.
9. Download the finished file from the run history panel.

## Template Creation Flow

- Pick the target element type.
- The field picker loads native attributes, common meta values, custom fields, relation paths, and Matrix paths where practical.
- Add only the fields you want in the final output.
- Edit column labels so exports match the destination system.
- Reuse the saved template whenever the same workflow comes back.

## Queued Export Behavior

- Each template includes a queue threshold.
- Exports at or below the threshold run immediately.
- Larger exports create an export run record and move into the Craft queue.
- Completed runs remain downloadable from the template screen.
- Failed runs store an error message for debugging.

## Permissions

- `manageDataExports`
- `runDataExports`
- `downloadDataExports`

Typical setup:

- developers or technical admins manage templates
- operations or editors can run approved exports
- finance or client services can download completed files when needed

## Data Handling Notes

- Dates are normalized to `Y-m-d H:i:s`.
- CSV output uses native `fputcsv()` escaping for quotes, commas, and multiline values.
- Relation values export as comma-separated labels in CSV and arrays in JSON.
- Rich text fields export raw values in V1 for predictable data fidelity.
- Matrix and nested content are exposed through stable field paths and degrade gracefully when a shape is too complex to flatten perfectly.

## Known Limitations

- V1 focuses on basic filters only.
- Matrix discovery is practical, not exhaustive, for every custom field combination.
- JSON exports currently write local files rather than remote destinations.
- Scheduled delivery, S3 storage, webhook delivery, transformations, and XLSX are not part of V1.

## Screenshots

Add real screenshots before store submission:

1. Export templates index with empty state and primary CTA
2. Template builder screen with field picker and run history
3. Completed export history showing queued, completed, and failed states
4. Commerce order template example for Pro edition marketing

## Manual QA Checklist

See [docs/manual-qa-checklist.md](/Users/luukmolenbeek/Developer/Data-export-builder/docs/manual-qa-checklist.md).

## FAQ

### Does this replace custom integration logic?

No. It replaces repeated export scaffolding and makes common operational exports reusable. It is intentionally not a generic integration platform.

### Does it support large datasets?

Yes. V1 uses the Craft queue for larger exports and streams files to disk to avoid obvious memory blowups.

### Can I export relational content?

Yes. Relation fields are supported, with human-readable CSV output and structured JSON output where practical.

### Can clients use it after handoff?

Yes. That is one of the strongest use cases. Agencies can leave behind reusable export templates instead of custom code fragments.

## Support

- GitHub issues for reproducible bugs
- commercial support email placeholder: `support@example.com`
- documentation improvements should live in this repository for transparent buyer trust

## Roadmap

- scheduled exports
- email delivery
- remote storage destinations
- webhook delivery hooks
- field transformations
- CLI triggers
- richer Commerce mappings
- XLSX export

## Pricing Recommendation

See [docs/pricing-edition-notes.md](/Users/luukmolenbeek/Developer/Data-export-builder/docs/pricing-edition-notes.md).
