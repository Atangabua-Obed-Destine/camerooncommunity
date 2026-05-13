<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PeopleController extends Controller
{
    /**
     * Facebook-style People page.
     *
     * Sections (via ?tab=):
     *   - home        : mixed (incoming requests + suggestions)
     *   - requests    : incoming pending connection requests
     *   - suggestions : people the viewer is not connected with yet
     *   - all         : every user in the network (paginated)
     *   - connections : the viewer's accepted connections
     */
    public function index(Request $request)
    {
        $viewer = Auth::user();
        $tab    = $request->query('tab', 'home');
        $q      = trim((string) $request->query('q', ''));

        // Helper: base query of "browsable" users (everyone except viewer & blocked pair members).
        $blockedIds = method_exists($viewer, 'blockedUserIds')
            ? $viewer->blockedUserIds()
            : collect();

        $browsable = User::query()
            ->whereKeyNot($viewer->id)
            ->when(!$blockedIds->isEmpty(), fn ($q2) => $q2->whereNotIn('id', $blockedIds))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                       ->orWhere('username', 'like', "%{$q}%");
                });
            });

        // Counts (used by the sidebar)
        $incomingRequestsCount = UserConnection::where('status', UserConnection::STATUS_PENDING)
            ->where(function ($w) use ($viewer) {
                $w->where('user_a_id', $viewer->id)->orWhere('user_b_id', $viewer->id);
            })
            ->where('requested_by', '!=', $viewer->id)
            ->count();

        $myConnectionsCount = UserConnection::where('status', UserConnection::STATUS_ACCEPTED)
            ->where(function ($w) use ($viewer) {
                $w->where('user_a_id', $viewer->id)->orWhere('user_b_id', $viewer->id);
            })
            ->count();

        // Section data
        $incoming    = collect();
        $suggestions = collect();
        $allPeople   = null;   // paginator
        $myConnections = collect();

        switch ($tab) {
            case 'requests':
                $incoming = $this->incomingRequests($viewer);
                break;

            case 'suggestions':
                $suggestions = $this->suggestions($viewer, $browsable, 60);
                break;

            case 'all':
                $allPeople = $browsable->orderByDesc('id')->paginate(24)->withQueryString();
                break;

            case 'connections':
                $myConnections = $this->myConnections($viewer);
                break;

            case 'home':
            default:
                $incoming    = $this->incomingRequests($viewer);
                $suggestions = $this->suggestions($viewer, $browsable, 18);
                break;
        }

        return view('people.index', [
            'tab' => $tab,
            'q'   => $q,
            'incomingRequestsCount' => $incomingRequestsCount,
            'myConnectionsCount'    => $myConnectionsCount,
            'incoming'      => $incoming,
            'suggestions'   => $suggestions,
            'allPeople'     => $allPeople,
            'myConnections' => $myConnections,
        ]);
    }

    /** Pending requests directed at the viewer. */
    private function incomingRequests(User $viewer)
    {
        $rows = UserConnection::with(['userA', 'userB', 'requester'])
            ->where('status', UserConnection::STATUS_PENDING)
            ->where(function ($w) use ($viewer) {
                $w->where('user_a_id', $viewer->id)->orWhere('user_b_id', $viewer->id);
            })
            ->where('requested_by', '!=', $viewer->id)
            ->orderByDesc('created_at')
            ->limit(60)
            ->get();

        return $rows->map(function ($c) use ($viewer) {
            $other = $c->user_a_id === $viewer->id ? $c->userB : $c->userA;
            return ['user' => $other, 'connection_id' => $c->id];
        })->filter(fn ($x) => $x['user']);
    }

    /** A simple suggestion list — recent active users not yet connected to viewer. */
    private function suggestions(User $viewer, $browsableQuery, int $limit)
    {
        // Exclude anyone already involved in a connection record with the viewer.
        $connectedIds = UserConnection::query()
            ->where(function ($w) use ($viewer) {
                $w->where('user_a_id', $viewer->id)->orWhere('user_b_id', $viewer->id);
            })
            ->get(['user_a_id', 'user_b_id'])
            ->flatMap(fn ($r) => [$r->user_a_id, $r->user_b_id])
            ->unique()
            ->reject(fn ($id) => $id === $viewer->id)
            ->values();

        return (clone $browsableQuery)
            ->whereNotIn('id', $connectedIds)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** Accepted connections for the viewer. */
    private function myConnections(User $viewer)
    {
        $rows = UserConnection::with(['userA', 'userB'])
            ->where('status', UserConnection::STATUS_ACCEPTED)
            ->where(function ($w) use ($viewer) {
                $w->where('user_a_id', $viewer->id)->orWhere('user_b_id', $viewer->id);
            })
            ->orderByDesc('accepted_at')
            ->limit(200)
            ->get();

        return $rows->map(function ($c) use ($viewer) {
            return $c->user_a_id === $viewer->id ? $c->userB : $c->userA;
        })->filter();
    }
}
