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

## Commands

| Command | Alias | Runs in |
|---|---|---|
| `multisite:create` | `mc`, `umc` | host |
| `report:domains` | `domains` | DDEV |
| `report:splits` | `splits` | DDEV + SSH agent |
| `report:inactive` | `inactive` | DDEV + SSH agent |
| `routes:custom` | — | host or DDEV |

The report commands run inside DDEV because they use drush aliases and reach
prod sites over your forwarded SSH agent. Load your keys first with
`ddev auth ssh`. A command run in the wrong environment exits with an error
naming the correct invocation.

## Structure

- `sn` (repository root): entry point; registers commands.
- `sitenow/src/Command/`: command classes.
- `sitenow/src/Report/`: report helpers (fleet-domain iteration, the drush
  runner, the CSV export writer).
- `sitenow/src/Traits/`: cross-command helpers (Acquia/drush/environment
  helpers, option parsing).
- `sitenow/src/Plan/`, `sitenow/src/Operation/`, `sitenow/src/Config/`:
  validation, file and API operations, and the application registry used by
  `multisite:create`.
