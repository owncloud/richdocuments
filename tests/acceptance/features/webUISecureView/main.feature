@webUI @insulated @disablePreviews
Feature: Secure View
  As an admin
  I want to be able to enable secure view
  So that users can share files with very restricted access to documents

  Scenario: Admin enables the secure view through webUI
    Given the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And the administrator has browsed to the admin additional settings page
    When the administrator enables secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"

  Scenario: Admin enables the secure view, enabled print permissions through webUI
    Given the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And the administrator has browsed to the admin additional settings page
    When the administrator enables secure view using the webUI
    And the administrator enables print permission in secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"
    And the config key "secure_view_can_print_default" of app "richdocuments" should have value "true"

  Scenario: Admin enables the secure view, disables secure-view permissions through webUI
    Given the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And the administrator has browsed to the admin additional settings page
    When the administrator enables secure view using the webUI
    And the administrator disables watermark permission in secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"
    And the config key "secure_view_has_watermark_default" of app "richdocuments" should have value "false"

  Scenario: Admin enables the secure view using occ command, disables print default and enables it again through webUI
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "secure_view_can_print_default" with value "false" in app "richdocuments"
    And the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And the administrator has browsed to the admin additional settings page
    When the administrator enables print permission in secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"
    And the config key "secure_view_can_print_default" of app "richdocuments" should have value "true"

  Scenario: Admin enables the secure view using occ command, disables secure-view default and enables it again through webUI
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "secure_view_has_watermark_default" with value "false" in app "richdocuments"
    And the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And the administrator has browsed to the admin additional settings page
    When the administrator enables watermark permission in secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"
    And the config key "secure_view_has_watermark_default" of app "richdocuments" should have value "true"

  @skipOnOcV10.3
  Scenario: Admin enables secure view, user shares without edit permissions with default secure view permissions set and resharing disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has logged in using the webUI
    When the user shares folder "simple-folder" with user "Brian" using the webUI
    And the user sets the sharing permissions of user "Brian" for "simple-folder" using the webUI to
      | edit | no |
    And user "Alice" gets the info of the last share using the sharing API
    Then the fields of the last response to user "Alice" sharing with user "Brian" should include
      | id                     | A_NUMBER       |
      | item_type              | folder         |
      | item_source            | A_NUMBER       |
      | share_type             | user           |
      | file_source            | A_NUMBER       |
      | path                   | /simple-folder |
      | permissions            | read           |
      | stime                  | A_NUMBER       |
      | storage                | A_NUMBER       |
      | mail_send              | 0              |
      | uid_owner              | %username%     |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | %displayname%  |
      | share_with             | %username%     |
      | share_with_displayname | %displayname%  |
    And the additional sharing attributes for the response should include
      | scope         | key                 | enabled |
      | permissions   | download            | false   |
      | richdocuments | view-with-watermark | true    |
      | richdocuments | print               | false   |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "Alice" with range "bytes=0-17" should be "This is lorem text"
    And as "Brian" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "Brian" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  @skipOnOcV10.3
  Scenario: Admin enables secure view, disables secure-view default and user shares without edit permissions with default secure view permissions set and resharing disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "secure_view_has_watermark_default" with value "false" in app "richdocuments"
    And the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has logged in using the webUI
    When the user shares folder "simple-folder" with user "Brian" using the webUI
    And the user sets the sharing permissions of user "Brian" for "simple-folder" using the webUI to
      | edit | no |
    And user "Alice" gets the info of the last share using the sharing API
    Then the fields of the last response to user "Alice" sharing with user "Brian" should include
      | id                     | A_NUMBER       |
      | item_type              | folder         |
      | item_source            | A_NUMBER       |
      | share_type             | user           |
      | file_source            | A_NUMBER       |
      | path                   | /simple-folder |
      | permissions            | read           |
      | stime                  | A_NUMBER       |
      | storage                | A_NUMBER       |
      | mail_send              | 0              |
      | uid_owner              | %username%     |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | %displayname%  |
      | share_with             | %username%     |
      | share_with_displayname | %displayname%  |
    And the additional sharing attributes for the response should be empty
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "Alice" with range "bytes=0-17" should be "This is lorem text"
    And as "Brian" file "simple-folder/lorem.txt" should exist
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "Brian" with range "bytes=0-17" should be "This is lorem text"

  @skipOnOcV10.3
  Scenario: Admin enables secure view, enables secure-view default and enables print default and user shares without edit permissions with default secure view permissions set and resharing disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "secure_view_has_watermark_default" with value "true" in app "richdocuments"
    And the administrator has added config key "secure_view_can_print_default" with value "true" in app "richdocuments"
    And the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has logged in using the webUI
    When the user shares folder "simple-folder" with user "Brian" using the webUI
    And the user sets the sharing permissions of user "Brian" for "simple-folder" using the webUI to
      | edit | no |
    And user "Alice" gets the info of the last share using the sharing API
    Then the fields of the last response to user "Alice" sharing with user "Brian" should include
      | id                     | A_NUMBER       |
      | item_type              | folder         |
      | item_source            | A_NUMBER       |
      | share_type             | user           |
      | file_source            | A_NUMBER       |
      | path                   | /simple-folder |
      | permissions            | read           |
      | stime                  | A_NUMBER       |
      | storage                | A_NUMBER       |
      | mail_send              | 0              |
      | uid_owner              | %username%     |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | %displayname%  |
      | share_with             | %username%     |
      | share_with_displayname | %displayname%  |
    And the additional sharing attributes for the response should include
      | scope         | key                 | enabled |
      | permissions   | download            | false   |
      | richdocuments | view-with-watermark | true    |
      | richdocuments | print               | true    |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "Alice" with range "bytes=0-17" should be "This is lorem text"
    And as "Brian" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "Brian" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  @skipOnOcV10.3
  Scenario: Admin enables secure view and user shares without edit permissions and with secure-view disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has logged in using the webUI
    When the user shares folder "simple-folder" with user "Brian" using the webUI
    And the user sets the sharing permissions of user "Brian" for "simple-folder" using the webUI to
      | edit        | no |
      | secure-view | no |
    And user "Alice" gets the info of the last share using the sharing API
    Then the fields of the last response to user "Alice" sharing with user "Brian" should include
      | id                     | A_NUMBER       |
      | item_type              | folder         |
      | item_source            | A_NUMBER       |
      | share_type             | user           |
      | file_source            | A_NUMBER       |
      | path                   | /simple-folder |
      | permissions            | read           |
      | stime                  | A_NUMBER       |
      | storage                | A_NUMBER       |
      | mail_send              | 0              |
      | uid_owner              | %username%     |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | %displayname%  |
      | share_with             | %username%     |
      | share_with_displayname | %displayname%  |
    And the additional sharing attributes for the response should be empty
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "Alice" with range "bytes=0-17" should be "This is lorem text"
    And as "Brian" file "simple-folder/lorem.txt" should exist
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "Brian" with range "bytes=0-17" should be "This is lorem text"

  @skipOnOcV10.3
  Scenario: Admin enables secure view and user shares without edit permissions and with secure view enabled and print enabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has logged in using the webUI
    When the user shares folder "simple-folder" with user "Brian" using the webUI
    And the user sets the sharing permissions of user "Brian" for "simple-folder" using the webUI to
      | edit        | no  |
      | secure-view | yes |
      | print       | yes |
    And user "Alice" gets the info of the last share using the sharing API
    Then the fields of the last response to user "Alice" sharing with user "Brian" should include
      | id                     | A_NUMBER       |
      | item_type              | folder         |
      | item_source            | A_NUMBER       |
      | share_type             | user           |
      | file_source            | A_NUMBER       |
      | path                   | /simple-folder |
      | permissions            | read           |
      | stime                  | A_NUMBER       |
      | storage                | A_NUMBER       |
      | mail_send              | 0              |
      | uid_owner              | %username%     |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | %displayname%  |
      | share_with             | %username%     |
      | share_with_displayname | %displayname%  |
    And the additional sharing attributes for the response should include
      | scope         | key                 | enabled |
      | permissions   | download            | false   |
      | richdocuments | view-with-watermark | true    |
      | richdocuments | print               | true    |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "Alice" with range "bytes=0-17" should be "This is lorem text"
    And as "Brian" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "Brian" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  @skipOnOcV10.3
  Scenario: Admin enables secure view and user shares without edit permissions and with secure view enabled and print disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has logged in using the webUI
    When the user shares folder "simple-folder" with user "Brian" using the webUI
    And the user sets the sharing permissions of user "Brian" for "simple-folder" using the webUI to
      | edit        | no  |
      | secure-view | yes |
      | print       | no  |
    And user "Alice" gets the info of the last share using the sharing API
    Then the fields of the last response to user "Alice" sharing with user "Brian" should include
      | id                     | A_NUMBER       |
      | item_type              | folder         |
      | item_source            | A_NUMBER       |
      | share_type             | user           |
      | file_source            | A_NUMBER       |
      | path                   | /simple-folder |
      | permissions            | read           |
      | stime                  | A_NUMBER       |
      | storage                | A_NUMBER       |
      | mail_send              | 0              |
      | uid_owner              | %username%     |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | %displayname%  |
      | share_with             | %username%     |
      | share_with_displayname | %displayname%  |
    And the additional sharing attributes for the response should include
      | scope         | key                 | enabled |
      | permissions   | download            | false   |
      | richdocuments | view-with-watermark | true    |
      | richdocuments | print               | false   |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "Alice" with range "bytes=0-17" should be "This is lorem text"
    And as "Brian" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "Brian" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  @skipOnOcV10.3
  @issue-enterprise-3441
  Scenario: Admin enables secure view and user shares with reshare permission and no edit permission, secure-view is not available to be set for the share
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has logged in using the webUI
    When the user shares folder "simple-folder" with user "Brian" using the webUI
    And the user sets the sharing permissions of user "Brian" for "simple-folder" using the webUI to
      | edit  | no  |
      | share | yes |
    And user "Alice" gets the info of the last share using the sharing API
    Then the fields of the last response to user "Alice" sharing with user "Brian" should include
      | id                     | A_NUMBER       |
      | item_type              | folder         |
      | item_source            | A_NUMBER       |
      | share_type             | user           |
      | file_source            | A_NUMBER       |
      | path                   | /simple-folder |
      | permissions            | read,share     |
      | stime                  | A_NUMBER       |
      | storage                | A_NUMBER       |
      | mail_send              | 0              |
      | uid_owner              | %username%     |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | %displayname%  |
      | share_with             | %username%     |
      | share_with_displayname | %displayname%  |
    And the additional sharing attributes for the response should be empty

  @skipOnOcV10.3 @issue-enterprise-3441
  Scenario: When resharing a folder and secure-view is enabled by default, receiver has secure-view enabled by default
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has logged in using the webUI
    When the user shares folder "simple-folder" with user "Brian" using the webUI
    And the user sets the sharing permissions of user "Brian" for "simple-folder" using the webUI to
      | edit  | yes |
      | share | yes |
    And user "Alice" gets the info of the last share using the sharing API
    Then the fields of the last response to user "Alice" sharing with user "Brian" should include
      | path                   | /simple-folder |
      | uid_owner              | %username%     |
      | permissions            | all            |
      | share_with             | %username%     |
      | share_with_displayname | %displayname%  |
    And the additional sharing attributes for the response should be empty
    And the user re-logs in as "Brian" using the webUI
    And the user shares folder "simple-folder" with user "Carol" using the webUI
    And user "Brian" gets the info of the last share using the sharing API
    Then the fields of the last response to user "Brian" should include
      | permissions | read |
    And the additional sharing attributes for the response should include
      | scope         | key                 | enabled |
      | permissions   | download            | false   |
      | richdocuments | view-with-watermark | true    |
      | richdocuments | print               | false   |

  @skipOnOcV10.3 @issue-enterprise-3441
  Scenario: Reshare in secure-view is disabled for previous share even after share permission
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "start_grace_period" with value "true" in app "richdocuments"
    And user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has logged in using the webUI
    When the user shares folder "simple-folder" with user "Brian" using the webUI
    And the user sets the sharing permissions of user "Brian" for "simple-folder" using the webUI to
      | edit        | no  |
      | secure-view | yes |
    When the user re-logs in as "Brian" using the webUI
    Then it should not be possible to share folder "simple-folder" using the webUI
    When the user re-logs in as "Alice" using the webUI
    And the user sets the sharing permissions of user "Brian" for "simple-folder" using the webUI to
      | share | yes |
    And user "Alice" gets the info of the last share using the sharing API
    Then the fields of the last response to user "Alice" sharing with user "Brian" should include
      | id                     | A_NUMBER       |
      | item_type              | folder         |
      | item_source            | A_NUMBER       |
      | share_type             | user           |
      | file_source            | A_NUMBER       |
      | path                   | /simple-folder |
      | permissions            | read,share     |
      | stime                  | A_NUMBER       |
      | storage                | A_NUMBER       |
      | mail_send              | 0              |
      | uid_owner              | %username%     |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | %displayname%  |
      | share_with             | %username%     |
      | share_with_displayname | %displayname%  |
    And the additional sharing attributes for the response should be empty
    But user "Brian" should not be able to share folder "simple-folder" with user "Carol" using the sharing API
    And the OCS status code should be "404"
    And the HTTP status code should be "200"
