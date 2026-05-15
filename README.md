# customfield_omniselect

A Moodle custom field type that allows multiple values to be selected from a
predefined list. Designed to power faceted filtering in course catalogues.

## Features

- Administrators define options (one per line) in the field configuration.
- Editors can select one or more values when editing a course.
- Selected values are stored in a normalised table (`customfield_omniselect_vals`)
  for fast indexed queries.
- A comma-separated display string is also written to `customfield_data` so
  that Moodle's backup, restore, and export pipelines work without modification.

## Requirements

- Moodle 5.1 or later (requires Moodle 2025092600+)
- PHP 8.1+

## Installation

1. Copy the plugin folder to `<moodleroot>/public/customfield/field/omniselect/`.
2. Log in as admin and navigate to **Site administration → Notifications**.
3. Click **Upgrade Moodle database now**.

## Usage

1. Go to **Site administration → Courses → Course custom fields**.
2. Click **Add a new custom field** and choose **Multi-select**.
3. Enter a name and one option per line under **Options**.
4. Open any course edit page — the field appears in the **Custom fields** section.

## Compatibility

Tested with PostgreSQL and MySQL. Uses `$DB->get_in_or_equal()` throughout to
avoid vendor-specific SQL.

## License

GNU GPL v3 or later — https://www.gnu.org/copyleft/gpl.html
