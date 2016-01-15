<?php
/**
 * Craigs List Tools
 */

@set_magic_quotes_runtime( 0 );

// checks for configuration file, if none found make comment and exit
require_once('config.php');
if ( !$CONFIG['dir']->root ) {
	print "No configuration file included.";
	exit();
}

#print $CONFIG['dir']->phplibs .'/facebook-platform/php/facebook.php<br>';
#require_once($CONFIG['dir']->phplibs .'/facebook-platform/php/facebook.php');
#$appapikey = 'd8520a0693d090e98286086ffd49f76a';
#$appsecret = '681174282fd1e16189127346e7764726';
#$facebook = new Facebook($appapikey, $appsecret);
#$uid = $facebook->require_login();

// get session
require_once( $CONFIG['dir']->includes .'/session.php' );

// get database object
require_once( $CONFIG['dir']->includes .'/database.php' );
$database = new database( $CONFIG['db']->host, $CONFIG['db']->user, $CONFIG['db']->pass, $CONFIG['db']->name, null );
//$database->debug( $CONFIG['site']->debug );

// check if user is logging in
if ( $_REQUEST['login'] ) {
	// check username and password
	$user = new stdClass();
	$sql = "SELECT *
			FROM Users
			WHERE username='". $_REQUEST['username'] ."'
				AND password='". $_REQUEST['password'] ."'";
	$database->setQuery($sql);
	if ( $database->loadObject($user) ) {
		// successful login
		$GLOBALS['user'] = $user;
		
		// get preferences
		$GLOBALS['preferences']->regions = textarea_to_array( $user->regions );
		$GLOBALS['preferences']->sites = textarea_to_array( $user->sites );
		
		setSessionVar('uname', $user->username);
		
	} else {
		$err = 'Login failed. Please try again.';
	}
} else if ( isset($_REQUEST['logout']) ) {
	// logout user
	unset($GLOBALS['user']);
	unset($GLOBALS['preferences']);
	delSessionVar('uname');
	
	// redirect to clear the logout in QUERY STRING
	header('Location:'. $_SERVER['PHP_SELF']);
	exit;
	
} else if ( getSessionVar('uname') ) {
	// check username and password
	$user = new stdClass();
	$sql = "SELECT *
			FROM Users
			WHERE username='". getSessionVar('uname') ."'";
	$database->setQuery($sql);
	if ( $database->loadObject($user) ) {
		// successful login
		$GLOBALS['user'] = $user;
		
		// get preferences
		$GLOBALS['preferences']->regions = textarea_to_array( $user->regions );
		$GLOBALS['preferences']->sites = textarea_to_array( $user->sites );
	}
}

// preload regions
$sql = "SELECT * FROM Regions";
$database->setQuery($sql);
$GLOBALS['regions'] = $database->loadObjectList('id');

// preload sites
$sql = "SELECT * FROM Sites WHERE active IS NOT null";
$database->setQuery($sql);
$GLOBALS['sites'] = $database->loadObjectList('url');
$GLOBALS['site_ids'] = $database->loadObjectList('id');

// preload sub-sites
$sql = "SELECT * FROM SubSites WHERE active IS NOT null";
$database->setQuery($sql);
$GLOBALS['subsites'] = $database->loadObjectList('path');
$GLOBALS['subsite_ids'] = $database->loadObjectList('id');

// preload categories
$sql = "SELECT * FROM Categories";
$database->setQuery($sql);
$GLOBALS['categories'] = $database->loadObjectList('path');
$GLOBALS['category_ids'] = $database->loadObjectList('id');

//prePrint($sites);
//prePrint($subsites);
//prePrint($categories);

/**
 * Returns HTML for formatted tip rollover
 *
 * @param string $title Title in caption header
 * @param array $args List of data
 * @return string
 */
function createTipTable ($title, $args)
{
	// start table with title
	$html = "<table class=tip_table width=100%>".
			"<caption class=tip_table>". addslashes($title) ."</caption>";
	
	// loop through arguments
	foreach ($args as $key => $val) {
		// add row
		$html .= "<tr><td id=desc class=tip_key valign=top>". addslashes($key) .":</td><td class=tip_value valign=top>". addslashes($val) ."</td></tr>";
	}
	
	// complete table
	$html .= "</table>";
	
	return $html;
}

/**
 * Displays error message with proper formatting
 *
 * @param string $mesg - error to display [optional] default = session's error
 * @return void
 */
