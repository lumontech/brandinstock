<?php

namespace Tests\Unit;

use App\Support\LeadSource;
use PHPUnit\Framework\TestCase;

class LeadSourceTest extends TestCase
{
    public function test_free_text_is_mapped_to_a_lead_source(): void
    {
        $this->assertSame('social', LeadSource::match('Instagram'));
        $this->assertSame('social', LeadSource::match('Campagna Meta Ads'));
        $this->assertSame('google', LeadSource::match('Google Ads'));
        $this->assertSame('fiera', LeadSource::match('Fiera Pitti Uomo'));
        $this->assertSame('passaparola', LeadSource::match('Passaparola'));
        $this->assertSame('cliente_esistente', LeadSource::match('Già cliente'));
        $this->assertSame('cold_call', LeadSource::match('cold_call'));
        $this->assertSame('whatsapp', LeadSource::match('WhatsApp'));
        $this->assertNull(LeadSource::match('Boh qualcosa'));
        $this->assertNull(LeadSource::match(''));
    }
}
