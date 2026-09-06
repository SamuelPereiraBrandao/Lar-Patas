<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\AuditLog;
use App\Models\ContentReport;
use App\Models\ProfilePost;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationsController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        abort_unless($r->user()->hasRole('admin'), 403);

        $reportPage = ContentReport::orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->latest('updated_at')->orderByDesc('id')->paginate(5, ['*'], 'reports_page');
        $reports = $reportPage->getCollection();
        $posts = ProfilePost::withoutGlobalScope('visible')->with('user:id,name,avatar_path')->whereIn('id', $reports->pluck('profile_post_id'))->get()->keyBy('id');
        $reporters = User::whereIn('id', $reports->pluck('user_id')->merge($reports->pluck('reviewed_by'))->filter())->get(['id', 'name'])->keyBy('id');
        $reports->each(function (ContentReport $report) use ($posts, $reporters): void {
            $report->setAttribute('post', $posts->get($report->profile_post_id));
            $report->setAttribute('post_body', $posts->get($report->profile_post_id)?->body);
            $report->setAttribute('reporter_name', $reporters->get($report->user_id)?->name);
            $report->setAttribute('reviewer_name', $reporters->get($report->reviewed_by)?->name);
        });

        return response()->json([
            'pending' => Adoption::with(['pet:id,name,image_path,shelter_id,status', 'pet.shelter:id,name,address,district,city,state', 'user:id,name,avatar_path,city,state'])->where('status', 'pending')->oldest()->limit(50)->get(),
            'pickups' => Adoption::with(['pet:id,name,image_path', 'user:id,name,avatar_path'])->where('status', 'approved')->whereNull('released_at')->whereNotNull('pickup_at')->orderBy('pickup_at')->limit(50)->get(),
            'followups' => Adoption::with(['pet:id,name,image_path', 'user:id,name'])->whereNotNull('released_at')->where(fn ($q) => $q->where(fn ($q) => $q->where('adaptation_status', 'needs_help')->whereNull('followup_resolved_at'))->orWhereNull('followup_completed_at'))->oldest('released_at')->limit(50)->get(),
            'reports' => $reports,
            'reports_pagination' => ['current_page' => $reportPage->currentPage(), 'last_page' => $reportPage->lastPage(), 'total' => $reportPage->total()],
            'audit' => AuditLog::query()->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')->select('audit_logs.*', 'users.name as actor')->orderByDesc('audit_logs.id')->paginate(20),
        ]);
    }

    public function resolve(Request $r, ContentReport $report): JsonResponse
    {
        abort_unless($r->user()->hasRole('admin'), 403);
        $data = $r->validate(['status' => 'required|in:dismissed,hidden', 'resolution' => 'nullable|string|max:2000', 'deactivate_author' => 'sometimes|boolean']);
        DB::transaction(function () use ($r, $report, $data): void {
            if ($data['deactivate_author'] ?? false) {
                $post = ProfilePost::withoutGlobalScope('visible')->findOrFail($report->profile_post_id);
                $author = User::whereKey($post->user_id)->lockForUpdate()->firstOrFail();
                abort_if($r->user()->is($author), 422, 'Você não pode inativar sua própria conta.');
                $author->forceFill(['is_active' => false, 'remember_token' => null, 'two_factor_code' => null, 'two_factor_expires_at' => null])->save();
                $author->tokens()->delete();
                if (config('session.driver') === 'database') {
                    DB::table('sessions')->where('user_id', $author->id)->delete();
                }
            }
            if ($data['status'] === 'hidden') {
                ProfilePost::withoutGlobalScopes()->whereKey($report->profile_post_id)->update(['hidden_at' => now()]);
            }
            $report->update(['status' => $data['status'], 'resolution' => ($data['resolution'] ?? '').(($data['deactivate_author'] ?? false) ? "\nAutor inativado nesta análise." : ''), 'reviewed_by' => $r->user()->id]);
        });

        return response()->json(['data' => $report]);
    }
}
