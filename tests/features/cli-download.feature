Feature: Download files to private uploads directory via WP-CLI

  Background:
    Given a WP install
    And the development plugin is installed

  Scenario: Check help command is available
    When I run `wp help my_plugin private_media download`
    Then the return code should be 0
    And STDOUT should contain:
      """
      Download a file from a remote URL to the private uploads directory.
      """

  Scenario: Download a file successfully
    When I run `wp my_plugin private_media download https://www.brianhenry.ie/resume/`
    Then the return code should be 0
    And STDOUT should contain:
      """
      Beginning download of
      """

  Scenario: Download with table format (default)
    When I run `wp my_plugin private_media download https://www.brianhenry.ie/resume/`
    Then the return code should be 0
    And STDOUT should end with a table containing rows:
      | file | url | type |

  Scenario: Download with invalid URL
    When I try `wp my_plugin private_media download not-a-valid-url`
    Then the return code should not be 0
