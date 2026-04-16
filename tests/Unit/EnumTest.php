<?php

namespace Tests\Unit;

use App\Enums\Language;
use App\Enums\RoomType;
use App\Enums\MessageType;
use App\Enums\Solidarity\CampaignCategory;
use App\Enums\Solidarity\CampaignStatus;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    public function test_room_type_has_all_cases(): void
    {
        $cases = RoomType::cases();
        $this->assertCount(4, $cases);
        $this->assertSame('national', RoomType::National->value);
        $this->assertSame('city', RoomType::City->value);
        $this->assertSame('private_group', RoomType::PrivateGroup->value);
        $this->assertSame('direct_message', RoomType::DirectMessage->value);
    }

    public function test_room_type_labels_return_strings(): void
    {
        foreach (RoomType::cases() as $case) {
            $this->assertIsString($case->label());
            $this->assertNotEmpty($case->label());
        }
    }

    public function test_room_type_icons_return_strings(): void
    {
        foreach (RoomType::cases() as $case) {
            $this->assertIsString($case->icon());
        }
    }

    public function test_message_type_has_expected_cases(): void
    {
        $this->assertSame('text', MessageType::Text->value);
        $this->assertSame('system', MessageType::System->value);
        $this->assertSame('solidarity_card', MessageType::SolidarityCard->value);
        $this->assertCount(9, MessageType::cases());
    }

    public function test_campaign_status_labels(): void
    {
        $this->assertSame('Pending Approval', CampaignStatus::PendingApproval->label());
        $this->assertSame('Active', CampaignStatus::Active->label());
        $this->assertSame('Goal Reached', CampaignStatus::GoalReached->label());
    }

    public function test_campaign_status_colors(): void
    {
        $this->assertSame('yellow', CampaignStatus::PendingApproval->color());
        $this->assertSame('blue', CampaignStatus::Active->color());
        $this->assertSame('red', CampaignStatus::Rejected->color());
    }

    public function test_campaign_category_labels_and_icons(): void
    {
        foreach (CampaignCategory::cases() as $case) {
            $this->assertIsString($case->label());
            $this->assertNotEmpty($case->label());
            $this->assertIsString($case->icon());
        }
    }

    public function test_language_enum_has_en_and_fr(): void
    {
        $this->assertSame('en', Language::English->value);
        $this->assertSame('fr', Language::French->value);
        $this->assertCount(2, Language::cases());
    }
}
