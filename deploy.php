<?php
if ( ! defined( 'WP_CLI' ) ) return;
require 'helpers.php';
require 'runner.php';
use \WP_Deploy_Command\Helpers as Util;
use \WP_Deploy_Command\Command_Runner as Runner;

use \Symfony\Component\Yaml\Yaml;


/**
 * __Current Version__: 1.2.0
 *
 * Deploys the local WordPress database or the uploads, plugins, or themes directories.
 *
 * The tool requires defining a set of constants in your wp-cli.yml file.
 * The constants should be prefixed with the environment handle which you will use as the first paramater for your desired subcommand. An example configuration for a "dev" environment:
 *
 * @dev:
 *   path: /path/to/the/wp/dir/on/the/server
 *   url: the-remote-website-url.com
 *   host: ssh_host
 *   ssh_user: ssh_user
 *   port: ssh_port
 *   writable_path: /path/to/a/writable/dir/on/the/server
 *   uploads_path: /path/to/the/remote/uploads/directory
 *   themes_path: /path/to/the/remote/themes/directory
 *   plugins_path: /path/to/the/remote/plugins/directory
 *   db_host: the_remote_db_host
 *   db_name: the_remote_db_name
 *   db_user: the_remote_db_user
 *   db_password: the_remote_db_password
 *
 * => `wp deploy push dev ...`
 *
 * Not all commands / subcommands require all constants to be defined. To test what
 * a subcommand requires, execute it with a non-existing environment handle. e.g.
 * `wp deploy dump johndoe`.
 *
 * You can define as many constant groups as deployment eviroments you wish to have.
 *
 * __Examples__
 *
 *     # Deploy the local db to the staging environment
 *     wp deploy push staging --what=db
 *
 *     # Pull both the production database and uploads
 *     wp deploy pull production --what=db && wp deploy pull production --what=uploads
 *
 *     # Dump the local db with the siteurl replaced
 *     wp deploy dump andrew
 *
 *
 * #### Configuration Dependecies
 *
 * Subcommands depend on different constants in order to work.
 * Here's the dependency list:
 *
 * * __`wp deploy push`__: In order to push to your server, you need to define the
 * ssh credentials, and a path to a writable directory on the server. _These
 * constants are needed whatever the arguments passed to the `push` subcommand_:
 *     * `%%ENV%%_SSH_USER`
 *     * `%%ENV%%_HOST`
 *     * `%%ENV%%_WRITABLE_PATH`
 *
 * * __`wp deploy push %%env%% --what=db`__: In order to deploy the database to your
 * server, you need to define the url of your WordPress website, the path to
 * the WordPress code on your server, and the credentials to the database on
 * the server:
 *     * `%%ENV%%_URL`
 *     * `%%ENV%%_PATH`
 *     * `%%ENV%%_DB_HOST`
 *     * `%%ENV%%_DB_NAME`
 *     * `%%ENV%%_DB_USER`
 *     * `%%ENV%%_DB_PASSWORD`
 *
 * * __`wp deploy push %%env%% --what=uploads`__: In order to push the uploads directory,
 * you need to define the path to the uploads directory on your server:
 *     * `%%ENV%%_UPLOADS_PATH`
 *
 *  __`wp deploy pull`__: In order to pull to your server, you need to define the
 * sh credentials constants. _These constants are needed whatever the arguments
 * assed to the `pull` subcommand_:
 *     * `%%ENV%%_SSH_USER`
 *     * `%%ENV%%_HOST`
 *
 * * __`wp deploy pull %%env%% --what=db`__: In order to pull the database to from your
 * server, you need to define the url of your remote WordPress website, the
 * path to the WordPress code on your server, and the credentials to the
 * database on the server:
 *     * `%%ENV%%_WRITABLE_PATH`
 *     * `%%ENV%%_URL`
 *     * `%%ENV%%_PATH`
 *     * `%%ENV%%_DB_HOST`
 *     * `%%ENV%%_DB_NAME`
 *     * `%%ENV%%_DB_USER`
 *     * `%%ENV%%_DB_PASSWORD`
 *
 * * __`wp deploy push %%env%% --what=uploads`__: As in the `push` command's case, in
 * order to pull the remote server uploads, we need their path on the server.
 *     * `%%ENV%%_UPLOADS_PATH`
 *
 * * __`wp dump %%env%%`__: This subcommand only requires the path to the target
 * WordPress path and its URL.
 *
 * #### `%%ENV%%_POST_HOOK`
 *
 * You can __optionally__ define a constant with bash code which is called at the
 * end of the subcommand execution.
 *
 * You can refer to environment variables using placeholders. Some of the
 * available environment variables are:
 * * `env`: The environment handle
 * * `command`: The subcommand (Currently `push`, `pull`, or `dump`).
 * * `what`: The what argument value for the `push` or `pull` subcommand.
 * * `wd`: The path to the working directory for the deploy command. This is
 * the directory where the database is pulled, and other temporary files are
 * created.
 * * `timestamp`: The date formatted with "Y_m_d-H_i"
 * * `tmp_path`: The path to the temporary files directory used by the deploy
 * tool.
 * * `bk_path`: The path to the backups directory used by the deploy tool.
 * * `local_uploads`: The path to the local WordPress instance uploads
 * directory.
 * * `ssh`: The ssh server handle in the `user@host` format.
 *
 *
 * __Example__
 *
 * Here's an example of a `DEV_POST_HOOK` that posts a message to a hipchat
 * room after a `pull` or a `push` is performed using the HipChat REST API
 * (https://github.com/hipchat/hipchat-cli).
 * For pushes, it also clears the cache.
 *
 * ```php
 * <?php
 * $hipchat_message = "http://%%url%%"
 *  . "\njeandoe has successfully %%command%%ed %%what%%";
 * $command = "if [[ '%%command%%' != 'dump' ]]; then "
 *      . "echo '$hipchat_message' | %%abspath%%/hipchat-cli/hipchat_room_message -t 1245678 -r 123456 -f 'WP-Cli Deploy';"
 *  . "fi;"
 *  . "if [[ '%%command%%' == 'push' ]]; then "
 *      . "curl -Ss http://example.com/clear_cache.php?token=12385328523;"
 *  . "fi;";
 * define( 'DEV_POST_HOOK', $command );
 * ```
 *
 */