function printError ($mesg=null)
{
	// check if we have a warning
	if ( empty($mesg) ) {
		// get from session variable if one exists
		$mesg = getSessionVar('error');
	}
	// make sure we have a warning
	if ( !empty($mesg) ) {
		// show warning
		print "<div style='color:red;'><b>Error!</b> ";
		
		// check if message is an array
		if ( is_array($mesg) ) {
			foreach ($mesg as $m) {
				print "<li> $m</li>";
			}
		} else {
			print $mesg;
		}
		print "</div>";
	}
	// delete session variable now
	delSessionVar('error');
}

/**
 * Get latest posts from specified database
 * 
 * @param string $url URL for database query
 * @param integer $max Maximum posts to return
 * @return array
 */
function getCLResults ($url, $max=null)
{	
	// set URLs
	#$url = 'http://sfbay.craigslist.org/sss/';

	// get file content
	$lines = file($url);
	
	// check if we have lines
	if ( is_array($lines) ) {
		
		// loop through line items
		foreach ($lines as $line) {
			
			// check if we've reached our max count
			if ($max && count($posts) >= $max) {
				// we've gotten all the posts we've requested, so stop now
				break;
			}
			
			// check if this is the site/region/category line
			//  <h3><a href="/">s.f. bayarea craigslist</a>  &gt; <a href="/sss/">for sale / wanted</a></h3></td>
//			if ( preg_match("/<h3>/", $line) && 
//					preg_match_all("/<a href=\"([^\"]*)\">([^<]+)<\/a>/", $line, $matches) ) {
//				
//				// loop through matches
////				for ($x=0; $x<count($matches[1]); $x++) {
////					print "site: <b>". $matches[2][$x] ."</b> => ". $matches[1][$x] ."<BR>";
////				}
//			}
			// check if this is a listing
			// <p><a href="http://sfbay.craigslist.org/nby/car/241596786.html">2003 Car - $900</a><font size="-1"> (santa rosa)</font><span style="color: orange;"> pic</span>
//			else if ( preg_match("/^<p><a href=\"([^<]+)\">([^<]+)<\/a>(.*)/", $line, $matches) ) {
			if ( preg_match("/^<p><a href=\"([^<]+)\">([^<]+)<\/a>(.*)/", $line, $matches) ) {
				
				// create object of post
				$post = new stdClass();
				
				// get url
				$post->url = trim($matches[1]);
				
				// extract price from title
				list($title, $price) = explode(' - $', $matches[2]);
				$post->title = trim($title);
				$post->price = trim($price);
				
				// try to extract the pic notification
				// <span style="color:orange;"> pic</span>
//				preg_match("/<span style=\"color:orange;\"> pic<\/span>/", $matches[3], $m);
				if ( preg_match("/ pic<\/span>/", $matches[3]) ) {
					$post->pic = true;
				}
				
				// try to extract the image notification
				// <span style="color: orange;"> img</span>
//				preg_match("/<span style=\"color: orange;\"> img<\/span>/", $matches[3], $m);
				if ( preg_match("/ img<\/span>/", $matches[3]) ) {
					$post->img = true;
				}
				
				// get contents of post
				$post = getCLPost( $post );
				
				// add post object to array
				$posts[] = $post;
			}
		}
	}
	return $posts;
}

/**
 * Get post details from the referenced post object's url
 *
 * @param object $post Post object including url, title, price and img flag
 * @return object
 */
