<template>
  <main>
    <div id="app" class="oc-width-1-1 oc-height-1-1">
      <oc-modal
        :title="$gettext('Save As')"
        :button-cancel-text="$gettext('Cancel')"
        :button-confirm-text="$gettext('Save')"
        :has-input="true"
        :input-label="$gettext('New filename')"
        :input-description="
          $gettext('Please enter filename to which this document should be stored.')
        "
        :input-value="fileName"
        class="oc-mb-l"
        :hidden="hideSaveAs"
        @cancel="cancelSaveAs"
        @confirm="confirmSaveAs"
      />
      <div id="mainContainer" />
    </div>
  </main>
</template>

<script lang="ts">
import axios from 'axios'
import {
  useAccessToken,
  useAppDefaults,
  useConfigurationManager,
  useStore
} from '@ownclouders/web-pkg'
import { defineComponent, ref } from 'vue'

export default defineComponent({
  setup() {
    const store = useStore()
    const accessToken = useAccessToken({ store })
    const configurationManager = useConfigurationManager()

    const fileName = ref(null)
    const hideSaveAs = ref(true)

    const messageDisplay = (desc, status = 'danger', title = '') => {
      store.dispatch('showMessage', {
        title,
        desc,
        status
      })
    }

    return {
      ...useAppDefaults({
        applicationId: 'richdocuments'
      }),
      accessToken,
      fileName,
      hideSaveAs,
      messageDisplay,
      serverUrl: configurationManager.serverUrl
    }
  },

  async mounted() {
    try {
      let docFile = await this.getDocumentFileInfo()
      this.fileName = docFile.title
      let urlsrc = this.generateDocUrlSrc(docFile)
      this.showEditor(docFile, urlsrc)
      document.title = this.fileName
    } catch (error) {
      this.messageDisplay(error)
      this.closeApp()
    }
  },

  methods: {
    cancelSaveAs() {
      this.hideSaveAs = true
    },

    confirmSaveAs(value) {
      if (!value) {
        return
      }
      let frame = document.getElementById('loleafletframe')
      this.WOPIPostMessage(frame, 'Action_SaveAs', {
        Filename: value,
        Notify: true
      })
      this.hideSaveAs = true
    },

    WOPIPostMessage(iframe, msgId, values) {
      if (!iframe) {
        return
      }
      const msg = {
        MessageId: msgId,
        SendTime: Date.now(),
        Values: values
      }
      iframe.contentWindow.postMessage(JSON.stringify(msg), '*')
    },

    // where we get document url from owncloud api
    async getDocumentFileInfo() {
      return axios({
        method: 'GET',
        url:
          this.serverUrl +
          'index.php/apps/richdocuments/ajax/documents/index/' +
          this.currentFileContext.itemId,
        headers: {
          Authorization: 'Bearer ' + this.accessToken
        }
      }).then((response) => {
        return response.data
      })
    },

    generateWOPISrc(documentInfo) {
      const documentId = [
        documentInfo.fileId,
        documentInfo.instanceId,
        documentInfo.version,
        documentInfo.sessionId
      ].join('_')
      return this.serverUrl + `index.php/apps/richdocuments/wopi/files/${documentId}`
    },

    generateDocUrlSrc(docFile) {
      let wopiSrc = encodeURIComponent(this.generateWOPISrc(docFile))
      return (
        docFile.urlsrc +
        'WOPISrc=' +
        wopiSrc +
        '&title=' +
        encodeURIComponent(docFile.title) +
        '&lang=' +
        docFile.locale.replace('_', '-') +
        '&closebutton=1'
      )
    },

    showEditor(docFile, urlsrc) {
      const formHTML =
        '<form id="loleafletform" name="loleafletform" target="loleafletframe" action="' +
        urlsrc +
        '" method="post">' +
        '<input name="access_token" value="' +
        docFile.access_token +
        '" type="hidden"/>' +
        '<input name="access_token_ttl" value="' +
        docFile.access_token_ttl +
        '" type="hidden"/>' +
        '<input name="postmessage_origin" value="' +
        window.location.origin +
        '" type="hidden"/>' +
        '</form>'
      const frameHTML =
        '<iframe id="loleafletframe" name= "loleafletframe" class="oc-width-1-1 oc-height-1-1" allowfullscreen onload="this.contentWindow.focus()"/>'
      const mainContainer = document.getElementById('mainContainer')
      mainContainer.insertAdjacentHTML('beforeend', formHTML)
      mainContainer.insertAdjacentHTML('beforeend', frameHTML)
      mainContainer.className = "oc-width-1-1 oc-height-1-1"
      const loleafletForm = document.getElementById('loleafletform')
      const frame = document.getElementById('loleafletframe')
      const that = this
      frame.onload = function () {
        window.addEventListener('message', function (e) {
          let msg, msgId, deprecated, args
          try {
            msg = JSON.parse(e.data)
            msgId = msg.MessageId
            args = msg.Values
            deprecated = !!args.Deprecated
          } catch (exc) {
            msgId = e.data
          }
          if (msgId === 'UI_Close' || msgId === 'close' /* deprecated */) {
            // If a postmesage API is deprecated, we must ignore it and wait for the standard postmessage
            // (or it might already have been fired)
            if (deprecated) return
            that.closeApp()
          }
          if (msgId === 'UI_SaveAs') {
            that.hideSaveAs = false
          } else if (msgId === 'Action_Save_Resp') {
            if (args && args.success && args.fileName) {
              that.fileName = args.fileName
              document.title = that.fileName
            }
          } else if (msgId === 'File_Rename') {
            if (args && args.success && args.NewName) {
              that.NewName = args.NewName
              document.title = that.NewName
            }
          }
        })
        that.WOPIPostMessage(frame, 'Host_PostmessageReady', {})
      }
      loleafletForm.submit()
    }
  }
})
</script>
