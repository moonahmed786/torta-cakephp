<?php
class TorrentsController extends AppController {

	var $name = 'Torrents';
	var $helpers = array('Html', 'Form');
	var $info_hash;
	var $tor_info;
	var $peer;
	var $peer_id;
	var $ctracker;
	var $BDecode; 
	var $BEncode;

	function beforeFilter() {
		// always - ALWAYS RUN PARENT!
		parent::beforeFilter();
		
    }

	function view($id = null) {
		if (!$id) {
			$this->Session->setFlash(__('Invalid Torrent.', true));
			$this->redirect(array('action'=>'index'));
		}
		$this->set('torrent', $this->Torrent->read(null, $id));		
	}

	function index() {
                $this->Torrent->recursive = 0;
                $this->set('torrents', $this->paginate());
    }

	function announce() {
	///////////////////////////////////////////////////////////////////
	// Handling of parameters from the URL and other setup
		$this->__filterClientTorrent();
		
		// Error: no web browsers allowed
		if (!isset($_GET["info_hash"]) || !isset($_GET["peer_id"])) {
			header("HTTP/1.0 400 Bad Request");
			die("This file is for BitTorrent clients.\n");
		}
		if (get_magic_quotes_gpc()) {
			$this->info_hash = bin2hex(stripslashes($_GET["info_hash"]));
			$this->peer_id = bin2hex(stripslashes($_GET["peer_id"]));
		} else {
			$this->info_hash = bin2hex($_GET["info_hash"]);
			$this->peer_id = bin2hex($_GET["peer_id"]);
		}
		if (!isset($_GET["port"]) || !isset($_GET["downloaded"]) || !isset($_GET["uploaded"]) || !isset($_GET["left"])) {
			$this->Torrent->showError("Invalid information received from BitTorrent client"); 
		}

		$this->peer = $this->ReadPeerInfo();
		
		//Tracker config
		if (!isset($this->ctracker['ip_override'])) { $this->ctracker['ip_override'] = true; }
		if (isset($_GET["trackerid"])) { // Tracker ID
			if (is_numeric($_GET["trackerid"])) {
				$this->ctracker['trackerid'] = mysql_escape_string($_GET["trackerid"]); } 
		}

		/////// CHECK TORRENT 
		// The hash should be in the database
		// if tracker configuration is set to 'dynamic_torrents' = true
		// then torrent will be added to database in verifyTorrent function.
		if(!$this->Torrent->__verifyHash($this->info_hash)) {
			$this->Torrent->showError("Received an invalid hash"); 
		} else if($this->Torrent->verifyTorrent($this->info_hash,$this->ctracker) ) {
			// or evilReject($ip, $peer_id,$port);
			
			// Get Torrent information and put it on variable
			$this->tor_info = $this->Torrent->getTorrentId($this->info_hash);
			// Check the EVENT which decides what to respond to client!
			$this->__event($this->peer);
		} else {
			$this->Torrent->showError("Torrent is not authorized for use on this tracker.");
		}
	//// End announce
	}

	function scrape() {
		$this->__filterClientTorrent();
			// Scrape provides information about a given Torrent or All Torrents
			// Prints (info_hash, completed(seeds), downloaded(finished), incomplete(Leechers), name(filename)
		if($this->isInfoHashSet()) {	// Info_Hash is good
			$thisDatas = array();
			$thisDatas[] = $this->Torrent->findByInfoHash($this->info_hash);
		} else {
			$thisDatas = $this->Torrent->find('all');
		}

		$torrents = array();
		foreach ($thisDatas as $thisData) {
			$hash = $this->Torrent->__hex2bin($thisData['Torrent']['info_hash']);
			$thisData['Torrent']['hash'] = $hash;
			$torrents[]['Torrent']=$thisData['Torrent'];
		}
		$this->set('torrents', $torrents);
	//// End Scrape
	}

	function isInfoHashSet() {
		$usehash = false;
		if (isset($_GET["info_hash"])) {
			if (get_magic_quotes_gpc()) {
				$this->info_hash = stripslashes($_GET["info_hash"]); 
			} else {
				$this->info_hash = $_GET["info_hash"]; 
			}

			if (strlen($this->info_hash) == 20) {
				$this->info_hash = bin2hex($this->info_hash);
			} elseif (strlen($this->info_hash) == 40) {
				$this->Torrent->__verifyHash($this->info_hash) or $this->Torrent->showError("Invalid value."); 
			} else {
				$this->Torrent->showError("Invalid Hash value");
			}
			$usehash = true;
		}
		return $usehash;
	//// End isInfoHashSet
	}
	
