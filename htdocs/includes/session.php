<?php
/**
 * Session initiation for intranet
 * 
 * @version $Id: session.php,v 1.5 2005/12/20 20:09:21 gconklin Exp $
 * @package Intranet
 * @author Grant Conklin
 * @copyright 2005 Entrisphere
 */

startSession();

/**
 * Initialize session
 * 
 * @return boolean
 */
function startSession()
{
	global $CONFIG;

	// Stop adding SID to URLs
	ini_set('session.use_trans_sid', 0);

	// User-defined save handler
	ini_set('session.serialize_handler', 'php');

	// Use cookie to store the session ID
	ini_set('session.use_cookies', 1);
	ini_set('session.use_only_cookies', 1);
	ini_set('session.cookie_path', $CONFIG['path']->root);
	ini_set('session.cookie_lifetime', 0);

	// Name of our cookie
	ini_set('session.name', 'INTRANETSID');

	ini_set('display_errors', 'on');
	ini_set('output_buffering', 1);
	ini_set('file_uploads', 'on');
	ini_set('post_max_size', '8M');
	ini_set('upload_max_filesize', '8M');

	// kickoff the session
	session_start();

	return true;
}

/**
 * Get a session variable or cookie variable if session variable isn't found
 * 
 * @param string $name - the name of the session variable to get
 * @return string
 */
function getSessionVar($name)
{
	if (!empty($_SESSION[$name])) {
		return $_SESSION[$name];
	}
	if (!empty($_COOKIE[$name])) {
		return $_COOKIE[$name];
	}
}

/**
 * Set a session variable
 * 
 * @param string $name - the name of the session variable to set
 * @param string $value - the value to set the named session variable
 * @return boolean
 */
function setSessionVar($name, $value)
{
	$_SESSION[$name] = $value;
	session_register($name);
	return true;
}

/**
 * Delete a session variable
 * 
 * @param string $name - the name of the session variable to delete
 * @return boolean
 */
function delSessionVar($name)
{
	unset($_SESSION[$name]);
	session_unregister($name);
	return true;
}
?>