class WP_Deploy_Command extends WP_CLI_Command {

    /**
     * TODO 1.2.0:
     * Update paths in messages to be relative to wordpress dir.
     * Fix the missing path directory at push issue.
     * Update doc.
     * Add dry run
     * Test excludes. Need to be separated by :
     */

    /** The config holder. */
    private static $config;

    private static $configEnv;

    private static $env;

    private static $default_verbosity;

    private static $runner;

    private static $config_dependencies;

    public function __construct() {
        if ( defined( 'WP_DEPLOY_DEBUG' ) && WP_DEPLOY_DEBUG ) {
            ini_set( 'error_reporting', E_ALL & ~E_STRICT );
            ini_set( 'display_errors', 'STDERR' );
        }

        try {
            self::$configEnv = Yaml::parse(file_get_contents('wp-cli.yml'));
        } catch (ParseException $e) {
            WP_Cli::error( "Unable to parse the YAML string: " . $e->getMessage() );
        }

        self::$default_verbosity = 1;

        /** Define the constants dependencies. */
        self::$config_dependencies = array(
            'push' => array(
                'global' => array(
                    'ssh_user',
                    'host',
                    'writable_path',
                ),
                'db' => array(
                    'url',
                    'path',
                    'db_host',
                    'db_name',
                    'db_user',
                    'db_password',
                ),
                'uploads' => array( 'uploads_path' ),
                'themes'  => array( 'themes_path' ),
                'plugins' => array( 'plugins_path' ),
                'core'    => array( 'path' )
            ),
            'pull' => array(
                'global' => array(
                    'ssh_user',
                    'host',
                ),
                'db' => array(
                    'writable_path',
                    'url',
                    'path',
                    'db_host',
                    'db_name',
                    'db_user',
                    'db_password',
                ),
                'uploads' => array( 'uploads_path' ),
                'themes'  => array( 'themes_path' ),
                'plugins' => array( 'plugins_path' ),
                'core'    => array( 'path' )
            ),
            'dump' => array(
                'path',
                'url'
            ),
            'optional' => array(
                'port',
                'post_hook',
                'excludes'
            )
        );

        /**
         * Depending paths need to be under the
         * paths they depend on.
         */
        self::$config = array(
            'env' => '%%env%%',

            /** Constants which refer to remote. */
            'host'          => '%%host%%',
            'ssh_user'      => '%%ssh_user%%',
            'writable_path' => '%%writable_path%%',
            'url'           => '%%url%%',
            'path'          => '%%path%%',
            'uploads'       => '%%uploads_path%%',
            'themes'        => '%%themes_path%%',
            'plugins'       => '%%plugins_path%%',
            'db_host'       => '%%db_host%%',
            'db_name'       => '%%db_name%%',
            'db_user'       => '%%db_user%%',
            'db_password'   => '%%db_password%%',

            /** Optional */
            'port'      => '%%port%%',
            'post_hook' => '%%post_hook%%',
            'safe_mode' => '%%safe_mode%%', /** TODO */
            'excludes'  => '%%excludes%%',
            'themename' => '%%themename%%',

            /** Helpers which refer to local. */
            'command'        => '%%command%%',
            'what'           => '%%what%%',
            'abspath'        => '%%abspath%%',
            'wd'             => '%%abspath%%/%%env%%_%%hash%%',
            'timestamp'      => '%%pretty_date%%',
            'tmp_path'       => '%%wd%%/tmp',
            'bk_path'        => '%%wd%%/bk',
            'tmp'            => '%%tmp_path%%/%%rand%%',
            'local_hostname' => '%%hostname%%',
            'ssh'            => '%%ssh_user%%@%%host%%',
            'local_uploads'  => '%%local_uploads%%',
            'local_themes'   => '%%local_themes%%',
            'local_plugins'  => '%%local_plugins%%',
            'local_core'     => '%%local_core%%',
            'siteurl'        => '%%siteurl%%',
        );
    }

