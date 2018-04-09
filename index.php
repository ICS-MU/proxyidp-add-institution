<?php
require_once('config.php');
require_once('functions.php');

///////////////// model preparation ///////////////

// Omit php warnings in logs
$_SERVER += defineOnArray($_SERVER, array('eppn', 'persistent-id', 'affiliation', 'unscoped-affiliation', 'schacHomeOrganization', 'mail', 'displayName','o','sourceIdPEntityID'));

$eppn = $_SERVER['eppn'];
$persistentId = $_SERVER['persistent-id'];
$affiliation = $_SERVER['affiliation'];
$unscopedAffiliation = $_SERVER['unscoped-affiliation'];
$schacHomeOrganization = $_SERVER['schacHomeOrganization'];
$mail = $_SERVER['mail'];
$displayName = $_SERVER['displayName'];
$sourceIdPEntityID = $_SERVER['sourceIdPEntityID'];
$o = $_SERVER['o'];

$hasUid = false;
$hasAffiliation = false;
$hasOrganization = false;


if (isset($eppn) or isset($persistentId)) {
	$hasUid = true;
}
if (isset($affiliation) or isset($unscopedAffiliation)) {
	$hasAffiliation = true;
}
if (isset($schacHomeOrganization)) {
	$hasOrganization = true;
}


$isOk = $hasUid && $hasOrganization && $hasAffiliation;


$idpEntityId = $sourceIdPEntityID;


$idpDisplayName;
if (!empty($o)) {
        $idpDisplayName = $o;
} else if (!empty($schacHomeOrganization)) {
        $idpDisplayName = $schacHomeOrganization;
} else if (!empty($idpEntityId)) {
        $entityIdUrl = parse_url($idpEntityId);
        if (!$entityIdUrl) {
                $idpDisplayName = $entityIdUrl['host'];
        } else {
                $idpDisplayName = $idpEntityId;
        }
} else {
        $idpDisplayName = 'unknown';
}



# Store information about the session, format: date - IdP - result
$resultInFile = file_put_contents($ai_results_file, date("Y-m-d H:i:s") . ' - ' . $sourceIdPEntityID . ' - ' . ($isOk ? 1 : 0) . "\n", FILE_APPEND);
$resultInFile = ($resultInFile === false) ? false : true;

$resultOnProxy = 'NOT_TRIED';
# Send information to Proxy IdP if its OK. So it can be added
if ($isOk) {
	$resultOnProxy = 'ERROR';
	$response = post($ai_whitelist_idp,
                array(
                        'entityId' => $sourceIdPEntityID,
                	'reason' => "Attributes checked by user"
		)
        );

	if (!empty($response) && isJson($response)) {
		$json = json_decode($response, true);
		$resultOnProxy = $json['result'];
		if ($resultOnProxy === 'ERROR') {
			$resultOnProxy .= ", error msg: ".$json['msg'];
		}
	}
}

# set whole model into one var to easier manipulation
$model = array(
	'isOk' => $isOk,
	'hasUid' => $hasUid,
	'hasAffiliation' => $hasAffiliation,
	'hasOrganization' => $hasOrganization,
	'idpDisplayName' => $idpDisplayName,
	'resultInFile' => $resultInFile,
	'resultOnProxy' => $resultOnProxy,
	'idpEntityId' => $idpEntityId,
);

///////////////// view //////////////////
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $ai_instance_name;?> Attribute Release Test</title>
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">
    <link rel="shortcut icon" href="<?php echo $ai_shortcut_icon;?>" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?php echo $ai_shortcut_icon;?>" type="image/x-icon">
    <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>    
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    
    <style>
	.logo {
		display: block;
		width: 170px;
		margin: 24px auto;
	}
	.result {
		margin: 28px 0; 
	}
	.result hr {
		margin-top: 8px;
		margin-bottom: 8px; 
		border-color: #ddd;
	}
	.result h1:first-child,
	.result h2:first-child, 
	.result h3:first-child,
	.result h4:first-child {
		margin-top: 0;
	}
        .result h1:last-child,
        .result h2:last-child,
        .result h3:last-child,
        .result h4:last-child {
                margin-bottom: 0;
        }
	.panel-more {
		margin: -11px 11px 11px -11px;
		padding: 0 4px;
		background: white;
	}
	.report-box .form-horizontal .control-label {
		text-align: left;
	}
    </style>

</head>
<body>
<div class="container">
	<img class="logo" src="<?php echo $ai_logo;?>">


	<p>This application tests the compatibility of your identity provider with the <?php echo $ai_instance_name;?> AAI (Authentication and Authorisation Infrastructure). The results are automatically stored and used to determine if identity providers are ready to join the <?php echo $ai_instance_name;?> AAI.</p>

	<div class="result panel panel-default">

