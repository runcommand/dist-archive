Feature: Generate a distribution archive of a project

  Scenario: Generates a ZIP archive by default
    Given a WP install

    When I run `wp scaffold plugin hello-world`
    Then the wp-content/plugins/hello-world directory should exist
    And the wp-content/plugins/hello-world/hello-world.php file should exist
    And the wp-content/plugins/hello-world/.travis.yml file should exist
    And the wp-content/plugins/hello-world/bin directory should exist

    When I run `wp dist-archive wp-content/plugins/hello-world`
    Then STDOUT should be:
      """
      Success: Created hello-world.0.1.0.zip
      """
    And STDERR should be empty
    And the wp-content/plugins/hello-world.0.1.0.zip file should exist

    When I run `wp plugin delete hello-world`
    Then the wp-content/plugins/hello-world directory should not exist

    When I run `wp plugin install wp-content/plugins/hello-world.0.1.0.zip`
    Then the wp-content/plugins/hello-world directory should exist
    And the wp-content/plugins/hello-world/hello-world.php file should exist
    And the wp-content/plugins/hello-world/.travis.yml file should not exist
    And the wp-content/plugins/hello-world/bin directory should not exist

  Scenario: Generates a tarball archive with a flag
    Given a WP install

    When I run `wp scaffold plugin hello-world`
    Then the wp-content/plugins/hello-world directory should exist
    And the wp-content/plugins/hello-world/hello-world.php file should exist
    And the wp-content/plugins/hello-world/.travis.yml file should exist
    And the wp-content/plugins/hello-world/bin directory should exist

    When I run `wp dist-archive wp-content/plugins/hello-world --format=targz`
    Then STDOUT should be:
      """
      Success: Created hello-world.0.1.0.tar.gz
      """
    And STDERR should be empty
    And the wp-content/plugins/hello-world.0.1.0.tar.gz file should exist

    When I run `wp plugin delete hello-world`
    Then the wp-content/plugins/hello-world directory should not exist

    When I run `cd wp-content/plugins/ && tar -zxvf hello-world.0.1.0.tar.gz`
    Then the wp-content/plugins/hello-world directory should exist
    And the wp-content/plugins/hello-world/hello-world.php file should exist
    And the wp-content/plugins/hello-world/.travis.yml file should not exist
    And the wp-content/plugins/hello-world/bin directory should not exist

  Scenario: Generate a ZIP archive to a custom path
    Given a WP install

    When I run `wp scaffold plugin hello-world`
    Then the wp-content/plugins/hello-world directory should exist
    And the wp-content/plugins/hello-world/hello-world.php file should exist
    And the wp-content/plugins/hello-world/.travis.yml file should exist
    And the wp-content/plugins/hello-world/bin directory should exist

    When I run `wp dist-archive wp-content/plugins/hello-world hello-world.zip`
    Then STDOUT should be:
      """
      Success: Created hello-world.zip
      """
    And the hello-world.zip file should exist
    And the wp-content/plugins/hello-world.0.1.0.zip file should not exist

  Scenario: Generate a ZIP archive using version number in composer.json
    Given an empty directory
    And a foo/.distignore file:
      """
      .gitignore
      .distignore
      features/
      """
    And a foo/features/sample.feature file:
      """
      Testing
      """
    And a foo/composer.json file:
      """
      {
          "name": "runcommand/profile",
          "description": "Quickly identify what's slow with WordPress.",
          "homepage": "https://runcommand.io/wp/profile/",
          "version": "0.2.0-alpha"
      }
      """

    When I run `wp dist-archive foo`
    Then STDOUT should be:
      """
      Success: Created foo.0.2.0-alpha.zip
      """
    And the foo.0.2.0-alpha.zip file should exist

    When I run `rm -rf foo`
    Then the foo directory should not exist

    When I run `unzip foo.0.2.0-alpha.zip`
    Then the foo directory should exist
    And the foo/composer.json file should exist
    And the foo/.distignore file should not exist
    And the foo/features/sample.feature file should not exist

  Scenario Outline: Ignores hidden files in subdirectories
    Given an empty directory
    And a foo/.distignore file:
      """
      .DS_Store
      """
    And a foo/test.php file:
      """
      <?php
      echo 'Hello world;';
      """
    And a foo/test-dir/test.php file:
      """
      <?php
      echo 'Hello world;';
      """
    And a foo/test-dir/.DS_Store file:
      """
      Bad!
      """

    When I run `wp dist-archive foo --format=<format>`
    Then STDOUT should be:
      """
      Success: Created foo.<extension>
      """
    And the foo.<extension> file should exist

    When I run `rm -rf foo`
    Then the foo directory should not exist

    When I run `<extract> foo.<extension>`
    Then the foo directory should exist
    And the foo/test.php file should exist
    And the foo/test-dir/test.php file should exist
    And the foo/test-dir/.DS_Store file should not exist

    Examples:
      | format  | extension | extract   |
      | zip     | zip       | unzip     |
      | targz   | tar.gz    | tar -zxvf |

  Scenario: Create directories automatically if requested
    Given a WP install

    When I run `wp scaffold plugin hello-world`
    Then the wp-content/plugins/hello-world directory should exist
    And the wp-content/plugins/hello-world/hello-world.php file should exist
    And the wp-content/plugins/hello-world/.travis.yml file should exist
    And the wp-content/plugins/hello-world/bin directory should exist

    When I try `wp dist-archive wp-content/plugins/hello-world {RUN_DIR}/some/nested/folder/hello-world.zip`
    Then STDERR should contain:
      """
      Error: Target directory does not exist
      """

    When I run `wp dist-archive --create-target-dir wp-content/plugins/hello-world {RUN_DIR}/some/nested/folder/hello-world.zip`
    Then STDOUT should be:
      """
      Success: Created hello-world.zip
      """
    And STDERR should be empty
    And the {RUN_DIR}/some/nested/folder/hello-world.zip file should exist


  Scenario: Finds the version tag even if ill-formed
    Given a WP install

    When I run `wp scaffold plugin hello-world`
    Then the wp-content/plugins/hello-world directory should exist
    And the wp-content/plugins/hello-world/hello-world.php file should exist
    And the wp-content/plugins/hello-world/.travis.yml file should exist
    And the wp-content/plugins/hello-world/bin directory should exist

    When I run `sed -i wp-content/plugins/hello-world/hello-world.php -e "s/* Version:/Version/" -e "s/0.1.0/0.2.0/"`
    Then STDERR should be empty

    When I run `wp dist-archive wp-content/plugins/hello-world`
    Then STDOUT should be:
      """
      Success: Created hello-world.0.2.0.zip
      """
    And STDERR should be empty
    And the wp-content/plugins/hello-world.0.2.0.zip file should exist

    When I run `wp plugin delete hello-world`
    Then the wp-content/plugins/hello-world directory should not exist

    When I run `wp plugin install wp-content/plugins/hello-world.0.2.0.zip`
    Then the wp-content/plugins/hello-world directory should exist
    And the wp-content/plugins/hello-world/hello-world.php file should exist
    And the wp-content/plugins/hello-world/.travis.yml file should not exist
    And the wp-content/plugins/hello-world/bin directory should not exist