    /**
     * Displays information about the current version of the deploy command.
     *
     * ## EXAMPLE
     *
     *    wp deploy info
     */
    public function info( $args, $assoc_args ) {

        WP_CLI::line( 'WP-Cli Deploy Command: https://github.com/c10b10/wp-cli-deploy' );
        WP_CLI::line( 'Supported subcommands: push, pull, dump' );
        WP_CLI::line( 'Version: 1.1.0-alpha' );
        WP_CLI::line( 'Author: Alex Ciobica / @ciobi' );
        WP_CLI::line( 'Run "wp help deploy" for the documentation' );
    }

    /**
     * Pushes the local database and / or uploads from local to remote.
     *
     * ## OPTIONS
     *
     * <environment>
     * : The handle of of the environment. This is the prefix of the constants
     * defined in wp-config.
     *
     * `--what`=<what>
     * : What needs to be deployed on the server. Valid options are:
     *      db: pushes the database to the remote server
     *      uploads: pushes the uploads to the remote server
     *
     * [`--themename`=<themename>]
     * : Optional: Specify theme name for --what=themes
     *
     * [`--v`=<verbosity>]
     * : Verbosity level. Default 1. 0 is highest and 2 is lowest.
     *
     * ## EXAMPLE
     *
     *    # Push the database and the uploads for to "staging" environment.
     *    # You must have STAGING_* constants defined for this to work.
     *
     *    wp deploy push staging --what=db,uploads
     *
     * @synopsis <environment> --what=<what> [--v=<v>] [--themename=<themename>]
     */
    public function push( $args, $assoc_args ) {

        $args = self::sanitize_args( __FUNCTION__, $args, $assoc_args );

        if ( isset( $args->error ) ) {
            WP_Cli::line( $args->error );
            return false;
        }

        call_user_func( __CLASS__ . "::push_" . self::$config->what );

        self::run_post_hook();

        self::wow();
    }

