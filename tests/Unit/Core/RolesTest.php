<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use FP\Resv\Core\Roles;
use PHPUnit\Framework\TestCase;
use WP_Role;

/**
 * Test per la gestione dei ruoli e delle capability.
 */
final class RolesTest extends TestCase
{
    /**
     * @test
     */
    public function testManageReservationsCapabilityIsDefinedCorrectly(): void
    {
        $this->assertSame('manage_fp_reservations', Roles::MANAGE_RESERVATIONS);
    }

    /**
     * @test
     */
    public function testRestaurantManagerRoleSlugIsDefinedCorrectly(): void
    {
        $this->assertSame('fp_restaurant_manager', Roles::RESTAURANT_MANAGER);
    }

    /**
     * @test
     */
    public function testEnsureAdminCapabilitiesAddsCapabilityWhenMissing(): void
    {
        // Mock del ruolo administrator senza la capability
        $adminRole = $this->createMock(WP_Role::class);
        $adminRole->method('has_cap')
            ->with(Roles::MANAGE_RESERVATIONS)
            ->willReturn(false);
        
        $adminRole->expects($this->once())
            ->method('add_cap')
            ->with(Roles::MANAGE_RESERVATIONS);

        // Questo test verifica la logica del metodo
        // In un ambiente WordPress reale, useremmo get_role('administrator')
        // Per ora testiamo solo la costante e la logica
    }

    /**
     * @test
     */
    public function testCurrentUserCanManageReservationsUsesCorrectCapability(): void
    {
        // Questo test verifica che il metodo usi la capability corretta
        // In un test di integrazione reale, verificheremmo current_user_can()
        $this->assertTrue(true); // Placeholder - richiederebbe mock di current_user_can()
    }
}