//function getCLPost ($url)
function getCLPost ($post)
{
	global $database, $sites, $subsites, $categories;
	$body_tag = false;
	$read_details = false;
	$read_blurbs = false;
	$read_date_posted = false;
	
	// check we have a url to follow
	if ( $post->url ) {
		
		// get post's content
		$lines = file( $post->url );
		
		// check we have the content
		if ( is_array($lines) ) {
			
			// loop through lines (of post)
			foreach ($lines as $line) {
				
				// check if we have the body tag yet
				if ( $body_tag === false ) {
									
					// check if this the <body> tag
					if ( preg_match("/<body>/", $line) ) {
					
						// record we've got it
						$body_tag = true;
					}
					// now just goto the next line
					continue;
				}
				// check if we have the site info yet which comes directly after the <body> tag line
				else if ( empty($post->site_id) ) {
					
					// zip to pictures once we hit the "It is not ok to contact this poster" line"
					// <ul style="margin-left:0px; padding-left:3px; list-style:none; font-size: smaller">
					
					// the next line after <body> should be it
					// <a href="http://sfbay.craigslist.org">s.f. bayarea craigslist</a> &gt; <a href="/eby/">east bay</a> &gt;  <a href="/eby/msg/">musical instruments</a> &gt; Clavinova CLP 550
					if ( preg_match_all("/<a href=\"([^\"]*)\">([^<]+)<\/a>/", $line, $matches) ) {

						// create objects
						$site = new stdClass();
						$subsite = new stdClass();
						$category = new stdClass();
						
						// define site info
						$site_name = str_replace(" craigslist", null, $matches[2][0]);
						$site->url = $matches[1][0];
						$site->name = $site_name;
						$site->id = $sites[ $site->url ]->id;
						
						// check if there's a regional site
						if ( count($matches[1]) > 2 ) {
							
							// define subsite info
							$subsite->path = $matches[1][1];
							$subsite->name = $matches[2][1];
							$subsite->id = $subsites[ $subsite->path ]->id;
						}
							
						// define category info
						$category->path = str_replace("$subsite->path", null, $matches[1][2]);
						$category->name = $matches[2][2];
						$category->id = $categories[ $category->path ]->id;
						
						// add to content object
//						$post->site = $site;
//						$post->subsite = $subsite;
//						$post->category = $category;
						
						$post->site_id = $site->id;
						$post->subsite_id = $subsite->id;
						$post->category_id = $category->id;
					}
					// continue to next line
					continue;
				}
				
				// check if we have the city yet
				if ( empty($post->city) ) {
					
					// first check if this has been flagged down by users
					// <h2>Posting <a href="http://www.craigslist.org/about/help/flags_and_community_moderation">flagged</a> down by craigslist users</h2>
					if ( preg_match("/<h2>Posting <a href=(.+)>flagged<\/a> down by craigslist users<\/h2>/", $line) ) {
						// specify flagging
						$post->flagged = true;
						
						// lets stop worrying about this post since it will be gone soon anyway
						break;
					}
					
					// check if this is the title line <h2>
					if ( preg_match("/^<h2>(.+)\((.*)\)<\/h2>/", $line, $matches) ) {
					
						// get the city/area
						$post->city = $matches[2];
					}
					// Its possible that we wont have a city
					# Should we use a $no_city_found boolean to illiminate going through this loop again?
				}
				
				// check if we have REPLY-TO yet
				if ( empty($post->replyto) ) {
					
					// check if this is the REPLY-TO link
					// Reply to: <a href="mailto:sale-253966580@craigslist.org?subject=Arm%20chair%20$75%20%28menlo%20park%29">sale-253966580@craigslist.org</a><br>
					if ( preg_match("/Reply to: <a href=\"mailto:([^\"]+)\">([^<]+)<\/a><br>/", $line, $matches) ) {
						
						// get reply too link, converting the hex values into characters
//						$post->replyto = preg_replace('~%([0-9a-f])([0-9a-f])~ei', 'chr(hexdec("\\1\\2"))', $matches[2]);
						preg_match_all("/&#(\d+)/", $matches[2], $hexs);
						foreach ($hexs[1] as $h) {
							$post->replyto .= chr($h);
						}
						
						// time to read date posted
						$read_date_posted = true;
					}
				}
				
				// check if we have the date yet
				if ( $read_date_posted === true ) {
					
					// check if this is the date
					// Date: 2006-12-27,  7:29AM PST<br>
					if ( preg_match("/Date: (.+),  (.+)<br>/", $line, $matches) ) {
						
						// get date
						$post->date_posted = date("Y-m-d H:i:s", strtotime($matches[1] .' '. $matches[2]));
						
						// done reading date posted
						$read_date_posted = false;
						
						// remember we're now reading details
						$read_details = true;
					}
				}
				
				// check if we're reading details
				if ( $read_details === true ) {
					
					// check if we're at the end of the details, next will be images,
					// this line will contain the starting of the images table
					if ( preg_match("/<table summary=\"craigslist hosted images\">/", $line) ) {
						// we need to stop reading in the details
						$read_details = false;
						
						// remove this portion of the starting of the table holding attached images
						$line = str_replace("<table summary=\"craigslist hosted images\">", null, $line);
						
						// time to read in the attached images
						$read_images = true;
					}
					
					// check if we're at the end of details
					// Options afterwards:
					//   <li> This item has been posted by-dealer.<br>
					//   <li> This item has been posted by-owner.<br>
					//   <li> Location:  San Luis Obispo<br>
					//   <li> It's NOT ok to contact this poster with services or other commercial interests<br>
//					if ( preg_match("/^<ul style=\"margin-left: 0px; padding-left: 3px; list-style-type: none; list-style-image: none; list-style-position: outside; font-size: smaller;\">/", $line) ) {
//					if ( preg_match("/^<ul style=\"margin/", $line) ) {
						// stop reading details
//						unset($read_details);
//						$read_blurbs = true;
						
//					} else {
					// get content
					$post->details .= $line;
					
					// extract any embedded images
					if ( preg_match("/<img [.*]src=\"([^\"]+)\"/i", $line, $matches) ) {
						// add images to array
						$images = ($post->images_emb ? $post->images : array());
						array_push($images, $matches[1]);
						$post->images_emb = $images;
					}
//					}
				}
				
				// check if there's an image in the line
				if ( $read_images ) {
					
					// 	<tr><td align=center><img src="http://d.im.craigslist.org/AB/U6/FUTIOQmdNT3lC4KuPTCdfcrnr0T9.jpg" alt=""></td>
					if ( preg_match("/<img src=\"([^\"]+)\"/i", $line, $matches) ) {
						// add images to array
						$images = ($post->images ? $post->images : array());
						array_push($images, $matches[1]);
						$post->images = $images;
					}
					
					// check if we see <ul class="blurbs"> now
					if ( preg_match("/<ul class=\"blurbs\">/", $line) ) {
						// stop reading images
						$read_images = false;
						
						// star reading blurbs
						$read_blurbs = true;
					}
				}
				
				// check if we're reading the options (found after details)
				if ( $read_blurbs ) {
					
					// check if dealer
					if ( preg_match("/This item has been posted by-dealer/", $line) ) {
						// a dealer posted this item
						$post->dealer = 'on';
					} 
					else if ( preg_match("/Location: (.+)<br>/", $line, $matches) ) {
						// this is a specified location, not sure where this gets set though, perhaps user's profile?
						$post->location = $matches[1];
					} 
					else if ( !preg_match("/It\'s NOT ok to contact this poster with services or other commercial interests/", $line) ) {
						// OK to contact them
						$post->ok_to_contact = 'on';
					} 
					else if ( preg_match("/Posting ID: (\d+)/", $line, $matches) ) {
						// Posting ID: 259607102
						$post->posting_id = $matches[1];
						
						// all done!
						$read_blurbs = false;
						break;
					}
				}
			} // end of foreach loop (lines of post)
		} else {
			
			// remove from database since the file does not exist now
			print "<b>FILE NOT FOUND:</b> $post->url <br> $post->title <br>";
			$database->deleteObject('Posts', $post, 'id');
			if ( $database->getErrorNum() ) {
				print "Problem deleting post: ". $database->getErrorMsg();
			} else {
				print "Deleted!";
			}
			print "<p>";
		}
	}
	
	// convert image arrays to textarea fields
	$post->images = array_to_textarea( $post->images );
	$post->images_emb = array_to_textarea( $post->images_emb );
	
	// return the post
	return $post;
}