    /**
     * Pulls the database and / or uploads from remote to local. After pulling
     * the uploads, they need to copied to the correct location.
     *
     * <environment>
     * : The name of the environment. This is the prefix of the constants defined in
     * wp-config.
     *
     * `--what`=<what>
     * : What needs to be pulled. Valid options are:
     *      db: pushes the database to the remote server
     *      uploads: pushes the uploads to the remote server
     *
     * [`--themename`=<themename>]
     * : Optional: Specify theme name for --what=themes
     *
     * [`--v`=<verbosity>]
     * : Verbosity level. Default 1. 0 is highest and 2 is lowest.
     *
     * ## EXAMPLES
     *
     *    # Pulls database and uploads folder
     *    wp deploy pull staging --what=db,uploads
     *
     *    # Pull the remote db without prior local backup
     *    wp deploy pull staging --what=db --backup=false
     *
     * @synopsis <environment> --what=<what> [--cleanup] [--backup=<backup>] [--v=<v>] [--themename=<themename>]
     */
    public function pull( $args, $assoc_args ) {

        $args = self::sanitize_args( __FUNCTION__, $args, $assoc_args );

        if ( isset( $args->error ) ) {
            WP_Cli::line( $args->error );
            return false;
        }

        call_user_func( __CLASS__ . "::pull_" . self::$config->what );

        self::run_post_hook();

        self::wow();
    }

    /**
     * Dumps the local database and / or uploads from local to remote. The
     * database will be prepared for upload to the specified environment.
     *
     * ## OPTIONS
     *
     * <environment>
     * : The name of the environment. This is the prefix of the constants
     * defined in wp-config.php.
     *
     * [`--v`=<v>]
     * : Verbosity level. Default 1. 0 is highest and 2 is lowest.
     *
     * ## EXAMPLE
     *
     *    # Dumps database for to "staging" environment.
     *    wp deploy dump staging
     *
     * @synopsis <environment> [--file=<file>] [--v=<v>]
     */
    public function dump( $args, $assoc_args ) {

        $args = self::sanitize_args( __FUNCTION__, $args, $assoc_args );

        if ( isset( $args->error ) ) {
            WP_Cli::line( $args->error );
            return false;
        }

        self::dump_db();

        self::run_post_hook();

        self::wow();
    }

    /** Pushes the database to the server. */
    private function push_db() {

        $c = self::$config;

        $dump_file = self::dump_db( array(
            'wd' => $c->tmp_path,
            'name' => basename( $c->tmp ),
        ) );
        $server_file = "{$c->local_hostname}_{$c->env}.sql";

        $runner = self::$runner;

        $runner->add(
            Util::get_rsync(
                $dump_file,
                "$c->ssh:$c->writable_path/$server_file",
                $c->port
            ),
            "Uploaded the database file to '$c->writable_path/$server_file' on the server.",
            'Failed to upload the database to the server'
        );

        /** Removing the dump file after upload. */
        $runner->add( "rm -f $dump_file" );

        $runner->add(
            "ssh $c->ssh -p $c->port 'cd $c->writable_path;"
            . " mysql --user=$c->db_user --password=" . escapeshellarg( $c->db_password ) ." --host=$c->db_host"
            . " $c->db_name < $server_file'",
            'Deployed the database on server.',
            'Failed deploying the db on server.'
        );

        $runner->run();
    }

    /** Pushes the uploads to the server. */
    private function push_uploads() {

        $c = self::$config;

        $runner = self::$runner;

        /** TODO safe mode */
        $path = isset( $c->safe_mode ) ? $c->writable_path : $c->uploads;

        $runner->add(
            Util::get_rsync(
                // When pushing safe, we push the dir, hence no trailing slash
                "$c->local_uploads/",
                "$c->ssh:$path",
                $c->port,
                true,
                true,
                $c->excludes
            ),
            "Synced local uploads to '$path' on '$c->host'.",
            'Failed to upload the database to the server'
        );

        $runner->run();
    }

