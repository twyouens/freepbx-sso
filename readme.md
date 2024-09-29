# FreePBX Custom SSO

This is a basic/hacky attempt at adding oauth2 SSO into the FreePBX UCP. This is to make the user experience better in organisations that use SSO to authenticate users. The current UCP currently only supports signing in with a local credential or using an external directory like Active Directory. This project enables you to leverage a web Identity Proivder to authenticate your users against the synced LDAP directory.

In this example, it has been tested with Microsoft Active Directory syncing FreePBX and using ADFS for the IDP. I would reccomend testing this on a staging server before deploying to your production server.
This was written based on FreePBX 17 running on Debian 12 Linux. However, I have tested this in production with FreePBX 15.
NOTE: This implementation relies on a JWT been sent in the token response that it can decode.

## Set up UCP
- Download the repository.
- Open the customsso.php file in the ucp folder.
- Enter your oauth2 client credentials (If you're using ADFS like I did, and need a refresher how to create a client, check out this [amazing guide](https://wiki.resolution.de/doc/openid-oauth-authentication/latest/setup-guides/adfs-setup-guide)).
```php
<?php

$_ENV['OAUTH2_CLIENT_ID'] = 'your-oauth-client';
$_ENV['OAUTH_CLIENT_SECRET'] = 'your-oauth-secret';
$_ENV['OAUTH2_REDIRECT_URI'] = 'http://{your-freepbx-server}/ucp/customsso.php';

// use openid configuration url if your IDP supports it
$_ENV['OAUTH2_OPENID_CONFIG_URL'] = '{your-oauth-server}/.well-known/openid-configuration';

// alternatively, you can define the endpoints manually
$_ENV['OAUTH2_AUTHORIZATION_URL'] = 'your-oauth-server-authorization-url';
$_ENV['OAUTH2_TOKEN_URL'] = 'your-oauth-server-token-url';
$_ENV['OAUTH2_JWKS_URL'] = 'your-oauth-server-jwks-url';
$_ENV['KEY_SIGNING_CHECK'] = true; // Optional to check the JWT signature

$_ENV['OAUTH2_SCOPE'] = 'your-oauth-scope-required-for-returning-user-profile';
$_ENV['OAUTH2_USERINFO_JWT_KEY'] = 'id_token';
$_ENV['OAUTH2_USERINFO_JWT_USERNAME_KEY'] = 'upn';
...
```

Copy these files:
```
customsso.php
.htaccess
views/login.php
```
to `/var/www/html/ucp` on your FreePBX server, making sure the login template goes in the views folder.

If encounter a file permissions error once you have copied the files over, depending on what user you used. You might need to run:
```
sudo chown asterisk:asterisk customsso.php
sudo chmod 644 customsso.php
```

Test the SSO script works, by going to *http://{your-freepbx-server}/ucp/customsso.php*
