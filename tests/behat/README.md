# Behat
In order to run behat tests locally, you must override the `project.local.protocol` in your blt/local.blt.yml file. It needs to be set to `http` so that it matches the base URL set in the tests/behat/local.yml configuration.

Example snippet to include the blt/local.blt.yml file:
```
project:
  local:
    protocol: http
```

Once set, run `blt setup:behat` to generate the behat local config file.

To run behat tests, run `blt behat`. Additional options and filtering can be specified - see `blt behat --help` for details or `blt list tests` for all tests-related commands.
