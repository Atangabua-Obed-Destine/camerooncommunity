<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Solidarity\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\SolidarityCampaign;
use App\Models\User;
use App\Models\UserLocationHistory;
use App\Models\YardMessage;
use App\Models\SponsoredAd;
use App\Models\YardRoom;
use App\Services\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'totalUsers' => $totalUsers = User::count(),
            'activeToday' => $activeToday = User::whereDate('last_active_at', today())->count(),
            'messagesToday' => $messagesToday = YardMessage::whereDate('created_at', today())->count(),
            'activeCampaigns' => $activeCampaigns = SolidarityCampaign::active()->count(),
            'pendingReports' => $pendingReports = Report::where('status', 'pending')->count(),
            'pendingSolidarity' => $pendingSolidarity = SolidarityCampaign::pending()->count(),
            'newUsersToday' => User::whereDate('created_at', today())->count(),
        ];

        $aiInsight = \Illuminate\Support\Facades\Cache::remember('admin_ai_insight_' . today()->toDateString(), now()->addHours(6), function () use ($stats) {
            return app(\App\Services\AIService::class)->generateDashboardInsight($stats);
        });

        return view('admin.dashboard', [
            'totalUsers' => $totalUsers,
            'activeToday' => $activeToday,
            'messagesToday' => $messagesToday,
            'activeCampaigns' => $activeCampaigns,
            'pendingReports' => $pendingReports,
            'pendingSolidarity' => $pendingSolidarity,
            'recentUsers' => User::latest()->limit(5)->get(),
            'pendingCampaigns' => SolidarityCampaign::pending()->with('creator', 'room')->latest()->limit(5)->get(),
            'topRooms' => YardRoom::orderByDesc('messages_count')->limit(5)->get(),
            'aiInsight' => $aiInsight,
        ]);
    }

    public function users(Request $request)
    {
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($country = $request->input('country')) {
            $query->where('current_country', $country);
        }

        if ($region = $request->input('region')) {
            $query->where('current_region', $region);
        }

        // "Who has moved recently" is the question that sends an admin to this
        // page during an incident, so it is a filter rather than a sort.
        if ($request->input('moved') === '24h') {
            $query->where('location_updated_at', '>=', now()->subDay());
        } elseif ($request->input('moved') === '7d') {
            $query->where('location_updated_at', '>=', now()->subWeek());
        }

        if ($request->input('status') === 'suspended') {
            $query->where('is_active', false);
        } elseif ($request->input('status') === 'active') {
            $query->where('is_active', true);
        }

        $users = $query->latest()->paginate(25)->withQueryString();

        return view('admin.users', [
            'users'   => $users,
            'regions' => User::query()
                ->whereNotNull('current_region')
                ->distinct()
                ->orderBy('current_region')
                ->pluck('current_region'),
        ]);
    }

    public function showUser(User $user)
    {
        $stats = [
            'rooms_joined'  => \DB::table('yard_room_members')->where('user_id', $user->id)->count(),
            'messages_sent' => \DB::table('yard_messages')->where('user_id', $user->id)->count(),
            'reports_filed' => \DB::table('reports')->where('reporter_id', $user->id)->count(),
            'roles'         => $user->getRoleNames(),
        ];

        // Only fetched for people allowed to see it; the view hides the card
        // entirely otherwise.
        $recentLocations = auth()->user()->can('view_user_location')
            ? UserLocationHistory::where('user_id', $user->id)
                ->latest('created_at')
                ->limit(5)
                ->get()
            : collect();

        return view('admin.user-show', compact('user', 'stats', 'recentLocations'));
    }

    /**
     * A member's movement history.
     *
     * Gated by `view_user_location` rather than a role, so the line can be
     * moved without a deploy, and every visit is written to the audit log —
     * following someone around should itself leave a trace.
     */
    public function userLocations(Request $request, User $user)
    {
        abort_unless(auth()->user()->can('view_user_location'), 403);

        $from = $request->date('from') ?: now()->subDays(30)->startOfDay();
        $to   = $request->date('to') ?: now();

        $query = UserLocationHistory::where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->latest('created_at');

        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['from' => $from->toDateString(), 'to' => $to->toDateString()])
            ->log('Viewed location history');

        if ($request->input('export') === 'csv') {
            return $this->streamLocationCsv($user, (clone $query)->get());
        }

        return view('admin.user-locations', [
            'user'   => $user,
            'points' => $query->paginate(100)->withQueryString(),
            // The map draws the route oldest-first, capped so one very busy
            // member cannot push megabytes of coordinates into the page.
            'track'  => (clone $query)->reorder('created_at')->limit(500)
                ->get(['lat', 'lng', 'city', 'region', 'country', 'source', 'created_at']),
            'from'   => $from,
            'to'     => $to,
        ]);
    }

    private function streamLocationCsv(User $user, $points)
    {
        $filename = 'locations-' . ($user->username ?: $user->id) . '-' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($points) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['recorded_at', 'latitude', 'longitude', 'city', 'region', 'country', 'source', 'ip']);

            foreach ($points as $p) {
                fputcsv($out, [
                    $p->created_at?->toIso8601String(),
                    $p->lat, $p->lng, $p->city, $p->region, $p->country, $p->source, $p->ip_address,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Everyone's latest known position on one map.
     *
     * Reads the snapshot columns on `users` rather than the history table, so
     * it stays a single indexed query however long the trails get.
     */
    public function map(Request $request)
    {
        abort_unless(auth()->user()->can('view_user_location'), 403);

        $query = User::query()
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng');

        if ($country = $request->input('country')) {
            $query->where('current_country', $country);
        }

        if ($seen = $request->input('seen')) {
            $query->where('last_active_at', '>=', $seen === '24h' ? now()->subDay() : now()->subWeek());
        }

        return view('admin.map', [
            'people' => $query->limit(2000)->get([
                'id', 'name', 'username', 'avatar', 'current_lat', 'current_lng',
                'current_city', 'current_region', 'current_country', 'last_active_at',
            ]),
            'countries' => User::whereNotNull('current_country')->distinct()->orderBy('current_country')->pluck('current_country'),
        ]);
    }

    public function toggleAdmin(User $user)
    {
        // Prevent removing your own admin role
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot change your own role.');
        }

        if ($user->hasRole('admin')) {
            $user->removeRole('admin');
            return back()->with('success', "{$user->name} is no longer an admin.");
        }

        $user->assignRole('admin');
        return back()->with('success', "{$user->name} has been made an admin.");
    }

    /**
     * Suspend or restore an account.
     *
     * `is_active` is already enforced by the EnsureUserActive middleware, which
     * signs the member out on their next request — this just gives it a button
     * and makes the reason part of the permanent record.
     */
    public function toggleSuspension(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $data = $request->validate(['reason' => 'nullable|string|max:500']);
        $suspending = (bool) $user->is_active;

        $user->forceFill(['is_active' => ! $suspending])->save();

        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['reason' => $data['reason'] ?? null])
            ->log($suspending ? 'Suspended account' : 'Restored account');

        return back()->with('success', $suspending
            ? "{$user->name} has been suspended."
            : "{$user->name} has been restored.");
    }

    /**
     * Sign a member out everywhere.
     *
     * Sessions live in files on the server, so there is no row to delete.
     * Bumping the stamp invalidates every existing session for that member the
     * next time Laravel validates one.
     */
    public function forceLogout(User $user)
    {
        $user->forceFill(['remember_token' => \Illuminate\Support\Str::random(60)])->save();

        \Illuminate\Support\Facades\DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();

        activity()->performedOn($user)->causedBy(auth()->user())->log('Forced logout');

        return back()->with('success', "{$user->name} has been signed out on all devices.");
    }

    /**
     * See the app as a member sees it.
     *
     * The most useful support tool there is, and the most dangerous button on
     * the page: it needs its own permission, never applies to another admin,
     * and both entering and leaving are logged. The original admin id is kept
     * in the session so the banner can hand it back.
     */
    public function impersonate(User $user)
    {
        abort_unless(auth()->user()->can('impersonate_users'), 403);

        if ($user->id === auth()->id()) {
            return back();
        }

        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            return back()->with('error', 'You cannot impersonate another administrator.');
        }

        activity()->performedOn($user)->causedBy(auth()->user())->log('Started impersonation');

        session(['impersonator_id' => auth()->id()]);
        auth()->login($user);

        return redirect()->route('home');
    }

    /** Hand the session back to the admin who started impersonating. */
    public function stopImpersonating()
    {
        $adminId = session('impersonator_id');

        if (! $adminId) {
            return redirect()->route('home');
        }

        $admin = User::find($adminId);
        session()->forget('impersonator_id');

        if (! $admin) {
            auth()->logout();

            return redirect()->route('login');
        }

        activity()->performedOn(auth()->user())->causedBy($admin)->log('Stopped impersonation');
        auth()->login($admin);

        return redirect()->route('admin.users');
    }

    /**
     * Operational state of the things that fail quietly: realtime, the queue,
     * and the configuration that only bites in production.
     */
    public function health()
    {
        abort_unless(auth()->user()->can('view_system_health'), 403);

        $probe = app(\App\Services\RealtimeProbe::class)->run();

        $manifest = public_path('build/manifest.json');

        return view('admin.health', [
            'realtime' => $probe,
            'queue' => [
                'driver'  => config('queue.default'),
                'pending' => \Illuminate\Support\Facades\Schema::hasTable('jobs')
                    ? \Illuminate\Support\Facades\DB::table('jobs')->count() : null,
                'failed'  => \Illuminate\Support\Facades\Schema::hasTable('failed_jobs')
                    ? \Illuminate\Support\Facades\DB::table('failed_jobs')->count() : null,
                'recentFailures' => \Illuminate\Support\Facades\Schema::hasTable('failed_jobs')
                    ? \Illuminate\Support\Facades\DB::table('failed_jobs')->latest('failed_at')->limit(5)->get()
                    : collect(),
            ],
            'config' => [
                'env'            => config('app.env'),
                // Debug mode in production prints the environment, secrets and
                // all, on any unhandled error.
                'debug'          => config('app.debug'),
                'url'            => config('app.url'),
                'configCached'   => file_exists(base_path('bootstrap/cache/config.php')),
                'storageLinked'  => is_link(public_path('storage')) || is_dir(public_path('storage')),
                'buildManifest'  => file_exists($manifest) ? substr(md5_file($manifest), 0, 8) : null,
                'cacheDriver'    => config('cache.default'),
                'sessionDriver'  => config('session.driver'),
                'broadcastDriver'=> config('broadcasting.default'),
            ],
            'errors' => $this->recentLogErrors(),
        ]);
    }

    /** Today's ERROR lines, grouped so repeats read as one problem. */
    private function recentLogErrors(int $limit = 8): array
    {
        $candidates = [
            storage_path('logs/laravel-' . now()->toDateString() . '.log'),
            storage_path('logs/laravel.log'),
        ];

        foreach ($candidates as $path) {
            if (! is_file($path)) {
                continue;
            }

            // Only the tail: these files reach hundreds of megabytes.
            $size = filesize($path);
            $handle = fopen($path, 'r');
            fseek($handle, max(0, $size - 256 * 1024));
            $tail = fread($handle, 256 * 1024) ?: '';
            fclose($handle);

            $counts = [];
            foreach (explode("
", $tail) as $line) {
                if (! str_contains($line, '.ERROR:')) {
                    continue;
                }
                $message = trim(\Illuminate\Support\Str::limit(\Illuminate\Support\Str::after($line, '.ERROR:'), 160));
                $counts[$message] = ($counts[$message] ?? 0) + 1;
            }

            arsort($counts);

            return array_slice($counts, 0, $limit, true);
        }

        return [];
    }

    public function yard()
    {
        $rooms = YardRoom::withCount('members')
            ->orderByDesc('last_message_at')
            ->paginate(25);

        return view('admin.yard', compact('rooms'));
    }

    public function solidarity(Request $request)
    {
        $tab = $request->input('tab', 'pending');

        $campaigns = SolidarityCampaign::with(['creator', 'room'])
            ->when($tab === 'pending', fn ($q) => $q->pending())
            ->when($tab === 'active', fn ($q) => $q->active())
            ->when($tab === 'completed', fn ($q) => $q->where('status', CampaignStatus::Completed))
            ->when($tab === 'rejected', fn ($q) => $q->where('status', CampaignStatus::Rejected))
            ->latest()
            ->paginate(20);

        return view('admin.solidarity', compact('campaigns', 'tab'));
    }

    public function approveCampaign(SolidarityCampaign $campaign)
    {
        $campaign->update([
            'status' => CampaignStatus::Active,
            'approved_by' => auth()->id(),
        ]);

        // Post solidarity card as system message in originating room + national room
        if ($campaign->room_id) {
            $this->postSolidaritySystemMessage($campaign, $campaign->room_id);
        }

        // Find national room for the room's country
        $room = $campaign->room;
        if ($room && $room->country) {
            $nationalRoom = YardRoom::where('room_type', 'national')
                ->where('country', $room->country)
                ->first();
            if ($nationalRoom && $nationalRoom->id !== $campaign->room_id) {
                $this->postSolidaritySystemMessage($campaign, $nationalRoom->id);
            }
        }

        return back()->with('success', 'Campaign approved and published.');
    }

    public function rejectCampaign(Request $request, SolidarityCampaign $campaign)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $campaign->update([
            'status' => CampaignStatus::Rejected,
            'rejection_reason' => $request->reason,
        ]);

        return back()->with('success', 'Campaign rejected.');
    }

    protected function postSolidaritySystemMessage(SolidarityCampaign $campaign, int $roomId)
    {
        YardMessage::create([
            'tenant_id' => $campaign->tenant_id,
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'room_id' => $roomId,
            'user_id' => $campaign->created_by,
            'message_type' => \App\Enums\MessageType::SolidarityCard,
            'content' => $campaign->title,
            'solidarity_campaign_id' => $campaign->id,
        ]);
    }

    public function moderation()
    {
        $flaggedMessages = YardMessage::where('is_flagged', true)
            ->with(['user', 'room'])
            ->latest()
            ->paginate(20);

        return view('admin.moderation', compact('flaggedMessages'));
    }

    public function dismissFlag(YardMessage $message)
    {
        $message->update([
            'is_flagged' => false,
            'ai_moderation_score' => null,
            'ai_moderation_detail' => null,
        ]);

        activity()->performedOn($message)->causedBy(auth()->user())->log('Dismissed flag on message');

        return back()->with('success', 'Flag dismissed.');
    }

    public function deleteMessage(YardMessage $message)
    {
        $message->update(['is_deleted' => true]);

        activity()->performedOn($message)->causedBy(auth()->user())->log('Deleted flagged message');

        return back()->with('success', 'Message deleted.');
    }

    public function reports()
    {
        $reports = Report::with(['reportable', 'reporter'])
            ->latest()
            ->paginate(20);

        return view('admin.reports', compact('reports'));
    }

    public function cms()
    {
        $pages = \App\Models\CmsPage::orderBy('title')->get();
        return view('admin.cms', compact('pages'));
    }

    public function settings()
    {
        $settings = \App\Models\PlatformSetting::all()->groupBy('group');
        $branding = [
            'site_name' => SiteSettings::name(),
            'site_logo' => SiteSettings::get('site_logo'),
            'site_favicon' => SiteSettings::get('site_favicon'),
        ];
        return view('admin.settings', compact('settings', 'branding'));
    }

    public function updateSettings(Request $request)
    {
        foreach ($request->input('settings', []) as $key => $value) {
            \App\Models\PlatformSetting::setValue($key, $value);

            // Notify all connected clients in real-time so the change
            // takes effect without a page reload (e.g. Location Detection
            // Mode flips between GPS and IP across all open tabs).
            try {
                broadcast(new \App\Events\PlatformSettingUpdated($key, $value));
            } catch (\Throwable $e) {
                \Log::warning('Broadcast PlatformSettingUpdated failed: '.$e->getMessage());
            }
        }

        SiteSettings::clearCache();

        return back()->with('success', 'Settings updated.');
    }

    public function updateBranding(Request $request)
    {
        $request->validate([
            'site_name' => 'required|string|max:100',
            'site_logo' => 'nullable|image|mimes:png,jpg,jpeg,gif,svg,webp|max:2048',
            'site_favicon' => 'nullable|image|mimes:png,jpg,jpeg,ico,svg|max:512',
        ]);

        \App\Models\PlatformSetting::setValue('site_name', $request->input('site_name'), 'branding');

        if ($request->hasFile('site_logo')) {
            // Delete old logo
            $oldLogo = SiteSettings::get('site_logo');
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }

            $path = $request->file('site_logo')->store('branding', 'public');
            \App\Models\PlatformSetting::setValue('site_logo', $path, 'branding');
        }

        if ($request->hasFile('site_favicon')) {
            $oldFavicon = SiteSettings::get('site_favicon');
            if ($oldFavicon) {
                Storage::disk('public')->delete($oldFavicon);
            }

            $path = $request->file('site_favicon')->store('branding', 'public');
            \App\Models\PlatformSetting::setValue('site_favicon', $path, 'branding');
        }

        if ($request->boolean('remove_logo')) {
            $oldLogo = SiteSettings::get('site_logo');
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
            \App\Models\PlatformSetting::setValue('site_logo', null, 'branding');
        }

        if ($request->boolean('remove_favicon')) {
            $oldFavicon = SiteSettings::get('site_favicon');
            if ($oldFavicon) {
                Storage::disk('public')->delete($oldFavicon);
            }
            \App\Models\PlatformSetting::setValue('site_favicon', null, 'branding');
        }

        SiteSettings::clearCache();

        return back()->with('success', 'Branding updated successfully.');
    }

    public function tenants()
    {
        $tenants = \App\Models\Tenant::all();
        return view('admin.tenants', compact('tenants'));
    }

    public function ai()
    {
        return view('admin.ai', [
            'conversationCount' => \Illuminate\Support\Facades\DB::table('sessions')
                ->where('payload', 'like', '%kamer_chat_history%')
                ->count(),
            'moderationCount' => YardMessage::where('is_flagged', true)->count(),
            'recentFlagged' => YardMessage::where('is_flagged', true)
                ->with(['user:id,name', 'room:id,name'])
                ->latest()
                ->take(10)
                ->get(),
        ]);
    }

    public function updateAiSettings(Request $request)
    {
        $request->validate([
            'ai_system_prompt' => 'nullable|string|max:5000',
            'auto_flag_threshold' => 'nullable|integer|min:0|max:100',
            'auto_delete_threshold' => 'nullable|integer|min:0|max:100',
            'solidarity_risk_threshold' => 'nullable|integer|min:0|max:100',
            'openai_enabled' => 'nullable|string|in:true,false',
        ]);

        foreach (['ai_system_prompt', 'auto_flag_threshold', 'auto_delete_threshold', 'solidarity_risk_threshold', 'openai_enabled'] as $key) {
            if ($request->has($key)) {
                \App\Models\PlatformSetting::setValue($key, $request->input($key));
            }
        }

        return back()->with('success', 'AI settings updated.');
    }

    public function audit(Request $request)
    {
        $logs = \Spatie\Activitylog\Models\Activity::with('causer')
            ->latest()
            ->paginate(50);

        return view('admin.audit', compact('logs'));
    }

    public function analytics()
    {
        // Ten minutes is fresh enough for a dashboard and keeps a page refresh
        // from re-running a dozen aggregates, the way the AI insight is cached.
        $extra = \Illuminate\Support\Facades\Cache::remember('admin_analytics_v1', now()->addMinutes(10), function () {
            return [
                'activity'   => $this->activityCounts(),
                'engagement' => $this->engagementCounts(),
                'calls'      => $this->callCounts(),
                'funnel'     => $this->funnelCounts(),
            ];
        });

        return view('admin.analytics', array_merge($extra, [
            'userGrowth' => User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupByRaw('DATE(created_at)')
                ->orderBy('date')
                ->get(),
            'countryCounts' => User::selectRaw('current_country, COUNT(*) as count')
                ->whereNotNull('current_country')
                ->groupBy('current_country')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
        ]));
    }

    /** Daily / weekly / monthly actives, from last_active_at. */
    private function activityCounts(): array
    {
        return [
            'dau' => User::where('last_active_at', '>=', now()->subDay())->count(),
            'wau' => User::where('last_active_at', '>=', now()->subWeek())->count(),
            'mau' => User::where('last_active_at', '>=', now()->subMonth())->count(),
            // Of the people who signed up 7+ days ago, how many came back this week.
            'returning' => User::where('created_at', '<', now()->subWeek())
                ->where('last_active_at', '>=', now()->subWeek())
                ->count(),
            'total' => User::count(),
        ];
    }

    /** What people actually did, per day, over the last fortnight. */
    private function engagementCounts(): array
    {
        $since = now()->subDays(14)->startOfDay();

        return [
            'messages' => YardMessage::where('created_at', '>=', $since)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')->orderBy('date')->get(),
            'listings' => \Illuminate\Support\Facades\Schema::hasTable('marketplace_listings')
                ? \Illuminate\Support\Facades\DB::table('marketplace_listings')
                    ->where('created_at', '>=', $since)
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                    ->groupByRaw('DATE(created_at)')->orderBy('date')->get()
                : collect(),
            'messagesToday' => YardMessage::whereDate('created_at', today())->count(),
        ];
    }

    /**
     * Calls, with the number that matters: how many actually connected.
     * Nothing else in the panel shows whether calling works.
     */
    private function callCounts(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('yard_calls')) {
            return ['total' => 0, 'answered' => 0, 'rate' => null, 'medianSeconds' => null];
        }

        $since = now()->subDays(30);

        $total = \Illuminate\Support\Facades\DB::table('yard_calls')->where('created_at', '>=', $since)->count();
        $answered = \Illuminate\Support\Facades\DB::table('yard_calls')
            ->where('created_at', '>=', $since)
            ->where('duration_seconds', '>', 0)
            ->count();

        $durations = \Illuminate\Support\Facades\DB::table('yard_calls')
            ->where('created_at', '>=', $since)
            ->where('duration_seconds', '>', 0)
            ->orderBy('duration_seconds')
            ->pluck('duration_seconds');

        return [
            'total'         => $total,
            'answered'      => $answered,
            'rate'          => $total > 0 ? round($answered / $total * 100) : null,
            'medianSeconds' => $durations->count() ? (int) $durations[intdiv($durations->count(), 2)] : null,
        ];
    }

    /** Signup → onboarded → in a room → first message, and where people stop. */
    private function funnelCounts(): array
    {
        $registered = User::count();

        $onboarded = User::whereNotNull('current_country')->count();

        $inRoom = \Illuminate\Support\Facades\DB::table('yard_room_members')
            ->distinct()->count('user_id');

        $spoke = YardMessage::distinct()->count('user_id');

        return [
            ['label' => 'Registered',    'count' => $registered],
            ['label' => 'Gave location', 'count' => $onboarded],
            ['label' => 'Joined a room', 'count' => $inRoom],
            ['label' => 'Sent a message','count' => $spoke],
        ];
    }

    // ── Sponsored Ads ──

    public function sponsoredAds(Request $request)
    {
        $tab = $request->get('tab', 'all');

        $query = SponsoredAd::latest();

        $query = match ($tab) {
            'active' => $query->where('status', 'active'),
            'draft' => $query->where('status', 'draft'),
            'paused' => $query->where('status', 'paused'),
            'expired' => $query->where(function ($q) {
                $q->where('status', 'expired')
                  ->orWhere(fn ($q2) => $q2->where('status', 'active')->where('expires_at', '<', now()));
            }),
            default => $query,
        };

        return view('admin.sponsored-ads', [
            'ads' => $query->paginate(15),
            'tab' => $tab,
            'stats' => [
                'total' => SponsoredAd::count(),
                'active' => SponsoredAd::where('status', 'active')->count(),
                'totalImpressions' => SponsoredAd::sum('impressions'),
                'totalClicks' => SponsoredAd::sum('clicks'),
            ],
        ]);
    }

    public function createAd()
    {
        return view('admin.sponsored-ads-form', ['ad' => null]);
    }

    public function storeAd(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|max:2048',
            'image_url' => 'nullable|url|max:500',
            'video_url' => 'nullable|url|max:500',
            'link_url' => 'nullable|url|max:500',
            'link_label' => 'nullable|string|max:100',
            'advertiser_name' => 'nullable|string|max:255',
            'placement' => 'required|in:yard_sidebar,home_banner',
            'status' => 'required|in:draft,active,paused',
            'priority' => 'nullable|integer|min:0|max:100',
            'budget' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('ads', 'public');
            $validated['image_url'] = null;
        } elseif ($request->filled('image_url')) {
            $validated['image_path'] = null;
        }
        unset($validated['image']);

        $validated['created_by'] = auth()->id();
        $validated['priority'] = $validated['priority'] ?? 0;

        SponsoredAd::create($validated);

        return redirect()->route('admin.sponsored-ads')->with('success', 'Ad created successfully.');
    }

    public function editAd(SponsoredAd $ad)
    {
        return view('admin.sponsored-ads-form', compact('ad'));
    }

    public function updateAd(Request $request, SponsoredAd $ad)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|max:2048',
            'image_url' => 'nullable|url|max:500',
            'video_url' => 'nullable|url|max:500',
            'link_url' => 'nullable|url|max:500',
            'link_label' => 'nullable|string|max:100',
            'advertiser_name' => 'nullable|string|max:255',
            'placement' => 'required|in:yard_sidebar,home_banner',
            'status' => 'required|in:draft,active,paused,expired',
            'priority' => 'nullable|integer|min:0|max:100',
            'budget' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        if ($request->hasFile('image')) {
            if ($ad->image_path) {
                Storage::disk('public')->delete($ad->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('ads', 'public');
            $validated['image_url'] = null;
        } elseif ($request->filled('image_url')) {
            if ($ad->image_path) {
                Storage::disk('public')->delete($ad->image_path);
            }
            $validated['image_path'] = null;
        }
        unset($validated['image']);

        $validated['priority'] = $validated['priority'] ?? 0;

        $ad->update($validated);

        return redirect()->route('admin.sponsored-ads')->with('success', 'Ad updated successfully.');
    }

    public function toggleAdStatus(SponsoredAd $ad)
    {
        $newStatus = match ($ad->status) {
            'active' => 'paused',
            'paused', 'draft' => 'active',
            default => $ad->status,
        };

        $ad->update(['status' => $newStatus]);

        return redirect()->back()->with('success', 'Ad status updated.');
    }

    public function deleteAd(SponsoredAd $ad)
    {
        if ($ad->image_path) {
            Storage::disk('public')->delete($ad->image_path);
        }
        $ad->delete();

        return redirect()->route('admin.sponsored-ads')->with('success', 'Ad deleted.');
    }
}
