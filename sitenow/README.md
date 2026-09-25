# SiteNow CLI (`sn`)

`sn` runs SiteNow repository commands. Run it from your host. A few commands
act on a site's local database, which only exists in the web container, so run
those as `ddev sn`. `./sn list` marks them `(ddev required)`.

Run it from the repository root:

```
./sn                          # list available commands
./sn <command> --help         # arguments and options for a command
ddev sn <command>             # for commands that run inside the container
```

`ddev sn` is a thin wrapper around `./sn` inside the container
(`.ddev/commands/web/sn`). Prefer it over `ddev exec ./sn`: it forwards a
terminal, so a command that asks a question can be answered.

## Applications

Sites are spread across several Acquia applications rather than living on one.
An application's SSL certificate lists its domains as SANs and that list caps
near 100, so no single application can hold the whole fleet. `applications.yml`
is the registry of applications; `manifest.yml` maps each site to its application.

## Code structure

`src/` is organized by the resource acted on, with verbs as methods:
`Acquia\CloudApi`, `Config\Manifest`, `Config\SitesPhp`. Commands live in
`Command/`.
