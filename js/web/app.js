import editor from "./editor.vue"

const routes = [
    {
      path: "/:fileId/:filePath/:mode",
      components: {
        fullscreen: editor
      },
      name: "editor",
      meta: { hideHeadbar: true }
    }
];

const appInfo = {
    name: "Collabora Online",
    id: "richdocuments",
    icon: "x-office-document",
    isFileEditor: true,
    extensions: [
        {
          extension: "odt",
          routeName: "richdocuments-editor",
          newTab: true,
          newFileMenu: {
            menuTitle ($gettext) {
              return $gettext("Document")
            },
            icon: "x-office-document"
          },
          routes: [
            "files-personal",
            "files-favorites",
            "files-shared-with-others",
            "files-shared-with-me"
          ]
        },
        {
          extension: "docx",
          routeName: "richdocuments-editor",
          newTab: true,
          routes: [
            "files-personal",
            "files-favorites",
            "files-shared-with-others",
            "files-shared-with-me"
          ]
        },
        {
          extension: "ods",
          routeName: "richdocuments-editor",
          newTab: true,
          newFileMenu: {
            menuTitle ($gettext) {
              return $gettext("Spreadsheet")
            },
            icon: "x-office-spreadsheet"
          },
          routes: [
            "files-personal",
            "files-favorites",
            "files-shared-with-others",
            "files-shared-with-me"
          ]
        },
        {
          extension: "xlsx",
          routeName: "richdocuments-editor",
          newTab: true,
          routes: [
            "files-personal",
            "files-favorites",
            "files-shared-with-others",
            "files-shared-with-me"
          ]
        },
        {
          extension: "odp",
          routeName: "richdocuments-editor",
          newTab: true,
          newFileMenu: {
            menuTitle ($gettext) {
              return $gettext("Presentation")
            },
            icon: "x-office-presentation"
          },
          routes: [
            "files-personal",
            "files-favorites",
            "files-shared-with-others",
            "files-shared-with-me"
          ]
        },
        {
          extension: "pptx",
          routeName: "richdocuments-editor",
          newTab: true,
          routes: [
            "files-personal",
            "files-favorites",
            "files-shared-with-others",
            "files-shared-with-me"
          ]
        }
      ]
};

export default {
    appInfo,
    routes
};