    /** Pushes the themes to the server. */
    private function push_themes() {

        $c = self::$config;

        $runner = self::$runner;

        $local_themes = "$c->local_themes/";

        /** TODO safe mode */
        $path = isset( $c->safe_mode ) ? $c->writable_path : $c->themes;

        if(!empty($c->themename)){
          if ( ! is_dir($local_themes . $c->themename) ) {
              WP_Cli::error( "Using unknown '$c->themename' parameter for --themename argument." );
          }
          else {
            $path .= "/$c->themename/";
            $local_themes .= "$c->themename/";
          }
        }

        $runner->add(
            Util::get_rsync(
                // When pushing safe, we push the dir, hence no trailing slash
                "$local_themes/",
                "$c->ssh:$path",
                $c->port,
                true,
                true,
                "$c->excludes"
            ),
            "Synced local themes to '$path' on '$c->host'.",
            'Failed to upload the database to the server'
        );

        $runner->run();
    }

    /** Pushes the plugins to the server. */
    private function push_plugins() {

        $c = self::$config;

        $runner = self::$runner;

        /** TODO safe mode */
        $path = isset( $c->safe_mode ) ? $c->writable_path : $c->plugins;

        $runner->add(
            Util::get_rsync(
                // When pushing safe, we push the dir, hence no trailing slash
                "$c->local_plugins/",
                "$c->ssh:$path",
                $c->port,
                true,
                true,
                "$c->excludes"
            ),
            "Synced local plugins to '$path' on '$c->host'.",
            'Failed to upload the database to the server'
        );

        $runner->run();
    }

    /** Pushes core to the server. */
    private function push_core() {

    $c = self::$config;

    $runner = self::$runner;

    $excludes = self::core_excludes();

    /** TODO safe mode */
    $path = isset($c->safe_mode) ? $c->writable_path : $c->path;

    $runner->add(
      Util::get_rsync(
        // When pushing safe, we push the dir, hence no trailing slash
        "$c->local_core/",
        "$c->ssh:$path",
        $c->port,
        true,
        true,
        $excludes
      ),
      "Synced local core to '$path' on '$c->host'.",
      'Failed to upload the database to the server'
    );

    $runner->run();
    }

    /** Pulls the database from the server. */
    private function pull_db() {

        $c = self::$config;

        $server_file = "{$c->env}_{$c->timestamp}.sql";

        $runner = self::$runner;

        $runner->add(
            "ssh $c->ssh -p $c->port 'mkdir -p $c->writable_path; cd $c->writable_path;"
            . " mysqldump --user=$c->db_user --password=" . escapeshellarg( $c->db_password ) . " --host=$c->db_host"
            . " --single-transaction"
            . " --add-drop-table $c->db_name > $server_file'",
            "Dumped the remote database to '$c->writable_path/$server_file' on the server.",
            'Failed dumping the remote database.'
        );

        $runner->add(
            Util::get_rsync(
                "$c->ssh:$c->writable_path/$server_file",
                "$c->wd/$server_file",
                $c->port,
                false, false // No delete or compression
            ),
            "Copied the database from the server to '$c->wd/$server_file'."
        );

        $runner->add(
            "ssh $c->ssh -p $c->port 'cd $c->writable_path; rm -f $server_file'",
            'Deleted the server dump.'
        );

        /** TODO Finalize safe mode. */
        $runner->add(
            ! isset( $c->safe_mode ),
            "wp db export $c->bk_path/$c->timestamp.sql",
            "Backed up local database to '$c->bk_path/$c->timestamp.sql'"
        );

        $runner->add(
            "wp db import $c->wd/$server_file",
            'Imported the remote database.'
        );

        $runner->add(
            ( $c->siteurl != $c->url ),
            "wp search-replace --all-tables $c->url $c->siteurl",
            "Replaced '$c->url' with '$c->siteurl' on the imported database."
        );

        $runner->add(
            ( $c->abspath != $c->path ),
            "wp search-replace --all-tables $c->path $c->abspath",
            "Replaced '$c->path' with '$c->abspath' on local database."
        );

        $runner->run();
    }

