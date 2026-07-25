# Changelog

All notable changes to Fulflix Stats will be documented in this file.

The format is based on Keep a Changelog, and the project follows semantic
versioning where practical.

## [Unreleased]

### Added

- Added central configuration for branding, Jellyfin connection, Playback Reporting database, labelled media paths, cache directory, and timezone.
- Removed installation-specific Fulflix URLs and branding from runtime source.
- Added support for any number of labelled media-library paths in storage statistics.


- MIT license.
- Contribution and security guidelines.
- GitHub issue and pull request templates.
- EditorConfig and expanded Git ignore rules.

### Fixed

- Fixed the storage summary card so it renders all configured `$mediaLibraries` labels and sizes instead of hardcoded Movies/TV keys.


- Fixed the Most Watched navigation anchor and removed the non-functional menu icon.

### Changed

- Renamed movie and TV rankings to Most played, restored play-count sorting, and replaced total watch time with unique viewers.


- Changed Most Watched rankings to sort by total watch time first, with play count used only as a tiebreaker.


- Increased KPI labels, KPI subtitles, section headings, ranking text, tabs, playback details, and lower-dashboard typography for better readability.


- Added a Jellyfin-inspired Fulflix visual theme and top navigation without changing dashboard data or metrics.


- Increased typography and spacing across lower dashboard panels, especially Playback Method and Media Profile, without changing the top summary rows.


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
