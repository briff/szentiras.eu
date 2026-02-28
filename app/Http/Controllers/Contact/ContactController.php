<?php

namespace SzentirasHu\Http\Controllers\Contact;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Data\Entity\ContactMessage;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Rules\TurnstileValidationRule;

class ContactController extends Controller
{
    public function showForm()
    {
        $isLoggedIn = Session::has('anonymous_token');
        return view('contact.form', [
            'isLoggedIn' => $isLoggedIn,
        ]);
    }

    public function submit(): RedirectResponse
    {
        $isLoggedIn = Session::has('anonymous_token');
        $rules = [
            'message' => 'required|string|min:5|max:2000',
        ];

        if (!$isLoggedIn) {
            $rules['cf-turnstile-response'] = ['required', app(TurnstileValidationRule::class)];
        }

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $sender = null;
        if ($isLoggedIn) {
            $token = Session::get('anonymous_token');
            $sender = AnonymousId::where('token', $token)->first();
        }

        ContactMessage::create([
            'sender_anonymous_id' => $sender?->id,
            'message' => request('message'),
        ]);

        return redirect()->route('contact.thankyou');
    }

    public function thankYou()
    {
        return view('contact.thankyou');
    }
}