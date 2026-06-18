# SiteNow CLI (`sn`)

`sn` runs SiteNow repository commands. Each command documents where it must
run: some touch the git working tree and run on the **host shell** (the `.git`
directory is not mounted into the container), while others need the **DDEV
container**'s drush aliases and SSH agent and are run via `ddev exec`.

Run it from the repository root:

```
./sn                          # list available commands
./sn <command> --help         # arguments and options for a command
ddev exec ./sn <command>      # for commands that run inside the container
```
