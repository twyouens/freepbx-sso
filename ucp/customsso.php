<?php

$_ENV['OAUTH2_CLIENT_ID'] = 'your-oauth-client';
$_ENV['OAUTH_CLIENT_SECRET'] = 'your-oauth-secret';
$_ENV['OAUTH2_REDIRECT_URI'] = 'http://your-freepbx-server/ucp/customsso.php';
$_ENV['OAUTH2_AUTHORIZATION_URL'] = 'your-oauth-server-authorization-url';
$_ENV['OAUTH2_TOKEN_URL'] = 'your-oauth-server-token-url';
$_ENV['OAUTH2_SCOPE'] = 'your-oauth-scope-required-for-returning-user-profile';
$_ENV['OAUTH2_USERINFO_JWT_KEY'] = 'id_token';
$_ENV['OAUTH2_USERINFO_JWT_USERNAME_KEY'] = 'upn';

$assembled_url = $_ENV['OAUTH2_AUTHORIZATION_URL'] . '?response_type=code&client_id=' . $_ENV['OAUTH2_CLIENT_ID'] . '&redirect_uri=' . $_ENV['OAUTH2_REDIRECT_URI'] . '&scope=' . $_ENV['OAUTH2_SCOPE'];
$error = "";
$error_description = "";
$additonal_info = "";
if(isset($_GET['code'])) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $_ENV['OAUTH2_TOKEN_URL']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=authorization_code&code=' . $_GET['code'] . '&redirect_uri=' . $_ENV['OAUTH2_REDIRECT_URI'],);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . base64_encode($_ENV['OAUTH2_CLIENT_ID'] . ':' . $_ENV['OAUTH_CLIENT_SECRET'])));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($response, true);
    if(isset($response['access_token'])) {
        if(isset($_ENV['OAUTH2_USERINFO_JWT_KEY'])) {
            $jwt = explode('.', $response[$_ENV['OAUTH2_USERINFO_JWT_KEY']]);
            $jwt = json_decode(base64_decode($jwt[1]), true);
            if(isset($jwt[$_ENV['OAUTH2_USERINFO_JWT_USERNAME_KEY']])) {
                $username = $jwt[$_ENV['OAUTH2_USERINFO_JWT_USERNAME_KEY']];
                ob_start();
                $bootstrap_settings = array();
                $bootstrap_settings['freepbx_auth'] = false;
                $restrict_mods = true; //Set to true so that we just load framework and the page wont bomb out because we have no session
                include '/etc/freepbx.conf';

                include(dirname(__FILE__).'/includes/bootstrap.php');
                try {
                    $ucp = \UCP\UCP::create();
                    $ucp->Modgettext->textdomain("ucp");
                } catch(\Exception $e) {
                    if(isset($_REQUEST['quietmode'])) {
                        echo json_encode(array("status" => false, "message" => "UCP is disabled"));
                    } else {
                        echo "<html><head><title>UCP</title></head><body style='background-color: rgb(211, 234, 255);'><div style='border-radius: 5px;border: 1px solid black;text-align: center;padding: 5px;width: 90%;margin: auto;left: 0px;right: 0px;background-color: rgba(53, 77, 255, 0.18);'>"._('UCP is currently disabled. Please talk to your system Administrator')."</div></body></html>";
                    }
                    die();
                }
                ob_end_clean();
                if($ucp->User->login($username,"sso",false,true)) {
                    //successfully signed in
                    header('Location: /ucp');
                    die();
                }else {
                    $error = 'login error';
                    $error_description = 'Could not log in user. Please contact your system administrator.';
                }
            }else {
                $error = 'username error';
                $error_description = 'Could not find username in JWT response. Please check your configuration.';
            }
        }else {
            $error = 'jwt error';
            $error_description = 'Could not find JWT in response. Please check your configuration.';
        }
    }elseif(isset($response['error'])) {
        $error = $response['error'];
        $error_description = $response['error_description'];
        $additonal_info = "<p>You will shortly be redirected to sign in again.</p><script>window.setTimeout(function(){window.location.href ='".$assembled_url."&prompt=login'}, 5000);</script>";
    }else{
        $error = 'unknown error';
        $error_description = 'An unknown error occurred. Please contact your system administrator.';
    }

}elseif(isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
    $error_description = htmlspecialchars($_GET['error_description']);
}else {
    header('Location: '.$assembled_url);
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset='UTF-8' />
        <title>An Error Occurred</title>
        <style>
            body { background-color: #fff; color: #222; font: 16px/1.5 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; margin: 0; }
            .container { margin: 30px; max-width: 700px; }
            h1 { color: #dc3545; font-size: 24px; }
            h2 { font-size: 18px; }
            a { color: #0C72D8; text-decoration: none;}
    </style>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body>
        <div class='container'>
            <h1>Oops! An Error Occurred</h1>
            <h2><?php echo $error ?>.</h2>
            <p>
                Sorry, there was a problem when trying to sign you in. Please try again. If this issue persists, please contact your system administrator.
            </p>
            <p>
                <b>Details:</b><br><?php echo $error_description ?>
            </p>
            <?php echo $additonal_info ?>
        </div>
    </body>
</html>