	///////////////////////////////////////////////////////////////////////////////////////
	// Actual work. Depends on value of $event. (Missing event is mapped to '' above)
	function __event($peer) {
		$this->peer = $peer;
		$event = $this->peer['event'];
		switch ($event) {// client sent start
		case "started":
			$start = $this->__newpeerstart($this->tor_info, $this->peer);
			//$start = start($this->info_hash,$this->peer);
			// Don't send the tracker id for newly started clients. Send it next time. Make sure
			// they get a good random list of peers to begin with.
			if ($this->ctracker['peercaching']) { 
				$this->Torrent->sendRandomPeers($this->info_hash,$this->ctracker);
			} else {
				$peers = $this->Torrent->getRandomPeers($this->tor_inf, $this->ctracker);
				$this->Torrent->sendPeerList($peers,$this->ctracker);
			}
			// (started) end
			break;		
		case "stopped":
			$this->Torrent->killPeer($this->peer, $this->tor_info, $this->ctracker);	
			// I don't know why, but the real tracker returns peers on event=stopped
			// but I'll just send an empty list.
			if (isset($_GET['tracker'])) {
				$peers = $this->Torrent->getRandomPeers($this->info_hash,$this->ctracker);
			} else {
				$peers = array('size' => '0'); 
			}	
			$this->Torrent->sendPeerList($peers,$this->ctracker);
			// (stopped) end
			break;
		case "completed": // client sent complete
			// now the same as an empty string
			$data = $this->Torrent->getPeerInfo($this->peer, $this->tor_info, $this->ctracker);
			if (!is_array($data)) {
				$start = $this->__newpeerstart($this->tor_info, $this->peer);
				$data = $this->Torrent->getPeerInfo($this->peer, $this->tor_info, $this->ctracker);
			} else {
				// Update some peer info 
				$this->peer['status'] = 'seeder';
				$this->peer['bytes'] = '0';
				$this->peer['sequence'] = $this->ctracker['trackerid'];
				$this->Torrent->updatePeer($this->peer,$data['id']);
				
				$this->Torrent->__summaryAdd('leechers', -1, $this->tor_info);
				$this->Torrent->__summaryAdd('seeds', 1, $this->tor_info);
				$this->Torrent->__summaryAdd('finished', 1, $this->tor_info);
			}

			$this->Torrent->collectBytes($data,$this->tor_info, $this->peer['left'],$this->ctracker);
			$peers = $this->Torrent->getRandomPeers($this->info_hash,$this->ctracker);
			$this->Torrent->sendPeerList($peers,$this->ctracker);
			// (completed) end
			break;
		case "":	// client sent no event
			$data = $this->Torrent->getPeerInfo($this->peer, $this->tor_info, $this->ctracker);
			//$peer_exists = getPeerInfo($peer_id, $info_hash);
			if (!is_array($data)) {
				$start = $this->__newpeerstart($this->tor_info, $this->peer);
				$data = $this->Torrent->getPeerInfo($this->peer, $this->tor_info, $this->ctracker);
			}
			if ($data['bytes'] != 0 && $this->peer['left'] == 0) {
				// Update some peer info 
				$this->peer['status'] = 'seeder';
				$this->peer['bytes'] = '0';
				$this->peer['sequence'] = $this->ctracker['trackerid'];
				$this->Torrent->updatePeer($this->peer,$data['id']);

				$this->Torrent->__summaryAdd('leechers', -1, $this->tor_info);
				$this->Torrent->__summaryAdd('seeds', 1, $this->tor_info);
				$this->Torrent->__summaryAdd('finished', 1, $this->tor_info);
			} else {
				$this->Torrent->updatePeer($this->peer,$data['id']);
			}
			$this->Torrent->collectBytes($data,$this->tor_info, $this->peer['left'],$this->ctracker);
			
			if ($this->ctracker['peercaching']) { 
				$this->Torrent->sendRandomPeers($this->info_hash,$this->ctracker);
			} else {
				$peers = $this->Torrent->getRandomPeers($this->tor_info,$this->ctracker);
				$this->Torrent->sendPeerList($peers,$this->ctracker);
			}

			break;
		default:		// not valid event
			$this->Torrent->showError("Invalid event from client.");
		} // Close Switch
	}

