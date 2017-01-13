## Project maintainers welcomed
*January, 2017*

## WP-Cli Deploy

__Current Version__: 1.2.0

Deploys the local WordPress database or uploads directory.

The tool requires defining a set of values in your wp-cli.yml file.
The constants should be prefixed with the environment handle which you will use as the first parameter for your desired subcommand. An example configuration for a "dev" environment:

```
@dev:
  path: /path/to/the/wp/dir/on/the/server
  url: the-remote-website-url.com
  host: ssh_host
  ssh_user: ssh_user
  port: ssh_port
  writable_path: /path/to/a/writable/dir/on/the/server
  uploads_path: /path/to/the/remote/uploads/directory
  themes_path: /path/to/the/remote/themes/directory
  plugins_path: /path/to/the/remote/plugins/directory
  db_host: the_remote_db_host
  db_name: the_remote_db_name
  db_user: the_remote_db_user
  db_password: the_remote_db_password
```

=> `wp deploy push dev ...`

Not all commands / subcommands require all constants to be defined. To test what
a subcommand requires, execute it with a non-existing environment handle. e.g.
`wp deploy dump dev`.

You can define as many constant groups as deployment enviroments you wish to have.

__Examples__

    # Deploy the local db to the staging environment
    wp deploy push staging --what=db

    # Pull both the production database and uploads
    wp deploy pull production --what=db && wp deploy pull production --what=uploads

    # Pull both the production themes and plugins
    wp deploy pull production --what=themes && wp deploy pull production --what=plugins

    # Dump the local db with the siteurl replaced
    wp deploy dump production


#### Configuration Dependecies

Subcommands depend on different constants in order to work.
Here's the dependency list:

* __`wp deploy push`__: In order to push to your server, you need to define the
ssh credentials, and a path to a writable directory on the server. _These
constants are needed whatever the arguments passed to the `push` subcommand_:
    * `%%ENV%%_SSH_USER`
    * `%%ENV%%_HOST`
    * `%%ENV%%_WRITABLE_PATH`

* __`wp deploy push %%env%% --what=db`__: In order to deploy the database to your
server, you need to define the url of your WordPress website, the path to
the WordPress code on your server, and the credentials to the database on
the server:
    * `%%ENV%%_URL`
    * `%%ENV%%_PATH`
    * `%%ENV%%_DB_HOST`
    * `%%ENV%%_DB_NAME`
    * `%%ENV%%_DB_USER`
    * `%%ENV%%_DB_PASSWORD`

* __`wp deploy push %%env%% --what=uploads`__: In order to push the uploads directory,
you need to define the path to the uploads directory on your server:
    * `%%ENV%%_UPLOADS_PATH`

 __`wp deploy pull`__: In order to pull to your server, you need to define the
sh credentials constants. _These constants are needed whatever the arguments
assed to the `pull` subcommand_:
    * `%%ENV%%_USER`
    * `%%ENV%%_HOST`

* __`wp deploy pull %%env%% --what=db`__: In order to pull the database to from your
server, you need to define the url of your remote WordPress website, the
path to the WordPress code on your server, and the credentials to the
database on the server:
    * `%%ENV%%_WRITABLE_PATH`
    * `%%ENV%%_URL`
    * `%%ENV%%_PATH`
    * `%%ENV%%_DB_HOST`
    * `%%ENV%%_DB_NAME`
    * `%%ENV%%_DB_USER`
    * `%%ENV%%_DB_PASSWORD`

* __`wp deploy push %%env%% --what=uploads`__: As in the `push` command's case, in
order to pull the remote server uploads, we need their path on the server.
    * `%%ENV%%_UPLOADS_PATH`

* __`wp deploy push %%env%% --what=themes`__: As in the `push` command's case, in
order to pull the remote server themes, we need their path on the server.
    * `%%ENV%%_THEMES_PATH`

* __`wp deploy push %%env%% --what=themes --themename=mytheme`__: As in the `push` command's case, in
order to pull the remote server specific theme, we need their path on the server.
    * `%%ENV%%_THEMES_PATH`

* __`wp deploy push %%env%% --what=plugins`__: As in the `push` command's case, in
order to pull the remote server plugins, we need their path on the server.
    * `%%ENV%%_PLUGINS_PATH`

* __`wp deploy push %%env%% --what=core`__: As in the `push` command's case, in
order to pull the remote server core, we need their path on the server.
    * `%%ENV%%_PATH`

* __`wp dump %%env%%`__: This subcommand only requires the path to the target
WordPress path and its URL.

#### `%%ENV%%_POST_HOOK`

You can __optionally__ define a constant with bash code which is called at the
end of the subcommand execution.

You can refer to environment variables using placeholders. Some of the
available environment variables are:
* `env`: The environment handle
* `command`: The subcommand (Currently `push`, `pull`, or `dump`).
* `what`: The what argument value for the `push` or `pull` subcommand.
* `wd`: The path to the working directory for the deploy command. This is
the directory where the database is pulled, and other temporary files are
created.
* `timestamp`: The date formatted with "Y_m_d-H_i"
* `tmp_path`: The path to the temporary files directory used by the deploy
tool.
* `bk_path`: The path to the backups directory used by the deploy tool.
* `local_uploads`: The path to the local WordPress instance uploads
directory.
* `ssh`: The ssh server handle in the `user@host` format.


__Example__

Here's an example of a `DEV_POST_HOOK` that posts a message to a hipchat
room after a `pull` or a `push` is performed using the HipChat REST API
(https://github.com/hipchat/hipchat-cli).
For pushes, it also clears the cache.

```php
<?php
$hipchat_message = "http://%%url%%"
	. "\njeandoe has successfully %%command%%ed %%what%%";
$command = "if [[ '%%command%%' != 'dump' ]]; then "
		. "echo '$hipchat_message' | %%abspath%%/hipchat-cli/hipchat_room_message -t 1245678 -r 123456 -f 'WP-Cli Deploy';"
	. "fi;"
	. "if [[ '%%command%%' == 'push' ]]; then "
		. "curl -Ss http://example.com/clear_cache.php?token=12385328523;"
	. "fi;";
define( 'DEV_POST_HOOK', $command );
```

__Credits__

* Contributors: [opsone](https://github.com/opsone)
* Fork of: [terminalpixel](https://github.com/terminalpixel)
* https://github.com/demental/wp-deploy-flow for inspiration.
