<div class="torrents view">
<h2><?php __('Torrents');?></h2>

<table>
   <tr>
    <th><?php echo $paginator->sort('id');?></th>
    <th><?php echo $paginator->sort('name');?></th>
    <th><?php echo $paginator->sort('description');?></th>
    <th><?php echo $paginator->sort('filename');?></th>
    <th><?php echo $paginator->sort('size');?></th>
    <th><?php echo $paginator->sort('downloaded');?></th>
    <th><?php echo $paginator->sort('seeds');?></th>
    <th><?php echo $paginator->sort('leechers');?></th>
    <th><?php echo $paginator->sort('finished');?></th>
    <th><?php echo $paginator->sort('speed');?></th>
    <th class="actions"><?php __('Actions');?></th>
  </tr>
<?php
$i = 0;
foreach ($torrents as $torrent):
	$class = null;
	if ($i++ % 2 == 0) {
		$class = ' class="altrow"';
	}
?>
   <tr<?php echo $class;?>>
	<td><?php echo $torrent['Torrent']['id']; ?></td>
	<td><?php echo $torrent['Torrent']['name']; ?></td>
	<td><?php echo $torrent['Torrent']['description']; ?></td>
	<td><?php echo $torrent['Torrent']['filename']; ?></td>
	<td><?php echo $torrent['Torrent']['info']; ?></td>
	<td><?php echo $torrent['Torrent']['dlbytes']; ?></td>
	<td><?php echo $torrent['Torrent']['seeds']; ?></td>
	<td><?php echo $torrent['Torrent']['leechers']; ?></td>
	<td><?php echo $torrent['Torrent']['finished']; ?></td>
	<td><?php echo $torrent['Torrent']['Speed']; ?></td>
	<td class="actions">
	<?php echo $html->link(__('View', true), array('action'=>'view', $torrent['Torrent']['id'])); ?>
	<?php echo $html->link(__('Delete', true), array('action'=>'delete', $torrent['Torrent']['id']), null, sprintf(__('Are you sure you want to delete # %s?', true), $torrent['Torrent']['id'])); ?>
	<?php echo $html->link(__('Download', true), '../'.$torrent['Torrent']['path']); ?>
	</td>
   </tr>
<?php endforeach; ?>

</table>

<div class="paging">
	<?php echo $paginator->prev('<< '.__('previous', true), array(), null, array('class'=>'disabled'));?>
 | 	<?php echo $paginator->numbers();?>
	<?php echo $paginator->next(__('next', true).' >>', array(), null, array('class'=>'disabled'));?>
</div>
<div class="actions">
	<ul>
		<li><?php echo $html->link(__('New Torrent', true), array('action'=>'add')); ?></li>
	</ul>
</div>
</div>
