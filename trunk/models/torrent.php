<?php

class Torrent extends AppModel {
	
	var $name = 'Torrent';
	var $hasMany = 'Peer';
	//var $belongsTo = array('Group');

	function ctracker() {
	// Tracker Configuration This configuration informatino for the tracker.
	$tracker = array( 
			'report_interval'	=> '100',	// Maximum reannounce interval.	(1800)
			'min_interval'		=> '300',	// Minimum reannounce interval (optional).
			'maxpeers'			=> '50',	// Number of peers to send in one request.
			'external'			=> false,	// If true, tracker will accept external torrents
			'dynamic_torrents'	=> false,	// If set to true, then the tracker will accept any and all
											// torrents given to it. Not recommended, but available if you need it.
			'NAT'				=> false,	// If set to true, NAT checking will be performed.
											// This may cause trouble with some providers, so it is
											// off by default. (on peer add is heavy on server)
			'persist'			=> false,	// Persistent connections: true or false.
											// Check with your webmaster to see if you are allowed to use these.
											// Highly recommended, especially for higher loads.
			'ip_override'		=> false,	// Allow users to override ip= ?
											// Enable this if you know people have a legit reason to use
											// this function. Leave disabled otherwise.
			'countbytes'		=> true,	// For heavily loaded trackers, set this to false. It will stop count the number
											// of downloaded bytes and the speed of the torrent, but will significantly reduce
											// the load.
			'peercaching'		=> false,	// Table caches!
											// Lowers the load on all systems, but takes up more disk space.
											// You win some, you lose some. But since the load is the big problem,
											// grab this.
	);

	return $tracker;
	}

	function __verifyHash($input) {
		if (strlen($input) === 40 && preg_match('/^[0-9a-f]+$/', $input)) {
			return true; }
		else { return false; }
	}

	function __hex2bin ($input, $assume_safe=true) {
		if ($assume_safe !== true && ! ((strlen($input) % 2) === 0 || preg_match ('/^[0-9a-f]+$/i', $input)))
			return "";
		return pack('H*', $input );
	}

	function getTorrentId($hash) { // Get Torrent hash
		$thisData = $this->find('first', array(
				'fields'		=> array('Torrent.id','Torrent.info_hash'),
				'conditions'	=> array('info_hash'	=> $hash)
			));
		return $thisData['Torrent'];
	}

	function PeerCount($id) {
		$thisCtr = $this->Peer->find('count', array(
				'conditions'	=> array('torrent_id'	=> $id)
			));
		return $thisCtr;
	}

	function FindPeer($peer,$tor_info) {
		$thisCtr = $this->Peer->find('count', array(
				'conditions'	=>	array(
						'peer_id'		=> $peer['peer_id'],
						'torrent_id'	=> $tor_info['id'])
			));
		if ($thisCtr == 0) { return false; }
		else { return true; }
	}

	function addnewpeer($peer,$tor_info) {
	//$results = "INSERT INTO x$info_hash SET peer_id=\"$peer_id\", port=\"$port\", ip=\"$ip\", lastupdate=UNIX_TIMESTAMP(), bytes=\"$left\", status=\"$status\", natuser=$nat");
		if ($peer) {
			$data['Peer'] = $peer;
			$data['Peer']['torrent_id'] = $tor_info['id'];
			$this->Peer->create();
			$this->Peer->save($data);
		}
	}

	function updatePeer($peer,$id) {
		// Updates the peer user's info.
		// Currently it does absolutely nothing. lastupdate is set in collectBytes
		// as well.
		$this->Peer->id = $id;
		$this->Peer->save($peer);
	}

	function __summaryAdd($column, $value, $tor_info) {
		$record = $this->read(null,$tor_info['id']);
		$new = $record['Torrent'][$column] + $value;
		$this->saveField($column, $new);
	}

	function getPeerInfo($peer,$tor_info,$ctracker) { 	// Returns info on one peer
	// If "trackerid" is set, let's try that
		if (isset($ctracker['trackerid'])) {
			// Check Sequence and Torrent for a possible match
			$thisCtr = $this->Peer->find('count', array(
						'conditions'	=> array(
							'sequence'		=> $ctracker['trackerid'],
							'torrent_id'	=> $tor_info['id'])
						));
			if ($thisCtr == 0) {	// No luck try peer_id
				$thisCtr = $this->Peer->find('count', array(
							'conditions'	=> array (
								'peer_id'		=> $peer['peer_id'],
								'torrent_id'	=> $tor_info['id'])
							));
				if ($thisCtr != 0) { // OK find peer information
					$thisData = $this->Peer->find('first', array(
								'conditions'	=> array(
									'peer_id'		=> $peer['peer_id'],
									'torrent_id'	=> $tor_info['id'])
								));
				}
			} else {
				$thisData = $this->Peer->find('first', array(
							'conditions'	=> array(
								'sequence'		=> $ctracker['trackerid'],
								'torrent_id'	=> $tor_info['id'])
							));
			}
		} else {
			$thisData = $this->Peer->find('first', array(
							'conditions'	=> array(
								'peer_id'		=> $peer['peer_id'],
								'torrent_id'	=> $tor_info['id'])
						));
		}
		return $thisData['Peer'];
	}

