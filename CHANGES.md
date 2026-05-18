# Changelog — customfield_omniselect

## 1.0.2 (2026051402)
- Renamed plugin display name from "Multi-select" to "Omni-select" to avoid
  confusion with other multi-select field type plugins.

## 1.0.1 (2026051401)
- Redesigned option storage: options are now held in a dedicated
  `customfield_omniselect_opts` table with stable integer IDs, replacing the
  plain-text `configdata[options]` blob.
- Selected values are stored by option ID (not by text) so renaming an option
  does not break existing course data.
- Added `instanceid` index to `customfield_omniselect_vals` for faster lookups.
- Existing data is migrated automatically on upgrade.

## 1.0.0 (2026051400)
- Initial release.
- Custom field type that allows multiple values to be selected per course.
- Options are configured per field and stored as plain text in configdata.
- Privacy provider declares null_provider (no personal data stored directly).
