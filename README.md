This project is a fork of GitList (https://github.com/klaussilveira/gitlist/tree/master/cache) of Klaus Silveira,
with some basic repository administrations features.

## License
[New BSD license](http://www.opensource.org/licenses/bsd-license.php)

## Configuration
Just edit config.ini to fit your needs. This project add the following entries to this file:

administrators[] ; users that are able to administrate the repositories
                 ; add a new line for every administration account

## Apache2
This an example of a virtual-host in apache2.4 with fcgi (i moved the original .htaccess
to this configuration) with SSL and Basic authentication (private pull and push).

```
<VirtualHost *:80>
    ServerName <server name>
    ServerAdmin <webadmin email account>
    DocumentRoot /var/www/gitlist-admin

    Redirect permanent / https://<server name>/
</VirtualHost>

<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName <server name>
    ServerAdmin <webadmin email account>
    DocumentRoot /var/www/gitlist-admin

    <Files config.ini>
        Require all denied
    </Files>

    <Location />
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All

        AddHandler fcgid-script .php
        Options +ExecCGI
        FcgidWrapper /usr/bin/php-cgi .php

        AuthName "GitList Respositories Frontend"
        AuthType Basic
        AuthUserFile "/etc/htpasswd/.htpasswd"
        Require valid-user

        RewriteEngine On
        RewriteBase /
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(.*)$ index.php?$1 [L,NC]
    </Location>

    # Configure Git HTTP Backend
    SetEnv GIT_PROJECT_ROOT /var/repositories
    SetEnv GIT_HTTP_EXPORT_ALL
    ScriptAliasMatch \
          "(?x)^/(.*/(HEAD | \
            info/refs | \
            objects/(info/[^/]+ | \
                 [0-9a-f]{2}/[0-9a-f]{38} | \
                 pack/pack-[0-9a-f]{40}\.(pack|idx)) | \
            git-(upload|receive)-pack))$" \
        /usr/lib/git-core/git-http-backend/$1

    ErrorLog ${APACHE_LOG_DIR}/<server name>-ssl-error.log
    CustomLog ${APACHE_LOG_DIR}/<server name>-ssl-access.log combined
    LogLevel warn

    SSLEngine on
    SSLCertificateFile        <server name>.crt
    SSLCertificateKeyFile     <server name>-key-noenc.pem
    SSLCertificateChainFile   <server name>-chain.crt
    SSLCACertificateFile      ca-chain.pem
    SSLCARevocationFile       ca-crl.pem
    SSLCARevocationCheck      chain

    SSLOptions +FakeBasicAuth +ExportCertData +StrictRequire

    SSLProtocol all -SSLv2 -SSLv3
    SSLHonorCipherOrder on
    SSLCipherSuite "EECDH+ECDSA+AESGCM EECDH+aRSA+AESGCM EECDH+ECDSA+SHA384 \
                    EECDH+ECDSA+SHA256 EECDH+aRSA+SHA384 EECDH+aRSA+SHA256 EECDH+aRSA+RC4 \
                    EECDH EDH+aRSA RC4 !aNULL !eNULL !LOW !3DES !MD5 !EXP !PSK !SRP !DSS"

    BrowserMatch "MSIE [2-6]" nokeepalive ssl-unclean-shutdown downgrade-1.0 force-response-1.0
    BrowserMatch "MSIE [17-9]" ssl-unclean-shutdown

</VirtualHost>
</IfModule>
```
