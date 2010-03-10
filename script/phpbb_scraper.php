<?php
// *******************************************************************
// *******************************************************************
// **                                                               **
// ** phpBB Scraper Script                                          **
// ** Version 1.0                                                   **
// ** 10th March 2010                                               **
// **                                                               **
// *******************************************************************
// ** Features:                                                     **
// ** - Logs in to the forum, so no unaccessable threads            **
// ** - Iterates through all threads within a given range           **
// **   - Will skip missing/deleted threads automatically           **
// ** - Generates an XML file for each thread, including:           **
// **   - Thread Topic                                              **
// **   - Post Author                                               **
// **   - Post Date/Time                                            **
// **   - Details of any externally linked images                   **
// **   - Details of any quoted posts, including content            **
// **                                                               **
// *******************************************************************
// **                                                               **
// ** (c) 2010 Graham Thomson (graham.thomson@gmail.com)            **
// ** Released under the GNU General Public License (GPL) version 3 **
// ** - See COPYING                                                 **
// **                                                               **
// ** Thanks to:                                                    **
// ** - JayJay (PreludeUK) for testing, suggestions                 **
// **                                                               **
// *******************************************************************

// ************************************************************
// ** Edit the following details to suit                     **
// ************************************************************
// Username and Forum details
$username       = "username";
$password       = "password";
$url            = "http://preludeuk.forumup.com";
// this is the name of the folder to save the files
$projectname    = "preludeuk";
// Select the topics you wish to grab
$start_topic    = 1;
$end_topic      = 10000;

// ************************************************************
// ************************************************************
// *                                                          *
// *  BEYOND THIS LIES THE MAIN CODE, EDIT AT YOUR OWN RISK!  *
// *                                                          *
// ************************************************************
// ************************************************************
// ------------------------------------------------------------
// This part logs you in to the forum!
// ------------------------------------------------------------
// Set login URL
$login_url = $url . "/login.php";
// new curl object
$ch = curl_init();
// set Curl Options
curl_setopt($ch, CURLOPT_URL, $login_url);
curl_setopt($ch, CURLOPT_POST, 1);
$post_fields = array(
  'username'  => $username,
  'password'  => $password,
  'autologin' => 0,
  'redirect'  => 'index.php',
  'login'     => 'Log In',
);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_COOKIEJAR, "cookie.txt");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// execute curl,fetch the result and close curl connection
$result = curl_exec($ch);
// check for errors - if we can't login, exit!
if (curl_errno($ch)){
	die (curl_errno($ch));
}

// ------------------------------------------------------------
// This part grabs the topics, posts, and images
// ------------------------------------------------------------

// Set topic and page
$topic = $start_topic;
$page = 1;
$topic_content = "";

// topic check, stops us printing the title each time round the loop
$topic_check = 0;

// Create a directory to hold the xml files
// sanitise the project name to do so
$foldername = preg_replace("/[^A-Za-z0-9]/","",strtolower(trim($projectname)));
@mkdir($foldername);

