# Manual QA Checklist

## Core Template Flow

- Install the plugin on Craft CMS 5 and confirm the `Exports` CP nav item appears.
- Create a new export template for entries.
- Add native fields, custom fields, and at least one relation field.
- Rename columns and reorder them.
- Save the template and confirm it persists correctly after reload.

## Immediate Export Flow

- Set the queue threshold above the expected row count.
- Run the export.
- Confirm the run is marked `completed`.
- Download the file.
- Verify column order, labels, values, and escaping.

## Queue Flow

- Set the queue threshold below the expected row count.
- Run the export.
- Confirm the run is marked `queued`, then `running`, then `completed`.
- Verify the file remains downloadable after completion.

## Filters

- Entries: limit by section and confirm only matching entries export.
- Multi-site elements: limit by site and confirm the correct site content exports.
- Use created-from and created-to dates and verify the output range.

## Data Shapes

- Export relation fields and confirm CSV values are human-readable.
- Export JSON and confirm relation values become arrays.
- Export multiline text and quotes and confirm CSV remains valid.
- Export Matrix content using a defined nested field path and confirm the output is readable.

## Permissions

- Confirm users without `manageDataExports` cannot access the CP UI.
- Confirm users without `runDataExports` cannot trigger runs.
- Confirm users without `downloadDataExports` cannot download completed files.

## Commerce

- Install Craft Commerce.
- Confirm Commerce orders appear only when Commerce is installed and Pro edition is enabled.
- Run an order export and verify key operational fields.
