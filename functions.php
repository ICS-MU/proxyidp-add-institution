<?php

function post($url, $params) {
    global $ai_whitelist_username, $ai_whitelist_password;
    $options = array(
        'http' => array(
            'header'  => array(
                "Content-type: application/x-www-form-urlencoded",
                "Authorization: Basic " . base64_encode("$ai_whitelist_username:$ai_whitelist_password"),
            ),
            'method'  => 'POST',
            'content' => http_build_query($params),
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

    <form action="./sendReportForm.php" method="post" class="fform-horizontal" id="reportForm">
        <div class="form-group">
                <label class="ccol-sm-1 control-label" for="from">Email</label>
                <input name="from" type="email" class="form-control" id="from" placeholder="Email">
                <span  class="help-block">Type in your email and we will inform you once we've resolved the problem.</span>
        </div>

        <div class="form-group">
                <textarea name="body" class="form-control" rows="2" placeholder="We automatically attach all necessary data, but feel free to comment."></textarea>
        </div>
	<input type='hidden' name='url' value='{$url}' />
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
