<x-layouts.guest>
    <x-slot name="title">{{ app()->getLocale() === 'fr' ? 'Conditions d\'Utilisation' : 'Terms of Service' }} — Cameroon Network</x-slot>

    @php
        $sections = [
            [
                'icon' => '🤝',
                'en' => ['title' => 'A community, not a billboard', 'body' => "Cameroon Network is a free space for Cameroonians to chat, organise, help one another, and stay connected. By creating an account, you agree to use it in good faith — to build, not to break."],
                'fr' => ['title' => 'Une communauté, pas un panneau publicitaire', 'body' => "Cameroon Network est un espace libre pour que les Camerounais discutent, s'organisent, s'entraident et restent connectés. En créant un compte, vous vous engagez à l'utiliser de bonne foi — pour bâtir, pas pour briser."],
            ],
            [
                'icon' => '✋',
                'en' => ['title' => 'What is not allowed', 'body' => "Hate speech, harassment, scams, impersonation, illegal activity, and spam will result in immediate suspension. We do not negotiate on these — our community deserves better."],
                'fr' => ['title' => 'Ce qui n\'est pas autorisé', 'body' => "Discours haineux, harcèlement, arnaques, usurpation d'identité, activités illégales et spam entraîneront une suspension immédiate. Aucune négociation — notre communauté mérite mieux."],
            ],
            [
                'icon' => '🎁',
                'en' => ['title' => 'Solidarity with integrity', 'body' => "Fundraising campaigns through Solidarity are real causes for real people. Misuse of funds, fake campaigns, or false claims are taken seriously and may be reported to authorities."],
                'fr' => ['title' => 'La solidarité avec intégrité', 'body' => "Les campagnes de financement via Solidarité concernent de vraies causes pour de vraies personnes. Les détournements, fausses campagnes ou fausses déclarations sont pris au sérieux et peuvent être signalés aux autorités."],
            ],
            [
                'icon' => '⚖️',
                'en' => ['title' => 'Your account, your responsibility', 'body' => "Keep your password safe. You are responsible for what happens under your account. Tell us immediately at security@cameroonnetwork.org if you suspect unauthorised access."],
                'fr' => ['title' => 'Votre compte, votre responsabilité', 'body' => "Protégez votre mot de passe. Vous êtes responsable de ce qui se passe sous votre compte. Informez-nous immédiatement à security@cameroonnetwork.org en cas d'accès non autorisé."],
            ],
            [
                'icon' => '🔄',
                'en' => ['title' => 'Changes & ending the agreement', 'body' => "We may update these terms as the platform evolves. Major changes will be announced in The Yard. You can leave at any time by deleting your account — no questions asked, no strings attached."],
                'fr' => ['title' => 'Modifications & fin de l\'accord', 'body' => "Nous pouvons mettre à jour ces conditions au fur et à mesure de l'évolution de la plateforme. Les changements majeurs seront annoncés dans Le Yard. Vous pouvez partir à tout moment en supprimant votre compte — sans questions, sans conditions."],
            ],
        ];
    @endphp

    <x-legal.shell
        :badge="['en' => 'Terms of Service', 'fr' => 'Conditions d\'Utilisation']"
        :title="['en' => 'Simple rules. Strong community.', 'fr' => 'Règles simples. Communauté forte.']"
        :subtitle="['en' => 'The agreement between you and Cameroon Network — written in plain language, because legalese has no place between neighbours.', 'fr' => 'L\'accord entre vous et Cameroon Network — écrit en langage clair, car le jargon juridique n\'a pas sa place entre voisins.']"
        :sections="$sections"
        :updated="'May 2026'"
        accent="yellow"
    />
</x-layouts.guest>
