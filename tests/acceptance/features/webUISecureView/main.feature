@webUI @insulated @disablePreviews
Feature: Secure View
  As an admin
  I want to be able to enable secure view
  So that users can share files with very restricted access to documents

  Scenario: Admin enables the secure view through webUI
    Given the administrator has browsed to the admin additional settings page
    When the administrator enables secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"

  Scenario: Admin enables the secure view, disables print permissions through webUI
    Given the administrator has browsed to the admin additional settings page
    When the administrator enables secure view using the webUI
    And the administrator disables print permission in secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"
    And the config key "secure_view_can_print_default" of app "richdocuments" should have value "false"

  Scenario: Admin enables the secure view, disables print permissions through webUI
    Given the administrator has browsed to the admin additional settings page
    When the administrator enables secure view using the webUI
    And the administrator disables watermark permission in secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"
    And the config key "secure_view_has_watermark_default" of app "richdocuments" should have value "false"

  Scenario: Admin enables the secure view, disables print and watermark permissions through webUI
    Given the administrator has browsed to the admin additional settings page
    When the administrator enables secure view using the webUI
    And the administrator disables watermark permission in secure view using the webUI
    And the administrator disables print permission in secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"
    And the config key "secure_view_has_watermark_default" of app "richdocuments" should have value "false"
    And the config key "secure_view_can_print_default" of app "richdocuments" should have value "false"

  Scenario: Admin enables the secure view using occ command, disables print and enables it again through webUI
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "secure_view_can_print_default" with value "false" in app "richdocuments"
    And the administrator has browsed to the admin additional settings page
    When the administrator enables print permission in secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"
    And the config key "secure_view_can_print_default" of app "richdocuments" should have value "true"

  Scenario: Admin enables the secure view using occ command, disables watermark and enables it again through webUI
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "secure_view_has_watermark_default" with value "false" in app "richdocuments"
    And the administrator has browsed to the admin additional settings page
    When the administrator enables watermark permission in secure view using the webUI
    Then the config key "secure_view_option" of app "richdocuments" should have value "true"
    And the config key "secure_view_has_watermark_default" of app "richdocuments" should have value "true"

  Scenario: Admin enables secure view, user shares without edit permissions with default secure view permissions set
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
      | permissions            | read,share     |
      | stime                  | A_NUMBER       |
      | storage                | A_NUMBER       |
      | mail_send              | 0              |
      | uid_owner              | user0          |
      | file_parent            | A_NUMBER       |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should include
      | scope         | key       | enabled |
      | permissions   | download  | false   |
      | richdocuments | watermark | true    |
      | richdocuments | print     | true    |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user0" with range "bytes=0-17" should be "This is lorem text"
    And as "user1" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "user1" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  Scenario: Admin enables secure view, disables watermark and user shares without edit permissions with default secure view permissions set
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
      | permissions            | read,share     |
      | stime                  | A_NUMBER       |
      | storage                | A_NUMBER       |
      | mail_send              | 0              |
      | uid_owner              | user0          |
      | file_parent            | A_NUMBER       |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should include
      | scope         | key       | enabled |
      | permissions   | download  | false   |
      | richdocuments | watermark | false   |
      | richdocuments | print     | true    |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user0" with range "bytes=0-17" should be "This is lorem text"
    And as "user1" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "user1" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  Scenario: Admin enables secure view, disables watermark and print and user shares without edit permissions with default secure view permissions set
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And the administrator has added config key "secure_view_has_watermark_default" with value "false" in app "richdocuments"
    And the administrator has added config key "secure_view_can_print_default" with value "false" in app "richdocuments"
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
      | permissions            | read,share     |
      | stime                  | A_NUMBER       |
      | storage                | A_NUMBER       |
      | mail_send              | 0              |
      | uid_owner              | user0          |
      | file_parent            | A_NUMBER       |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should include
      | scope         | key       | enabled |
      | permissions   | download  | false   |
      | richdocuments | watermark | false   |
      | richdocuments | print     | false   |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user0" with range "bytes=0-17" should be "This is lorem text"
    And as "user1" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "user1" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  Scenario: Admin enables secure view and user shares without edit permissions and watermark disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit      | no |
      | watermark | no |
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
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should include
      | scope         | key       | enabled |
      | permissions   | download  | false   |
      | richdocuments | watermark | false   |
      | richdocuments | print     | true    |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user0" with range "bytes=0-17" should be "This is lorem text"
    And as "user1" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "user1" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  Scenario: Admin enables secure view and print and user shares without edit permissions and print disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit      | no |
      | print     | no |
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
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should include
      | scope         | key       | enabled |
      | permissions   | download  | false   |
      | richdocuments | watermark | true    |
      | richdocuments | print     | false   |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user0" with range "bytes=0-17" should be "This is lorem text"
    And as "user1" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "user1" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  Scenario: Admin enables secure view and print and user shares without edit permissions and watermark and print disabled
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit      | no |
      | watermark | no |
      | print     | no |
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
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should include
      | scope         | key       | enabled |
      | permissions   | download  | false   |
      | richdocuments | watermark | false   |
      | richdocuments | print     | false   |
    And the downloaded content when downloading file "simple-folder/lorem.txt" for user "user0" with range "bytes=0-17" should be "This is lorem text"
    And as "user1" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "user1" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  Scenario: Admin enables secure view and user shares without edit permissions and watermark disabled, another user reshares
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user2" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit      | no |
      | watermark | no |
    And the user re-logs in as "user1" using the webUI
    And the user shares folder "simple-folder" with user "User Two" using the webUI
    And user "user1" gets the info of the last share using the sharing API
    Then the fields of the last response should include
      | id                     | A_NUMBER       |
      | item_type              | folder         |
      | item_source            | A_NUMBER       |
      | share_type             | user           |
      | file_source            | A_NUMBER       |
      | path                   | /simple-folder |
      | permissions            | 17             |
      | stime                  | A_NUMBER       |
      | storage                | A_NUMBER       |
      | mail_send              | 0              |
      | uid_owner              | user1          |
      | file_parent            | A_NUMBER       |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User One       |
      | share_with             | user2          |
      | share_with_displayname | User Two       |
    And the additional sharing attributes for the response should include
      | scope         | key       | enabled |
      | permissions   | download  | false   |
      | richdocuments | watermark | true    |
      | richdocuments | print     | true    |
    And as "user2" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "user2" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """

  Scenario: Admin enables secure view and user shares without edit & share permissions and watermark disabled, another user tries to reshare
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user0" has logged in using the webUI
    When the user shares folder "simple-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "simple-folder" using the webUI to
      | edit      | no |
      | share     | no |
      | watermark | no |
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
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User Zero      |
      | share_with             | user1          |
      | share_with_displayname | User One       |
    And the additional sharing attributes for the response should include
      | scope         | key       | enabled |
      | permissions   | download  | false   |
      | richdocuments | watermark | false   |
      | richdocuments | print     | true    |
    And as "user1" file "simple-folder/lorem.txt" should exist
    But the downloading of file "simple-folder/lorem.txt" for user "user1" should fail with error message
    """
    Access to this resource has been denied because it is in view-only mode.
    """
    When the user re-logs in as "user1" using the webUI
    Then it should not be possible to share folder "simple-folder" using the webUI

  Scenario: Admin enables secure view and user shares without edit permissions and watermark disabled, another user shares using public link
    Given the administrator has added config key "secure_view_option" with value "true" in app "richdocuments"
    And user "user0" has been created with default attributes and without skeleton files
    And user "user1" has been created with default attributes and without skeleton files
    And user "user2" has been created with default attributes and without skeleton files
    And user "user0" has created folder "a-folder"
    And user "user0" has uploaded file with content "some content" to "/a-folder/randomfile.txt"
    And user "user0" has logged in using the webUI
    When the user shares folder "a-folder" with user "User One" using the webUI
    And the user sets the sharing permissions of user "User One" for "a-folder" using the webUI to
      | edit      | no |
      | watermark | no |
    And the user re-logs in as "user1" using the webUI
    And the user creates a new public link for folder "a-folder" using the webUI
    And user "user1" gets the info of the last share using the sharing API
    Then the fields of the last response should include
      | id                     | A_NUMBER       |
      | item_type              | folder         |
      | item_source            | A_NUMBER       |
      | share_type             | public_link    |
      | file_source            | A_NUMBER       |
      | path                   | /a-folder      |
      | permissions            | read           |
      | stime                  | A_NUMBER       |
      | storage                | A_NUMBER       |
      | mail_send              | 0              |
      | uid_owner              | user1          |
      | file_parent            | A_NUMBER       |
      | file_parent            | A_NUMBER       |
      | displayname_owner      | User One       |
    And the additional sharing attributes for the response should be empty
    When the public accesses the last created public link using the webUI
    And file "randomfile.txt" should be listed on the webUI
