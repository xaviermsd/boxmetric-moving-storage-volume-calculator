# Changelog

All notable changes to BoxMetric are listed here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the
project uses [Semantic Versioning](https://semver.org/).

## [1.0.1] - 2026-11-12

### Changed

- New name: BoxMetric: Moving & Storage Volume Calculator. The slug and text domain are now `boxmetric-moving-storage-volume-calculator`. The shortcode and saved data are unchanged.
- The customer copy email is off by default. Each address gets at most one copy a day, and the site sends at most 20 an hour.
- The daily clean-up of old quote requests re-schedules itself if the event goes missing.
- The failed-email notice shows on the plugin's own screens only.

### Removed

- The optional "Powered by" link, and the plugin name line in emails, the PDF report and the admin footer.

### Security

- A lead's Page link is only kept for addresses on your own site.
- New `msvcalc_client_ip` filter, so sites behind a proxy or CDN can rate-limit by the real visitor address.

## [1.0.0] - 2026-11-08

### Added

- Room-by-room moving inventory: 441 items in 21 rooms and 5 categories, with search and custom items. Volumes and vehicle, storage and container sizes are checked against a dozen published moving calculators.
- 14 Quick Starts, from "Boxes only" to "5 bed", garage, storage unit, office, shop and café.
- Live total in m³ or ft³, with items, boxes, estimated weight and a packing margin.
- Best-fit van or truck, shipping container and storage unit, for the UK, Europe, the United States and Australia.
- Box estimator: five quick questions suggest a count for each box size.
- Quote form with a forgotten-rooms reminder that follows the chosen service, move details, consent and a confirmation screen.
- Share link, branded print copy, and the list remembered in the visitor's browser.
- Branded quote request email, with an optional copy for the customer (off by default, at most one a day per address).
- Leads: statuses, follow-up reminders, notes, timeline, quick contact actions, and Excel, CSV and PDF export.
- Failed-email flag with "Send email again", duplicate merging and rate limiting.
- Staff access to Leads for Editors, privacy exporter and eraser, and automatic clean-up.
- Consent record on every lead: when the customer agreed, the exact wording and the privacy policy link shown, also in the exports and the personal data export.
- BoxMetric Volume Calculator block and `[msvcalc_calculator]` shortcode, light, dark or automatic theme.

[1.0.1]: https://github.com/xaviermsd/boxmetric-moving-storage-volume-calculator/releases/tag/v1.0.1
[1.0.0]: https://github.com/xaviermsd/boxmetric-moving-storage-volume-calculator/releases/tag/v1.0.0
