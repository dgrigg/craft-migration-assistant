# Migration Assistant Changelog

## 4.0.0 - 2024-06-17

- Craft 4 compatible release

## 3.2.7 - 2021-11-19

- fixed field layout issue with Neo fields

## 3.2.6 - 2021-03-01

### Fixed

- fixed issue with Neo blocks having empty Field Layouts after field migrations

## 3.2.5 - 2021-02-05

### Fixed

- site name and base url no longer parse the environment variables, the proper variable remains in the migrated value
- fixed issue with section migrations creating a ghost entry type that matched the default section entry handle when it was not in the migration

### Added

- Neo migrations include the 'maxSiblingBlocks' attribute

## 3.2.4 - 2020-12-11

- fixed setting to enforce migration permissions for Globals content
- cleaned up button placement for Create Migration button in Globals

## 3.2.3 - 2020-11-20

- fixed bug with LinkField and null values

## 3.2.1 - 2020-09-08

- fixed issue with circular reference in section migrations

## 3.2.0 - 2020-09-04

### Fixed

- fixed issue with fieldHandle throwing error during migration
- updated user group permissions

## 3.1.9 - 2020-08-20

### Added

- support for new Entry Type layouts and elements in Craft 3.5

### Fixed

- fixed issue with null path when migration assets
- fixed 'titleLabel' bug when creating migrations in Craft 3.5

## 3.1.8 - 2020-06-13

### Fixed

- fixed issue introduced with Craft 3.2.0 with order of items in Matrix/Neo/SuperTables
- fixed entry enabled/disabled setting on content migrations for site/locale entries

## 3.1.7 - 2020-06-12

### Fixed

- section `Propagation Method` added to export data
- fixed bug with entry being duplicated when `Progogration Method` is set to 'Only save entries to the site they were created in' on content migrations
- assets in deep folders are now located correctly during content migrations
- fixed whitespace in generated migration file

## 3.1.6 - 2019-12-23

### Fixed

- matrix content migrations sort order, block order now imports in correct sequence

## 3.1.5 - 2019-09-20

### Fixed

- structure entries with an unpublished parent entry where not being associated to the parent entry

## 3.1.4 - 2019-09-03

### Added

- entry exports now include the author field value
- added support for the Link Field plugin field type https://github.com/sebastian-lenz/craft-linkfield

### Fixed

- fixed issue with empty values in Entry fields contained within Matrix blocks that are translatable
- fixed entry source handle for single entry sections

## 3.1.3 - 2019-07-23

### Fixed

- entry imports now recognize timezone value for post and expiry dates
- fixed bug with duplicate entries being created during import for non live entries

## 3.1.2 - 2019-05-31

### Fixed

- export UID on entry content exports to prevent null error during import
- fixed invalid postDate error for content entry imports
- fixed user group export issue with uid

## 3.1.1 - 2019-05-09

### Fixed

- fixed issue for PostgresSQL creating fields

## 3.1.0 - 2019-03-08

### News

- [Migration Manager](https://github.com/Firstborn/Craft-Migration-Manager) is now [Migration Assistant](https://github.com/dgrigg/craft-migration-assistant). We have transitioned to a paid plugin in order to provide better support and updates going forward. As Craft continues to grow and evolve and more people take advantage of simplified migrations via the Migration Manager, it was becoming difficult to provide a level of support and responsiveness that people needed when depending on this plugin to help manage their Craft sites. The current free version of Migration Manager will remain available for a period of time to ensure a smooth transition for everyone using the plugin. Thank you to everyone who used this plugin and offered feedback to make it better.

## 3.0.21 - 2019-03-05

### Fixed

- fixed invalid entry type issue with section migrations for 'single' section types

### Added

- additional error logging for failed migrations

## 3.0.20.1 - 2019-03-04

### Fixed

- bump version for Packagist

## 3.0.20 - 2019-03-01

### Fixed

- fixed a bug with SuperTable nested in Matrix field that caused content to be orphaned during migrations

## 3.0.19 - 2019-02-27

### Fixed

- fixed issues for Neo field content migrations

## 3.0.18 - 2019-02-27

### Fixed

- fixed JSON encoding error with environment variables, switched to NOWDOC vs HEREDOC
- fixed migration file name bug when name contained accented characters
- fixed color field content migration bug that resulted in empty field values
- fixed missing use in AssetVolumes

### Added

- translation support for Supertable fields

## 3.0.17 - 2019-01-30

### Fixed

- fixed issue creating migrations for Matrix fields
- updated support for SuperTable and Neo 3.1 releases
- removed Routes from migration options for Craft 3.1 + (database routes not supported in Craft 3.1, moved to project config)

## 3.0.16 - 2019-01-28 [CRITICAL]

### Fixed

- fixed errors caused by Uids introduced in Craft 3.1 which caused Migration Manager to fail. This updated is needed for Migration Manager to run in 3.1 installations
- fixed deprecation error on js include
- fixed user permissions being applied after migration
- fixed global set migrations being run consecutively

## 3.0.15 - 2018-11-27

### Added

- support for Neo fields (structure and content migrations)

### Fixed

- fixed error logging issue
- fixed source error for Entry and Asset fields
- updates for Supertable content migrations

## 3.0.14 - 2018-10-26

### Fixed

- fixed index conflict when importing global sets
- fixed missing site group id for newly created site groups

## 3.0.13 - 2018-10-10

### Fixed

- fixed asset transforms failing validation error
- fixed deprecation errors for content migrations

### Added

- user permission to allow content migrations for non admin users

## 3.0.12 - 2018-9-28

### Fixed

- corrected UTF 8 encoding for content migrations
- fix Matrix block issue

## 3.0.11 - 2018-07-06

### Fixed

- Fixed entry dates for content migrations
- Fixed invalid volume error when exporting asset fields
- Fixed null item error for custom field types

## 3.0.10 - 2018-05-31

### Fixed

- Fixed null field error for empty content migrations

## 3.0.9 - 2018-05-25

### Fixed

- Fixed json decoding that resulted in null migration error

## 3.0.8 - 2018-05-10

### Fixed

- Fixed a template issue when migrations run with 'backupOnUpdate' set to 'false'

## 3.0.7 - 2018-05-03

### Fixed

- Retrieve default site handle instead of using 'default'
- Better error reporting for Entry errors

## 3.0.6 - 2018-05-02

### Fixed

- Fixed query table prefix error when retrieving field groups

## 3.0.5 - 2018-04-26

### Fixed

- Fixed volume folder references in Asset and Redactor field settings

## 3.0.4 - 2018-04-26

### Fixed

- Fixed escaping for backslashes in settings

## 3.0.3 - 2018-04-25

### Fixed

- Deprecation errors in templates
- Null value when creating Asset Volume migration

## 3.0.2 - 2018-04-23

### Fixed

- Exporting of Redactor field
- SuperTable field export no longer throws errors
- Removed unnecessary asset bundle for sidebar
- Field migrations for Matrix and SuperTable fixed to prevent orphaned data

## 3.0.1 - 2018-04-20

### Fixed

- Edition check for user group permissions

## 3.0.0 - 2018-04-19

### Added

- Initial release for Craft 3
