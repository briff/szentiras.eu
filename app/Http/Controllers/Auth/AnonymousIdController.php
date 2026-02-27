<?php

namespace SzentirasHu\Http\Controllers\Auth;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Validator;
use Redirect;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Rules\TurnstileValidationRule;
use SzentirasHu\Rules\LocalRedirectRule;

class AnonymousIdController extends Controller
{
    public function showLoginForm() {
        if (session()->has('anonymous_token')) {
            return Redirect::to('/profile');
        }
        $redirect = request()->query('redirect');
        return view("auth.login", ['redirect' => $redirect]);
    }
    
    public function showAnonymousRegistrationForm() {
        if (session()->has('anonymous_token')) {
            return Redirect::to('/profile');
        }
        $redirect = request()->query('redirect');
        return view("auth.anonymousRegistration", ['redirect' => $redirect]);
    }

    public function registerAnonymousId() {
        $validator = Validator::make(request()->all(), [
            'approve' => 'accepted',
            'cf-turnstile-response' => ['required', new TurnstileValidationRule()],
            'redirect' => ['nullable', new LocalRedirectRule()],
        ]);
        
        // Check if redirect validation failed
        if ($validator->fails() && $validator->errors()->has('redirect')) {
            return response()->json(['error' => 'Invalid redirect URL'], 400);
        }
        
        // Check for other validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }
        
        $validated = $validator->validated();
        
        $token = $this->generateToken();
        // Save token to database
        $anonymousId = AnonymousId::create([
            'token' => $token,
            'last_login' => now(),
        ]);
        
        // Set session and cookie
        session(['anonymous_token' => $anonymousId->token]);
        Cookie::queue(Cookie::forever('anonymous_token', $anonymousId->token));
        
        // Redirect to target URL or profile
        $redirect = $validated['redirect'] ?? null;
        if ($redirect && $this->isValidLocalRedirect($redirect)) {
            return Redirect::to($redirect);
        }
        
        return Redirect::to("/profile/{$anonymousId->token}");
    }

    public function login() {
        $validator = Validator::make(request()->all(), [
            'anonymous_token' => 'required|exists:anonymous_ids,token',
            'redirect' => ['nullable', new LocalRedirectRule()],
        ]);
        
        // Check if redirect validation failed
        if ($validator->fails() && $validator->errors()->has('redirect')) {
            return response()->json(['error' => 'Invalid redirect URL'], 400);
        }
        
        // Check for other validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }
        
        $validated = $validator->validated();
        $anonymousId = AnonymousId::where('token', $validated['anonymous_token'])->first();
        
        session(['anonymous_token' => $anonymousId->token]);
        Cookie::queue(Cookie::forever('anonymous_token', $anonymousId->token));
        
        // Redirect to target URL or home
        $redirect = $validated['redirect'] ?? null;
        if ($redirect && $this->isValidLocalRedirect($redirect)) {
            return Redirect::to($redirect);
        }
        
        return Redirect::to('/');
    }

    public function showProfile(?string $profileId = null) {
        if (is_null($profileId)) {
            $profileId= session('anonymous_token');
        }
        $anonymousId = AnonymousId::where(
            'token', $profileId
        )->first();
        if (empty($anonymousId)) {
            return $this->logout();
        }
        session(['anonymous_token' => $anonymousId->token]);
        Cookie::queue(Cookie::forever('anonymous_token', $anonymousId->token));
        
        // Check for redirect parameter
        $redirect = request()->query('redirect');
        
        // If a valid redirect parameter is provided, redirect immediately (auto-login)
        if ($redirect) {
            if ($this->isValidLocalRedirect($redirect)) {
            return Redirect::to($redirect);
            } else {
                return response(null, 400);
            }
        } 
               
        return view("auth.anonymousId", [
            'anonymousId' => $anonymousId,
        ]);
    }
    
    public function logout() {
        Cookie::queue(Cookie::forget('anonymous_token'));
        session()->forget('anonymous_token');
        return Redirect::to('/');
    }

    private function generateToken() {
        return $this->shortenUuid((string)\Illuminate\Support\Str::uuid()->getHex());
    }

    private function shortenUuid(string $hexUuid)  {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $value = new \GMP($hexUuid, 16);
        $result = '';

        while (gmp_cmp($value, 0) > 0) {
            list($value, $remainder) = gmp_div_qr($value, 62);
            $result .= $chars[gmp_intval($remainder)];
        }
        return strrev($result);
    }
    
    private function isValidLocalRedirect(string $url): bool
    {
        return !empty($url) && LocalRedirectRule::isLocalPath($url);
    }
    
}
