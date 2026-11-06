# Shakewell Sitemap

Free, GPL-2.0-or-later XML sitemap plugin for normal and headless WordPress sites. See `readme.txt` for settings, headless use and the migration from Merivale Sitemap. Built from the Shakewell plugin template.

```sh
composer install && npm ci
npm run env:start      # http://localhost:8900 (admin / password), tests on 8901
npm test               # PHPUnit; tests/fixtures/original holds Merivale Sitemap 1.1.0's exact output
composer lint
npm run plugin-check
```

Release: bump the version in `shakewell-sitemap.php` (header and `VERSION`) and `readme.txt` (`Stable tag`), add a changelog line, tag `vX.Y.Z`.
