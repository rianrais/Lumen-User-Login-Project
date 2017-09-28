<?php 

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

use App\User; 

class EmailVerification extends Mailable
{ 
    use Queueable, SerializesModels;
    
    protected $user; 
    
    public function __construct(User $user)
    { 
        $this->user = $user;
    }
    
    public function build()
    {
        return $this->view('verification')->with([
        'email_token' => $this->user->email_token,
        ]);
    }
}