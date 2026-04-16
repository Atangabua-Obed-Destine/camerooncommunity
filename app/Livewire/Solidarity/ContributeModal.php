<?php

namespace App\Livewire\Solidarity;

use App\Enums\Solidarity\PaymentMethod;
use App\Enums\Solidarity\PaymentStatus;
use App\Models\SolidarityCampaign;
use App\Models\SolidarityContribution;
use App\Models\YardMessage;
use App\Enums\MessageType;
use Illuminate\Support\Str;
use Livewire\Component;

class ContributeModal extends Component
{
    public ?SolidarityCampaign $campaign = null;
    public bool $showModal = false;

    public $amount = '';
    public bool $isAnonymous = false;
    public string $message = '';
    public string $selectedPreset = '';

    public bool $submitted = false;
    public bool $isSuggesting = false;

    protected $listeners = ['open-contribute' => 'open'];

    public function open(int $campaignId)
    {
        $this->campaign = SolidarityCampaign::findOrFail($campaignId);
        $this->showModal = true;
        $this->reset(['amount', 'isAnonymous', 'message', 'selectedPreset', 'submitted']);
    }

    public function setPreset(string $amount)
    {
        $this->amount = $amount;
        $this->selectedPreset = $amount;
    }

    public function getFeeAttribute(): float
    {
        if (!is_numeric($this->amount) || $this->amount <= 0) return 0;
        return round($this->amount * ($this->campaign->platform_cut_percent / 100), 2);
    }

    public function getNetAttribute(): float
    {
        if (!is_numeric($this->amount) || $this->amount <= 0) return 0;
        return round($this->amount - $this->fee, 2);
    }

    public function confirm()
    {
        $this->validate([
            'amount' => 'required|numeric|min:1',
            'message' => 'nullable|string|max:200',
        ]);

        $user = auth()->user();
        $fee = round($this->amount * ($this->campaign->platform_cut_percent / 100), 2);
        $net = round($this->amount - $fee, 2);

        $contribution = SolidarityContribution::create([
            'tenant_id' => $user->tenant_id,
            'uuid' => Str::uuid()->toString(),
            'campaign_id' => $this->campaign->id,
            'contributor_id' => $user->id,
            'amount' => $this->amount,
            'platform_fee' => $fee,
            'net_amount' => $net,
            'currency' => $this->campaign->currency,
            'payment_method' => PaymentMethod::BankTransfer,
            'payment_status' => PaymentStatus::Pending,
            'is_anonymous' => $this->isAnonymous,
            'message' => $this->message ?: null,
        ]);

        // Update campaign totals
        $this->campaign->increment('current_amount', (float) $this->amount);
        $this->campaign->increment('total_contributors');

        // Post system message in room
        if ($this->campaign->room_id) {
            $displayName = $this->isAnonymous ? __('Someone') : $user->name;
            YardMessage::create([
                'tenant_id' => $user->tenant_id,
                'uuid' => Str::uuid()->toString(),
                'room_id' => $this->campaign->room_id,
                'user_id' => $user->id,
                'message_type' => MessageType::System,
                'content' => "❤️ {$displayName} just contributed to {$this->campaign->title}.",
            ]);
        }

        // Award points
        app(\App\Services\PointsService::class)->award($user, 'solidarity_contribution');

        $this->submitted = true;
    }

    public function close()
    {
        $this->showModal = false;
    }

    public function suggestMessage()
    {
        if (!$this->campaign) return;

        $this->isSuggesting = true;
        $aiService = app(\App\Services\AIService::class);
        $lang = auth()->user()?->language_pref ?? 'en';

        $suggestion = $aiService->suggestContributionMessage(
            $this->campaign->title,
            $this->campaign->category,
            $lang,
        );

        if ($suggestion) {
            $this->message = $suggestion;
        }

        $this->isSuggesting = false;
    }

    public function render()
    {
        return view('livewire.solidarity.contribute-modal');
    }
}
