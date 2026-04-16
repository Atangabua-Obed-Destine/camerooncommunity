<?php

namespace App\Livewire\Solidarity;

use App\Enums\MessageType;
use App\Enums\Solidarity\CampaignCategory;
use App\Enums\Solidarity\CampaignStatus;
use App\Models\SolidarityCampaign;
use App\Models\YardMessage;
use App\Models\YardRoom;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateCampaign extends Component
{
    use WithFileUploads;

    public int $step = 1;
    public ?int $roomId = null;

    // Step 1
    public string $title = '';
    public string $category = '';
    public string $beneficiaryName = '';
    public string $beneficiaryRelationship = '';
    public string $description = '';

    // Step 2
    public $targetAmount = '';
    public string $currency = 'GBP';
    public ?string $deadline = null;
    public $proofDocument;
    public bool $isAnonymousAllowed = true;

    public bool $showPanel = false;
    public bool $isDrafting = false;
    public string $draftSituation = '';

    protected $listeners = ['open-solidarity-create' => 'open'];

    public function open(int $roomId)
    {
        $this->roomId = $roomId;
        $this->showPanel = true;
    }

    public function close()
    {
        $this->showPanel = false;
        $this->reset(['step', 'title', 'category', 'beneficiaryName', 'beneficiaryRelationship', 'description', 'targetAmount', 'currency', 'deadline', 'proofDocument', 'isAnonymousAllowed']);
        $this->step = 1;
    }

    public function nextStep()
    {
        if ($this->step === 1) {
            $this->validate([
                'title' => 'required|string|max:200',
                'category' => 'required|string',
                'beneficiaryName' => 'required|string|max:200',
                'beneficiaryRelationship' => 'required|string|max:500',
                'description' => 'required|string|min:50|max:5000',
            ]);
        }
        $this->step = 2;
    }

    public function submit()
    {
        $this->validate([
            'targetAmount' => 'required|numeric|min:1',
            'currency' => 'required|in:GBP,EUR,USD,XAF,CAD',
            'deadline' => 'nullable|date|after:today',
            'proofDocument' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        $user = auth()->user();

        $proofPath = null;
        if ($this->proofDocument) {
            $proofPath = $this->proofDocument->store('solidarity/proofs', 'private');
        }

        $platformCut = \App\Models\PlatformSetting::getValue('solidarity_platform_cut', 5.00);

        $campaign = SolidarityCampaign::create([
            'tenant_id' => $user->tenant_id,
            'uuid' => Str::uuid()->toString(),
            'room_id' => $this->roomId,
            'created_by' => $user->id,
            'title' => $this->title,
            'description' => $this->description,
            'beneficiary_name' => $this->beneficiaryName,
            'beneficiary_relationship' => $this->beneficiaryRelationship,
            'category' => $this->category,
            'target_amount' => $this->targetAmount,
            'current_amount' => 0,
            'platform_cut_percent' => $platformCut,
            'currency' => $this->currency,
            'status' => CampaignStatus::PendingApproval,
            'is_anonymous_allowed' => $this->isAnonymousAllowed,
            'deadline' => $this->deadline,
            'proof_document' => $proofPath,
        ]);

        // Notify admins (via notification service)
        app(\App\Services\NotificationService::class)->notifyAdmins(
            'solidarity_pending',
            "New Solidarity campaign submitted: {$campaign->title}",
            ['campaign_id' => $campaign->id]
        );

        // AI Risk Assessment (runs after creation, non-blocking)
        $this->runAiRiskAssessment($campaign);

        $this->close();
        $this->dispatch('solidarity-created');
        session()->flash('message', __('Your Solidarity campaign has been submitted for review.'));
    }

    protected function runAiRiskAssessment(SolidarityCampaign $campaign): void
    {
        $aiService = app(\App\Services\AIService::class);
        if (!$aiService->isAvailable()) {
            return;
        }

        try {
            $result = $aiService->assessCampaignRisk(
                $campaign->title,
                $campaign->description,
                $campaign->category,
                $campaign->beneficiary_name,
                $campaign->target_amount,
            );

            $campaign->update([
                'ai_risk_score' => $result['risk_percentage'] ?? 0,
                'ai_risk_level' => $result['risk_score'] ?? 'low',
                'ai_risk_reason' => $result['reason'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // AI failure should never block campaign submission
        }
    }

    public function helpMeWrite()
    {
        if (trim($this->draftSituation) === '') {
            return;
        }

        $this->isDrafting = true;
        $aiService = app(\App\Services\AIService::class);
        $lang = auth()->user()?->language_pref ?? 'en';

        $draft = $aiService->draftCampaignDescription($this->draftSituation, $lang);

        if ($draft) {
            $this->description = $draft;
        }

        $this->isDrafting = false;
        $this->draftSituation = '';
    }

    public function render()
    {
        return view('livewire.solidarity.create-campaign', [
            'categories' => CampaignCategory::cases(),
        ]);
    }
}
