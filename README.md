# php_github_updater
A small script with the aim to update a local php app installation from GitHub without special needs
(easy to install, configure, integrate or run stand alone)

## Installation and configuration
It's really easy, all you need to do is:
 * Place the script in the root directory of the project (if it's cloned the directory where .git is located)

Adjust the following settings according to your demands
 * `$user` the repository owner
 * `$repo` name of the repository
 * `$do_update` to `true` if available updates should be installed, otherwise chose `false` (only relevant to manual usage)
 * `$target_directory`  directory with the files to update (if the script is in the main project path `__DIR__` will do the trick)
 * `$write_output` Defines if the current progress is written in the file `$write_output_file` (`true` or `false`)
 * `$write_output_file` Filename of the progress file
 * `$usage_password` Password for update when using the own gui
 * `$capture_requests` Defines if the `$_REQUEST` will be parsed (`true` or `false`) required when own gui is used
 * `$use_own_gui` Defines is the own gui will be used (`true` or `false`)
 * `$github_account['user']` Your github user account login name(most likely not needed)
 * `$github_account['pass']` Your github user account password(most likely not needed)
 (You can also copy rename the file ``mygitpw.php.dist` to  `mygitpw.php` and adjust the values within it)

GitHub limits the unauthorized requests to 60 per hour, afterwards you'll get blocked
For develeopment reasons it's proposed to set the `$github_account` variables to fit your GitHub account data

## Direct Usage ($use_own_gui = false)
Simply visit the `gitupdater.php` in your installation with your browser
If `$do_update` is set to `true` the updates will applied and a message displayed.
Otherwise only the message will be shown.

## GUI usage ($use_own_gui = true)
Simply visit the page and you'll see a small website offering you the options to
either only check for updates or applying them
If you defined a password `$usage_password`  this will be required to perform the actions
If `$write_output_file` is defined and `$write_output` is `true` the current state of the action will be displayed (and refreshed every second)

### How it works
The script captures a list of commits from api.github.com
 * It will than start with the newest commit and caputure the checksums of the files changed
 * If the checksums of at least one file from the commit doesn't match with the one of the local available file,
 * the file will be added to the list and the next (older) commit will be inspected and so on.

This will end as soon as all files of a commit match with the local versions (Version A) or all commits supplied by the api query have unmet files (Version B).

If files need to be updatd, the script will (provided $do_update is set to true )
 * Version A: Copy each single file from github and replaces the local version
 * Version B: Download the master.zip from the Github repository and unzip it in the directory
 

--a Schnoog project--