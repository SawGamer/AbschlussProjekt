<?php
class JwtHandler
{
    private $headers;


    private $secret;

    public function __construct($jwtconfig)
    {
        $this->headers = $jwtconfig["header"];
        $this->secret = $jwtconfig["secret"];
    }


    public function generate(array $payload): string
    {
        $headers = $this->encode(json_encode($this->headers)); // encode headers
        $payload["exp"] = time() + 1000; // add expiration to payload
        $payload = $this->encode(json_encode($payload)); // encode payload
        $signature = hash_hmac('SHA256', "$headers.$payload", $this->secret, true); // create SHA256 signature
        $signature = $this->encode($signature); // encode signature

        return "$headers.$payload.$signature";
    }


    private function encode(string $str): string
    {
        return rtrim(strtr(base64_encode($str), '+/', '-_'), '='); // base64 encode string
    }


    public function is_valid(string $jwt): bool
    {
        $token = explode('.', $jwt);
        if (!isset($token[1]) && !isset($token[2])) {
            return false;
        }
        $headers = base64_decode($token[0]);
        $payload = base64_decode($token[1]);
        $clientSignature = $token[2];

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        if (!json_decode($payload)) {
            return false;
        }

        if ((json_decode($payload)->exp - time()) < 0) {
            return false;
        }

        if (isset(json_decode($payload)->iss)) {
            if (json_decode($headers)->iss != json_decode($payload)->iss) {
                return false;
            }
        } else {
            return false;
        }

        if (isset(json_decode($payload)->aud)) {
            if (json_decode($headers)->aud != json_decode($payload)->aud) {
                return false;
            }
        } else {
            return false;
        }

        $base64_header = $this->encode($headers);
        $base64_payload = $this->encode($payload);

        $signature = hash_hmac('SHA256', $base64_header . "." . $base64_payload, $this->secret, true);
        $base64_signature = $this->encode($signature);

        return ($base64_signature === $clientSignature);
    }

    public function is_validTI(string $jwt, int $id): bool //experiment ValidateToken-withID
    {
        $token = explode('.', $jwt);

        $payload = base64_decode($token[1]);

        if (isset($payload)) {
            if (json_decode($payload)->id === $id) {

                return true;
            }

        }
        return false;
    }



    public function remove(string $jwt, int $id)
    {
        $token = explode('.', $jwt);

        $payload = base64_decode($token[1]);

        if (isset($payload)) {
            if (json_decode($payload)->id === $id) {
                json_decode($payload)->exp = time() - 1000;
            }
        }
    }
}
?>