	function __newpeerstart($tor_info,$peer) {
		$this->tor_info = $tor_info;
		$this->peer = $peer;
		// If client using a Proxy or Forwarder of some sort?
		if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
			foreach(explode(",",$_SERVER["HTTP_X_FORWARDED_FOR"]) as $address) {
				$addr = ip2long(trim($address));
				if ($addr != -1) {
					if ($addr >= -1062731776 && $addr <= -1062666241) {	}		// 192.168.x.x 
					else if ($addr >= -1442971648 && $addr <= -1442906113) { }	// 169.254.x.x
					else if ($addr >= 167772160 && $addr <= 184549375) { }		// 10.x.x.x
					else if ($addr >= 2130706432 && $addr <= 2147483647) { }	// 127.0.0.1
					else if ($addr >= -1408237568 && $addr <= -1407188993) { }	// 172.[16-31].x.x
					else {		// Finally, we can accept it as a "real" ip address.
						$this->peer['ip'] = mysql_escape_string(trim($address));
						break;
					}
				}
			}
		}
		// Just in case client is passing IP option
		if (isset($_GET["ip"]) && $this->ctracker['ip_override']) {
			// compact check: valid IP address:
			if (ip2long($_GET["ip"]) == -1) {
				$this->Torrent->showError("Invalid IP address. Must be standard dotted decimal (hostnames not allowed)"); 
			}
			$this->peer['ip'] = mysql_escape_string($_GET["ip"]);
		}
		// Peer Status
		if ($this->peer['left'] == 0) { 
			$this->peer['status'] = 'seeder'; 
		} else { 
			$this->peer['status'] = 'leecher'; 
		}
		
		if ($this->Torrent->isFireWalled($this->tor_info['info_hash'],$this->peer['peer_id'],$this->peer['ip'],$this->peer['port'], $this->ctracker)) {
			$this->peer['natuser'] = 'Y';
		} else {
			$this->peer['natuser'] = 'N'; 
		}
			
		// Check if peer is found
		$peercheck = $this->Torrent->FindPeer($this->peer,$this->tor_info);
		if (!$peercheck) { // Could not find Peer, create new peer record!
			//function __newpeerstart($info_hash, $ip, $port, $peer_id, $left) {
			$this->Torrent->addnewpeer($this->peer,$this->tor_info);
		} else {	// OOPS peer already in DB
			// Special case: duplicated peer_id. 
			// Duplicate peer_id! Check IP address
			$data = $this->Torrent->getPeerInfo($this->peer, $this->tor_info, $this->ctracker);
			print_r($this->peer);
			print_r($data);
			if ($this->peer['ip'] != $data['ip'])
			{
				// Different IP address. Assume they were disconnected and alter the IP address.
				$this->Torrent->updatePeer($this->peer,$data['id']);
			}
			$this->Torrent->showError("Tracker/database error. Seems Peer is already in DB.");
		}

		$this->ctracker['trackerid'] = mysql_insert_id();
		if ($this->ctracker['peercaching']) {
			$compact = mysql_escape_string(pack('Nn', ip2long($this->peer['ip']), $this->peer['port']));
			$peerid = mysql_escape_string('2:ip' . strlen($this->peer['ip']) . ':' . $this->peer['ip'] . '7:peer id20:' . $this->Torrent->__hex2bin($this->peer['peer_id']) . "4:porti{".$this->peer['port']."}e");
			$no_peerid = mysql_escape_string('2:ip' . strlen($this->peer['ip']) . ':' . $this->peer['ip'] . "4:porti{".$this->peer['port']."}e");
			
			//mysql_query("INSERT INTO y$info_hash SET sequence=\"{$GLOBALS["trackerid"]}\", compact=\"$compact\", with_peerid=\"$peerid\", without_peerid=\"$no_peerid\"");
			// Let's just assume success... :/
		}

		if ($this->peer['left'] == 0) {
			$this->Torrent->__summaryAdd('seeds', 1, $this->tor_info);
			//return "WHERE status=\"leecher\" AND natuser='N'";
		} else {
			$this->Torrent->__summaryAdd('leechers', 1, $this->tor_info);
			//return "WHERE natuser='N'";
		}
	}
