# Changelog

All notable changes to Fulflix Stats will be documented in this file.

The format is based on Keep a Changelog, and the project follows semantic
versioning where practical.

## [Unreleased]

### Added

- MIT license.
- Contribution and security guidelines.
- GitHub issue and pull request templates.
- EditorConfig and expanded Git ignore rules.

### Changed

- Replaced separate active-user panels with a tabbed **Most watched** top-10 user ranking for 7 and 30 days.


- Moved dashboard and poster caches to `/var/cache/fulflix-stats` to avoid systemd `PrivateTmp` isolation.

- Standardized all interface terminology in English.
- Reformatted the main dashboard and image proxy for readability.
- Improved naming, type declarations, comments, and error handling.
- Reworked the README into a complete project guide.

## [2.0.0] - 2026-07-25

### Added

- Hourly JSON dashboard cache.
- Atomic cache writes and refresh locking.
- Seven- and thirty-day rankings.
- Recently added media summaries.

### Changed

- Normal page views now read cached dashboard data instead of querying
  Jellyfin and Playback Reporting directly.
