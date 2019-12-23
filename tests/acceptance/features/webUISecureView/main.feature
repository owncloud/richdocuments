@webUI @insulated @disablePreviews
Feature: Secure View
  As an admin
  I want to be able to enable secure view
  So that users can share files with very restricted access to documents

  Scenario: Admin enables the secure view through webUI
    Given the administrator has browsed to the admin additional settings page
    When the administrator enables secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"

  Scenario: Admin enables the secure view, enabled print permissions through webUI
    Given the administrator has browsed to the admin additional settings page
    When the administrator enables secure view using the webUI
    And the administrator enables print permission in secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"
    And the config key "secure_view_can_print_default" of app "richdocuments" should have value "true"

  Scenario: Admin enables the secure view, disables secure-view permissions through webUI
    Given the administrator has browsed to the admin additional settings page
    When the administrator enables secure view using the webUI
    And the administrator disables watermark permission in secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"
    And the config key "secure_view_has_watermark_default" of app "richdocuments" should have value "false"

  Scenario: Admin enables the secure view using occ command, disables print default and enables it again through webUI
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "secure_view_can_print_default" with value "false" in app "richdocuments"
    And the administrator has browsed to the admin additional settings page
    When the administrator enables print permission in secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"
    And the config key "secure_view_can_print_default" of app "richdocuments" should have value "true"

  Scenario: Admin enables the secure view using occ command, disables secure-view default and enables it again through webUI
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "secure_view_has_watermark_default" with value "false" in app "richdocuments"
    And the administrator has browsed to the admin additional settings page
    When the administrator enables watermark permission in secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"
    And the config key "secure_view_has_watermark_default" of app "richdocuments" should have value "true"

  @skipOnOcV10.3
  Scenario: Admin enables secure view, user shares without edit permissions with default secure view permissions set and resharing disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit   | no |
    And user "user0" gets the info of the last share using the sharing API
    Then the fields of the last response should include
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
      | uid_owner              | user0          |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should include
      | scope         | key                 | enabled |
      | permissions   | download            | false   |
      | richdocuments | view-with-watermark | true    |
      | richdocuments | print               | false   |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user0" with range "bytes=0-17" should be "This is lorem text"
    And as "user1" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "user1" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  @skipOnOcV10.3
  Scenario: Admin enables secure view, disables secure-view default and user shares without edit permissions with default secure view permissions set and resharing disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "secure_view_has_watermark_default" with value "false" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit   | no |
    And user "user0" gets the info of the last share using the sharing API
    Then the fields of the last response should include
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
      | uid_owner              | user0          |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should be empty
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user0" with range "bytes=0-17" should be "This is lorem text"
    And as "user1" file "simple-folder/lorem.txt" should exist
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user1" with range "bytes=0-17" should be "This is lorem text"

  @skipOnOcV10.3
  Scenario: Admin enables secure view, enables secure-view default and enables print default and user shares without edit permissions with default secure view permissions set and resharing disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "secure_view_has_watermark_default" with value "true" in app "richdocuments"
    And the administrator has added config key "secure_view_can_print_default" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit   | no |
    And user "user0" gets the info of the last share using the sharing API
    Then the fields of the last response should include
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
      | uid_owner              | user0          |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should include
      | scope         | key                 | enabled |
      | permissions   | download            | false   |
      | richdocuments | view-with-watermark | true    |
      | richdocuments | print               | true    |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user0" with range "bytes=0-17" should be "This is lorem text"
    And as "user1" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "user1" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """
    
  @skipOnOcV10.3
  Scenario: Admin enables secure view and user shares without edit permissions and with secure-view disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit        | no |
      | secure-view | no |
    And user "user0" gets the info of the last share using the sharing API
    Then the fields of the last response should include
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
      | uid_owner              | user0          |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should be empty
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user0" with range "bytes=0-17" should be "This is lorem text"
    And as "user1" file "simple-folder/lorem.txt" should exist
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user1" with range "bytes=0-17" should be "This is lorem text"

  @skipOnOcV10.3
  Scenario: Admin enables secure view and user shares without edit permissions and with secure view enabled and print enabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit        | no  |
      | secure-view | yes |
      | print       | yes |
    And user "user0" gets the info of the last share using the sharing API
    Then the fields of the last response should include
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
      | uid_owner              | user0          |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should include
      | scope         | key                 | enabled |
      | permissions   | download            | false   |
      | richdocuments | view-with-watermark | true    |
      | richdocuments | print               | true    |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user0" with range "bytes=0-17" should be "This is lorem text"
    And as "user1" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "user1" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  @skipOnOcV10.3
  Scenario: Admin enables secure view and user shares without edit permissions and with secure view enabled and print disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit        | no  |
      | secure-view | yes |
      | print       | no  |
    And user "user0" gets the info of the last share using the sharing API
    Then the fields of the last response should include
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
      | uid_owner              | user0          |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should include
      | scope         | key                 | enabled |
      | permissions   | download            | false   |
      | richdocuments | view-with-watermark | true    |
      | richdocuments | print               | false   |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user0" with range "bytes=0-17" should be "This is lorem text"
    And as "user1" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "user1" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  @skipOnOcV10.3
  @issue-enterprise-3441
  Scenario: Admin enables secure view and user shares with reshare permission and no edit permission, secure-view is not available to be set for the share
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user2" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit         | no |
      | share        | yes |
    And user "user0" gets the info of the last share using the sharing API
    Then the fields of the last response should include
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
      | uid_owner              | user0          |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should be empty

  @skipOnOcV10.3 @issue-enterprise-3441
  Scenario: Reshare in secure-view is disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user2" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit         | yes |
      | share        | yes |
    And user "user0" gets the info of the last share using the sharing API
    Then the fields of the last response should include
      | path                   | /simple-folder |
      | uid_owner              | user0          |
      | permissions            | all            |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should be empty
    And the user re-logs in as "user1" using the webUI
    And the user shares folder "simple-folder" with user "User Two" using the webUI
    Then a notification should be displayed on the webUI with the text 'Cannot set the requested share attributes for simple-folder'
    And as "user2" folder "/simple-folder" should not exist

  @skipOnOcV10.3 @issue-enterprise-3441
  Scenario: Reshare in secure-view is disabled for previous share even after share permission
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user2" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit        | no  |
      | secure-view | yes |
    When the user re-logs in as "user1" using the webUI
    Then it should not be possible to share folder "simple-folder" using the webUI
    When the user re-logs in as "user0" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | share       | yes |
    And user "user0" gets the info of the last share using the sharing API
    Then the fields of the last response should include
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
      | uid_owner              | user0          |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should be empty
    But user "user1" should not be able to share file "simple-folder" with user "user2" using the sharing API
    And the OCS status code should be "404"
    And the HTTP status code should be "200"

