<?php

class AppController extends Controller {

	var $pageTitle = 'Torrent Tracker';
	var $helpers = array('Session', 'Html', 'Form');
	
	function uploadFile($Ctrl,$file,$types,$typeOK = false ) {
		$uploads = 'uploads/';
		$folder = $Ctrl;
		$ab_folder = WWW_ROOT.$uploads.$folder;
		$rel_url = $uploads.$folder;
		// create the folder if it does not exist  
		if(!is_dir($ab_folder)) { // check WWW_root/controller
			if(!is_dir(WWW_ROOT.$uploads)) {
				mkdir(WWW_ROOT.$uploads); }	// Create dir if not there
			mkdir($ab_folder); }
		/**
		1.  $this->data['Document']['submittedfile'] = array(
		2. 'name' => conference_schedule.pdf
		3. 'type' => application/pdf
		4. 'tmp_name' => C:/WINDOWS/TEMP/php1EE.tmp
		5. 'error' => 0
		6. 'size' => 41737
		7. );    */	
		
		// Replace spaces with underscores	
		$filename = str_replace(' ', '_', $file['name']);
		// check filetype is ok
		if ($types != null) {
			foreach($types as $type) {  
				if($type == $file['type']) { $typeOK = true;  
					break; }  
			}
		}
		if($typeOK) {
			// Switch based on Error code
			switch($file['error']) {
				case 0:
					// check filename already exists
					if(!file_exists($ab_folder.'/'.$filename)) {
						// create full filename
						$full_url = $ab_folder.'/'.$filename;
						$url = $rel_url.'/'.$filename;
						// upload the file
						$success = move_uploaded_file($file['tmp_name'], $url);
					} else {
						// create unique filename and upload file
						ini_set('date.timezone', 'Europe/London');
						$now = date('Y-m-d-His');
						$full_url = $ab_folder.'/'.$now.$filename;
						$url = $rel_url.'/'.$now.$filename;
						$success = move_uploaded_file($file['tmp_name'], $url);
					}
					// if upload was successful
					if($success) {
						// save the url of the file
						$result['url'] = $url;
					} else { 
						$result['errors'] = "Error uploaded $filename. Please try again.";
					}
					break;
				case 3:
					// an error occured
					$result['errors'] = "Error uploading $filename. Please try again.";
					break;
				default:
					// an error occured
					$result['errors'] = "System error uploading $filename. Contact webmaster.";
					break;
			}
		} elseif($file['error'] == 4) {		// no file was selected for upload
			$result['errors'] = "No file Selected";
		} else {	// unacceptable file type
			$result['errors'] = "$filename cannot be uploaded. Acceptable file types: gif, jpg, png.";
		}	
		return $result;
	}

}
?>
