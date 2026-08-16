<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class MessageLogController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();

        $query = Message::query()
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.tenant_id', $tenantId)
            ->select('messages.*', 'conversations.customer_number');

        // RBAC: Members only see logs for conversations with contacts assigned to them
        if (auth()->user() && method_exists(auth()->user(), 'isMember') && auth()->user()->isMember()) {
            $query->whereExists(function ($q) use ($tenantId) {
                $q->select(DB::raw(1))
                  ->from('contacts')
                  ->whereColumn('contacts.phone_number', 'conversations.customer_number')
                  ->where('contacts.tenant_id', $tenantId)
                  ->where('contacts.assigned_user_id', auth()->id());
            });
        }

        if ($request->filled('status')) {
            $query->where('messages.status', $request->status);
        }
        
        if ($request->filled('direction')) {
            $query->where('messages.direction', $request->direction);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('conversations.customer_number', 'like', "%{$search}%")
                  ->orWhere('messages.id', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderBy('messages.created_at', 'desc')->paginate(50)->withQueryString();

        return Inertia::render('Logs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['status', 'direction', 'search'])
        ]);
    }
}
