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
    name: "Richdocuments",
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
            "files-personal"
          ]
        },
        {
          extension: "docx",
          routeName: "richdocuments-editor",
          newTab: true,
          routes: [
            "files-personal"
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
            "files-personal"
          ]
        },
        {
          extension: "xlsx",
          routeName: "richdocuments-editor",
          newTab: true,
          routes: [
            "files-personal"
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
            "files-personal"
          ]
        },
        {
          extension: "pptx",
          routeName: "richdocuments-editor",
          newTab: true,
          routes: [
            "files-personal"
          ]
        }
      ]
};

export default {
    appInfo,
    routes
};