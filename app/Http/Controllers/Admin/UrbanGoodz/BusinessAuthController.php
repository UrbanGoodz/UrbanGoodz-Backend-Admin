<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessAuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest:business', ['except' => 'logout']);
    }

    public function showLoginForm()
    {
        return view('business.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (Auth::guard('business')->attempt($credentials, $remember)) {
            $user = auth('business')->user();

            if (!$user->is_active || $user->status !== 'active') {
                Auth::guard('business')->logout();
                return redirect()->route('business.login')
                    ->withErrors(['Your account is not active. Please contact support.']);
            }

            $client = $user->client;
            if (!$client || $client->status !== 'approved') {
                Auth::guard('business')->logout();
                return redirect()->route('business.login')
                    ->withErrors(['Your company account is not active. Please contact support.']);
            }

            $user->last_login_at = now();
            $user->save();

            if ($client->isDispatchCompany() && $user->isDispatchRole()) {
                return redirect()->route('business.dispatcher.dashboard');
            }

            return redirect()->route('business.dashboard');
        }

        return redirect()->route('business.login')
            ->withErrors(['Invalid email or password.'])
            ->withInput($request->only('email', 'remember'));
    }

    public function logout()
    {
        Auth::guard('business')->logout();
        return redirect()->route('business.login');
    }
}
