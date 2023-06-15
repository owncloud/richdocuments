import editor from './editor.vue'

const routes = [
  {
    path: '/:driveAliasAndItem(.*)?',
    component: editor,
    name: 'editor',
    meta: {
      title: 'Collabora Online',
      patchCleanPath: true
    }
  }
]

const appInfo = {
  name: 'Collabora Online',
  id: 'richdocuments',
  icon: 'resource-type-text',
  iconFillType: 'fill',
  extensions: [
    {
      extension: 'odt',
      routeName: 'richdocuments-editor',
      newFileMenu: {
        menuTitle($gettext) {
          return $gettext('Document')
        },
        icon: 'x-office-document'
      },
      canBeDefault: true
    },
    {
      extension: 'docx',
      routeName: 'richdocuments-editor',
      canBeDefault: true
    },
    {
      extension: 'ods',
      routeName: 'richdocuments-editor',
      newFileMenu: {
        menuTitle($gettext) {
          return $gettext('Spreadsheet')
        },
        icon: 'x-office-spreadsheet'
      },
      canBeDefault: true
    },
    {
      extension: 'xlsx',
      routeName: 'richdocuments-editor',
      canBeDefault: true
    },
    {
      extension: 'odp',
      routeName: 'richdocuments-editor',
      newFileMenu: {
        menuTitle($gettext) {
          return $gettext('Presentation')
        },
        icon: 'x-office-presentation'
      },
      canBeDefault: true
    },
    {
      extension: 'pptx',
      routeName: 'richdocuments-editor',
      canBeDefault: true
    },
    {
      extension: 'odg',
      routeName: 'richdocuments-editor',
      newFileMenu: {
        menuTitle($gettext) {
          return $gettext('Drawing')
        },
        icon: 'x-office-drawing'
      },
      canBeDefault: true
    }
  ]
}

export default {
  appInfo,
  routes
}
