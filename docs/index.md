---
title: 'laravel-device-sessions documentation'
description: 'How device-bound sessions are put together, and how a consuming application builds on them.'
icon: 'lucide:book-open-text'
navigation: false
---

This package binds Laravel's remember-me logins to the browser they were issued to, and keeps a row per device that an application can list, rename and revoke. These pages cover the parts a reader cannot recover from the source — how the pieces hand off to each other, what revoking does and does not end, and how to build on it.

## Sections

::page-cards
::

Everything else has a home that cannot go stale: installation, the wiring and the contract table are in the [README](https://github.com/kirchDev/laravel-device-sessions/blob/main/README.md), every configuration key is documented inline in [`config/device-sessions.php`](https://github.com/kirchDev/laravel-device-sessions/blob/main/config/device-sessions.php), the development setup is in [CONTRIBUTING.md](https://github.com/kirchDev/laravel-device-sessions/blob/main/CONTRIBUTING.md), and consuming applications that run Laravel Boost get the same material through `resources/boost/`.
