<script>
	 var rd_instanceId = '<?php p($_['instanceId']) ?>';
	 var rd_version = '<?php p($_['version']) ?>';
	 var rd_sessionId = '<?php p($_['sessionId']) ?>';
	 var rd_permissions = '<?php p($_['permissions']) ?>';
	 var rd_title = '<?php p($_['title']) ?>';
	 var rd_fileId = '<?php p($_['fileId']) ?>';
	 var rd_access_token = '<?php p($_['access_token']) ?>';
	 var rd_access_token_ttl = '<?php p($_['access_token_ttl']) ?>';
	 var rd_urlsrc = '<?php p($_['urlsrc']) ?>';
	 var rd_path = '<?php p($_['path']) ?>';
	 var rd_canonical_webroot = '<?php p($_['canonical_webroot']) ?>';
</script>

<?php
style('richdocuments', 'style');
	 script('richdocuments', 'documents');
	 script('files', 'file-upload');
	 script('files', 'jquery.fileupload');
	 ?>

<?php if ($_['show_custom_header']): ?>
	<div id="notification-container">
		<div id="notification"></div>
	</div>
	<header role="banner">
		<div id="header">
			<a href="<?php print_unescaped(link_to('', 'index.php')); ?>" id="owncloud" tabindex="1">
				<h1 class="logo-icon">
					<?php print_unescaped($theme->getHTMLName()); ?>
				</h1>
			</a>
			<div id="logo-claim" style="display:none;"><?php print_unescaped($theme->getLogoClaim()); ?></div>
		</div>
	</header>
<?php endif; ?>

<div id="documents-content">
	<ul class="documentslist">
		<li class="add-document">
			<a class="icon-add add-<?php p($_['doc_format'] === 'ooxml' ? 'docx' : 'odt') ?> svg" target="_blank" href="">
				<label><?php p($l->t('New Document')) ?></label>
			</a>
			<a class="icon-add add-<?php p($_['doc_format'] === 'ooxml' ? 'xlsx' : 'ods') ?> svg" target="_blank" href="">
				<label><?php p($l->t('New Spreadsheet')) ?></label>
			</a>
			<a class="icon-add add-<?php p($_['doc_format'] === 'ooxml' ? 'pptx' : 'odp') ?> svg" target="_blank" href="">
				<label><?php p($l->t('New Presentation')) ?></label>
			</a>
			<a class="icon-add add-odg svg" target="_blank" href="">
				<label><?php p($l->t('New Drawing')) ?></label>
			</a>
			<div id="upload" title="<?php p($l->t('Upload (max. %s)', [$_['uploadMaxHumanFilesize']])) ?>">
				<form id="uploadform" enctype="multipart/form-data">
					<input type="file" id="file_upload_start" name='file' accept=".csv,.doc,.docx,.odp,.ods,.odt,.odg,.pdf,.ppt,.pptx,.txt,.xhtml,.xls,.xlsx,.xml,.xul,.epub,.rtf" required/>
					<a href="#" class="icon-upload upload svg">
						<label><?php p($l->t('Upload')) ?></label>
					</a>
				</form>
			</div>
		</li>
		<li class="progress icon-loading"><div><?php p($l->t('Loading documents...')); ?></div></li>
		<li class="document template" data-id="" style="display:none;">
			<a target="_blank" href=""><label></label></a>
		</li>
	</ul>
</div>
<input type="hidden" id="wopi-url" name="wopi-url" value="<?php p($_['wopi_url']) ?>" />
<?php if ($_['enable_previews']): ?>
<input type="hidden" id="previews_enabled" value="<?php p($_['enable_previews']) ?>" />
<?php endif; ?>