    /** Pulls the uploads from the server. */
    private static function pull_uploads() {

        $c = self::$config;

        $runner = self::$runner;

        /** TODO Finalize safe mode. */
        $runner->add(
            isset( $c->safe_mode ),
            "cp -rf $c->local_uploads $c->bk_path/uploads_$c->timestamp",
            'Backed up local uploads.'
        );

        $runner->add(
            Util::get_rsync(
                "$c->ssh:$c->uploads/",
                $c->local_uploads,
                $c->port,
                true,
                true,
                $c->excludes
            ),
            "Pulled the '$c->env' uploads locally."
        );


        $runner->run();
    }

    /** Pulls the themes from the server. */
    private static function pull_themes() {

        $c = self::$config;

        $runner = self::$runner;

        /** TODO Finalize safe mode. */
        $runner->add(
            isset( $c->safe_mode ),
            "cp -rf $c->local_themes $c->bk_path/themes_$c->timestamp",
            'Backed up local themes.'
        );

        $local_themes = "$c->local_themes/";
        $path = "$c->themes/";

        if(!empty($c->themename)){
          $path .= "$c->themename/";
          $local_themes .= "$c->themename/";
        }

        $runner->add(
            Util::get_rsync(
                "$c->ssh:$path",
                $local_themes,
                $c->port,
                true,
                true,
                $c->excludes
            ),
            "Pulled the '$c->env' themes locally."
        );


        $runner->run();
    }

    /** Pulls the plugins from the server. */
    private static function pull_plugins() {

        $c = self::$config;

        $runner = self::$runner;

        /** TODO Finalize safe mode. */
        $runner->add(
            isset( $c->safe_mode ),
            "cp -rf $c->local_plugins $c->bk_path/plugins_$c->timestamp",
            'Backed up local plugins.'
        );

        $runner->add(
            Util::get_rsync(
                "$c->ssh:$c->plugins/",
                $c->local_plugins,
                $c->port,
                true,
                true,
                $c->excludes
            ),
            "Pulled the '$c->env' plugins locally."
        );

        $runner->run();
    }

  /** Pulls core from the server. */
  private static function pull_core() {

    $c = self::$config;

    $runner = self::$runner;

    /** TODO Finalize safe mode. */
    $runner->add(
      isset( $c->safe_mode ),
      "cp -rf $c->local_core $c->bk_path/core_$c->timestamp",
      'Backed up local core.'
    );

    $excludes = self::core_excludes();

    $runner->add(
      Util::get_rsync(
        "$c->ssh:$c->path/",
        "$c->local_core/",
        $c->port,
        true,
        true,
        $excludes
      ),
      "Pulled the '$c->env' core locally."
    );

    $runner->run();
  }

    /** Dumps the local database after performing search-replace. */
    private static function dump_db( $args = array() ) {

        $c = self::$config;

        $args = wp_parse_args( $args, array(
            'name' => "{$c->env}_{$c->timestamp}",
            'wd' => $c->wd
        ) );
        $path = "{$args['wd']}/{$args['name']}.sql";

        $runner = self::$runner;

        $runner->add(
            ( $c->abspath != $c->path ) || ( $c->url != $c->siteurl ),
            "wp db export $c->tmp",
            "Exported a local backup of the database to '$c->tmp'."
        );

        $runner->add(
            ( $c->siteurl != $c->url ),
            "wp search-replace --all-tables $c->siteurl $c->url",
            "Replaced '$c->siteurl' with '$c->url' in local database."
        );

        $runner->add(
            ( $c->abspath != $c->path ),
            "wp search-replace --all-tables $c->abspath $c->path",
            "Replaced '$c->abspath' with with '$c->path' in local database."
        );

        $runner->add(
            "wp db export $path",
            "Dumped the database to '$path'."
        );

        $runner->add(
            ( $c->abspath != $c->path ) || ( $c->url != $c->siteurl ),
            "wp db import $c->tmp",
            'Imported the local backup.'
        );

        $runner->add(
            ( $c->abspath != $c->path ) || ( $c->url != $c->siteurl ),
            "rm -f $c->tmp",
            'Cleaned up.'
        );

        $runner->run();

        return $path;
    }

