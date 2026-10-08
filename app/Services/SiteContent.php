<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SiteContent
{
    public static function defaults(): array
    {
        return [
            'home' => ['eyebrow' => 'Your work. A better way forward.', 'title' => 'Software built around your business.', 'description' => 'Turn manual processes into connected, dependable systems. Custom web and mobile solutions for business, government, and education.', 'cta' => 'Let’s build your solution', 'services_heading' => 'Your challenges. Purpose-built solutions.', 'demo_heading' => 'See your next system in action.', 'process_heading' => 'Built together. Supported for the long run.', 'closing_heading' => 'What could work better?', 'closing_description' => 'Let’s talk about the system your organization needs.'],
            'about' => ['title' => 'Practical software. Built with purpose.', 'mission' => 'Our mission is to build dependable custom software that helps businesses, government offices, and schools simplify operations, improve service, and replace manual work.', 'description' => 'We begin with how your organization works, then build the tools to support it—from records and approvals to sales and connected systems.', 'founder_image' => '/images/founder-profile.jpg', 'founder_name' => 'Thenmarck V. Dulos', 'founder_role' => 'Founder & Lead Developer', 'founder_bio' => 'PixelForge.ai is a founder-led development team offering custom web and mobile applications, system integrations, and ongoing support.'],
            'services' => [
                ['icon' => '⌘', 'title' => 'Custom web applications', 'description' => 'Purpose-built platforms that fit your operations, from internal tools to customer-facing systems.'],
                ['icon' => '▣', 'title' => 'Mobile applications', 'description' => 'Keep your team and customers connected with applications built for work on the go.'],
                ['icon' => '⇄', 'title' => 'Integrations & migration', 'description' => 'Connect your existing tools and move your data into a system that works together.'],
                ['icon' => '⚙', 'title' => 'Maintenance & support', 'description' => 'Keep your software dependable with ongoing improvements, hosting, and technical support.'],
            ],
            'maintenance' => ['title' => 'Support beyond delivery.', 'description' => 'Optional hosting and maintenance plans can include technical support, updates, and ongoing improvements. We agree on scope, response expectations, and pricing in your custom quotation.'],
            'case_studies' => [],
            'booking' => ['title' => 'Let’s understand your work.', 'description' => 'Tell us what you need, then request a 30-minute discovery call. We prepare a custom quotation after discussing your requirements.'],
        ];
    }

    public static function all(): array
    {
        $content = self::defaults();
        foreach (DB::table('site_settings')->get() as $row) {
            $content[$row->key] = json_decode($row->value, true);
        }

        return $content;
    }
}