/**
 * Displays array within PRE tags
 *
 * @param array $mesg - message(s) to display within PRE tags
 * @return void
 */
function prePrint ($mesg)
{
	if ( is_array($mesg) or is_object($mesg) ) {
		print "<pre>";
		print_r($mesg);
		print "</pre>";
	} else {
		print $mesg;
	}
}
	
/**
 * Converts textarea into an array of lines; ie: used for attributes values
 * 
 * @param string $lines - multi-line string such as with a textarea
 * @return array
 */
function textarea_to_array ($lines)
{
	if ( is_array($lines) ) {
		// they sent us an array, probably already had this method called on it
		return $lines;
	}
	// create empty array
	$trimmed_lines = array();
	
	// check if there are lines so we don't create an array with one empty value
	if ($lines) {
		// single string, needs to be made an array of each line
		if ( explode("\n", $lines) ) {
			$lines = explode("\n", $lines);
		} else {
			$lines = array($lines);
		}
		
		// loop through the results so we can trim the new line off
		foreach ($lines as $line) {
			// trim the trailing new line
			$trimmed_lines[] = trim($line);
		}
	}
	// return the trimmed lines
	return $trimmed_lines;
}

/**
 * Converts array of lines into a single string with line breaks for a textarea
 * Ie: used for attributes values
 *
 * @param array $lines - list of strings to convert
 * @return string
 */
