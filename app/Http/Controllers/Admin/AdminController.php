<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Solidarity\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\SolidarityCampaign;
use App\Models\User;
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

        $users = $query->latest()->paginate(25);

        return view('admin.users', compact('users'));
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
        return view('admin.analytics', [
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
        ]);
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
