<?php

namespace App\Support;

class ContactSubjects
{
    public const SUBJECTS = [
        'pilot' => 'Participare la programul pilot',
        'hotel' => 'Administrare tehnică pentru hoteluri și pensiuni',
        'developer' => 'Pașaport digital și predarea clădirilor',
        'portfolio' => 'Administrarea unui portofoliu de clădiri',
        'partnership' => 'Parteneriate și colaborări',
        'general' => 'Alte întrebări despre GNOSIX',
    ];

    public static function options(): array
    {
        return array_map(fn ($label) => __($label), self::SUBJECTS);
    }
}