    /** Sanitizes the arguments, and sets the configuration. */
    private static function sanitize_args( $command, $args, $assoc_args = null ) {

        self::$env = $args[0];

        /** If what is available, it needs to refer to an existing method. */
        $what = '';
        if ( isset( $assoc_args['what'] ) ) {
            $what = $assoc_args['what'];
            if ( ! method_exists( __CLASS__, "{$command}_{$what}" ) ) {
                WP_Cli::error( "Using unknown '$what' parameter for --what argument." );
            }
        }

        $themename = null;
        if ( isset( $assoc_args['themename'] ) ) {
            $themename = $assoc_args['themename'];
        }

        /**
         * Eeeek! So ugly.
         * TODO. Fix this.
         */
        $verbosity = self::$default_verbosity;
        if ( isset( $assoc_args['v'] ) && in_array( $assoc_args['v'], range( 0, 2 ) ) )
            $verbosity = $assoc_args['v'];
        self::$runner = new Runner( $verbosity );

        /** Get the environmental and set the tool config. */
        $constants = self::validate_config( $command, $what, self::$env );
        self::$config = self::expand( self::$config, $constants, $command, $what, $themename );

        /** Create paths. */
        Runner::get_result( 'mkdir -p ' . self::$config->tmp_path . ';' );
        Runner::get_result( 'mkdir -p ' . self::$config->bk_path . ';' );

        return self::$config;
    }

    /** Determines the verbosity level: 1, 2, or 3 */
    private static function get_verbosity( $string, $default ) {
        $number = count_chars_unicode( $string, 'v' );
        if ( $number )
            return min( $number, 2 );
        return $default;
    }

    /**
     * Verifies that all required constants are defined.
     * Constants must be of the form: "%ENV%_%NAME%"
     */
    private static function validate_config( $command, $what, $env ) {

        /** Get the required contstants from the dependency array. */
        $deps = self::$config_dependencies;
        $required = $deps[$command];
        if ( ! empty( $what ) ) {
            $required = array_unique( array_merge(
                $deps[$command][$what],
                $deps[$command]['global']
            ) );
        }

        /** Get all definable constants. */
        $all_const = array();
        foreach( $deps as $comm_deps ) {
            foreach ( $comm_deps as $item ) {
                $const = is_array( $item ) ? $item : array( $item );
                $all_const = array_merge( $all_const, $const );
            }
        }
        $all_const = array_unique( $all_const );

        $get_const = function ( $const ) use ( $env ) {
            return strtoupper( $env . '_' . $const );
        };

        $errors = array();
        $constants = array();
        foreach ( $all_const as $constant ) {
            /** The constants template */
            if ( in_array( $constant, $required ) && ! isset( self::$configEnv["@$env"][$constant] ) ) {
                $errors[] = "Required $constant is not defined for env $env.";
            } elseif ( isset( self::$configEnv["@$env"][$constant] ) ) {
                $constants[$constant] = self::$configEnv["@$env"][$constant];
            }
        }

        if ( count( $errors ) ) {
            foreach ( $errors as $error ) {
                WP_Cli::line( "$error" );
            }
            WP_Cli::error( "The missing constants are required in order to run this subcommand.\nType `wp help deploy` for more information." );
        }

        /** Add the optional constants. */
        foreach ( $deps['optional'] as $const ) {
            if ( isset( self::$configEnv["@$env"][$const] ) )
                $constants[$const] = self::$configEnv["@$env"][$const];
        }

        return $constants;
    }

