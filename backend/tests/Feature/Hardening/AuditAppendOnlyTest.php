<?php

declare(strict_types=1);

namespace Tests\Feature\Hardening;

use App\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class AuditAppendOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_events_cannot_be_updated(): void
    {
        $event = AuditEvent::query()->create(['event' => 'test.created']);

        $this->expectException(LogicException::class);

        $event->event = 'test.changed';
        $event->save();
    }

    public function test_audit_events_cannot_be_deleted_without_retention_authorization(): void
    {
        $event = AuditEvent::query()->create(['event' => 'test.created']);

        $this->expectException(LogicException::class);

        $event->delete();
    }

    public function test_mass_assignment_cannot_authorize_deletion(): void
    {
        $event = AuditEvent::query()->create(['event' => 'test.created', 'allowDeletion' => true]);

        $this->expectException(LogicException::class);

        $event->delete();
    }

    public function test_retention_path_can_delete_when_explicitly_authorized(): void
    {
        $event = AuditEvent::query()->create(['event' => 'test.created']);

        $event->deleteForRetention();

        $this->assertDatabaseMissing('audit_events', ['id' => $event->id]);
    }
}
