# SiteNow CLI (`sn`)

`sn` runs SiteNow repository commands. Each command documents where it must
run: some touch the git working tree and run on the **host shell** (the `.git`
directory is not mounted into the container), while others need the **DDEV
container**'s drush aliases and SSH agent and are run via `ddev sn`.

Run it from the repository root:

```
./sn                          # list available commands
./sn <command> --help         # arguments and options for a command
ddev sn <command>             # for commands that run inside the container
```

`ddev sn` is a thin wrapper around `./sn` inside the container
(`.ddev/commands/web/sn`). Prefer it over `ddev exec ./sn`: it forwards a
terminal, so a command that asks a question can be answered.