// outer loop
while ($topic <= $end_topic) {

	// set whether or not we wish to grab any data
	$good_to_go = false;

	// Calculate offset (Forum pages show 15 posts at a time)
	$offset = 15*($page - 1);

	// Create the url
	$url_to_try = $url . '/viewtopic.php?t=' . $topic . '&start=' . $offset;
	
	// Set the URL of the page
	curl_setopt($ch, CURLOPT_URL, $url_to_try);

	// we want to check what we get back
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	// grab the reply
	$result = curl_exec($ch);

	// Check if we have an error message meaning no topic found
	if (strstr($result, "No posts exist for this topic") !== false) {
		// If we have something worth writing
		if (strlen($topic_content) > 0) {
			// close the XML tags
			$topic_content .= "</thread>\n";
			// We have dropped off the end of the topic, save what we have
			$file_name = $foldername . "/topic_" . str_pad($topic,6,"0",STR_PAD_LEFT) . "_" . preg_replace("/[^A-Za-z0-9_]/","",strtolower(trim(substr(str_replace(" ", "_", $current_title),0,30)))) . ".xml";
			$fh = fopen($file_name, 'w') or die("ERROR: Can't open file: " . $file_name);
			fwrite($fh, $topic_content);
			fclose($fh);
		} 
		// Topic is missing, increment the counter and reset variables
		$good_to_go = false;
		$topic_found = false;
		$topic++;
		$page = 1;
		$topic_content = "";
		$current_title = "";
	} else {
		// The below means we are too many pages into the topic - we've dropped off the end, time to find a new topic!
		if (strstr($result, "The topic or post you requested does not exist") !== false) {
			// If we have something worth writing
			if (strlen($topic_content) > 0) {
				// close xml tags
				$topic_content .= "</thread>\n";
				// We have dropped off the end of the topic, save what we have
				$file_name = $foldername . "/topic_" . str_pad($topic,6,"0",STR_PAD_LEFT) . "_" . preg_replace("/[^A-Za-z0-9_]/","",strtolower(trim(substr(str_replace(" ", "_", $current_title),0,30)))) . ".xml";
				$fh = fopen($file_name, 'w') or die("ERROR: Can't open file: " . $file_name);
				fwrite($fh, $topic_content);
				fclose($fh);
			}
			// Now increment the topic and reset the variables
			$topic_content = "";
			$current_title = "";
			$topic++;
			$page = 1;
		} else {
			if ($topic_check != $topic) {
				// New topic, so we need to change this to match
				$topic_check = $topic;
			}
			// If we are here, we *do* want to harvest data
			$good_to_go = true;	
		}
	}
	
	// if we have a valid page hit, harvest the details!
	if ($good_to_go) {
		// Clean up returned html
		$result = @mb_convert_encoding($result, 'HTML-ENTITIES', 'utf-8');
		// New DOM document
		$dom = new DOMDocument();
		@$dom->loadHTML($result);	
		// grab page 'nodes' (tags basically)
		$nodes = $dom->getElementsByTagName('*');
		// now loop through the tags and grab name and post content
		foreach($nodes as $node) {
			//Get Topic
			if($node->nodeName == 'a' && $node->getAttribute('class') == 'maintitle' && !$topic_found) {
				$topic_content .= "<?xml version='1.0' encoding='UTF-8' ?>\n";
				$topic_content .= "<thread>\n";
				$topic_content .= "\t<database_id>" . $topic . "</database_id>\n";
				$topic_content .= "\t<title>" . htmlentities($node->nodeValue, ENT_COMPAT, "UTF-8") . "</title>\n"; 
				$topic_found = true;
				$current_title = $node->nodeValue;
			}
			// Get Post Owner
			if($node->nodeName == 'span' && $node->getAttribute('class') == 'name') {
				$topic_content .= "\t<post>\n";
				$topic_content .= "\t\t<author>" . htmlentities($node->nodeValue, ENT_COMPAT, "UTF-8") . "</author>\n";
			}
			// Get Post time/date
			if($node->nodeName == 'span' && $node->getAttribute('class') == 'postdetails' && (strstr($node->nodeValue, "Posted:") !== false)) {
				// We need to strip the content down to grab just the time/date
				$stripstring = trim(substr($node->nodeValue,8,27));
				$stripstring = htmlentities($stripstring);
				$stripstring = str_replace(array("&Acirc;","&nbsp;"), "", $stripstring);
				$topic_content .= "\t\t<time>" . $stripstring . "</time>\n";
				$topic_content .= "\t\t<message>\n";
			}
			// Get Post Content
			if($node->nodeName == 'div' && $node->getAttribute('id') == 'word2click') {
				// Check if we have extra data in the post
				if ($node->hasChildNodes()) {
					$inodes = $node->childNodes;
					foreach($inodes as $inode) {
						// If we have an image - check they are external, and not smilies
						if($inode->nodeName == 'img' && strstr($inode->getAttribute('src'), "http") !== false) {
							$topic_content .= "\t\t\t<linkedimage>" . htmlentities($inode->getAttribute('src'), ENT_COMPAT, "UTF-8") . "</linkedimage>\n";
						}
						// If we have a table, it should contain a quote
						if($inode->nodeName == 'table' && $inode->hasChildNodes()) {
							// recurse to grab quotes
							$topic_content .= print_quotes($inode, 1);
							// remove recursed content
							remove_children($inode);
						}
					}
				}
				// Remove any children and print main message
				$topic_content .= preg_replace('/(\r\n|\r|\n)/s',"\n",htmlentities($node->nodeValue, ENT_COMPAT, "UTF-8")) . "\n";
				// Close content
				$topic_content .= "\t\t</message>\n";
				$topic_content .= "\t</post>\n";
			}
		}

		// Update page count and back round the loop
		$page++;
	}
}

// Close cURL session
curl_close($ch);

// *****************************************************************
// Functions Required for the Script
// *****************************************************************

// This recurses and prints all quotes
function print_quotes($node, $depth) {
	// Increase level as we go deeper
	$depth++;
	// default tab level
	$quotetab  = "\t\t";
	$detailtab = "\t\t\t";
	// set tab indentation based on level
	for($i=1; $i < $depth; $i++) {
		$quotetab  .= "\t";
		$detailtab .= "\t";
	}
	// now start again
	$inodes = $node->childNodes;
	foreach($inodes as $inode) {
		// Drill down to tr
		if ($inode->nodeName == 'tr' && $inode->hasChildNodes()) {
			$iinodes = $inode->childNodes;
			foreach($iinodes as $iinode) {
				// Drill down to td
				if ($iinode->nodeName == 'td' && $iinode->hasChildNodes()) {
					$iiinodes = $iinode->childNodes;
					foreach($iiinodes as $iiinode) {
						// And now catch any quotes
						if($iiinode->nodeName == 'span' && $iiinode->getAttribute('class') == 'genmed') {
							// We have found a quote
							// Get the author
							$topic_content .= $quotetab  . "<quote>\n";
							$topic_content .= $detailtab . "<author>" . htmlentities(str_replace(" wrote:", "", $iiinode->nodeValue), ENT_COMPAT, "UTF-8") . "</author>\n";
							$topic_content .= $detailtab . "<message>\n";
						}
					}
				}
				if($iinode->nodeName == 'td' && $iinode->getAttribute('class') == 'quote') {
					// Check for nested quotes
					if ($iinode->hasChildNodes()) {
						$iiinodes = $iinode->childNodes;
						foreach($iiinodes as $iiinode) {
							if($iiinode->nodeName == 'table' && $iiinode->hasChildNodes()) {
								// Recurse!
								$topic_content .= print_quotes($iiinode, $depth);
								// remove recursed content
								remove_children($iiinode);
							}
						}
					}
					// Get content
					$topic_content .= preg_replace('/(\r\n|\r|\n)/s',"\n",htmlentities($iinode->nodeValue, ENT_COMPAT, "UTF-8")) . "\n";
					$topic_content .= $detailtab . "</message>\n";
					$topic_content .= $quotetab  . "</quote>\n";
				}
			}
		}
	}
	// Return all the recursing goodness
	return $topic_content;
}

// This removes all 'childnodes' - required to give us clean message
// data. Required as we are splitting quotes out
function remove_children(&$node) {
	while ($node->firstChild) {
		while ($node->firstChild->firstChild) {
			remove_children($node->firstChild);
		}
		$node->removeChild($node->firstChild);
	}
}
?>