<?php
if ($isOk and $resultOnProxy === 'ALREADY_THERE') {
	// is OK and has been added
	echo <<<EOD
	<div class="panel-body">
	    <h2>Your institution <i>$idpDisplayName</i> is <span class="label label-success">already in the $ai_instance_name AAI</span></h2>
	    <h4>You can access your service with it</h4>
	</div>
EOD;


} else if ($isOk and $resultOnProxy === 'ADDED') {
        // is OK and has been added
        echo <<<EOD
        <div class="panel-body">
            <h2>Your institution <i>$idpDisplayName</i> has been <span class="label label-success">successfully added</span> to the $ai_instance_name AAI</h2>
            <h4>Please try to <b>access your service again</b></h4>
        </div>
EOD;


} else if ($isOk) {
	// is OK but not added
        echo <<<EOD
        <div class="panel-body">
	    <h2>Your institution <i>$idpDisplayName</i> <span class="text-success">is compatible</span> with the $ai_instance_name AAI but we had <span class="label label-warning">problem to add it automatically</span></h2>
	</div>
EOD;
        echo reportBox('Please try to <b>refresh this page</b> or report it so we can add it manually', $ai_report_idp, $model, !empty($_GET['mailSended']));


} else {
	// is NOT OK
        echo <<<EOD
        <div class="panel-body">
		<h2>Your institution <i>$idpDisplayName</i> can not be added because it is <span class="label label-danger">not compatible</span> with the $ai_instance_name AAI</h2>
	</div>
	<hr>
EOD;
	echo reportBox('Please <b>report</b> it so that we can add your institution to the ' . $ai_instance_name . ' AAI', $ai_report_idp, $model, !empty($_GET['mailSended']));

}
?>
		<div id="moreInfo" class="collapse">
		<hr>	
	
       			<div class="panel-body">

	<h4><?php echo $ai_instance_name;?> AAI requires following attributes</h4>
	<ul>
		<li>
			eduPersonPrincipalName <small><span class="label label-default">urn:oid:1.3.6.1.4.1.5923.1.1.1.6</span></small> 
			<i>- or -</i> 
			eduPersonTargetedId/persistentID <small><span class="label label-default">urn:oid:1.3.6.1.4.1.5923.1.1.1.10</span></small>
		</li>
		<li>
			eduPersonAffiliation <small><span class="label label-default">urn:oid:1.3.6.1.4.1.5923.1.1.1.1</span></small>
			<i>- or -</i>
			eduPersonScopedAffiliation <small><span class="label label-default">urn:oid:1.3.6.1.4.1.5923.1.1.1.9</span></small>
		</li>
		<li>
			schacHomeOrganization <small><span class="label label-default">urn:oid:1.3.6.1.4.1.25178.1.2.9</span></small>
		</li>
	</ul>

<?php
echo <<<EOD
        <h4>Your IdP sends</h4>
        <ul>
                <li>
                        eduPersonPrincipalName: <b>{$eppn}</b>
                        <i>- or -</i> 
                        eduPersonTargetedId/persistentID: <b>{$persistentId} </b>
EOD;
echo '<span class="glyphicon glyphicon-' . ($hasUid?'ok':'remove') . '" style="color: ' . ($hasUid?'green':'red') . ';"></span>';
echo <<<EOD
                </li>
                <li>
                        eduPersonAffiliation: <b>{$unscopedAffiliation}</b>  
                        <i>- or -</i>
                        eduPersonScopedAffiliation: <b>{$affiliation} </b> 
EOD;
echo '<span class="glyphicon glyphicon-' . ($hasAffiliation?'ok':'remove') . '" style="color: ' . ($hasAffiliation?'green':'red') . ';"></span>';
echo <<<EOD
                </li>
                <li>
                        schacHomeOrganization: <b>{$schacHomeOrganization} </b> 
EOD;
echo '<span class="glyphicon glyphicon-' . ($hasOrganization?'ok':'remove') . '" style="color: ' . ($hasOrganization?'green':'red') . ';"></span>';
echo <<<EOD

                </li>
        </ul>
EOD;
?>




        <h4><?php echo $ai_instance_name;?> AAI optionally consumes following attributes</h4>
        <ul>
                <li>
                        displayName <small><span class="label label-default">urn:oid:2.16.840.1.113730.3.1.241</span></small>
                </li>
                <li>
                        mail <small><span class="label label-default">urn:oid:0.9.2342.19200300.100.1.3</span></small>
                </li>
        </ul>

<?php   
echo <<<EOD
        <h4>Your IdP sends</h4>
        <ul>
                <li>
                        displayName: <b>{$displayName} </b>
                </li>
                <li>
                        mail: <b>{$mail} </b>
                </li>
        </ul>
EOD;
?>



        		</div>

		</div>

		<a class="pull-right panel-more" data-toggle="collapse" href="#moreInfo">technical info</a>

	</div>


</div>
<hr>
<div class="container">
        <p>This application doesn't store any personal data. We record just name of the identity provider and the aggregated result of the test.</p>
        <p>support: <a href="mailto:<?php echo $ai_contact;?>"><?php echo $ai_contact;?></a></p>
</div>
</body>
</html>
