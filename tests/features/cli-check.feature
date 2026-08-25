Feature: Check the private uploads directory is private via WP-CLI

  Background:
    Given a WP install
    And the development plugin is installed

  Scenario: Check help command is available
    When I run `wp help my_plugin private_media check`
    Then the return code should be 0
    And STDOUT should contain:
      """
      Check is the private uploads directory correctly private.
      """

  Scenario: Check with no files uploaded cannot determine privacy
    When I try `wp my_plugin private_media check --user=admin`
    Then the return code should not be 0
    And STDERR should contain:
      """
      Could not determine
      """

  # The wp-cli-tests install's site URL is example.com, which returns HTTP 404 for the probe
  # file's URL — a 404 counts as private. The public verdict path is covered by unit tests.
  Scenario: Check after a file exists reports the URL is private
    When I run `wp my_plugin private_media download https://www.brianhenry.ie/resume/ --user=admin`
    Then the return code should be 0

    When I run `wp my_plugin private_media check --user=admin`
    Then the return code should be 0
    And STDOUT should contain:
      """
      Success: The private uploads URL is private
      """
