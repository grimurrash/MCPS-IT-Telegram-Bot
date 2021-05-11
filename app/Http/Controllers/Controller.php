<?php

namespace App\Http\Controllers;

use App\Models\Token;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function loadViewData(): array
    {
        $viewData = [];


        // Check for flash errors
        if (session('error')) {
            $viewData['error'] = session('error');
            $viewData['errorDetail'] = session('errorDetail');
        }

        $token = Token::currentToken();
        // Check for logged on user
        if ($token->userName !== null)
        {
            $viewData['userName'] = $token->userName;
            $viewData['userEmail'] = $token->userEmail;
            $viewData['userTimeZone'] = $token->userTimeZone;
        }

        return $viewData;
    }
}
