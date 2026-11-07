# Shakewell Sitemap

> **Made by [Shakewell](https://shakewell.agency).** Free and open source, built and maintained by the Shakewell team.

Free, GPL-2.0-or-later XML sitemap plugin for normal and headless WordPress sites. See `readme.txt` for settings, headless use and the migration from Merivale Sitemap. Built from the Shakewell plugin template.

```sh
composer install && npm ci
npm run env:start      # http://localhost:8900 (admin / password), tests on 8901
npm test               # PHPUnit; tests/fixtures/original holds Merivale Sitemap 1.1.0's exact output
composer lint
npm run plugin-check
```

Release: bump the version in `shakewell-sitemap.php` (header and `VERSION`) and `readme.txt` (`Stable tag`), add a changelog line, tag `vX.Y.Z`.

## Made by Shakewell

This plugin is one of a family of free WordPress plugins from [Shakewell](https://shakewell.agency). Each one does one job, keeps its dependencies small and is released under GPL-2.0-or-later.

Use it, fork it and send fixes. To have it set up on your site, or to have a plugin built, [talk to Shakewell](https://shakewell.agency).

More from Shakewell:

- [Shakewell Activity Log](https://github.com/shakewellagency/shakewell-activity-log)
- [Shakewell Multiple Authors](https://github.com/shakewellagency/shakewell-authors)
- [Shakewell Clone Posts](https://github.com/shakewellagency/shakewell-clone-posts)
- [Shakewell Connector for Klaviyo and Gravity Forms](https://github.com/shakewellagency/shakewell-klaviyo-connector)
- [Shakewell Media Offload for Amazon S3](https://github.com/shakewellagency/shakewell-media-offload)
- [Shakewell Redirects](https://github.com/shakewellagency/shakewell-redirects)
- [Shakewell Two-way Relationships for ACF](https://github.com/shakewellagency/shakewell-relationship-sync)
- [Shakewell Single Sign-On (SAML)](https://github.com/shakewellagency/shakewell-saml-sso)
- [Shakewell Error Reporting for Sentry](https://github.com/shakewellagency/shakewell-sentry)
- [Shakewell Mailer for Amazon SES](https://github.com/shakewellagency/shakewell-ses-mailer)
- [Shakewell plugin template](https://github.com/shakewellagency/shakewell-plugin-template), the starting point for every plugin
