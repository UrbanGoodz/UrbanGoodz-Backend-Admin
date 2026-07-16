<?php
namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzImpersonationSession;
use App\Models\UrbanGoodzBusinessPortalAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class AdminImpersonationController extends Controller
{
    // Only super admins (role_id == 1) can impersonate
    private function authorizeAdmin()
    {
        $admin = auth('admin')->user();
        abort_unless($admin && $admin->role_id == 1, 403, 'Only Super Admins can access Business Portal impersonation.');
        return $admin;
    }

    // POST /admin/business-clients/{id}/impersonate
    public function startImpersonation(Request $request, int $clientId)
    {
        $admin = $this->authorizeAdmin();
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);

        // End any active sessions first
        UrbanGoodzImpersonationSession::where('admin_id', $admin->id)
            ->where('is_active', true)
            ->each(fn($s) => $s->end($admin->id));

        $session = UrbanGoodzImpersonationSession::create([
            'admin_id' => $admin->id,
            'business_client_id' => $client->id,
            'mode' => $request->input('mode', 'read_only'),
            'session_token' => Str::random(64),
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'is_active' => true,
        ]);

        Session::put('impersonation_active', true);
        Session::put('impersonation_token', $session->session_token);
        Session::put('impersonation_client_id', $client->id);
        Session::put('impersonation_mode', $session->mode);
        Session::regenerate();

        UrbanGoodzBusinessPortalAuditLog::create([
            'admin_id' => $admin->id,
            'business_client_id' => $client->id,
            'action' => 'impersonate_start',
            'mode' => $session->mode,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('business.dashboard')
            ->with('impersonation_banner', true);
    }

    // POST /admin/business-clients/impersonation/exit
    public function exitImpersonation(Request $request)
    {
        $admin = $this->authorizeAdmin();

        $token = Session::get('impersonation_token');
        if ($token) {
            $session = UrbanGoodzImpersonationSession::findByToken($token);
            if ($session) {
                $session->end($admin->id);

                UrbanGoodzBusinessPortalAuditLog::create([
                    'admin_id' => $admin->id,
                    'business_client_id' => $session->business_client_id,
                    'action' => 'impersonate_exit',
                    'mode' => $session->mode,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        Session::forget('impersonation_active');
        Session::forget('impersonation_token');
        Session::forget('impersonation_client_id');
        Session::forget('impersonation_mode');
        Session::regenerate();

        return redirect()->route('admin.urban-goodz.business-clients.index')
            ->with('success', 'Exited Business Portal view.');
    }

    // GET /admin/business-clients/impersonation/audit-log
    public function auditLog(Request $request)
    {
        $admin = $this->authorizeAdmin();

        $logs = UrbanGoodzBusinessPortalAuditLog::query()
            ->with('client')
            ->latest()
            ->paginate(50);

        return view('admin-views.urban-goodz.impersonation.audit-log', compact('logs'));
    }
}
