<div class="torrents form">
<?php echo $form->create('Torrent', array('type' => 'file'));?>
	<fieldset>
 		<legend><?php __('Add Torrent');?></legend>
	<?php
	echo $form->input('name');
	//echo $form->input('group_id');
	echo $form->input('description');
	echo $form->File('file', array('label'=>'File'));
	echo $form->end('Submit');
	?>
	</fieldset>
</div>
<div class="actions">
	<ul>
		<li><?php echo $html->link(__('List Files', true), array('action'=>'index'));?></li>
	</ul>
</div>
