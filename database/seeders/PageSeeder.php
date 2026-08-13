<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Page::factory()->published()->create([
            'title' => 'About us',
            'slug' => 'about-us',
            'excerpt' => 'Learn more about our team, values, and mission.',
            'body' => 'We build thoughtful products that help teams do their best work.',
        ]);

        Page::factory()->published()->create([
            'title' => 'Contact',
            'slug' => 'contact',
            'excerpt' => 'Find the best way to get in touch with our team.',
            'body' => 'Send us a message and a member of our team will get back to you.',
        ]);

        Page::factory()->published()->create([
            'title' => 'Privacy policy',
            'slug' => 'privacy-policy',
            'excerpt' => 'How we collect, use, and protect your information.',
            'body' => 'This privacy policy explains how information is handled when you use our services.',
        ]);

        Page::factory()->draft()->create([
            'title' => 'Services',
            'slug' => 'services',
            'excerpt' => 'An overview of the services we provide.',
            'body' => 'This draft page is ready for your service details.',
        ]);
    }
}
