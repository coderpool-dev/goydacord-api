<?php

namespace Tests\Unit;

use App\Models\Presence\Game;
use PHPUnit\Framework\TestCase;

class GameNameTest extends TestCase
{
    public function test_genshin_aliases_normalize_to_display_name(): void
    {
        $this->assertSame('Genshin Impact', Game::normalizePublicName('Genshin Impact'));
        $this->assertSame('Genshin Impact', Game::normalizePublicName('GenshinImpact'));
        $this->assertSame('Genshin Impact', Game::normalizePublicName('YuanShen'));
        $this->assertSame('Genshin Impact', Game::normalizePublicName('原神'));
        $this->assertSame('Genshin Impact', Game::normalizePublicName('Genshin Impact game'));
    }

    public function test_real_detected_process_names_normalize_to_display_name(): void
    {
        $this->assertSame('Subnautica 2', Game::normalizePublicName('Subnautica2'));
        $this->assertSame('Subnautica 2', Game::normalizePublicName('Subnautica 2 0.1.2.2-128456'));
        $this->assertSame('S.T.A.L.K.E.R. 2: Heart of Chornobyl', Game::normalizePublicName('Stalker2'));
        $this->assertSame('S.T.A.L.K.E.R. 2: Heart of Chornobyl', Game::normalizePublicName('S.T.A.L.K.E.R. 2 Heart of Chornobyl'));
        $this->assertSame('Mafia: The Old Country', Game::normalizePublicName('Mafia The Old Country'));
        $this->assertSame('Into the Dead: Our Darkest Days', Game::normalizePublicName('IntoTheDeadOurDarkestDays'));
        $this->assertSame('Corruption of Champions II', Game::normalizePublicName('CoC2'));
        $this->assertSame('The Long Dark', Game::normalizePublicName('TheLongDark'));
        $this->assertSame('7 Days to Die', Game::normalizePublicName('7daystodie'));
        $this->assertSame('Minecraft', Game::normalizePublicName('Minecraft* 1.20.1'));
        $this->assertSame('Minecraft', Game::normalizePublicName('Minecraft* Forge 1.20.1'));
        $this->assertSame('Minecraft', Game::normalizePublicName('Minecraft 1.12.2'));
    }

    public function test_non_game_utility_names_are_hidden(): void
    {
        $this->assertNull(Game::normalizePublicName('DayZ Uninstaller'));
        $this->assertNull(Game::normalizePublicName('Wallpaper UI'));
        $this->assertNull(Game::normalizePublicName('Crosshair V2'));
    }
}
