@0x96e87dc4e55dc40e;

using Spk = import "/sandstorm/package.capnp";
# This imports:
#   $SANDSTORM_HOME/latest/usr/include/sandstorm/package.capnp
# Check out that file to see the full, documented package definition format.

const pkgdef :Spk.PackageDefinition = (
  # The package definition. Note that the spk tool looks specifically for the
  # "pkgdef" constant.

  id = "s244puc94dz2nph0n38qgkxkg3yrckxc93vxuz31grtey4rke3j0",
  # Your app ID is actually its public key. The private key was placed in
  # your keyring. All updates must be signed with the same key.

  manifest = (
    # This manifest is included in your app package to tell Sandstorm
    # about your app.

    appTitle = (defaultText = "Framadate"),

    appVersion = 1,  # Increment this for every release.

    appMarketingVersion = (defaultText = "0.8"),
    # Human-readable representation of appVersion. Should match the way you
    # identify versions of your app in documentation and marketing.

    actions = [
      # Define your "new document" handlers here.
      ( title = (defaultText = "New date picker"),
        nounPhrase = (defaultText = "date picker"),
        command = .actionSchedule,
      ),
      ( title = (defaultText = "New Poll"),
        nounPhrase = (defaultText = "poll"),
        command = .actionPoll,
      ),
    ],

    continueCommand = .actionContinue,
    # This is the command called to start your app back up after it has been
    # shut down for inactivity. Here we're using the same command as for
    # starting a new instance, but you could use different commands for each
    # case.

    metadata = (
      icons = (
        appGrid = (svg = embed "framadate-128.svg"),
        grain = (svg = embed "framadate-24.svg"),
        market = (svg = embed "framadate-150.svg"),
      ),
      website = "https://framadate.org/",
      codeUrl = "https://github.com/zarvox/framadate",
      license = (openSource = gpl3),
      categories = [productivity],

      author = (
        contactEmail = "drew@sandstorm.io",
        pgpSignature = embed "pgp-signature",
        upstreamAuthor = "Framasoft",
      ),
      pgpKeyring = embed "pgp-keyring",
      description = (defaultText = embed "description.md"),
      shortDescription = (defaultText = "Pick a time, take a poll"),
      screenshots = [
        (width = 446, height = 300, png = embed "../images/classic.png"),
        (width = 446, height = 300, png = embed "../images/date.png"),
      ],
      changeLog = (defaultText = embed "changelog.md"),
    ),
  ),

  sourceMap = (
    # Here we defined where to look for files to copy into your package. The
    # `spk dev` command actually figures out what files your app needs
    # automatically by running it on a FUSE filesystem. So, the mappings
    # here are only to tell it where to find files that the app wants.
    searchPath = [
      ( sourcePath = "." ),  # Search this directory first.
      ( sourcePath = "/",    # Then search the system root directory.
        hidePaths = [ "home", "proc", "sys",
                      "etc/passwd", "etc/hosts", "etc/host.conf",
                      "etc/nsswitch.conf", "etc/resolv.conf",
                      "opt/app/.git", "opt/app/.sandstorm/.vagrant",
                      ]
        # You probably don't want the app pulling files from these places,
        # so we hide them. Note that /dev, /var, and /tmp are implicitly
        # hidden because Sandstorm itself provides them.
      )
    ]
  ),

  fileList = "sandstorm-files.list",
  # `spk dev` will write a list of all the files your app uses to this file.
  # You should review it later, before shipping your app.

  alwaysInclude = [
    "opt/app"
  ],
  # Fill this list with more names of files or directories that should be
  # included in your package, even if not listed in sandstorm-files.list.
  # Use this to force-include stuff that you know you need but which may
  # not have been detected as a dependency during `spk dev`. If you list
  # a directory here, its entire contents will be included recursively.

  bridgeConfig = (
    viewInfo = (
      permissions = [(
          name = "admin",
          title = (defaultText = "admin"),
          description = (defaultText = "grants ability to edit anyone's response or comments"),
        ),
      ],
      roles = [(
          title = (defaultText = "admin"),
          verbPhrase = (defaultText = "can edit and remove all responses"),
          permissions = [true],
          default = false,
        ),(
          title = (defaultText = "responder"),
          verbPhrase = (defaultText = "can submit and view responses"),
          permissions = [false],
          default = true,
        ),
      ],
    ),
  ),
);

const actionSchedule :Spk.Manifest.Command = (
  argv = ["/sandstorm-http-bridge", "8000", "--", "/opt/app/.sandstorm/new-schedule.sh"],
  environ = [
    # Note that this defines the *entire* environment seen by your app.
    (key = "PATH", value = "/usr/local/bin:/usr/bin:/bin")
  ]
);

const actionPoll :Spk.Manifest.Command = (
  argv = ["/sandstorm-http-bridge", "8000", "--", "/opt/app/.sandstorm/new-poll.sh"],
  environ = [
    # Note that this defines the *entire* environment seen by your app.
    (key = "PATH", value = "/usr/local/bin:/usr/bin:/bin")
  ]
);

const actionContinue :Spk.Manifest.Command = (
  # Here we define the command used to start up your server.
  argv = ["/sandstorm-http-bridge", "8000", "--", "/opt/app/.sandstorm/launcher.sh"],
  environ = [
    # Note that this defines the *entire* environment seen by your app.
    (key = "PATH", value = "/usr/local/bin:/usr/bin:/bin")
  ]
);