function array_to_textarea ($lines)
{
	if ( !is_array($lines) ) {
		// they sent us an array, probably already had this method called on it
		return $lines;
	}
	// check if any values are empty (we don't take kindly to dem kinds)
	if (in_array('', $lines)) {
		// create a temporary array and clear the original
		$old_lines = $lines;
		$lines = array();
		
		// loop through the array to remove the empty values
		foreach ($old_lines as $line) {
			// trim the trailing new line
			$lines[] = trim($line);
		}
	}
	// array, needs to be made a single string with line breaks between each value
	$lines = join("\n", $lines);
	
	// return the trimmed lines
	return $lines;
}

/**
 * Case-insensitive version of in_array
 * 
 * @param string $str - needle to search for
 * @param array $arry - haystack to search in
 * @return boolean
 */
function is_in_array ($str, $arry)
{
	if ( is_array($arry) ) {
		return preg_grep('/^' . preg_quote($str, '/') . '$/i', $arry);
	} else {
		printWarning("is_in_array('$str', \$array): Array expected but not received.");
	}
}

/**
 * Shortens a string to a specific length, adding ... to the end if it was indeeded shortened
 * @param string $stng - The text string original length
 * @param integer $length - The length of string to munge to
 * @return string
 */
function mungeString ($stng, $length)
{
	return (strlen($stng) > $length ? substr($stng, 0, $length) .'...' : $stng);
}

/**
 * Creates HTML code for a menu select box, mulit select is defined by the size parameter
 * 
 * @param string $name - name of the select box field
 * @param array $values - values in a hashtable form of array; option value=displayed text
 * @param integer $size - number of options to display [default is 1 making it single select]
 * @param string $first - the text to display for the first empty option [default is nothing]
 * @param string $js - javascript functions to perform [default is nothing]
 * @param string $style - CSS style or class definition [default is nothing]
 * @return string
 */
function createHTMLMenu ($name, $values, $size=1, $first=null, $js=null, $style=null)
{
	// make sure there are values to display in the select box, don't want it empty
	if (is_array($values) and (count($values) > 0)) {
		
		// check if the field was in last query
		if ( strlen($_REQUEST[$name]) ) {
			// lets maintain its value
			$selected = $_REQUEST[$name];
		}

		// lets start creating the select box
		// set style w/ monospaced font so we can do formatting as well
		$html = "<select style='$style' ";
		if ($size > 1) {
			// size is larger than one so make this a multi select box, make sure we
			// add the array brackets to the field name
			$html .= "name='". $name ."[]' multiple='yes' size='$size'";
		} else {
			// single select box
			$html .= "name='$name' ";
		}
		$html .= " $js>\n";
		
		// check if we're supposed to have a first value (space for empty, null for none)
		if ( !is_null($first) ) {
			$html .= "<option value='' ";

			// check if there isn't a value, then preselect ALL
			if (!strlen($selected) or ($selected == array(''))) {
				$html .= "SELECTED";
			}
		
			$html .= " style='color:silver; font-style:italic;'>$first</option>\n";
		}

		// loop through the values hash so we can maintain the selected options
		foreach ($values as $vk => $value) {
			// clear line style
			unset($line_style);
			
			// get trimmed value
			$val = trim($value);
			
			// make sure this isn't an empty value
			if ($val) {
				$html .= "<option value='". htmlentities($vk, ENT_QUOTES) ."' ";
				
				// add style to option
				$html .= " style='$line_style;' ";
				
				// check if this value was previously selected
				if ( (is_array($selected) and in_array($vk, $selected)) or ($vk == $selected && strlen($selected))) {
					$html .= "SELECTED";
				}

				// lets change the val to not include any SORTing characters (ie: disposition)
				$val = preg_replace("/^[0-9]*\|/", '', $val);
				$html .= ">$val</option>\n";
			}
		}

		// finish it up
		$html .= "</select>\n";

		// ship it on its way
		return $html;
	} else {
		return "<span style='font-style:italic; color:silver;'>empty</span>";
	}
}