	function TorrentInTracker($hash) {		
		// Returns true if the torrent exists.
		$thisCtr = $this->find('count', array(
				'conditions'	=> array('info_hash'	=> $hash)
			));
		if ($thisCtr != 0) { return true; }
		else { return false; }
	}
	function verifyTorrent($hash,$ctracker) { 
		// Returns true if the torrent exists.
		// Always returns true if $dynamic_torrents=true unless an error occured
		$thisCtr = $this->find('count', array(
				'conditions'	=> array('info_hash'	=> $hash)
			));
		if ($thisCtr != 0) { return true; }
		else {
			if ($ctracker['dynamic_torrents']) { 
				if($this->makeTorrent($hash)) { 
					return true; 
				} else { return false; }					
			} else {
				return false;
			}
		}
	}

	function makeTorrent($hash, $tolerate = false) {
		// Used by newtorrents and the dynamic_torrents setting
		// Returns true/false, depending on if there were errors.
		if (strlen($hash) != 40) {	
			$this->showError("makeTorrent: Received an invalid hash"); 
		}
		$thisCtr = $this->find('count', array(
				'conditions'	=>	array('info_hash'	=> $hash)
				));
		if ($thisCtr == 0) { // No hash found, create new record
			$data['Torrent']['info_hash'] = $hash;
			$data['Torrent']['lastSpeedCycle']= 'UNIX_TIMESTAMP()';
			$this->Torrent->create();
			$this->Torrent->save($data);
			return true;
		} else { return false; }
	}
	
	function sendPeerList($peers,$ctracker) {
		// Transmits the actual data to the peer. No other output is permitted if
		// this function is called, as that would break BEncoding.
		// I don't use the bencode library, so watch out! If you add data,
		// rules such as dictionary sorting are enforced by the remote side.
		echo "d";
		echo "8:intervali".$ctracker['report_interval']."e";
		if (isset($ctracker['min_interval'])) {
			echo "12:min intervali".$ctracker['min_interval']."e"; 
		}
		echo "5:peers";
		$size=count($peers);
		if (isset($_GET["compact"]) && $_GET["compact"] == '1') {
			$p = '';
			foreach ($peers as $peer) { //	for ($i=0; $i < $size; $i++) {
				$p .= pack("Nn", ip2long($peer['Peer']['ip']), $peer['Peer']['port']);
			}
			echo strlen($p).':'.$p;
		} else {	 // no_peer_id or no feature supported
			echo 'l';
			foreach ($peers as $peer) {	//for ($i=0; $i < $size; $i++) {
				echo "d2:ip".strlen($peer['Peer']['ip']).":".$peer['Peer']['ip'];
				if (isset($peer['Peer']['peer_id'])) {
					echo "7:peer id20:".$this->__hex2bin($peer['Peer']['peer_id']);
				}
				echo "4:porti".$peer['Peer']['port']."ee";
			}
			echo "e";
		}
		if (isset($ctracker['trackerid'])) {		// Now it gets annoying. trackerid is a string
			echo "10:tracker id".strlen($ctracker['trackerid']).":".$ctracker['trackerid'];
		}
		echo "e";
	}

	function getRandomPeers($tor_info, $ctracker) {
		// Slight redesign of loadPeers
		// Don't want to send a bad "num peers" for new seeds
		if ($ctracker['NAT']) {
			$count = $this->Peer->find('count', array(
					'conditions'	=> array('natuser'=>'N', 'torrent_id' => $tor_info['id']) ));
			//$results = mysql_query("SELECT COUNT(*) FROM x$hash WHERE natuser = 'N'");
		} else {
			$count = $this->PeerCount($tor_info['id']);
		}
		
		// Should we give peer_id?
		if (isset($_GET["no_peer_id"]) && $_GET["no_peer_id"] == 1) {
			$column = 'no_peer_id';
		} else { $column =''; } //$column = 'with_peer_id';}

		// ORDER BY RAND() is expensive. Don't do it when the load gets too high
		if ($count < 200) {
			$thisPeers = $this->Peer->find('all', array(
									'fields'		=> array( $column ,'ip','port','status'),
									'conditions'	=> array('torrent_id' => $tor_info['id']),
									'order'			=> array('RAND()'),
									'limit'			=> $ctracker['maxpeers'] ));
			//$query = "SELECT ".((isset($_GET["no_peer_id"]) && $_GET["no_peer_id"] == 1) ? "" : "peer_id,")."ip, port, status FROM x$hash ".$where." ORDER BY RAND() LIMIT ${GLOBALS['maxpeers']}";
		} else {
			$thisPeers = $this->Peer->find('all', array(
									'fields'		=> array($column,'ip','port','status'),
									'conditions'	=> array('torrent_id' => $tor_info['id']),
									'order'			=> array('RAND()'),
									'limit'			=> @mt_rand(0, $count - $ctracker['maxpeers'])
				));
			//$query = "SELECT ".((isset($_GET["no_peer_id"]) && $_GET["no_peer_id"] == 1) ? "" : "peer_id,")."ip, port, status FROM x$hash LIMIT ".@mt_rand(0, $peercount - $GLOBALS["maxpeers"]).", ${GLOBALS['maxpeers']}";
		}
		
		//$peerno = 0;
		//while ($return[] = mysql_fetch_assoc($results)) {$peerno++; }
		//array_pop ($return);
		//mysql_free_result($results);
		//$return['size'] = $peerno;
		return $thisPeers;
	}

