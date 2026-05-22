<x-layouts.guest>
    <x-slot name="title">{{ app()->getLocale() === 'fr' ? 'Politique de Confidentialité' : 'Privacy Policy' }} — Cameroon Network</x-slot>

    @php
        $sections = [
            [
                'icon' => '🛡️',
                'en' => ['title' => 'Your trust is our currency', 'body' => "We built Cameroon Network so Cameroonians, wherever they are, can find each other safely. We collect only what we need to make that connection possible — your name, email, language, and approximate location."],
                'fr' => ['title' => 'Votre confiance est notre monnaie', 'body' => "Nous avons créé Cameroon Network pour que les Camerounais, où qu'ils soient, puissent se retrouver en toute sécurité. Nous ne collectons que le strict nécessaire — nom, e-mail, langue et localisation approximative."],
            ],
            [
                'icon' => '📍',
                'en' => ['title' => 'Location, the right way', 'body' => "We use your city and region to suggest nearby community rooms. We never sell your coordinates, never track you in the background, and you can clear your location at any time from your profile."],
                'fr' => ['title' => 'La localisation, comme il faut', 'body' => "Nous utilisons votre ville et région pour suggérer des salons de communauté proches. Nous ne vendons jamais vos coordonnées, ne vous suivons pas en arrière-plan, et vous pouvez effacer votre localisation à tout moment depuis votre profil."],
            ],
            [
                'icon' => '💬',
                'en' => ['title' => 'Your messages belong to you', 'body' => "Conversations in GoConnect are stored to deliver them across your devices. We do not read them. We do not train AI on them. Reports of abuse are reviewed by humans, not algorithms."],
                'fr' => ['title' => 'Vos messages vous appartiennent', 'body' => "Les conversations du Yard sont stockées pour être livrées sur vos appareils. Nous ne les lisons pas. Nous n'entraînons aucune IA dessus. Les signalements sont examinés par des humains, pas des algorithmes."],
            ],
            [
                'icon' => '🍪',
                'en' => ['title' => 'Cookies — only the necessary ones', 'body' => "Session cookies keep you logged in. Preference cookies remember your language. That's it. No third-party advertising trackers, no behavioural fingerprinting."],
                'fr' => ['title' => 'Cookies — uniquement le nécessaire', 'body' => "Les cookies de session vous gardent connecté. Les cookies de préférences mémorisent votre langue. C'est tout. Aucun traceur publicitaire tiers, aucun profilage comportemental."],
            ],
            [
                'icon' => '🔐',
                'en' => ['title' => 'Your rights', 'body' => "You can export your data, correct it, or delete your account at any time. Email us at privacy@cameroonnetwork.org and we'll respond within 7 days."],
                'fr' => ['title' => 'Vos droits', 'body' => "Vous pouvez exporter vos données, les corriger ou supprimer votre compte à tout moment. Écrivez-nous à privacy@cameroonnetwork.org et nous répondrons sous 7 jours."],
            ],
        ];
    @endphp

    <x-legal.shell
        :badge="['en' => 'Privacy Policy', 'fr' => 'Politique de Confidentialité']"
        :title="['en' => 'Built on respect.', 'fr' => 'Bâti sur le respect.']"
        :subtitle="['en' => 'A clear, plain-English commitment to how we treat your data — because trust is what binds a community together.', 'fr' => 'Un engagement clair et sans jargon sur la façon dont nous traitons vos données — parce que la confiance est le ciment d\'une communauté.']"
        :sections="$sections"
        :updated="'May 2026'"
        accent="green"
    />
</x-layouts.guest>
