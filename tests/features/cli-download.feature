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
    When I run `wp my_plugin private_media download https://www.brianhenry.ie/resume/ --user=admin`
    Then the return code should be 0
    And STDOUT should contain:
      """
      Beginning download of
      """

  Scenario: Download with table format (default)
    When I run `wp my_plugin private_media download https://www.brianhenry.ie/resume/ --user=admin`
    Then the return code should be 0
    And STDOUT should end with a table containing rows:
      | file | url | type |

  Scenario: Download with invalid URL
    When I try `wp my_plugin private_media download not-a-valid-url --user=admin`
    Then the return code should not be 0

  Scenario: Download a file and create a post recording it
    When I run `wp my_plugin private_media download https://www.brianhenry.ie/resume/ --create-post --format=json --user=admin`
    Then the return code should be 0
    And STDOUT should contain:
      """
      "post_id":
      """

    When I run `wp post list --post_type=private_media --post_status=inherit --format=count`
    Then STDOUT should be:
      """
      1
      """

  Scenario: Download a file without creating a post
    When I run `wp my_plugin private_media download https://www.brianhenry.ie/resume/ --user=admin`
    Then the return code should be 0

    When I run `wp post list --post_type=private_media --post_status=inherit --format=count`
    Then STDOUT should be:
      """
      0
      """

  Scenario: Download a file and assign an owner and parent post
    When I run `wp user create customer customer@example.org --porcelain`
    And save STDOUT as {USER_ID}

    When I run `wp post create --post_title='Order 123' --porcelain`
    And save STDOUT as {PARENT_ID}

    When I run `wp my_plugin private_media download https://www.brianhenry.ie/resume/ --post_author={USER_ID} --post_parent={PARENT_ID} --user=admin`
    Then the return code should be 0

    When I run `wp post list --post_type=private_media --post_status=inherit --field=post_author`
    Then STDOUT should be:
      """
      {USER_ID}
      """

    When I run `wp post list --post_type=private_media --post_status=inherit --field=post_parent`
    Then STDOUT should be:
      """
      {PARENT_ID}
      """

  Scenario: Created post defaults to no owner
    When I run `wp my_plugin private_media download https://www.brianhenry.ie/resume/ --create-post --user=admin`
    Then the return code should be 0

    When I run `wp post list --post_type=private_media --post_status=inherit --field=post_author`
    Then STDOUT should be:
      """
      0
      """

  Scenario: Download with invalid post_author
    When I try `wp my_plugin private_media download https://www.brianhenry.ie/resume/ --post_author=not-a-number --user=admin`
    Then the return code should not be 0
    And STDERR should contain:
      """
      Invalid --post_author
      """

  Scenario: Download with invalid post_parent
    When I try `wp my_plugin private_media download https://www.brianhenry.ie/resume/ --post_parent=not-a-number --user=admin`
    Then the return code should not be 0
    And STDERR should contain:
      """
      Invalid --post_parent
      """
