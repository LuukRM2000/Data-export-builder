# Final Build Notes and Remaining TODOs

## Strong Decisions Made

- V1 stays focused on reusable CP-driven exports instead of becoming a delivery platform.
- Local file storage is the default for completed exports.
- Queue threshold is template-specific so agencies can tune behavior per use case.
- Order exports are architected behind capability checks for clean Lite vs Pro separation later.

## Remaining TODOs Before Production Release

- swap placeholder package vendor and support contacts for the final commercial brand
- validate exact Craft 5 field layout APIs against a live Craft install
- add plugin store screenshots and final marketing assets
- replace the commercial license placeholder with the final EULA
- add integration coverage around live Craft element queries in a fixture-backed test project
- wire real licensing checks for edition gating
- add richer Matrix flattening rules for edge-case content models
- verify Commerce order layout discovery against the installed Commerce version
