<?php require_once('config.php'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $ai_instance_name;?> Whitelisted IdPs</title>
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">
    <link rel="shortcut icon" href="<?php echo $ai_shortcut_icon; ?>" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?php echo $ai_shortcut_icon; ?>" type="image/x-icon">
</head>
<body>
<div class="container">
    <div class="panel panel-info" style="margin-top: 50px;">
        <div class="panel-heading"><img src="<?php echo $ai_logo; ?>" class="" style="width: auto; height: 50px;">&nbsp;<?php echo $ai_instance_name;?> Whitelisted IdPs</div>
        <div class="panel-body">
	<p>List of IdPs which have been added to <?php echo $ai_instance_name;?> IdP/SP proxy because they have demonstrated they release the proper attributes</p>
	<table class="table table-striped">
	    <thead>
	      <tr>
		<th>IdP Name</th>
		<th>IdP EntityID</th>
	      </tr>
	    </thead>
	    <tbody>
<?php
	$context = stream_context_create(array(
    		'http' => array(
        	'header'  => "Authorization: Basic " . base64_encode("$ai_whitelist_username:$ai_whitelist_password")
    		)
	));
	$idpsList = file_get_contents($ai_whitelist_url, false, $context);
	
	$idps = array_reverse(explode("\n",$idpsList));

	foreach ($idps as $idpRaw) {
		$idp = explode('^', $idpRaw);
		print "<tr><td>$idp[0]</td><td>$idp[1]</td></tr>";
	}
?>
	
	   </tbody>
	</table>
<hr>

<div class="container">
    <center>
        <p>support: <a href="mailto:<?php echo $ai_contact;?>"><?php echo $ai_contact;?></a></p>
    </center>
</div>
</body>
</html>
