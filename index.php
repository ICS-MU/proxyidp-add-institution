<?php
require_once('config.php');

///////////////// model preparation ///////////////

// Omit php warnings in logs
$_SERVER += defineOnArray($_SERVER, array('eppn', 'persistent-id', 'affiliation', 'unscoped-affiliation', 'schacHomeOrganization', 'mail', 'displayName'));


$hasUid = false;
$hasAffiliation = false;
$hasOrganization = false;


if (isset($_SERVER["eppn"]) or isset($_SERVER["persistent-id"])) {
	$hasUid = true;
}
if (isset($_SERVER["affiliation"]) or isset($_SERVER["unscoped-affiliation"])) {
	$hasAffiliation = true;
}
if (isset($_SERVER["schacHomeOrganization"])) {
	$hasOrganization = true;
}


$isOk = $hasUid && $hasOrganization && $hasAffiliation;


$idpEntityId = $_SERVER['sourceIdPEntityID'];


$idpDisplayName;
if (!empty($_SERVER['o'])) {
        $idpDisplayName = $_SERVER['o'];
} else if (!empty($_SERVER['schacHomeOrganization'])) {
        $idpDisplayName = $_SERVER['schacHomeOrganization'];
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
$resultInFile = file_put_contents($ai_results_file, date("Y-m-d H:i:s") . ' - ' . $_SERVER['sourceIdPEntityID'] . ' - ' . ($isOk ? 1 : 0) . "\n", FILE_APPEND);
$resultInFile = ($resultInFile === false) ? false : true;

$resultOnProxy = 'NOT_TRIED';
# Send information to Proxy IdP if its OK. So it can be added
if ($isOk) {
	$resultOnProxy = 'ERROR';
	$response = post($ai_whitelist_idp,
                array(
                        'entityId' => $_SERVER['sourceIdPEntityID'],
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
	<img class="logo" lt="logo" src="<?php echo $ai_logo;?>">


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
                        eduPersonPrincipalName: <b>{$_SERVER["eppn"]}</b>
                        <i>- or -</i> 
                        eduPersonTargetedId/persistentID: <b>{$_SERVER["persistent-id"]} </b>
EOD;
echo '<span class="glyphicon glyphicon-' . ($hasUid?'ok':'remove') . '" style="color: ' . ($hasUid?'green':'red') . ';"></span>';
echo <<<EOD
                </li>
                <li>
                        eduPersonAffiliation: <b>{$_SERVER["unscoped-affiliation"]}</b>  
                        <i>- or -</i>
                        eduPersonScopedAffiliation: <b>{$_SERVER["affiliation"]} </b> 
EOD;
echo '<span class="glyphicon glyphicon-' . ($hasAffiliation?'ok':'remove') . '" style="color: ' . ($hasAffiliation?'green':'red') . ';"></span>';
echo <<<EOD
                </li>
                <li>
                        schacHomeOrganization: <b>{$_SERVER["schacHomeOrganization"]} </b> 
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
                        displayName: <b>{$_SERVER["displayName"]} </b>
                </li>
                <li>
                        mail: <b>{$_SERVER["mail"]} </b>
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















<?php
////////// Functions //////////////

function post($url, $params) {
	global $ai_whitelist_username, $ai_whitelist_password;
	
	$options = array(
    		'http' => array(
        		'header'  => array(
				"Content-type: application/x-www-form-urlencoded",
				"Authorization: Basic " . base64_encode("$ai_whitelist_username:$ai_whitelist_password"),
			),
        		'method'  => 'POST',
        		'content' => http_build_query($params)
    		)
	);
	$context  = stream_context_create($options);
	$result = file_get_contents($url, false, $context);        

	return $result; 
}




function isJson($string) {
	json_decode($string);
	return (json_last_error() == JSON_ERROR_NONE);
}





function startsWith($string, $start)
{
     $length = strlen($start);
     return (substr($string, 0, $length) === $start);
}




function defineOnArray($array, $attrs) {

	foreach ($attrs as $attr) {
		if (!isset($array[$attr])) {
			$array[$attr] = NULL;
		}
	}
	return $array;

}




function reportBox($title, $url, $data, $sended=false) {

	$time = date("Y-m-d H:i:s");
	$redirectUri = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	$box = <<<EOD
<div class="panel-body report-box">
    <h4>{$title}</h4>
 
    <form action="{$url}" method="post" class="fform-horizontal">

        <div class="form-group">
                <label class="ccol-sm-1 control-label" for="from">Email</label>
                <input name="from" type="email" class="form-control" id="from" placeholder="Email">
                <span  class="help-block">Type in your email and we will inform you once we've resolved the problem.</span>
        </div>

        <div class="form-group">
                <textarea name="body" class="form-control" rows="2" placeholder="We automatically attach all necessary data, but feel free to comment."></textarea>
        </div>

	<input type='hidden' name='title' value='Attr check of {$data['idpDisplayName']}, user report'>
	<input type='hidden' name='time' value='{$time}'>
	<input type='hidden' name='redirectUri' value='{$redirectUri}'>
EOD;
	foreach ($data as $key => $value) {
		$value = $value===true ? 'true' : $value;
        	$value = $value===false ? 'false' : $value;
		$box .= "<input type='hidden' name='{$key}' value='{$value}'>";
	}
	$box .= <<<EOD
        <button type="submit" name="send" class="btn btn-primary">Report</button>

	<span style="margin: 7px;">Thanks for cooperation</span>

    </form>
</div>
EOD;


	$closeUrl = exludeParamFromUrl($redirectUri, 'mailSended');
	$sendedBox = <<<EOD
<div class="panel-body">

    <div class="alert alert-success" role="alert">
	<a href="$closeUrl" class="close"><span>&times;</span></a>
	<h4>The report has been submitted</h4>
	Thanks for cooperation
    </div>

</div>
EOD;

	return $sended ? $sendedBox : $box;
}






function exludeParamFromUrl($url, $paramName) {

	$parseUrl = parse_url($url);
	if (empty($parseUrl['query'])) {
		$parseQuery = array();
	} else {
		parse_str($parseUrl['query'], $parseQuery);
	}
	unset($parseQuery[$paramName]);
	$parseUrl['query'] = http_build_query($parseQuery);
	return build_url($parseUrl);	

}






/*
 Opposite of standard parse_url. 
*/
function build_url($parsUrl) {

        $url = $parsUrl['scheme'] . "://";
        if (!empty($parsUrl['user'])) {
                $url .= $parsUrl['user'];
                if (!empty($parsUrl['pass'])) {
                        $url .= ":" . $parsUrl['pass'];
                }
                $url .= "@";
        }
        $url .= $parsUrl['host'];
        if (!empty($parsUrl['port'])) {
                $url .= ":" . $parsUrl['port'];
        }
        if (!empty($parsUrl['path'])) {
                $url .= $parsUrl['path'];
        }
        if (!empty($parsUrl['query'])) {
                $url .= "?" . $parsUrl['query'];
        }
        if (!empty($parsUrl['fragment'])) {
                $url .= "#" . $parsUrl['fragment'];
        }
        
        return $url;
}


?>
