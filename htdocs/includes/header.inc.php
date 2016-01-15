<html>
<head>
<style type="text/css">
BODY {
	margin: 0;
	font-family: arial;
	font-size: 12px;
}
#header_bar {
	background-color: silver;
	width: 100%;
	padding: 3px;
	height: 20px;
}
#header {
	color: #ffffff;
	font-weight: bold;
	font-size: 16px;
	font-family: verdana;
	float: left;
}
#welcome {
	color: gray;
	font-size: 12px;
	font-family: arial;
	float: right;
	padding-right: 8px;
}
#index_bar {
	background-color: gray;
	color: silver;
	width: 100%;
	padding: 3px;
	height: 16px;
	border-top: 1px solid #ffffff;
	border-bottom: 2px solid #ffffff;
}
#index_bar A {
	text-decoration: none;
	color: #ffffff;
}
#index_bar A:hover {
	color: lightblue;
}
TD {
	font-size: 12px;
}
#search_box CAPTION {
	background-color: silver;
	font-size: 13px;
	color: #ffffff;
	text-align: left;
	padding: 2px;
}
TABLE#search_box {
	border: 1px solid silver;
}
DIV#DIV_login_box {
	border: 1px solid silver;
	float: right;
}
#DIV_login_box CAPTION {
	background-color: silver;
	font-size: 13
}

.tip_table CAPTION {
	background-color: gray;
	color: white;
}
.tip_key {
	background-color: silver;
	color: white;
}
.tip_value {
	color: black;
	width: 90%;
}
/**** Tool Tip ****/
#dhtmltooltip{
	position: absolute;
	width: 150px;
	border: 1px solid black;
	padding: 2px;
	background-color: lightyellow;
	visibility: hidden;
	z-index: 100;
	/*Remove below line to remove shadow. Below line should always appear last within this CSS*/
	filter: progid:DXImageTransform.Microsoft.Shadow(color=gray,direction=135);
}
</style>
	<script language="JavaScript1.2" src="<?= $CONFIG['path']->includes ?>/common.js" type="text/javascript"></script>
</head>
<body>

<div id="header_bar">
	<div id="header">
		Craig's List Tools
	</div>
	<div id="welcome">
		<?php
		// check if user is logged in
		if ( $GLOBALS['user']->username ) {
			?>
			Welcome <?= $GLOBALS['user']->firstname ?>.
			<?php
		}
		?>
	</div>
</div>

<div id="index_bar">
	<div style="float:left;">
		<a href="index.php">Home</a> |
		<a href="index.php">Search</a> 
	</div>

	<script>
	function changeBox(obj, color, val)
	{
		obj.style.color=color;
		obj.value=val;
	}
	</script>
	
	<div style="float:right; margin-right:10px;">
		<?php
		// check if user is logged in
		if ( $GLOBALS['user']->username ) {
			// show logout link
			?>
			<a href="<?= $_SERVER['PHP_SELF'] ?>?logout">Logout</a>
			<?php
		} else {
			// show login link
			?>
			<form>
			<a href="register.php">Register</a> or
			Login: 
			<input type="text" name="username" size="10" value="username"
				style="color:silver" onFocus="changeBox(this,'#000000','')" offFocus="if(this.value=''){changeBox(this,'silver','username');}">
			<input type="text" name="password" size="10" value="password"
				style="color:silver" onFocus="changeBox(this,'#000000','')">
			</form>
			<!--<a href="" onclick="toggle('DIV_login_box', event);return false;">Login</a>-->
			<?php
		}
		?>
	</div>
</div>

<div id="DIV_login_box" style="display:<?= ($_REQUEST['login'] && $err ? 'normal' : 'none') ?>;">
	<form method="POST">
	
	<table class="search_box">
	<caption>Login</caption>
	<tr>
		<td>Username: </td>
		<td> <input type="texst" name="username" value="<?= $_REQUEST['username'] ?>" size="30"> </td>
	</tr>
	<tr>
		<td>Password: </td>
		<td> <input type="password" name="password" size="30"> </td>
	</tr>
	<tr>
		<td colspan="2" align="center"> <input type="submit" name="login" value="Login"> </td>
	</tr>
	</table>
	
	</form>
</div>

<?php
if ( $err ) {
	printError($err);
}
?>
