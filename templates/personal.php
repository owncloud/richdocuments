<?php
script('richdocuments', 'personal');
?>
<div class="section" id="richdocuments-personal">
	<h2><?php p($l->t('Office')); ?></h2>
	<div>
		<label for="documents-default-path"><?php p($l->t('Save new documents to')) ?></label>
		<input type="text" id="documents-default-path" value="<?php p($_['save_path']) ?>" /><span class="msg"></span>
	</div>
</div>
