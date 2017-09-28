<?php

namespace App\Http\Controllers;

// Guzzle
use GuzzleHttp\Client; 
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

// JWT
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use Illuminate\Http\Exception\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Notification;

use App\Http\Controllers\Controller;

use App\Jobs\SendVerificationEmail;

use App\User;

class AuthController extends Controller 
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
    private $appID = "D55AE78AC3DC2A4140E23EF7A72BBF12"; // Application ID registered in infobip. Last for 1 hour.
    private $messageID = "78DB962DEC5BE600AB9435B1AD0F7B9E"; // Message ID, msg: "Masukan nomor PIN berikut: <pin>".
    private $accKey = "EF04794F718A3FC8C6DFA0B215B2CF24"; // Account key to create API key.
    private $apiKey = "995c42c4005eb9862bee83790f2a0c92-df29d82c-77b2-4ae2-b932-dab91c087ac1"; // API key for Authentication.
    
    public function __construct()
    {
        $this->basicauth = base64_encode("Jukir_user1:qwepoi");
    }
    
    /**
     *  For registering new User. 
     * 
     * @param $request email, password and phone_number from input fields.
     */
    public function register(Request $request) 
    {
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|min:6',
            'phone_number' => 'required',
        ]);

        $user = new User;
        $user->email = $request->input('email');
        $user->password = app('hash')->make($request->input('password'));
        $user->phone_number = $request->input('phone_number');
        if($request->has(['name'])) {
            $user->name = $request->input('name');
        }
        //  Digunakan untuk verifikasi email
        $user->email_token = base64_encode('JUKIR:' . $request->input('email'));        
        $user->save();

        dispatch(new SendVerificationEmail($user));

        return response()->json([
            'message'   => 'Successfully created user',
            'status'    => true,
            'data'      => $user
        ], 200);
    }

    public function verifyEmail($token) 
    {
        $user = User::where('email_token', $token)->firstOrFail();
        
        $user->isVerified = true;

        if($user->save()) {
            return response()->json([
                'message' => 'successfully verified', 
                'isVerified' => $user->isVerified,
            ], 200);
        }
    }

    /**
     *  Authenticating User when logging in, this will
     *  NEED TO BE ADD OTPController for 2 Factor Authentication
     *  using SMS. 
     * 
     * @param $request (string) credentials (email & pass) OR (phone_number). 
     */
    public function authenticate(Request $request)
    {   
        $token = null;
        
        /**
         * This will check whether the field email / password is filled.
         * 
         * If it is filled, login using Email and Password.
         */
        if($request->filled(['email', 'password'])) {
            $input = $request->only('email', 'password');

            try {
                if (!$token = JWTAuth::attempt($input)) {
                    return response()->json(['invalid_email_or_password'], 422);
                }
            } catch (JWTAuthException $e) {
                 return response()->json(['failed_to_create_token'], 500);
            }

            $token = JWTAuth::attempt($input);
            $user = User::where('email', $request->input('email'))->first();
            $user->auth_token = $token;
            $user->save();

            return response()->json(compact('token'));
        }
        /**
         *  This will check if input phone_number field is filled.
         * 
         *  If it is filled, login using OTP 2 Factor Authentication.
         */
        elseif($request->filled('phone_number')) {
            $phone = $request->input('phone_number');
            $user = User::where('phone_number', $phone)->first();
    
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
    }

    /**
     * Verifying requested pin from User in OTP login.
     * 
     * @param Request (string) $token from header: auth_token. 
     * @param Request user input (string) $pin.
     */
    public function verifySms(Request $request)
    {
        $token = substr($request->header('Authorization'), 6);
        $user = User::where('auth_token', $token)->first();

        $clientPost = new Client();
        
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
    }


    /**
     *  Logging out by invalidating auth_token and login_token 
     *  from User DB as well as blacklisting JWTAuth token.
     * 
     * @param $request auth_token from User.
     */
    public function logout(Request $request) 
    {
        // Nulling all tokens and invalidate auth_token with JWT.
        $token = substr($request->header('Authorization'), 6);

        $user = User::where('auth_token', $token)->first();
        $user->auth_token = null;

        if($user->login_token != null) {
            $user->login_token = null;
        }

        if ($token != null) {
            JWTAuth::setToken($token)->invalidate();
            $user->save();
            
            return response()->json(['message' => "User successfully logout"], 200);
        }
    }
    
    /**
    *   Simple function to send error code and message. 
    */
    private function sendError($message, $code)
    {
        return response()->json([
            'message' => $message
        ], $code);
    }
}

?>