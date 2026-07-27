# Changelog

All notable changes to Jelana will be documented in this file.

The format is based on Keep a Changelog, and the project follows semantic
versioning where practical.

## [Unreleased]

### Changed

- Combined playback count and watch time into two tabbed cards with 30-day and all-time views.


## [2.1.0] - 2026-07-25

### Added

- Public project identity under the Jelana name.
- Central configuration for branding, Jellyfin connection, Playback Reporting
  database, labelled media paths, cache directory, and timezone.
- Support for any number of labelled media-library paths in storage statistics.
- Configuration validation with readable browser errors.
- Dashboard screenshot and dedicated installation guide.
- Version information in the dashboard footer.
- MIT license, contribution and security guidelines, GitHub issue templates,
  and a pull request template.

### Changed

- Movie and TV rankings are named **Most played**, sorted by play count, and
  display unique viewers.
- The user ranking remains **Most watched** and is sorted by total watch time.
- Default cache, cron, and log paths now use the Jelana project name.
- Documentation, examples, browser titles, and interface defaults now use the
  Jelana project name.
- Improved typography, spacing, navigation, and responsive layout.
- Standardized interface terminology in English.
- Normalized project structure and source formatting.

### Fixed

- Storage summary cards now render all configured `$mediaLibraries` labels and
  sizes instead of hardcoded Movies/TV keys.
- Fixed the ranking navigation anchor and removed the non-functional menu icon.
- Moved dashboard and poster caches out of temporary directories to avoid
  systemd `PrivateTmp` isolation.

## [2.0.0] - 2026-07-25

### Added

- Hourly JSON dashboard cache.
- Atomic cache writes and refresh locking.
- Seven- and thirty-day rankings.
- Recently added media summaries.

### Changed

- Normal page views now read cached dashboard data instead of querying
  Jellyfin and Playback Reporting directly.
