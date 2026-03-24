# Release Readiness Checklist

This is the Phase 1 commercial-readiness checklist for Data Export Builder. The goal is to remove trust leaks before spending effort on pricing expansion or larger go-to-market work.

## Positioning

- make README, Plugin Store copy, and package metadata describe the same product
- lead with reusable exports for reporting, migrations, and operational handoffs
- clearly state that the plugin is not a BI suite or general integration platform
- present Standard as the reusable export edition and Pro as the operational workflow edition

## Trust Signals

- replace placeholder support details with a real support inbox
- finalize the commercial license / EULA
- produce production-quality Plugin Store screenshots
- verify Craft CMS 5 compatibility in a live install
- verify Commerce export behavior in a live Commerce install

## Product Packaging

- ensure all public descriptions mention CSV, JSON, and XLSX consistently
- ensure Plugin Store copy matches actual Standard vs Pro behavior
- document scheduling, webhook delivery, and volume delivery as Pro workflows
- confirm edition-gated features are enforced by real licensing, not only local assumptions

## QA and Proof

- run the manual QA checklist end to end before release
- add fixture-backed integration coverage for real element queries
- verify run history, queued exports, retries, and downloads in a live Craft project
- verify schedule execution via `php craft data-export-builder/scheduler/run`
- verify remote delivery behavior for email, webhook, and volume upload

## Launch Assets

- prepare at least four Plugin Store screenshots
- prepare a short product demo GIF or video for marketing use
- finalize a one-paragraph support policy
- finalize an edition comparison table for launch materials

## Exit Criteria

Phase 1 is complete when a prospective buyer can understand what the plugin does, trust that it is maintained commercially, and see a clear reason to buy Standard or Pro without reading the source code.
