<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Project;
use App\Support\AuditEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $action = (string) $request->query('action');

        $logs = AuditLog::query()
            ->with('user')
            ->when($action !== '', fn ($query) => $query->where('action', 'like', $action.'%'))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->whereContains(['description', 'ip_address'], $search)
                ->orWhereHas('user', fn ($u) => $u->whereContains(['name', 'email'], $search))))
            ->latest()->latest('id')
            ->paginate(50)
            ->withQueryString();

        // Some entries name their project by app id: show its name instead.
        $projects = Project::query()
            ->whereIn('app_id', $logs->getCollection()->map(fn (AuditLog $log) => $log->metadata['project'] ?? null)->filter()->unique())
            ->pluck('name', 'app_id')
            ->all();

        return view('admin.audit', [
            'logs' => $logs,
            'days' => $logs->getCollection()
                ->map(fn (AuditLog $log) => new AuditEntry($log, $projects))
                ->groupBy(fn (AuditEntry $entry) => $entry->log->created_at->toDateString()),
            'search' => $search,
            'action' => $action,
            'groups' => AuditEntry::groups(),
        ]);
    }
}
