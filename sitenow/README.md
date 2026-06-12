# SiteNow CLI (`sn`)

`sn` runs SiteNow repository commands on the host shell, outside the DDEV
container. Commands that touch the git working tree run on the host because the
`.git` directory is not mounted into the container.

Run it from the repository root:

```
./sn                    # list available commands
./sn <command> --help   # arguments and options for a command
```

## Structure

- `sn` (repository root): entry point; registers commands.
- `sitenow/src/Command/`: command classes.
- `sitenow/src/Plan/`, `sitenow/src/Operation/`, `sitenow/src/Config/`: validation, file and API operations, and the application registry the commands use.