    /** Replaces the placeholders in the paths with actual data. */
    private static function expand( $config, $constants, $command, $what, $themename ) {

        $data = array(
            'env' => self::$env,
            'command' => $command,
            'what' => $what,
            'themename' => $themename,
            'excludes' => ( isset( $constants['excludes'] ) && is_string( $constants['excludes'] ) ? $constants['excludes'] : false ),
            'port' => ( isset( $constants['port'] ) ? $constants['port'] : '22' ),
            'hash' => Util::get_hash(),
            'abspath' => untrailingslashit( ABSPATH ),
            'pretty_date' => date( 'Y_m_d-H_i' ),
            'rand' => substr( sha1( time() ), 0, 8 ),
            'hostname' => Runner::get_result( "hostname" ),
            'local_uploads' => call_user_func( function() {
                $uploads_dir = wp_upload_dir();
                return untrailingslashit( Runner::get_result(
                    "cd {$uploads_dir['basedir']}; pwd -P;"
                ) );
            } ),
            'local_themes' => call_user_func( function() {
                $themes_dir = get_theme_root();
                return untrailingslashit( Runner::get_result(
                    "cd {$themes_dir}; pwd -P;"
                ) );
            } ),
            'local_plugins' => call_user_func( function() {
                $plugins_dir = WP_PLUGIN_DIR; // TODO: get the plugin directory in a better manner
                return untrailingslashit( Runner::get_result(
                    "cd {$plugins_dir}; pwd -P;"
                ) );
            } ),
            'local_core' => call_user_func( function() {
                $dir = WP_CONTENT_DIR . '/../'; // TODO: get the plugin directory in a better manner
                return untrailingslashit( Runner::get_result(
                    "cd {$dir}; pwd -P;"
                ) );
            } ),
            'siteurl' => untrailingslashit( Util::trim_url(
                get_option( 'siteurl' ),
                true
            ) ),
            'object' => (object) array_map( 'untrailingslashit', $constants ),
        );

        foreach ( $config as &$item ) {
            $item = Util::unplaceholdit( $item, array_merge(
                /** This ensures we can have dependecies. */
                $config,
                $data
            ) );
        }

        if ( isset( $constants['post_hook'] ) ) {
            $config['post_hook'] = Util::unplaceholdit( $config['post_hook'], array_merge( $config, $data ) );
        }

        /** Remove unset config items (constants). */
        $config = array_filter( $config, function ( $item ) {
            return strpos( $item, '%%' ) === false;
        } );

        /** Return the config in object form. */
        return (object) $config;
    }

    public function run_post_hook() {

        if ( isset( self::$config->post_hook ) ) {
            $result = Runner::get_result( self::$config->post_hook );
            if ( ! empty( $result ) ) {
                var_dump( $result );
            }
            WP_Cli::line( "Ran post hook." );
        }
    }

    private static function wow() {
        $doge = array( 'wow', 'many', 'such', 'so' );
        $words = array( 'finish', 'done', 'end', 'deploy' );
        WP_CLI::line('');
        WP_Cli::success(
            $doge[array_rand( $doge, 1 )] . ' ' .
            $words[array_rand( $words, 1 )] . '!'
        );
    }

  private static function core_excludes() {
    $c = self::$config;

    $user_excludes = $c->excludes ? explode( ':', (string) $c->excludes ) : array();
    $excludes = array(
      '/wp-cli.phar',
      '/wp-cli.yml',
      '/.htaccess',
      '/.htaccess.dist',
      '/robots.txt',
      '/robots.txt.dist',
      '/wp-config.php',
      '/wp-content/uploads',
      '/wp-content/blogs.dir',
      '/wp-content/wp-rocket-config',
      '/wp-content/advanced-cache.php',
      '/wp-content/plugins',
      '/wp-content/themes'
    );

    $excludes = array_merge($excludes, $user_excludes);
    $excludes = implode(':', $excludes);

    return $excludes;
  }
}

WP_CLI::add_command( 'deploy', 'WP_Deploy_Command' );
