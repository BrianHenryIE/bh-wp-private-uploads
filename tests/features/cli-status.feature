Feature: Show the private uploads directory status via WP-CLI

  Background:
    Given a WP install
    And the development plugin is installed

  Scenario: Check help command is available
    When I run `wp help my_plugin private_media status`
    Then the return code should be 0
    And STDOUT should contain:
      """
      Print the private uploads directory's path, URL, post count
      """

  Scenario: Status before any check shows the path and URL with empty check fields
    When I run `wp my_plugin private_media status --user=admin`
    Then the return code should be 0
    And STDOUT should end with a table containing rows:
      | path | url | post_count | is_private | last_checked |
    And STDOUT should contain:
      """
      wp-content/uploads/private-media
      """

  Scenario: Status after a check shows the is-private result and the post count
    When I run `wp my_plugin private_media download https://www.brianhenry.ie/resume/ --create-post --user=admin`
    Then the return code should be 0

    When I run `wp my_plugin private_media check --user=admin`
    Then the return code should be 0

    When I run `wp my_plugin private_media status --format=json --user=admin`
    Then the return code should be 0
    And STDOUT should contain:
      """
      "post_count":1
      """
    And STDOUT should contain:
      """
      "is_private":true
      """
