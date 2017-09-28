<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use GuzzleHttp\Client; 
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

use App\User;

Class OTPController 
{
    /** 
     *  This is the base URL in infobip OTP 2FA.
     * 
     * @var (string)
     */
    private $host = "http://api.infobip.com/2fa/1/pin";

    /**
     *  This is the credential of the infobip TRIAL account
     *  provided. To send create App and Message in Infobip
     *  we will need these credentials. 
     * 
     *  Or make a new one and rewrite these credentials through 
     *  infobip's API.
     * 
     *  @var (string)
     */
    private $basicauth; // Contain authentication consisting of base64 encode (string) "username:password"
    private $appID = "D55AE78AC3DC2A4140E23EF7A72BBF12"; // Application ID registered in infobip.
    private $messageID = "78DB962DEC5BE600AB9435B1AD0F7B9E"; // Message ID, msg: "Masukan nomor PIN berikut: <pin>".
    private $accKey = "EF04794F718A3FC8C6DFA0B215B2CF24"; // Account key to create API key.
    private $apiKey = "995c42c4005eb9862bee83790f2a0c92-df29d82c-77b2-4ae2-b932-dab91c087ac1"; // API key for Authentication.
    
    public function __construct()
    {
        $this->basicauth = base64_encode("Jukir_user1:qwepoi");
    }
    
    /**
     * Sending SMS through infobip's API to User.
     * 
     * @param Request (string) $token from header: auth_token. 
     */
    public function sendSms(Request $request)
    {
        $token = substr($request->header('Authorization'), 6);
        $user = User::where('auth_token', $token)->first();

        $clientPost = new Client();

        $headers = [
            'Authorization' => 'App ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $body = [
            'applicationId' => $this->appID,
            'messageId' => $this->messageID,
            'from' => 'JUKIR',
            'to' => $user->phone_number,
        ];

        $response = $clientPost->post($this->host, [
            'headers' => $headers, 
            'body' => json_encode($body)
        ]);

        $result = json_decode($response->getBody());
        
        if($result->smsStatus == "MESSAGE_SENT") {
            //  When MESSAGE_SENT successfully, PIN ID becomes User login_token.
            $user->login_token = $result->pinId;
            $user->save();

            return response()->json([
                'message' => 'SMS sent', 
                'OTP login token' => $result->pinId
            ], 200);
        } else {
            return response()->json([
                'message' => 'SMS not sent'
            ], 200);
        }
    }

    /**
     * Verifying requested pin from User.
     * 
     * @param Request (string) $token from header: auth_token. 
     * @param Request user input (string) $pin.
     */
    public function verifySms(Request $request)
    {
        $token = substr($request->header('Authorization'), 6);
        $user = User::where('auth_token', $token)->first();

        $clientPost = new Client();
        if($user->login_token != null) {
            $pinId = $user->login_token;
            $pin = $request->input('pin');
            
            $headers = [
                'Authorization' => 'App ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];
            $body = [
                'pin' => $pin,
            ];
            
            $response = $clientPost->post($this->host . "/" . $pinId . "/verify", [
                'headers' => $headers, 
                'body' => json_encode($body)
            ]);
            
            $result = json_decode($response->getBody());
            
            if($result->verified) {
                //  When PIN entered is correct                
                return response()->json([
                    'message' => 'PIN is correct', 
                    'isVerified' => $result->verified
                ], 200);

            } elseif($result->attemptsRemaining === 0) {
                //  When no attempts remaining
                return response()->json([
                    'error' => 'No attempts remaining',
                    'isVerified' => $result->verified
                ], 403);

            } else {
                // When PIN is incorrect but there's still attempt
                return response()->json([
                    'error' => $result->pinError,
                    'message' => 'Reenter correct PIN', 
                    'isVerified' => $result->verified, 
                    'attemptsRemaining' => $result->attemptsRemaining
                ], 200);
            }
        } else {
            //  No login_token detected in User therefore invalid credential
            return response()->json([
                'error' => 'Invalid credential',
                'message' => 'Missing login token'
            ], 400);
        }
        
    }

    public function resendSms()
    {
        
    }

    public function getSmsStatus()
    {

    }
}

?>