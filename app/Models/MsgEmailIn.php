<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgEmailIn extends Model
{
    use HasFactory;

    protected $protected = ['id'];

    protected $casts = [
        'data_mail' => 'json',
        'tos' => 'array',
        'data_sellsy' => 'json'
    ];

    public function msg_email_user()
    {
        return $this->belongsTo(MsgUser::class);
    }

    public function getStatusAttribute() {
        if($this->is_rejected) {
            return $this->reject_info;
        } else {
            return $this->category;
        }
    }
}
