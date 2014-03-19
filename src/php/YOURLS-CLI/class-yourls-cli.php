<?php

/**
 * Wrapper class for YOURLS-CLI
 *
 * @package yourls-cli
 */
class YOURLS_CLI {

    private static $commands = array();
    
    /**
     * Splits a string into positional and associative arguments.
     *
     * @param string
     * @return array
     */
    static function parse_args( $arguments ) {
        $regular_args = array();
        $assoc_args = array();

        foreach ( $arguments as $arg ) {
            if ( preg_match( '|^--([^=]+)$|', $arg, $matches ) ) {
                $assoc_args[ $matches[1] ] = true;
            } elseif ( preg_match( '|^--([^=]+)=(.+)|', $arg, $matches ) ) {
                $assoc_args[ $matches[1] ] = $matches[2];
            } else {
                $regular_args[] = $arg;
            }
        }

        return array( $regular_args, $assoc_args );
    }
 
    /**
     * Load dependencies or exit with an error message if not found
     *
     */
    static function load_dependencies() {
        $has_autoload = false;

        if ( file_exists( YOURLS_CLI_ROOT . '/vendor/autoload.php' ) ) {
            require_once YOURLS_CLI_ROOT . '/vendor/autoload.php';
        } else {
            fputs( STDERR, "Error: Can't find required libraries. Install using Composer.\n" );
            exit(1);
        }
    }

    /**
     * Run a command
     *
     * @param array $arguments
     * @param array $assoc_args
     */
    static function run_command( $arguments, $assoc_args ) {
        if ( empty( $arguments ) ) {
            $command = 'help';
        } else {
            $command = array_shift( $arguments );
        }
        
        define( 'YOURLS_CLI_COMMAND', $command );

        $implementation = self::load_command( $command );
        $instance = new $implementation( $arguments, $assoc_args );
    }
    
    /**
     * Load a command, prior to running it the first time
     *
     * @param string $command
     * @return 
     */
    static function load_command( $command ) {
        if ( !isset( YOURLS_CLI::$commands[$command] ) ) {
            $path = YOURLS_CLI_ROOT . "/commands/$command.php";
            if ( is_readable( $path ) ) {
                include $path;
            }
        }
        
        if ( !isset( YOURLS_CLI::$commands[$command] ) ) {
            YOURLS_CLI::error( "'$command' is not a registered yourls command. See 'yourls help'." );
            exit(1);
        }

        return YOURLS_CLI::$commands[$command];
    }

	static function load_all_commands() {
        foreach ( glob( YOURLS_CLI_ROOT . "/commands/*.php" ) as $filename ) {
            $command = substr( basename( $filename ), 0, -4 );

            if ( isset( self::$commands[ $command ] ) )
                continue;

            include $filename;
        }

		return self::$commands;
	}

    
    /**
     * Add a command to the list of commands
     *
     * @param string $name The name of the command that will be used in the cli
     * @param string $class The class to manage the command
     */
    static function add_command( $name, $class ) {
        self::$commands[ $name ] = $class;
    }

    /**
     * Display a message in the cli
     *
     * @param string $message
     */
    static function out( $message ) {
        if ( YOURLS_CLI_QUIET ) return;
        \cli\out($message);
    }

    /**
     * Display a message in the CLI and end with a newline
     *
     * @param string $message
     */
    static function line( $message = '' ) {
        if ( YOURLS_CLI_QUIET ) return;
        \cli\line($message);
    }

    /**
     * Display an error in the CLI and end with a newline
     *
     * @param string $message
     * @param string $label
     */
    static function error( $message, $label = 'Error' ) {
        \cli\err( '%R' . $label . ': %n' . $message );
        exit(1);
    }

    /**
     * Display a success in the CLI and end with a newline
     *
     * @param string $message
     * @param string $label
     */
    static function success( $message, $label = 'Success' ) {
        if ( YOURLS_CLI_QUIET )
            return;
        \cli\line( '%G' . $label . ': %n' . $message );
    }

    /**
     * Display a warning in the CLI and end with a newline
     *
     * @param string $message
     * @param string $label
     */
    static function warning( $message, $label = 'Warning' ) {
        if ( YOURLS_CLI_QUIET )
            return;
        \cli\err( '%C' . $label . ': %n' . self::error_to_string( $message ) );
    }

    /**
     * Issue warnings for each missing associative argument.
     *
     * @param array List of required arg names
     * @param array Passed args
     */
    static function check_required_args( $required, $assoc_args ) {
        $missing = false;

        foreach ( $required as $arg ) {
            if ( !isset( $assoc_args[ $arg ] ) ) {
                YOURLS_CLI::warning( "--$arg parameter is missing" );
                $missing = true;
            } elseif ( true === $assoc_args[ $arg ] ) {
                // passed as a flag
                YOURLS_CLI::warning( "--$arg needs to have a value" );
                $missing = true;
            }
        }

        if ( $missing )
            exit(1);
    }

    static function get_numeric_arg( $args, $index, $name ) {
        if ( ! isset( $args[$index] ) ) {
            YOURLS_CLI::error( "$name required" );
        }

        if ( ! is_numeric( $args[$index] ) ) {
            YOURLS_CLI::error( "$name must be numeric" );
        }

        return $args[$index];
    }

}