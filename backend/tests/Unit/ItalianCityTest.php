<?php

namespace Tests\Unit;

use App\Support\ItalianCity;
use PHPUnit\Framework\TestCase;

class ItalianCityTest extends TestCase
{
    public function test_normalizes_abbreviations_names_and_unknown_towns(): void
    {
        $this->assertSame(['city' => 'Catania', 'province' => 'CT'], ItalianCity::normalize('ct'));
        $this->assertSame(['city' => 'Catania', 'province' => 'CT'], ItalianCity::normalize(' CATANIA '));
        $this->assertSame(['city' => 'Forlì', 'province' => 'FC'], ItalianCity::normalize('forli'));
        $this->assertSame(['city' => "L'Aquila", 'province' => 'AQ'], ItalianCity::normalize("l'aquila"));
        $this->assertSame(['city' => 'Nola', 'province' => 'NA'], ItalianCity::normalize('nola (na)'));
        $this->assertSame(['city' => 'San Giovanni La Punta', 'province' => null], ItalianCity::normalize('SAN GIOVANNI LA PUNTA'));
        $this->assertSame(['city' => 'Torre del Greco', 'province' => null], ItalianCity::normalize('Torre del Greco'));
        $this->assertSame(['city' => null, 'province' => null], ItalianCity::normalize('  '));
    }
}
