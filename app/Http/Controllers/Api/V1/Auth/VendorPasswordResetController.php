<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use Carbon\CarbonInterval;
use App\Models\BusinessSetting;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class VendorPasswordResetController extends Controller
{
    /** Marks rows in the shared password_resets table as vendor-issued. */
    private const RESET_SCOPE = 'vendor';

    /**
     * Single generic acknowledgement for the "request a reset" step.
     *
     * Account recovery must never confirm or deny that an address is
     * registered. Every non-validation outcome - vendor found, vendor absent,
     * or mail transport failure - returns this identical 200 body.
     */
    private function genericResetAcknowledgement()
    {
        return response()->json([
            'message' => translate('If an account matches that email, a password reset code has been sent.'),
        ], 200);
    }

    /**
     * Single generic rejection for the "verify token" and "submit new
     * password" steps, so an unknown email is indistinguishable from a known
     * email with a wrong OTP.
     */
    private function genericInvalidToken()
    {
        return response()->json(['errors' => [
            ['code' => 'reset_token', 'message' => translate('messages.invalid_otp')]
        ]], 400);
    }

    public function reset_password_request(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $vendor = Vendor::where(['email' => $request['email']])->first();

        // No vendor: acknowledge identically and, critically, do NOT write a
        // password_resets row. Issuing a token for an address that owns no
        // account would both leak existence and create an unowned credential.
        if (! isset($vendor)) {
            return $this->genericResetAcknowledgement();
        }

        $token = random_int(100000, 999999);

        // Match on the email only, so a repeat request rotates that vendor's
        // token in place instead of appending an unbounded number of rows
        // (the previous call passed token/created_at as match keys, so the
        // "update" branch was unreachable and every request inserted).
        DB::table('password_resets')->updateOrInsert(
            ['email' => $vendor->getRawOriginal('email')],
            [
                'token'           => $token,
                'created_at'      => now(),
                'otp_hit_count'   => 0,
                'is_temp_blocked' => 0,
                'temp_block_time' => null,
                // password_resets is shared by the customer, delivery-man and
                // vendor flows. Stamping the issuing role lets the vendor
                // endpoints refuse a token that was issued to a different
                // account type that merely shares this email address.
                'created_by'      => self::RESET_SCOPE,
            ]
        );

        if (config('mail.status')) {
            try {
                Mail::to($vendor?->getRawOriginal('email'))->send(new \App\Mail\PasswordResetMail($token));
            } catch (\Throwable $th) {
                // Swallow: a transport failure must not become an oracle that
                // proves the address exists. Log for operators instead.
                \Illuminate\Support\Facades\Log::error('Vendor password reset mail failed', [
                    'exception' => $th->getMessage(),
                ]);
            }
        }

        return $this->genericResetAcknowledgement();
    }

    public function verify_token(Request $request)
    {
        // NOTE: deliberately NOT 'exists:vendors,email'. That rule answered
        // with 403 + "email does not exist" for unregistered addresses while a
        // registered address got 400 "Invalid OTP" - a direct enumeration
        // oracle. Unknown emails now follow the same path as a bad OTP.
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'reset_token'=> 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $data = DB::table('password_resets')->where([
            'token'      => $request['reset_token'],
            'email'      => $request->email,
            'created_by' => self::RESET_SCOPE,
        ])->first();
        if (isset($data) || (config('app.debug') && config('app.env') === 'local' && getEnvMode()=='demo'&& $request['reset_token'] == '123456' )) {
            return response()->json(['message'=>translate("OTP found, you can proceed")], 200);
        } else{
            // $otp_hit = BusinessSetting::where('key', 'max_otp_hit')->first();
            // $max_otp_hit =isset($otp_hit) ? $otp_hit->value : 5 ;
            $max_otp_hit = 5;
            // $otp_hit_time = BusinessSetting::where('key', 'max_otp_hit_time')->first();
            // $max_otp_hit_time = isset($otp_hit_time) ? $otp_hit_time->value : 30 ;
            $max_otp_hit_time = 60; // seconds
            $temp_block_time = 600; // seconds
            $verification_data= DB::table('password_resets')
                ->where('email', $request->email)
                ->where('created_by', self::RESET_SCOPE)
                ->first();


            if(isset($verification_data)){
                $time= $temp_block_time - Carbon::parse($verification_data->temp_block_time)->DiffInSeconds();

                if(isset($verification_data->temp_block_time ) && Carbon::parse($verification_data->temp_block_time)->DiffInSeconds() <= $temp_block_time){
                    $time= $temp_block_time - Carbon::parse($verification_data->temp_block_time)->DiffInSeconds();

                    $errors = [];
                    array_push($errors, ['code' => 'otp_block_time', 'message' => translate('messages.please_try_again_after_').CarbonInterval::seconds($time)->cascade()->forHumans()
                ]);
                return response()->json([
                    'errors' => $errors
                ], 405);
                }

                if($verification_data->is_temp_blocked == 1 && Carbon::parse($verification_data->created_at)->DiffInSeconds() >= $max_otp_hit_time){
                    DB::table('password_resets')
                        ->where('email', $request->email)
                        ->where('created_by', self::RESET_SCOPE)
                        ->update([
                            'otp_hit_count' => 0,
                            'is_temp_blocked' => 0,
                            'temp_block_time' => null,
                            'created_at' => now(),
                        ]);
                    }

                if($verification_data->otp_hit_count >= $max_otp_hit &&  Carbon::parse($verification_data->created_at)->DiffInSeconds() < $max_otp_hit_time &&  $verification_data->is_temp_blocked == 0){

                    DB::table('password_resets')
                        ->where('email', $request->email)
                        ->where('created_by', self::RESET_SCOPE)
                        ->update([
                        'is_temp_blocked' => 1,
                        'temp_block_time' => now(),
                        'created_at' => now(),
                        ]);
                    $errors = [];
                    array_push($errors, ['code' => 'otp_temp_blocked', 'message' => translate('messages.Too_many_attemps') ]);
                    return response()->json([
                        'errors' => $errors
                    ], 405);
                }

                // Count the failed attempt only against an existing reset
                // record. Using updateOrInsert here would create a
                // password_resets row for an address that owns no account.
                DB::table('password_resets')
                    ->where('email', $request->email)
                    ->where('created_by', self::RESET_SCOPE)
                    ->update([
                        'otp_hit_count' => DB::raw('otp_hit_count + 1'),
                        'created_at' => now(),
                        'temp_block_time' => null,
                    ]);
            }
            // No reset record at all (unknown email, or none requested): fall
            // through to the same generic rejection, writing nothing.
        }
        return $this->genericInvalidToken();
    }

    public function reset_password_submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Not 'exists:vendors,email' - see verify_token. An unknown email
            // must not be answered differently from a wrong reset token.
            'email' => 'required|email',
            'reset_token'=> 'required',
            'password' => ['required', Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised()],
            'confirm_password'=> 'required|same:password',
        ],

        [
            'password.required' => translate('The password is required'),
            'password.min_length' => translate('The password must be at least :min characters long'),
            'password.mixed' => translate('The password must contain both uppercase and lowercase letters'),
            'password.letters' => translate('The password must contain letters'),
            'password.numbers' => translate('The password must contain numbers'),
            'password.symbols' => translate('The password must contain symbols'),
            'password.uncompromised' => translate('The password is compromised. Please choose a different one'),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        if(config('app.debug') && config('app.env') === 'local' && getEnvMode()=='demo') {
            if ($request['reset_token'] != '123456') {
                return response()->json(['errors' => [
                    ['code' => 'invalid', 'message' => trans('messages.invalid_otp')]
                ]], 400);
            }
            if ($request['password'] == $request['confirm_password']) {
                DB::table('vendors')->where(['email' => $request->email])->update([
                    'password' => bcrypt($request['confirm_password'])
                ]);
                DB::table('password_resets')->where(['token' => $request['reset_token']])->delete();
                return response()->json(['message' => translate('Password changed successfully.')], 200);
            }
            return response()->json(['errors' => [
                ['code' => 'mismatch', 'message' => translate('messages.password_mismatch')]
            ]], 401);
        }

        // Scope to vendor-issued tokens: password_resets is shared with the
        // customer and delivery-man flows, so an unscoped lookup let a token
        // issued to a same-email account of another type reset a vendor login.
        $data = DB::table('password_resets')->where([
            'email'      => $request['email'],
            'token'      => $request['reset_token'],
            'created_by' => self::RESET_SCOPE,
        ])->first();

        if (isset($data)) {
            if ($request['password'] == $request['confirm_password']) {
                $updated = DB::table('vendors')->where(['email' => $data->email])->update([
                    'password' => bcrypt($request['confirm_password'])
                ]);

                DB::table('password_resets')
                    ->where('email', $data->email)
                    ->where('token', $request['reset_token'])
                    ->where('created_by', self::RESET_SCOPE)
                    ->delete();

                // A valid token that matched no vendor row must not report
                // success - report the same generic rejection instead.
                if ($updated < 1) {
                    return $this->genericInvalidToken();
                }

                return response()->json(['message' => translate('Password changed successfully.')], 200);
            }
            return response()->json(['errors' => [
                ['code' => 'mismatch', 'message' => translate('messages.password_mismatch')]
            ]], 401);
        }
        return $this->genericInvalidToken();
    }
}
