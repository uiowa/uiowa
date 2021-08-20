# Behat
In order to run behat tests locally, you must override the DrupalVM SSL certificate. See the README in the `box/certs/` directory for instructions on how to do this.

To run behat tests, run `blt behat`. Additional options and filtering can be specified - see `blt behat --help` for details or `blt list tests` for all tests-related commands.

**Note** that behat tests will run against the default site so any created content, users, etc. will be reflected there.
