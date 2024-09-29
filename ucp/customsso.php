<?php

$_ENV['OAUTH2_CLIENT_ID'] = 'your-oauth-client';
$_ENV['OAUTH_CLIENT_SECRET'] = 'your-oauth-secret';
$_ENV['OAUTH2_REDIRECT_URI'] = 'http://your-freepbx-server/ucp/customsso.php';

$_ENV['OAUTH2_OPENID_CONFIG_URL'] = '{your-oauth-server}/.well-known/openid-configuration'; // Optional to fetch the OpenID configuration OR:
$_ENV['OAUTH2_AUTHORIZATION_URL'] = 'your-oauth-server-authorization-url';
$_ENV['OAUTH2_TOKEN_URL'] = 'your-oauth-server-token-url';
$_ENV['OAUTH2_JWKS_URL'] = 'your-oauth-server-jwks-url';
$_ENV['KEY_SIGNING_CHECK'] = true; // Optional to check the JWT signature

$_ENV['OAUTH2_SCOPE'] = 'your-oauth-scope-required-for-returning-user-profile';
$_ENV['OAUTH2_USERINFO_JWT_KEY'] = 'id_token';
$_ENV['OAUTH2_USERINFO_JWT_USERNAME_KEY'] = 'upn';

function base64url_decode($data) {
    $urlDecodedData = str_replace(['-', '_'], ['+', '/'], $data);
    $padding = strlen($urlDecodedData) % 4;
    if ($padding > 0) {
        $urlDecodedData .= str_repeat('=', 4 - $padding);
    }
    return base64_decode($urlDecodedData);
}
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function fetch_jwt_key($url) {
    // Fetch the JWT keys from the URL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $keyResponse = curl_exec($ch);
    curl_close($ch);

    // Handle response error
    if (!$keyResponse) {
        throw new Exception('Failed to fetch the JWT key from the URL.');
    }

    // Parse the JWK response
    $jwk = json_decode($keyResponse, true);
    if (isset($jwk['keys']) && count($jwk['keys']) > 0) {
        $publicKeyJwk = $jwk['keys'][0];  // In case of multiple keys, use the first one

        // Extract the certificate from the x5c field
        if (isset($publicKeyJwk['x5c']) && count($publicKeyJwk['x5c']) > 0) {
            $cert = $publicKeyJwk['x5c'][0]; // Use the first certificate in the chain

            // Convert the certificate to PEM format
            $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split($cert, 64) . "-----END CERTIFICATE-----\n";

            return $pem;
        } else {
            throw new Exception('No x5c certificate found in the JWT response.');
        }
    } else {
        throw new Exception('Invalid JWK format received.');
    }
}
function verify_jwt_signature($jwt, $publicKeyPem) {
    // Split the JWT into its three parts
    list($headerEncoded, $payloadEncoded, $signatureProvided) = explode('.', $jwt);

    // Base64 decode the header and payload
    $header = json_decode(base64url_decode($headerEncoded), true);
    $payload = json_decode(base64url_decode($payloadEncoded), true);

    // Rebuild the signature base string
    $signatureBase = $headerEncoded . '.' . $payloadEncoded;

    // Check the algorithm used in the header (e.g., RS256)
    if ($header['alg'] === 'RS256') {
        // Verify the signature using the public key in PEM format
        $signatureVerified = openssl_verify($signatureBase, base64url_decode($signatureProvided), $publicKeyPem, OPENSSL_ALGO_SHA256);

        if ($signatureVerified === 1) {
            return $payload; // Signature is valid
        } elseif ($signatureVerified === 0) {
            throw new Exception('Invalid JWT signature.');
        } else {
            throw new Exception('Error occurred during signature verification.');
        }
    } else {
        throw new Exception('Unsupported algorithm: ' . $header['alg']);
    }
}
function fetch_openid_configuration(){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $_ENV['OAUTH2_OPENID_CONFIG_URL']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $config = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('Failed to fetch OpenID configuration: ' . curl_error($ch));
    }
    curl_close($ch);
    if(!$config) {
        throw new Exception('Failed to fetch OpenID configuration.');
    }
    return json_decode($config, true);
}
if(isset($_ENV['OAUTH2_OPENID_CONFIG_URL'])) {
    try {
        $config = fetch_openid_configuration();
        if(isset($config['authorization_endpoint'])) {
            $_ENV['OAUTH2_AUTHORIZATION_URL'] = $config['authorization_endpoint'];
        }
        if(isset($config['token_endpoint'])) {
            $_ENV['OAUTH2_TOKEN_URL'] = $config['token_endpoint'];
        }
        if(isset($config['jwks_uri'])) {
            $_ENV['OAUTH2_JWKS_URL'] = $config['jwks_uri'];
        }
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
}

$assembled_url = $_ENV['OAUTH2_AUTHORIZATION_URL'].'?response_type=code&client_id='.$_ENV['OAUTH2_CLIENT_ID'].'&redirect_uri='.$_ENV['OAUTH2_REDIRECT_URI'].'&scope='.$_ENV['OAUTH2_SCOPE'];
$error = "";
$error_description = "";
$additonal_info = "";
if(isset($_GET['code'])) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $_ENV['OAUTH2_TOKEN_URL']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=authorization_code&code=' . $_GET['code'] . '&redirect_uri=' . $_ENV['OAUTH2_REDIRECT_URI']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . base64_encode($_ENV['OAUTH2_CLIENT_ID'] . ':' . $_ENV['OAUTH_CLIENT_SECRET'])));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($response, true);
    if(isset($response['access_token'])) {
        if(isset($_ENV['OAUTH2_USERINFO_JWT_KEY'])) {
            $jwtToken = $response[$_ENV['OAUTH2_USERINFO_JWT_KEY']];
            if($_ENV['KEY_SIGNING_CHECK']) {
                try{
                    $publicKey = fetch_jwt_key($_ENV['OAUTH2_JWKS_URL']);
                    $jwtPayload = verify_jwt_signature($jwtToken, $publicKey);
                    if ($jwtPayload && isset($jwtPayload[$_ENV['OAUTH2_USERINFO_JWT_USERNAME_KEY']])) {
                        $username = $jwtPayload[$_ENV['OAUTH2_USERINFO_JWT_USERNAME_KEY']];
                    }else {
                        $error = 'username error';
                        $error_description = 'Could not find username in JWT response. Please check your configuration.';
                    }
                } catch (Exception $e) {
                    $error = 'jwt error';
                    $error_description = $e->getMessage();
                }
            }else{
                $jwtPayload = json_decode(base64url_decode(explode('.', $jwtToken)[1]), true);
                if ($jwtPayload && isset($jwtPayload[$_ENV['OAUTH2_USERINFO_JWT_USERNAME_KEY']])) {
                    $username = $jwtPayload[$_ENV['OAUTH2_USERINFO_JWT_USERNAME_KEY']];
                }else {
                    $error = 'username error';
                    $error_description = 'Could not find username in JWT response. Please check your configuration.';
                }
            }
            if(!$error && isset($username)) {
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
                    $error_description = 'Could not sign in user into FreePBX UCP. Please check your configuration.';
                }
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
                There was a problem when trying to sign you in. Please try again. If this issue persists, please contact your system administrator.
            </p>
            <p>
                <b>Details:</b><br><?php echo $error_description ?>
            </p>
            <?php echo $additonal_info ?>
        </div>
    </body>
</html>