/// End of function start

	function ReadPeerInfo() {
		// Nicely get all Peer information possible
		$this->peer['peer_id']	= $this->peer_id;
		$this->peer['port']		= $_GET["port"];
		$this->peer['ip']		= mysql_escape_string(str_replace("::ffff:", "", $_SERVER["REMOTE_ADDR"]));
		$this->peer['downloaded']= $_GET["downloaded"];
		$this->peer['uploaded']	= $_GET["uploaded"];
		$this->peer['left']		= $_GET["left"];
		if (isset($_GET["event"])) {	// Get Event (started / stopped / completed)
			$this->peer['event'] = $_GET["event"]; 
		} else { $this->peer['event'] = "";	}
		if (isset($_GET["numwant"])) {	// Number of peers requested (Optional for client)
			if ($_GET["numwant"] < $this->ctracker['maxpeers'] && $_GET["numwant"] >= 0) {
				$this->ctracker['maxpeers']=$_GET["numwant"]; 
			}
			$this->peer['numwant'] = $_GET["numwant"];
		}

		// Check what client sends
		if (!is_numeric($this->peer['port']) || !is_numeric($this->peer['downloaded']) || !is_numeric($this->peer['uploaded']) || !is_numeric($this->peer['left'])) {
			$this->Torrent->showError('Invalid numerical field(s) from client');
		}

		return $this->peer;
	}
	
	function __filterClientTorrent() {		// Function executes when dealing with Torrent Clients
		$this->ctracker = $this->Torrent->ctracker();		// Gets configuration from Model
		$this->layout = 'plain';							// Change layout to plain
		
		///// Check for unset directives
		if (!isset($this->ctracker['countbytes'])) {
			$this->ctracker['countbytes'] = true; }
		if (!isset($this->ctracker['peercaching'])) { 
			$this->ctracker['peercaching'] = false; }
	}
	
	function add() {
		## Import The Libraries
		App::import('Vendor', 'BDecode', array('file' => 'Bdecode.php'));
		App::import('Vendor', 'BEncode', array('file' => 'Bencode.php'));
		$this->ctracker = $this->Torrent->ctracker();		// Gets configuration from Model
		
        	if (!empty($this->data))
		{
				$ftypes = array('application/octet-stream','application/x-bittorrent');
				$fileOK = parent::uploadFile('torrents',$this->data['Torrent']['file'],$ftypes,true);
				if(array_key_exists('url',$fileOK) ) {
					$this->Torrent->create();
					$filedata=fread(fopen($fileOK['url'],"rb"),$this->data['Torrent']['file']['size']);
                        		$array = BDecode($filedata);
                        		if (!$array) {
                                		unlink($fileOK['url']);
                                		$this->Session->setFlash(__('There was an error handling your uploaded torrent. The parser didn&#39;t like it.', true));
                        		} else {
                                		$hash = sha1(BEncode($array['info']));
                                		//unlink($this->data['Torrent']['file']['tmp_name']);
                                		if (!$this->Torrent->TorrentInTracker($hash)) {         // Hash not in Database
                                        		$this->data['Torrent']['info_hash'] = $hash;                                                                    // Torrent hash
                                        		$this->data['Torrent']['filename']  = $array["info"]["name"];                                   // Torrent Name
                                        		$this->data['Torrent']['path'] = $fileOK['url'];                                                              // Contents File
                                        		$this->data['Torrent']['ftype']         = $this->data['Torrent']['file']['type'];       // File Type
                                        		$this->data['Torrent']['fsize']         = $this->data['Torrent']['file']['size'];       // File size
                                        		$this->data['Torrent']['url']           = $array["announce"];                                           // URL
                                        		if (isset($array["info"]["piece length"])) {    // Build the Info Field
                                             			$info = $array["info"]["piece length"] / 1024 * (strlen($array["info"]["pieces"]) / 20) /1024;
                                                		$info = round($info, 2) . " MB";
                                                		if (isset($array["comment"])) { $info .= " - ".$array["comment"]; }
                                        		}
                                        		$this->data['Torrent']['info'] = $info;
                                        		// Need a check for internal announce only 'external'
                                        		if ((strlen($hash) != 40) || !$this->Torrent->__verifyHash($hash)) {
                                                		$this->Session->setFlash(__('Error: Info hash must be exactly 40 hex bytes.', true));
                                        		}         // Check is done, store
							if ($this->Torrent->save($this->data)) {
								$this->Session->setFlash(__('The File has been saved.', true));
								$this->redirect(array('action'=>'index')); 
							} else {
								$this->Session->setFlash(__('The File could not be saved. Please, try again.', true));
							}
                                		} else { $this->Session->setFlash(__('This Torrent is already in the server.', true)); }
                        		} // Else !array
				} else {
					$this->Session->setFlash(__($fileOK['errors'], true));
				}
        	} 
		/**
		   1.  $this->data['Document']['submittedfile'] = array(
		   2. 'name' => conference_schedule.pdf
		   3. 'type' => application/pdf
		   4. 'tmp_name' => C:/WINDOWS/TEMP/php1EE.tmp
		   5. 'error' => 0
		   6. 'size' => 41737
		   7. );    */
    } // Close Add funtion
}
?>
