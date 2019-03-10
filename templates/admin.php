<?php
script('richdocuments', 'admin');
?>
<div class="section" id="richdocuments">
	<h2 class="app-name has-documentation"><?php p($l->t('Collabora Online')) ?></h2>
	<a target="_blank" rel="noreferrer" class="icon-info"
                title="<?php p($l->t('Open documentation'));?>"
                href="https://github.com/owncloud/richdocuments/wiki"></a>

        <br/>
	<label for="wopi_url"><?php p($l->t('Collabora Online server')) ?></label>
	<input type="text" name="wopi_url" id="wopi_url" value="<?php p($_['wopi_url'])?>" style="width:300px;">
	<br/><em><?php p($l->t('URL (and port) of the Collabora Online server that provides the editing functionality as a WOPI client.')) ?></em>
	<br/><button type="button" id="wopi_apply"><?php p($l->t('Apply')) ?></button>
	<span id="documents-admin-msg" class="msg"></span>
    <br/>

    <input type="checkbox" class="test-server-enable" id="test_server_enable-richdocuments" />
    <label for="test-server-enable"><?php p($l->t('Enable test server for specific groups')) ?></label><br/>
    <p id="test-server-section" class="indent <?php if ($_['test_server_groups'] === '' || $_['test_wopi_url'] === '') {
	p('hidden');
} ?>">
        <label for="test_server_group_select"><?php p($l->t('Groups')) ?></label>
        <input type="hidden" id="test_server_group_select" value="<?php p($_['test_server_groups'])?>" title="<?php p($l->t('None')); ?>" style="width: 200px" /><br/>

        <label for="test_wopi_url"><?php p($l->t('Test server')) ?></label>
        <input type="text" name="test_wopi_url" id="test_wopi_url" value="<?php p($_['test_wopi_url'])?>" style="width:300px;" /><br/>
        <em><?php p($l->t('URL (and port) of the Collabora Online test server.')) ?></em><br/>

        <button type="button" id="test_wopi_apply"><?php p($l->t('Apply')) ?></button>
        <span id="test-documents-admin-msg" class="msg"></span>
    </p>
    <input type="checkbox" class="edit-groups-enable" id="edit_groups_enable-richdocuments" />
    <label for="edit_groups_enable-richdocuments"><?php p($l->t('Enable edit for specific groups')) ?></label>
    <input type="hidden" id="edit_group_select" value="<?php p($_['edit_groups'])?>" title="<?php p($l->t('All')); ?>" style="width: 200px">
    <br/>
    <input type="checkbox" class="doc-format-ooxml" id="doc_format_ooxml_enable-richdocuments" <?php p($_['doc_format'] === 'ooxml' ? 'checked' : '') ?> />
    <label for="doc_format_ooxml_enable-richdocuments"><?php p($l->t('Use OOXML by default for new files')) ?></label>
	<br/>
    <input type="checkbox" id="enable_external_apps_cb-richdocuments" <?php p($_['external_apps'] !== '' ? 'checked' : '') ?> />
    <label for="enable_external_apps_cb-richdocuments"><?php p($l->t('Enable access for external apps')) ?></label>
	<div id="enable-external-apps-section" class="indent <?php if ($_['external_apps'] == '') {
	p('hidden');
} ?>" >
	    <div id="external-apps-section">
	       <input type="hidden" id="external-apps-raw" name="external-apps-raw" value="<?php p($_['external_apps']) ?>">
	    </div>

	    <button type="button" id="external-apps-save-button"><?php p($l->t('Save')) ?></button>
	    <button type="button" id="external-apps-add-button"><?php p($l->t('Add')) ?></button>
	    <span id="enable-external-apps-section-msg" class="msg"></span>
	</div>

	<br/>
	<input type="checkbox" id="enable_canonical_webroot_cb-richdocuments" <?php p($_['canonical_webroot'] !== '' ? 'checked' : '') ?> />
	<label for="canonical_webroot_cb-richdocuments"><?php p($l->t('Use Canonical webroot')) ?></label>
	<div id="enable-canonical-webroot-section" class="indent <?php if ($_['canonical_webroot'] == '') {
	p('hidden');
} ?>" >
	<input type="text" id="canonical-webroot" name="canonical-webroot-name" value="<?php p($_['canonical_webroot']) ?>">
	<br/>
	<p style="max-width: 50em;"><em><?php p($l->t('Canonical webroot, in case there are multiple, for Collabora to use. Provide the one with least restrictions. Eg: Use non-shibbolized webroot if this instance is accessed by both shibbolized and non-shibbolized webroots. You can ignore this setting if only one webroot is used to access this instance.')) ?></em></p>
	</div>
	<br/>

	<input type="checkbox" id="enable_menu_option_cb-richdocuments" <?php p($_['menu_option'] !== 'false' ? 'checked' : '') ?> />
	<label for="enable_menu_option_cb-richdocuments"><?php p($l->t('Use Menu option')) ?></label>

	<br/>
	<input type="checkbox" id="enable_secure_view_option_cb-richdocuments" <?php p($_['secure_view_option'] === 'true' ? 'checked' : '') ?> />
	<label for="enable_secure_view_option_cb-richdocuments"><?php p($l->t('Allow setting secure-view for read-only shares')) ?></label>
	<div id="enable-share-permissions-defaults" style="padding-left: 28px;" class="indent <?php if ($_['secure_view_option'] !== 'true') {
	p('hidden');
} ?>" >
		<p style="max-width: 50em;"><em><?php p($l->t('Set default share permissions for all the users')) ?></em></p>
		<input type="checkbox" id="enable_can_download_option_cb-richdocuments" <?php p($_['can_download_default'] === 'true' ? 'checked' : '') ?> />
		<label for="enable_can_download_option_cb-richdocuments"><?php p($l->t('can download')) ?></label>
		<input type="checkbox" id="enable_can_print_option_cb-richdocuments" <?php p($_['can_print_default'] === 'true' ? 'checked' : '') ?> />
		<label for="enable_can_print_option_cb-richdocuments"><?php p($l->t('can print')) ?></label>
	</div>
	<br/>
	<div id="enable-watermark-section" style="padding-left: 28px;" class="indent <?php if ($_['secure_view_option'] !== 'true') {
	p('hidden');
} ?>" >
		<p style="max-width: 50em;"><em><?php p($l->t('Set watermark to be used in secure-view documents. If you wish to insert user email dynamically, leave {viewer-email} field. Click outside of the field to save field value.')) ?></em></p>
		<input type="text" style="width: 400px" id="secure-view-watermark" name="secure-view-watermark-name" value="<?php p($_['watermark_text'] !== null ? $_['watermark_text']: $l->t('Strictly confidential. Only for ').'{viewer-email}') ?>">
	</div>
</div>
