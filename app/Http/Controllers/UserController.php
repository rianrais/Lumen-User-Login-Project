<?php

namespace App\Http\Controllers;

use App\User;
use App\Http\Controllers\Controller;

Class UserController extends Controller 
{
    public function viewUser(Request $request) 
    {
        $token = substr($request->header('Authorization'), 6);
        $user = User::where('auth_token', $token)->first();

        return response()->json($user, 200);
    }

    public function editUser(Request $request) 
    {
        $this->validate($request, [
            'email' => 'required',
            'phone_number' => 'required',
        ]);
        
        $token = substr($request->header('Authorization'), 6);
        $user = User::where('auth_token', $token)->first();

        $user->email = $request->input('email');
        $user->phone_number = $request->input('phone_number');
        $user->save();

        return response()->json(['message' => "Edit success"], 200);
    }
}

?>