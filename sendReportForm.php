<?php
/*
 * This small script creates redirect to the protected page which sends email.
 * In the end result is printed. The result contains page to be displayed.
 **/
	require_once('config.php');
	require_once('functions.php');

	$url = $_POST['url'];
	$result = post($url, $_POST, true);

	echo $result;
?>
