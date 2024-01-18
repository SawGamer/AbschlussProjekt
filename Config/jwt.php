<?php

$jwtConfig = [

    "header" => [
        'alg' => 'HS256',
        // SHA256 algorithm
        'typ' => 'JWT',
        'iss' => 'jwt.Fair',
        // token issuer
        'aud' => 'Fair.com'
    ],

    "secret" => 'thisIsASecret' // change this to your secret code 
];

?>