<?php
	/** @var array $_ */
	/** @var \OCP\IL10N $l */
	script('richdocuments', 'settings-personal');
	?>
<form id="richdocuments" class="section">

	<h2 class="app-name"><?php p($l->t('Zotero for Collabora Online')) ?></h2>
	<?php p($l->t("Connect Zotero for Bibliography and Citation inside Collabora Online.")); ?>
	<br />

	<p style="max-width: 50em; color: red;"><?php if ($_['zotero'] !== 'true') {
		p($l->t("Zotero integration is disabled, ask your administrator to enable it."));
	} ?></p>
	<p id="change_zotero_key_section-richdocuments" class="indent <?php if ($_['zotero'] !== 'true') {
		p('hidden');
	} ?>">
		<br />
		<label for="change_zotero_key-richdocuments"><?php p($l->t('Zotero Personal API Key:')) ?></label>
		<input
				type="text"
				id="change_zotero_key-richdocuments" 
				style="width: 350px; max-width: 100%"
				value="<?php echo($_['zoteroAPIPrivateKey'] ? $_['zoteroAPIPrivateKey'] : ''); ?>"/>
		<button
				type="button"
				id="save_zotero_key-richdocuments"
				disabled><?php p($l->t("Save")); ?>
		</button>
		<span class="msg"></span>
		<br />
		<em><?php p($l->t("To generate the API key navigate in Zotero to")); ?> <a href="https://www.zotero.org/settings/keys" target="_blank"><?php p($l->t('Home > Settings > Feeds/API > Create new private key')); ?></a></em>
		<br />
    </p>

</form>
