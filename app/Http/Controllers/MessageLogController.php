<?php

namespace App\Http\Controllers;

use App\Models\WhatsappMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class MessageLogController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();

        $query = WhatsappMessage::query()
            ->join('conversations', 'whatsapp_messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.tenant_id', $tenantId)
            ->select('whatsapp_messages.*', 'conversations.customer_number');

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
            $query->where('whatsapp_messages.status', $request->status);
        }
        
        if ($request->filled('direction')) {
            $query->where('whatsapp_messages.direction', $request->direction);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('conversations.customer_number', 'like', "%{$search}%")
                  ->orWhere('whatsapp_messages.id', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderBy('whatsapp_messages.created_at', 'desc')->paginate(50)->withQueryString();

        return Inertia::render('Logs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['status', 'direction', 'search'])
        ]);
    }
}
