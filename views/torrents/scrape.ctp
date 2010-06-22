<?php
echo "d5:filesd";
foreach ($torrents as $torrent):
	echo "20:".$torrent['Torrent']['hash']."d";
	print $torrent['Torrent']['seeds'];
	echo "8:completei".$torrent['Torrent']['seeds']."e";
	echo "10:downloadedi".$torrent['Torrent']['finished']."e";
	echo "10:incompletei".$torrent['Torrent']['leechers']."e";
	if ($torrent['Torrent']['filename']) {
		echo "4:name".strlen($torrent['Torrent']['filename']).":".$torrent['Torrent']['filename'];
	}
	echo "e";

endforeach;
echo "ee";

?>