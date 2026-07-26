<?php

namespace Tests\Feature;

use Tests\TestCase;

class RemovedLanguageRoutesTest extends TestCase
{
    public function test_old_language_urls_return_to_the_italian_homepage(): void
    {
        foreach (['it', 'en', 'fr', 'es'] as $locale) {
            $this->get('/language/'.$locale)
                ->assertRedirect(route('homepage'));
        }
    }
}