	function sendRandomPeers($info_hash, $ctracker) {
		// Faster pass-through version of getRandompeers => sendPeerList
		// It's the only way to use cache tables. In fact, it only uses it.
		$info_torrent = $this->getTorrentId($info_hash);
		$tor_id = $info_torrent['id'];
		$count = $this->PeerCount($tor_id);
		if (isset($_GET['compact']) && $_GET['compact'] == '1') {
			$column = 'compact'; 
		} else if (isset($_GET['no_peer_id']) && $_GET['no_peer_id'] == '1') {
			$column = 'without_peerid';
		} else { $column = ''; }
		
		if ($count < $ctracker['maxpeers']) {	// Peer counts in table less than minimum for maxpeers
			$thisPeers = $this->Peer->find('all', array(
									'fields'		=> array($column),
									'conditions'	=> array('torrent_id' => $tor_id) ));
			//$query = "SELECT $column FROM y$info_hash";
		} else if ($count > 200) {	// More than 200 peers
			do {
				$rand1 = mt_rand(0, $count-$ctracker['maxpeers']);
				$rand2 = mt_rand(0, $count-$ctracker['maxpeers']);
			} while (abs($rand1 - $rand2) < $ctracker['maxpeers']/2);
			$thisPeers = $this->Peer->find('all', array(
									'fields'		=> array($column),
									'conditions'	=> array('torrent_id' => $tor_id),
									'order'			=> array('RAN()'),
									'limit'			=> abs($rand1 - $rand2) ));
			//$query = "(SELECT $column FROM y$info_hash LIMIT $rand1, ".($GLOBALS["maxpeers"]/2). ") UNION (SELECT $column FROM y$info_hash LIMIT $rand2, ".($GLOBALS["maxpeers"]/2). ")";
		} else {
			$thisPeers = $this->Peer->find('all', array(
									'fields'		=> array($column),
									'conditions'	=> array('torrent_id' => $tor_id),
									'order'			=> array('RAN()'),
									'limit'			=> $ctracker['maxpeers'] ));
			//$query = "SELECT $column FROM y$info_hash ORDER BY RAND() LIMIT ".$GLOBALS["maxpeers"];
		}
		
		// Count result for printing
		$ctr = count($thisPeers);
		echo "d";
		echo "8:intervali".$ctracker['report_interval']."e";
		if (isset($ctracker['min_interval'])) {
			echo "12:min intervali".$ctracker['min_interval']."e"; }
		echo "5:peers";
		
		$ctr = count($thisPeers);
		if ($column == "compact") {
			echo ($ctr * 6) . ":";
			foreach ($thisPeers as $thisPeer) { echo str_pad($thisPeer['Peer'][$column], 6, chr(32)); }
		} else {
			echo "l";
			foreach ($thisPeers as $thisPeer) { echo "d".$thisPeer['Peer'][$column]."e"; }
			echo "e";
		}
		if (isset($ctracker['trackerid'])) {
			echo "10:tracker id".strlen($ctracker['trackerid']).":".$ctracker['trackerid'];
		}
		echo "e";
	//// End sendRandomPeers
	}

	function evilReject($ip, $peer_id, $port) {
		// It's cruel, but if people abuse my tracker, I just might do it.
		// It pretends to accept the torrent, and reports that you are the
		// only person connected.

		// For those of you who are feeling evil, comment out this line.
		$this->Torrent->showError("Torrent is not authorized for use on this tracker.");
		$peers[0]["peer_id"] = $peer_id;
		$peers[0]["ip"] = $ip;
		$peers[0]["port"] = $port;
		$peers["size"] = 1;
		$GLOBALS["report_interval"] = 86400;
		$GLOBALS["min_interval"] = 86000;
		$this->sendPeerList($peers);
		exit(0);
	}

