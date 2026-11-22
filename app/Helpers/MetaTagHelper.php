<?php

namespace App\Helpers;

class MetaTagHelper
{
    /**
     * Generate meta tags array for Inertia
     *
     * @param string $title Page title
     * @param string|null $description Meta description
     * @param string|null $image OG image URL
     * @param array $options Additional options
     * @return array
     */
    public static function generate(
        string $title,
        ?string $description = null,
        ?string $image = null,
        array $options = []
    ): array {
        $appName = config('app.name', 'Rukhsana Packaging');
        $defaultDescription = 'Professional packaging solutions and quality materials for your business needs.';
        
        return [
            'metaTitle' => $title . ' - ' . $appName,
            'metaDescription' => $description ?? $defaultDescription,
            'metaKeywords' => $options['keywords'] ?? 'packaging, rukhsana packaging, packaging materials, business packaging',
            'ogImage' => $image ?? asset('images/og-image.jpg'),
            'ogType' => $options['type'] ?? 'website',
            'ogImageAlt' => $options['imageAlt'] ?? $appName . ' Preview',
            'twitterSite' => $options['twitterSite'] ?? null,
            'twitterCreator' => $options['twitterCreator'] ?? null,
        ];
    }

    /**
     * Preset meta tags for common pages
     */
    public static function forHome(): array
    {
        return self::generate(
            'Home',
            'Leading packaging solutions provider in Bangladesh. Quality materials, professional service, and competitive prices.',
            asset('images/og-home.jpg'),
            ['keywords' => 'rukhsana packaging, packaging bangladesh, quality packaging, professional packaging']
        );
    }

    public static function forJobs(): array
    {
        return self::generate(
            'রেজিষ্ট্রেশন ফরম',
            'কম্পানির নিয়ম কানুন গুলো জানতে এই লিংকের উপর চাপ দিন, তারপর রেজিষ্ট্রেশন ফরম পূরণ করুন',
            asset('images/og-jobs.jpg'),
            ['type' => 'website', 'keywords' => 'rukhsana packaging jobs, packaging careers, job opportunities bangladesh']
        );
    }

    public static function forApplications(): array
    {
        return self::generate(
            'Job Applications',
            'View and manage job applications for Rukhsana Packaging positions. Track candidates and make hiring decisions.',
            asset('images/og-applications.jpg'),
            ['keywords' => 'job applications, candidate management, hiring']
        );
    }

    public static function forJobPost(string $jobTitle, string $description): array
    {
        return self::generate(
            $jobTitle,
            $description,
            asset('images/og-jobs.jpg'),
            [
                'type' => 'article',
                'keywords' => "$jobTitle, rukhsana packaging jobs, careers"
            ]
        );
    }

    /**
     * Generate structured data for rich snippets
     */
    public static function structuredData(string $type = 'Organization'): array
    {
        $baseUrl = config('app.url');
        
        $data = [
            '@context' => 'https://schema.org',
            '@type' => $type,
        ];

        if ($type === 'Organization') {
            $data = array_merge($data, [
                'name' => config('app.name'),
                'url' => $baseUrl,
                'logo' => asset('images/logo.png'),
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'contactType' => 'Customer Service',
                    'email' => 'info@rukhsanapackaging.online',
                ],
                'sameAs' => [
                    // Add your social media URLs here
                    'https://www.facebook.com/rukhsanapackaging',
                    'https://www.linkedin.com/company/rukhsanapackaging',
                ],
            ]);
        }

        return $data;
    }

public static function forLalcoin(): array
{
    return self::generate(
        'লাল কয়েন বিক্রির আবেদন ফরম',
        'লাল কয়েন বিক্রির জন্য অনলাইনে আবেদন করুন। সহজে আবেদন ফরম পূরণ করে আপনার আবেদন জমা দিন।',
        asset('images/lalcoin-share.jpg'),
        [
            'type' => 'website',
            'keywords' => 'লাল কয়েন, লাল কয়েন আবেদন, Lalcoin',
            'imageAlt' => 'লাল কয়েন বিক্রির আবেদন ফরম',
        ]
    );
}
}
