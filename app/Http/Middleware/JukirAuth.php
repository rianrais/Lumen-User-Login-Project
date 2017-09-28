<?php

namespace App\Http\Middleware;

use Closure;
use App\Http\Requests\Request;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
class JukirAuth
{
    /**
     * Run the request filter.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (
        substr($request->header('Authorization'), 0, 5) != "JUKIR" 
        && 
        substr($request->header('Authorization'), 6) != null
        ) {
            $token = explode(' ', $request->header('Authorization'))[1];
            $claims = JWTAuth::getJWTProvider()->decode($token);
                
            return "success :".date("Y-m-d", strtotime($claims['exp']));
        }

        echo "Not authenticated";
    }

}