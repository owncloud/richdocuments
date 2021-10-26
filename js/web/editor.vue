<template>
    <main>
        <div id="app">
            <oc-modal
                title="Save As"
                :button-cancel-text="$gettext('Cancel')"
                :button-confirm-text="$gettext('Save')"
                :has-input="true"
                :input-label="$gettext('New filename')"
                :input-description="
                    $gettext(
                        'Please enter filename to which this document should be stored.'
                    )
                "
                :input-value="fileName"
                class="oc-mb-l uk-position-absolute"
                v-on:cancel="cancelSaveAs"
                v-on:confirm="confirmSaveAs"
                :hidden="hideSaveAs"
            />
            <div id="mainContainer" />
        </div>
    </main>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import axios from "axios";

export default {
    data: () => ({
        fileName: null,
        hideSaveAs: true,
    }),

    methods: {
        ...mapActions(["showMessage"]),

        messageDisplay(desc, status = "danger", title = "") {
            this.showMessage({
                title,
                desc,
                status,
                autoClose: {
                    enabled: true,
                },
            });
        },

        cancelSaveAs() {
            this.hideSaveAs = true;
        },

        confirmSaveAs(value) {
            if (!value) return;
            let frame = document.getElementById("loleafletframe");
            this.WOPIPostMessage(frame, "Action_SaveAs", {
                Filename: value,
                Notify: true,
            });
            this.hideSaveAs = true;
        },

        onRequestClose() {
            let params = { item: null };
            if (this.currentFolder) {
                params.item = this.currentFolder.path;
            }

            this.$router.push({ name: "files-personal", params });
        },

        WOPIPostMessage(iframe, msgId, values) {
            if (iframe) return;
            var msg = {
                MessageId: msgId,
                SendTime: Date.now(),
                Values: values,
            };
            iframe.contentWindow.postMessage(JSON.stringify(msg), "*");
        },

        // where we get document url from owncloud api
        async getDocumentFileInfo() {
            return axios({
                method: "GET",
                url:
                    this.configuration.server +
                    "index.php/apps/richdocuments/ajax/documents/index/" +
                    this.fileId,
                headers: {
                    Authorization: "Bearer " + this.getToken,
                },
            }).then((response) => {
                return response.data;
            });
        },

        generateWOPISrc(documentInfo) {
            const documentId = [documentInfo.fileId, documentInfo.instanceId, documentInfo.version, documentInfo.sessionId].join('_');
            return (
                this.configuration.server +
                `index.php/apps/richdocuments/wopi/files/${documentId}`
            );
        },

        generateDocUrlSrc(docFile) {
            let wopiSrc = encodeURIComponent(this.generateWOPISrc(docFile));
            let urlsrc = docFile.urlsrc +
                "WOPISrc=" + wopiSrc +
                "&title=" + encodeURIComponent(docFile.title) +
                "&lang=" + docFile.locale.replace("_", "-") +
                "&closebutton=1";
            return urlsrc;
        },

        showEditor(docFile, urlsrc) {
            let formHTML = '<form id="loleafletform" name="loleafletform" target="loleafletframe" action="' + urlsrc +'" method="post">' +
                '<input name="access_token" value="' + docFile.access_token + '" type="hidden"/>' +
                '<input name="access_token_ttl" value="' + docFile.access_token_ttl + '" type="hidden"/>' +
                '<input name="postmessage_origin" value="' + window.location.origin + '" type="hidden"/>' +
                "</form>";
            let frameHTML = '<iframe id="loleafletframe" name= "loleafletframe" allowfullscreen style="width:100%;height:100%;position:absolute;" onload="this.contentWindow.focus()"/>';
            let mainContainer = document.getElementById("mainContainer");
            mainContainer.insertAdjacentHTML("beforeend", formHTML);
            mainContainer.insertAdjacentHTML("beforeend", frameHTML);
            let loleafletForm = document.getElementById("loleafletform");
            let frame = document.getElementById("loleafletframe");
            let that = this;
            frame.onload = function () {
                window.addEventListener("message", function (e) {
                    let msg, msgId, deprecated, args;
                    try {
                        msg = JSON.parse(e.data);
                        msgId = msg.MessageId;
                        args = msg.Values;
                        deprecated = !!args.Deprecated;
                    } catch (exc) {
                        msgId = e.data;
                    }
                    if (
                        msgId === "UI_Close" ||
                        msgId === "close" /* deprecated */
                    ) {
                        // If a postmesage API is deprecated, we must ignore it and wait for the standard postmessage
                        // (or it might already have been fired)
                        if (deprecated) return;
                        that.onRequestClose();
                    }
                    if (msgId === "UI_SaveAs") {
                        that.hideSaveAs = false;
                    } else if (msgId === "Action_Save_Resp") {
                        if (args && args.success && args.fileName) {
                            that.fileName = args.fileName;
                        }
                    }
                });
                that.WOPIPostMessage(frame, "Host_PostmessageReady", {});
            };
            loleafletForm.submit();
        },
    },

    computed: {
        ...mapGetters(["getToken", "configuration", "apps"]),
        ...mapGetters("Files", ["currentFolder"]),
        mode() {
            return this.$route.params.mode || 'edit';
        },
        fileId() {
            return this.$route.params.fileId;
        },
        filePath() {
            return this.$route.params.filePath;
        }
    },

    async mounted() {
        try {
            let docFile = await this.getDocumentFileInfo();
            this.fileName = docFile.title;
            let urlsrc = this.generateDocUrlSrc(docFile);
            this.showEditor(docFile, urlsrc);
        } catch (error) {
            this.messageDisplay(error);
            this.onRequestClose();
        }
    },
};
</script>
<style>
#app {
    width: 100%;
}
#app > iframe {
    position: absolute;
}
</style>