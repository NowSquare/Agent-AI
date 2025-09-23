<?php

return [
    "validation" => [
        "email_required" => "Vul uw e-mailadres in.",
        "email_invalid" => "Vul een geldig e-mailadres in.",
        "email_max" => "E-mailadres is te lang.",
        "code_required" => "Vul uw verificatiecode in.",
        "code_size" => "Verificatiecode moet 6 cijfers zijn.",
        "code_numeric" => "Verificatiecode mag alleen cijfers bevatten.",
    ],
    "verify" => [
        "invalid_code" => "Ongeldige verificatiecode.",
        "success" => "Succesvol ingelogd.",
        "rate_limited" => "Te veel pogingen. Probeer het over :minutes minuten opnieuw.",
    ],
    "challenge" => [
        "title" => "Uw Inlogcode",
        "preview" => "Hier is uw 6-cijferige inlogcode voor Agent AI",
        "greeting" => "Welkom terug! Gebruik de volgende code om in te loggen op uw account.",
        "code_instruction" => "Uw 6-cijferige inlogcode:",
        "expiry_notice" => "Deze code verloopt over :minutes minuten.",
        "security_notice" => "Als u deze code niet heeft aangevraagd, kunt u deze e-mail negeren.",
    ],
    "magic_link" => [
        "title" => "Inloglink",
        "preview" => "Klik om in te loggen bij Agent AI",
        "greeting" => "Welkom terug! Klik op de onderstaande knop om in te loggen op uw account.",
        "button" => "Inloggen",
        "expiry_notice" => "Deze link verloopt over :minutes minuten.",
        "security_notice" => "Als u deze link niet heeft aangevraagd, kunt u deze e-mail negeren.",
        "alternative_text" => "Als de knop niet werkt, kopieer en plak deze link in uw browser:",
        "expired" => "Deze inloglink is verlopen. Vraag een nieuwe aan.",
        "invalid" => "Deze inloglink is ongeldig of is al gebruikt.",
        "success" => "Succesvol ingelogd.",
    ],
    "logout" => [
        "success" => "Succesvol uitgelogd.",
    ],
];