	function showError($message, $log=false) {
		// Reports an error to the client in $message.
		// Any other output will confuse the client, so please don't do that.
		if ($log) {
			error_log("cakeTracker: Sent error ($message)"); }

		echo "d14:failure reason".strlen($message).":$message"."e";
		exit(0);
	}
	
	function isFireWalled($hash, $peerid, $ip, $port, $ctracker) {	
		/* Returns true if the user is firewalled, NAT'd, or whatever.
		* The original tracker had its --nat_check parameter, so
		* here is my version.
		*
		* This code has proven itself to be sufficiently correct,
		* but will consume system resources when a lot of httpd processes
		* are lingering around trying to connect to remote hosts.
		* Consider disabling it under higher loads.
		*/ 
		if (!$ctracker['NAT']) { 
			return false;
		} else {
			$protocol_name = 'BitTorrent protocol';
			$theError = "";
			// Open a socket to ip on port n check timing
			// Hoping 10 seconds will be enough
			$fd = fsockopen($ip, $port, $errno, $theError, 10);
			if (!$fd) {	// Found it firewalled
				return true; 
			} else {
			
			//stream_set_timeout($fd, 5, 0);
			//fwrite($fd, chr(strlen($protocol_name)).$protocol_name.$this->__hex2bins("0000000000000000").$this->__hex2bin($hash));
			
			//$data = fread($fd, strlen($protocol_name)+1+20+20+8); // ideally...
			fclose($fd);
			//$offset = 0;
			
			// First byte: strlen($protocol_name), then the protocol string itself
			//if (ord($data[$offset]) != strlen($protocol_name)) {
			//	return true; }
			//$offset++;
			//if (substr($data, $offset, strlen($protocol_name)) != $protocol_name) {
			//	return true; }
			//$offset += strlen($protocol_name);
			// 8 bytes reserved, ignore
			//$offset += 8;
			// Download ID (hash)
			//if (substr($data, $offset, 20) != $this->__hex2bin($hash)) {	return true; }
			//$offset+=20;
			// Peer ID
			//if (substr($data, $offset, 20) != $this->__hex2bin($peerid)) { return true; }
			return false;	
			}
		}
	}
	
	function killPeer($peer, $tor_info, $ctracker) {
		// Deletes a peer from the system and performs all cleaning up
		//
		//  $assumepeer contains the result of getPeerInfo, or false
		//  if we should grab it ourselves.
		if ($this->FindPeer($peer,$tor_info)) {
			$data = $this->getPeerInfo($peer, $tor_info, $ctracker);
			if ($peer['left'] != $data['bytes']) {
				$bytes = $this->__Subtract($data['bytes'], $peer['left']);
			} else { 
				$bytes = 0; 
			}
			// Remove one from Torrent Info		
			if ($data['status'] == "leecher") { // Remove 
				$this->__summaryAdd('leechers', -1, $tor_info);
			} else { 
				$this->__summaryAdd('seeds', -1, $tor_info); 
			}
			if ($data['bytes'] != 0 && $peer['left'] == 0) {
				$this->__summaryAdd('finished', 1, $tor_info); 
			}
			// Remove Peer
			$this->Peer->del($data['id']);
		} else {
			$bytes = 0;
		}
		// Torrent info ////////////////////////
		if ($ctracker['peercaching']) { 
			//quickQuery("DELETE FROM y$hash WHERE sequence=" . $peer["sequence"]);
		}
		if ($ctracker['countbytes'] && ((float)$bytes) > 0) {
			$this->__summaryAdd('dlbytes', $bytes, $tor_info); 
		}
	}

	// Uses the mysql database connection to perform string math. :)
	// Used by byte counting functions
	// No error handling as we assume nothing can go wrong. :|
	function __Add($left, $right) {
		$results = $left + $right;
		return $results;
	}
	function __Subtract($left, $right) {
		$results = $left - $right;
		return $results;
	}
	function __Divide($left, $right) {
		$results = $left / $right;
		return $results;
	}
	function __Multiply($left, $right) {
		$results = $left * $right;
		return $results;
	}
	
	function collectBytes($peer, $tor_info, $left, $ctracker) {
		// Transfers bytes from "left" to "dlbytes" when a peer reports in.
		if (!$ctracker["countbytes"]) {
			$this->Peer->id = $peer['id'];
			$this->Peer->saveField('modified', 'CURRENT_TIMESTAMP');
		} else {
			$diff = $this->__Subtract($peer['bytes'], $left);
			if ($diff != 0) {
				$this->Peer->id = $peer['id'];
				$this->Peer->saveField('bytes', $left);
			}
		}
		
		// Anti-negative clause
		if (((float)$diff) > 0) {
			$this->__summaryAdd('dlbytes', $diff, $tor_info);
		}
	}
	
}
?>