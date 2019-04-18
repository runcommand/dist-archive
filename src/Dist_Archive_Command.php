<?php

/**
 * Create a distribution archive based on a project's .distignore file.
 */
class Dist_Archive_Command {

	/**
	 * Create a distribution archive based on a project's .distignore file.
	 *
	 * For a plugin in a directory 'wp-content/plugins/hello-world', this command
	 * creates a distribution archive 'wp-content/plugins/hello-world.zip'.
	 *
	 * You can specify files or directories you'd like to exclude from the archive
	 * with a .distignore file in your project repository:
	 *
	 * ```
	 * .distignore
	 * .editorconfig
	 * .git
	 * .gitignore
	 * .travis.yml
	 * circle.yml
	 * ```
	 *
	 * Use one distibution archive command for many projects, instead of a bash
	 * script in each project.
	 *
	 * ## OPTIONS
	 *
	 * <path>
	 * : Path to the project that includes a .distignore file.
	 *
	 * [<target>]
	 * : Path and file name for the distribution archive. Defaults to project directory name plus version, if discoverable.
	 *
	 * [--format=<format>]
	 * : Choose the format for the archive.
	 * ---
	 * default: zip
	 * options:
	 *   - zip
	 *   - targz
	 * ---
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		list( $path ) = $args;
		if ( isset( $args[1] ) ) {
			$archive_file = $args[1];
			$info         = pathinfo( $archive_file );
			if ( '.' === $info['dirname'] ) {
				$archive_file = getcwd() . '/' . $info['basename'];
			}
		} else {
			$archive_file = null;
		}
		$path = rtrim( realpath( $path ), '/' );
		if ( ! is_dir( $path ) ) {
			WP_CLI::error( 'Provided path is not a directory.' );
		}

		$dist_ignore_path = $path . '/.distignore';
		if ( ! file_exists( $dist_ignore_path ) ) {
			WP_CLI::error( 'No .distignore file found.' );
		}

		$maybe_ignored_files = explode( PHP_EOL, file_get_contents( $dist_ignore_path ) );
		$ignored_files       = array();
		$archive_base        = basename( $path );
		foreach ( $maybe_ignored_files as $file ) {
			$file = trim( $file );
			if ( 0 === strpos( $file, '#' ) || empty( $file ) ) {
				continue;
			}
			if ( is_dir( $path . '/' . $file ) ) {
				$file = rtrim( $file, '/' ) . '/*';
			}
			if ( 'zip' === $assoc_args['format'] ) {
				$ignored_files[] = '*/' . $file;
			} elseif ( 'targz' === $assoc_args['format'] ) {
				$ignored_files[] = $file;
			}
		}

		$version = '';
		foreach ( glob( $path . '/*.php' ) as $php_file ) {
			$contents = file_get_contents( $php_file, false, null, 0, 5000 );
			$version = $this->get_version_in_code($contents);
			if( null !== $version ) {
				break;
			}
		}

		if ( empty( $version ) && file_exists( $path . '/composer.json' ) ) {
			$composer_obj = json_decode( file_get_contents( $path . '/composer.json' ) );
			if ( ! empty( $composer_obj->version ) ) {
				$version = '.' . trim( $composer_obj->version );
			}
		}

		if ( false !== stripos( $version, '-alpha' ) && is_dir( $path . '/.git' ) ) {
			$response   = WP_CLI::launch( "cd {$path}; git log --pretty=format:'%h' -n 1", false, true );
			$maybe_hash = trim( $response->stdout );
			if ( $maybe_hash && 7 === strlen( $maybe_hash ) ) {
				$version .= '-' . $maybe_hash;
			}
		}

		if ( is_null( $archive_file ) ) {
			$archive_file = dirname( $path ) . '/' . $archive_base . $version;
			if ( 'zip' === $assoc_args['format'] ) {
				$archive_file .= '.zip';
			} elseif ( 'targz' === $assoc_args['format'] ) {
				$archive_file .= '.tar.gz';
			}
		}

		chdir( dirname( $path ) );

		if ( 'zip' === $assoc_args['format'] ) {
			$excludes = implode( ' --exclude ', $ignored_files );
			if ( ! empty( $excludes ) ) {
				$excludes = ' --exclude ' . $excludes;
			}
			$cmd = "zip -r {$archive_file} {$archive_base} {$excludes}";
		} elseif ( 'targz' === $assoc_args['format'] ) {
			$excludes = array_map(
				function( $ignored_file ) {
					if ( '/*' === substr( $ignored_file, -2 ) ) {
						$ignored_file = substr( $ignored_file, 0, ( strlen( $ignored_file ) - 2 ) );
					}
						return "--exclude='{$ignored_file}'";
				}, $ignored_files
			);
			$excludes = implode( ' ', $excludes );
			$cmd      = "tar {$excludes} -zcvf {$archive_file} {$archive_base}";
		}

		WP_CLI::debug( "Running: {$cmd}", 'dist-archive' );
		$ret = WP_CLI::launch( escapeshellcmd( $cmd ), false, true );
		if ( 0 === $ret->return_code ) {
			$filename = pathinfo( $archive_file, PATHINFO_BASENAME );
			WP_CLI::success( "Created {$filename}" );
		} else {
			$error = $ret->stderr ? $ret->stderr : $ret->stdout;
			WP_CLI::error( $error );
		}
	}

	/**
	 * Gets the content of a version tag in any doc block in the given source code string
	 *
	 * The version tag might be specified as @version x.y.z or Version: x.y.z and it might be preceeded by an *
	 *
	 * @param string $code_str the source code string to look into
	 * @return null|string the version string
	 */

	private static function get_version_in_code($code_str)
	{
        $tokens = array_values(
            array_filter(
                token_get_all($code_str),
                function ($token) {
                    return !is_array($token) || $token[0] !== T_WHITESPACE;
                }
            )
        );
        foreach ( $tokens as $token ) {
			if ( $token[0] == T_DOC_COMMENT	) {
				$version = self::get_version_in_docblock($token[1]);
				if ( null !== $version ) {
					return $version;
				}
			}
		}
		return null;
	}

	/**
	 * Gets the content of a version tag in a docblock
	 *
	 * @param string $docblock
	 * @return null|string The content of the version tag
	*/
	private static function get_version_in_docblock($docblock)
	{
		$docblocktags = self::parse_doc_block($docblock);
		if ( isset($docblocktags['version'] ) ) {
			return $docblocktags['version'];
		}
		return null;
	}

	/**
	 * Parses a docblock and gets an array of tags with their values
	 *
	 * The tags might be specified as @version x.y.z or Version: x.y.z and they might be preceeded by an *
	 *
	 * This code is based on the phpactor package, namely:
	 *    https://github.com/phpactor/docblock/blob/master/lib/Parser.php
	 *
	 * @param string $docblock
	 * @return array
	*/
    private static function parse_doc_block($docblock): array
    {
		$tag_documentor = '{@([a-zA-Z0-9-_\\\]+)\s*?(.*)?}';
		$tag_property = '{\s*\*?\s*(.*?)\:(.*)}';
        $lines = explode(PHP_EOL, $docblock);
        $tags = [];
        $prose = [];
        foreach ($lines as $line) {
            if (0 === preg_match($tag_documentor, $line, $matches)) {
				if (0 === preg_match($tag_property, $line, $matches)) {
					continue;
				}
			}
            $tagName = strtolower($matches[1]);
            $metadata = trim($matches[2] ?? '');
            $tags[$tagName] = $metadata;
        }
        return $tags;
    }

}
