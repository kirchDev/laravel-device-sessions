# Changelog

## [0.6.0](https://github.com/kirchDev/laravel-device-sessions/compare/v0.5.0...v0.6.0) (2026-09-01)


### Features

* **boost:** ship a core guideline and a device-sessions-development skill ([2146fa9](https://github.com/kirchDev/laravel-device-sessions/commit/2146fa9f2c17bd59aa582a16579523b35715f0bc)), closes [#36](https://github.com/kirchDev/laravel-device-sessions/issues/36)


### Bug Fixes

* **boost:** describe revocation as a stamp, not a delete ([2a6abb4](https://github.com/kirchDev/laravel-device-sessions/commit/2a6abb4fa9f60e14c12b8a71796bd7b6cf1e9ff9)), closes [#36](https://github.com/kirchDev/laravel-device-sessions/issues/36)


### Documentation

* **agents:** note the consumer-facing boost resources ([ceb734b](https://github.com/kirchDev/laravel-device-sessions/commit/ceb734b482d80b99aa3ce6f4ab4fe814fb72cf33)), closes [#36](https://github.com/kirchDev/laravel-device-sessions/issues/36)

## [0.5.0](https://github.com/kirchDev/laravel-device-sessions/compare/v0.4.0...v0.5.0) (2026-08-31)


### ⚠ BREAKING CHANGES

* **migrations:** KirchDev\DeviceSessions\Support\PackageMigrations is removed and DeviceSessionsServiceProvider now extends Spatie\LaravelPackageTools\PackageServiceProvider. Code that booted the provider by hand must call register() before boot(). The package's own migration filenames changed; consumers only ever see the published names, which are unchanged.

### Bug Fixes

* **deps:** require laravel-package-tools 1.93.1 for the migration reuse rule ([f469d40](https://github.com/kirchDev/laravel-device-sessions/commit/f469d40301b0ed804e8b152316e33576b6f3e990)), closes [#33](https://github.com/kirchDev/laravel-device-sessions/issues/33)


### Performance

* **migrations:** skip the publish map outside the console ([dd91d7d](https://github.com/kirchDev/laravel-device-sessions/commit/dd91d7dfc677600bd84e5e99d746fed73664e851)), closes [#33](https://github.com/kirchDev/laravel-device-sessions/issues/33)


### Refactor

* **migrations:** publish migrations through spatie/laravel-package-tools ([e164d2f](https://github.com/kirchDev/laravel-device-sessions/commit/e164d2f0849f2115c4805f9b50de3ac8a98d73cd))

## [0.4.0](https://github.com/kirchDev/laravel-device-sessions/compare/v0.3.0...v0.4.0) (2026-08-27)


### ⚠ BREAKING CHANGES

* **migrations:** the package's migration filenames changed. Consumers are unaffected — they only ever see the published names, which are generated.
* **migrations:** published migrations no longer carry the package's own filenames. A consumer who relied on the removed auto-load and never published must record the published copies as run before migrating; the README upgrade note carries the recipe.
* migrations are no longer auto-loaded. Consumers relying on the auto-load must run `php artisan vendor:publish --tag=device-sessions-migrations` before their next `migrate`. Anyone who had already published is unaffected.

### Features

* **migrations:** stamp published migrations at publish time ([61c1ce8](https://github.com/kirchDev/laravel-device-sessions/commit/61c1ce85f728515c77d73303319d5eeffb5c2402))
* publish migrations instead of auto-loading them ([bed21a4](https://github.com/kirchDev/laravel-device-sessions/commit/bed21a496f85f6da30c45af25dadcd5cc8bdf552)), closes [#27](https://github.com/kirchDev/laravel-device-sessions/issues/27)


### Bug Fixes

* **ci:** let the queue PR body wrap itself ([c878b37](https://github.com/kirchDev/laravel-device-sessions/commit/c878b37e53cadb29e3a5a9ffd26b19c3c25e1cdc))
* **ci:** read the Queue App PEM from this owner's own -ci mirror ([8023203](https://github.com/kirchDev/laravel-device-sessions/commit/8023203aa19cc4c173140c8b766992d6c35c4885))


### Documentation

* document the publish-only migration flow ([8dfb02e](https://github.com/kirchDev/laravel-device-sessions/commit/8dfb02ed28dc26d9afda1034b0a2c000158f2321)), closes [#27](https://github.com/kirchDev/laravel-device-sessions/issues/27)
* **migrations:** state the unpublished upgrade as a breaking change ([bc999a3](https://github.com/kirchDev/laravel-device-sessions/commit/bc999a38c7956a33a456b35682ad962968aa0376))


### Refactor

* **migrations:** move publish naming into PackageMigrations ([f89e7ca](https://github.com/kirchDev/laravel-device-sessions/commit/f89e7ca778b9f24950b30c70728684673d462075))
* **migrations:** name source migrations by sequence instead of date ([944b8a1](https://github.com/kirchDev/laravel-device-sessions/commit/944b8a1e800595a7fc14548b3f4ad061d1e5d098))

## [0.3.0](https://github.com/kirchDev/laravel-device-sessions/compare/v0.2.0...v0.3.0) (2026-07-26)


### Features

* route questions, ideas and possible bugs to the Discord forum ([72da365](https://github.com/kirchDev/laravel-device-sessions/commit/72da36536bea43428d11eaa2942bb4192c317196))


### Bug Fixes

* align dependabot labels to the stack: convention ([29e3d7f](https://github.com/kirchDev/laravel-device-sessions/commit/29e3d7f2c010ea25bc766359d3eae03dd0b31d91))
* align issue-template labels with the label catalog ([c90a880](https://github.com/kirchDev/laravel-device-sessions/commit/c90a88028425752c29be2c2092303caa815518e4))


### Documentation

* add AGENTS.md and sync agent instruction files ([b529a54](https://github.com/kirchDev/laravel-device-sessions/commit/b529a5429ed71a69c6f9a24b0e8b82a786bafc2a))

## [0.2.0](https://github.com/kirchDev/laravel-device-sessions/compare/v0.1.0...v0.2.0) (2026-05-30)


### Features

* add auth provider, middleware, listeners, Fortify bridge, and prune command ([e729c85](https://github.com/kirchDev/laravel-device-sessions/commit/e729c85ab6455756de5200679a83c6a181b84e15))
* add configuration and database migrations ([8359171](https://github.com/kirchDev/laravel-device-sessions/commit/8359171bfe76f39feb830e9fcef020193b0876ba))
* add currentDevice helpers and centralize current-device resolution ([4f8b3be](https://github.com/kirchDev/laravel-device-sessions/commit/4f8b3befedaed617e439d17ae90d896712d81bae))
* add device lifecycle actions and the DeviceTouched event ([4110fa1](https://github.com/kirchDev/laravel-device-sessions/commit/4110fa131966c2ec5845666f2024bea8e317cf47))
* add models, enums, and key/session traits ([557a0ed](https://github.com/kirchDev/laravel-device-sessions/commit/557a0ed5432dc70e37027f023e1b5e9fddff4f83))
* add overridable contracts and default implementations ([d6ac164](https://github.com/kirchDev/laravel-device-sessions/commit/d6ac1642ce25f49660ebcc17c1be0eda033f5914))
* register the device-sessions service provider ([ee432ff](https://github.com/kirchDev/laravel-device-sessions/commit/ee432ffaa28dadb14b54bd06e6171efc2b57c628))
* support configurable remember-token expiry ([526824d](https://github.com/kirchDev/laravel-device-sessions/commit/526824d5ee355c8f6e3fcba368f298cba2165728))


### Performance

* bulk revoke-other-devices and chunked device pruning ([b3c4b25](https://github.com/kirchDev/laravel-device-sessions/commit/b3c4b25d29b7c5553a29963b43c47890e2566a4e))


### Documentation

* add README ([2847276](https://github.com/kirchDev/laravel-device-sessions/commit/28472760ce1176b18fe09ef5829d7a8d02c9b5bc))
* compact README landing view ([adfb4af](https://github.com/kirchDev/laravel-device-sessions/commit/adfb4afbb7667906cf5076abfb6e57e38107cf40))
