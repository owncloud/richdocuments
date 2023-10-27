<?php
	/** @var array $_ */
	/** @var \OCP\IL10N $l */
	script('richdocuments', 'settings-admin');
	?>
<form id="richdocuments" class="section">

	<h2 class="app-name has-documentation"><?php p($l->t('Collabora Online')) ?></h2>
	<a target="_blank" rel="noreferrer" class="icon-info"
        title="<?php p($l->t('Open documentation'));?>"
        href="https://github.com/owncloud/richdocuments/wiki">
	</a>

	<p style="max-width: 50em; color: red;"><?php if ($_['encryption_enabled'] === 'true' && $_['masterkey_encryption_enabled'] !== 'true') {
		p($l->t("Enabled encryption type will result in limited functionality of the app. App requires privileged access to the files, and the only currently supported type is master key encryption."));
	} ?></p>

	<label for="wopi_url-richdocuments"><?php p($l->t('Collabora Online server')) ?></label>
	<input type="text" id="wopi_url-richdocuments" value="<?php p($_['wopi_url'])?>" placeholder="https://localhost:9980" style="width:300px;">
	<button type="button" id="wopi_url_save-richdocuments"><?php p($l->t('Save')) ?></button>
	<span id="documents-admin-msg" class="msg"></span>
	<br/><em><?php p($l->t('URL (and port) of the Collabora Online server that provides the editing functionality as a WOPI client.')) ?></em>
    
	<br/>
    <br/>

    <input type="checkbox" class="test-server-enable" id="test_server_enable-richdocuments" />
    <label for="test-server-enable-richdocuments"><?php p($l->t('Enable test server for specific groups')) ?></label>
    <p id="test_server_section-richdocuments" style="padding-left: 28px;" class="indent <?php if ($_['test_server_groups'] === '' || $_['test_wopi_url'] === '') {
    	p('hidden');
    } ?>">
        <label for="test_server_group_select-richdocuments"><?php p($l->t('Groups')) ?></label>
        <input type="hidden" id="test_server_group_select-richdocuments" value="<?php p($_['test_server_groups'])?>" title="<?php p($l->t('None')); ?>" style="width: 200px" /><br/>

        <label for="test_wopi_url-richdocuments"><?php p($l->t('Test server')) ?></label>
        <input type="text" id="test_wopi_url-richdocuments" value="<?php p($_['test_wopi_url'])?>" style="width:300px;" />
		<br/>
        <em><?php p($l->t('URL (and port) of the Collabora Online test server.')) ?></em>
		<br/>
        <button type="button" id="test_wopi_url_save-richdocuments"><?php p($l->t('Save')) ?></button>
        <span id="test-documents-admin-msg" class="msg"></span>
		<br/>
    </p>

	<br/>

    <input type="checkbox" class="edit-groups-enable" id="edit_groups_enable-richdocuments" />
    <label for="edit_groups_enable-richdocuments"><?php p($l->t('Enable edit for specific groups')) ?></label>
    <input type="hidden" id="edit_group_select-richdocuments" value="<?php p($_['edit_groups'])?>" title="<?php p($l->t('All')); ?>" style="width: 200px">
    
	<br/>

    <input type="checkbox" class="doc-format-ooxml" id="doc_format_ooxml_enable-richdocuments" <?php p($_['doc_format'] === 'ooxml' ? 'checked' : '') ?> />
    <label for="doc_format_ooxml_enable-richdocuments"><?php p($l->t('Use OOXML by default for new files')) ?></label>

	<br/>

	<input type="checkbox" id="enable_canonical_webroot_cb-richdocuments" <?php p($_['canonical_webroot'] !== '' ? 'checked' : '') ?> />
	<label for="canonical_webroot_cb-richdocuments"><?php p($l->t('Use Canonical webroot')) ?></label>
	<div id="enable_canonical_webroot_section-richdocuments" style="padding-left: 28px;" class="indent <?php if ($_['canonical_webroot'] === '') {
		p('hidden');
	} ?>" >
		<input type="text" id="canonical_webroot-richdocuments" value="<?php p($_['canonical_webroot']) ?>">
        <button type="button" id="canonical_webroot_enable-richdocuments"><?php p($l->t('Save')) ?></button>
        <span id="cannonical-webroot-admin-msg" class="msg"></span>
		<br/>
		<p style="max-width: 50em;"><em><?php p($l->t('Canonical webroot, in case there are multiple, for Collabora to use. Provide the one with least restrictions. Eg: Use non-shibbolized webroot if this instance is accessed by both shibbolized and non-shibbolized webroots. You can ignore this setting if only one webroot is used to access this instance.')) ?></em></p>
	</div>

	<br/>

	<input type="checkbox" id="enable_menu_option_cb-richdocuments" <?php p($_['menu_option'] !== 'false' ? 'checked' : '') ?> />
	<label for="enable_menu_option_cb-richdocuments"><?php p($l->t('Use Menu option')) ?></label>

	<br/>
	<br/>
	
	<h2 class="app-name has-documentation"><?php p($l->t('Secure View for Collabora Online')) ?></h2>
	<a target="_blank" rel="noreferrer" class="icon-info"
                title="<?php p($l->t('Open documentation'));?>"
                href="https://doc.owncloud.com/server/next/admin_manual/enterprise/collaboration/collabora_secure_view.html"></a>

	<br/>
	<input type="checkbox" id="enable_secure_view_option_cb-richdocuments" <?php if ($_['secure_view_option'] === 'true') {
		p('checked');
	} elseif ($_['secure_view_allowed'] !== 'true') {
		p('disabled');
	}?> />
	<label for="enable_secure_view_option_cb-richdocuments"><?php if ($_['secure_view_allowed'] !== 'true') {
		print_unescaped('<em>');
		p($l->t('Enable Secure View (requires Enterprise edition)'));
		print_unescaped('</em>');
	} else {
		p($l->t('Enable Secure View'));
	}?></label>

	<div id="richdocuments-secure-view-preferences-section" style="padding-left: 28px;" class="indent <?php if ($_['secure_view_option'] !== 'true') {
		p('hidden');
	} ?>" >
		<p style="max-width: 50em;"><em><?php p($l->t('Preferences')) ?></em></p>

		<input type="checkbox" id="enable_secure_view_open_action_default_cb-richdocuments" <?php p($_['secure_view_open_action_default'] === 'true' ? 'checked' : '') ?> />
		<label for="enable_secure_view_open_action_default_cb-richdocuments"><?php p($l->t('Enforce to open documents always in Secure View mode with watermark')) ?></label>
		<br/>
		<input type="checkbox" id="secure_view_can_print_default_option_cb-richdocuments" <?php p($_['secure_view_can_print_default'] === 'true' ? 'checked' : '')  ?> <?php p($_['secure_view_has_watermark_default'] === 'false' ? 'disabled' : '')  ?>  />
		<label for="secure_view_can_print_default_option_cb-richdocuments"><?php p($l->t('Set "can print/export" as default share permission')) ?></label>
		<br/>
		<input type="checkbox" id="secure_view_has_watermark_default_option_cb-richdocuments" <?php p($_['secure_view_has_watermark_default'] === 'true' ? 'checked' : '') ?> />
		<label for="secure_view_has_watermark_default_option_cb-richdocuments"><?php p($l->t('Set "Secure View (with watermarks)" as default share permission')) ?></label>
	</div>

	<br/>

	<div id="richdocuments-watermark-section" style="padding-left: 28px;" class="indent <?php if ($_['secure_view_option'] !== 'true') {
		p('hidden');
	} ?>" >
		<p style="max-width: 50em;"><em><?php p($l->t('Set watermark text for Secure View. To include the user email address dynamically, use the {viewer-email} variable.')) ?></em></p>
		<input type="text" style="width: 400px" id="secure_view_watermark-richdocuments" value="<?php p(($_['watermark_text'] !== '' && $_['watermark_text'] !== null) ? $_['watermark_text']: '') ?>">
        <button type="button" id="save_secure_view_watermark-richdocuments"><?php p($l->t('Save')) ?></button>
        <span id="save-secure-view-watermark-admin-msg" class="msg"></span>
	</div>

	<br/>
	
	<h2 class="app-name has-documentation"><?php p($l->t('Zotero for Collabora Online')) ?></h2>
	<a target="_blank" rel="noreferrer" class="icon-info"
                title="<?php p($l->t('Open documentation'));?>"
                ref="https://github.com/owncloud/richdocuments/wiki"></a>

	<br/>

	<input type="checkbox" id="enable_zotero-richdocuments" <?php p($_['zotero'] !== 'false' ? 'checked' : '') ?> />
	<label for="enable_zotero-richdocuments"><?php p($l->t('Enable Zotero for all users')) ?></label>

</form>
