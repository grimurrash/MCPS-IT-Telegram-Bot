<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    use HasFactory;

    private static $tokenId = 1;

    protected $guarded = [];
    public $timestamps = false;

    public static function currentToken(){
        return self::find(self::$tokenId);
    }

    public function updateTokens($accessToken): void
    {
        $this->accessToken = $accessToken->getToken();
        $this->refreshToken = $accessToken->getRefreshToken();
        $this->tokenExpires = $accessToken->getExpires();
        $this->save();
    }

    public function clearTokens(): void
    {
        $this->update([
            'accessToken' => null,
            'refreshToken' => null,
            'tokenExpires' => null,
            'userName' => null,
            'userEmail' => null,
            'userTimeZone' => null
        ]);
    }
}
