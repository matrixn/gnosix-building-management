<?php

namespace App\Support;

class LandingContent
{
    public static function segments(): array
    {
        $segments = [
            'hotel' => ['Hoteluri & pensiuni', 'OSPITALITATE, FĂRĂ ÎNTRERUPERI', 'O experiență bună începe în spatele scenei.', 'De la aerul condiționat din camera 204 la centrala din subsol: istoricul tehnic ar trebui să fie la îndemâna echipei, chiar și când se schimbă tura.', ['Echipamente organizate pe camere și spații', 'Defecțiuni și intervenții cu istoric', 'Revizii, garanții și documente la un loc']],
            'developer' => ['Dezvoltatori', 'DE LA CONSTRUCȚIE LA CONTINUITATE', 'Predai clădirea. Păstrezi povestea întreagă.', 'Documentația și informațiile tehnice ar trebui să urmeze proprietatea după predare. Un pașaport digital poate deveni puntea dintre dezvoltator și viitoarea echipă de administrare.', ['Documentație structurată pentru predare', 'Echipamente și garanții legate de imobil', 'Istoric pregătit să crească după livrare']],
            'manager' => ['Administratori', 'O PERSPECTIVĂ ASUPRA PORTOFOLIULUI', 'Mai multe clădiri. Un mod comun de lucru.', 'O structură consecventă pentru documente, intervenții și echipamente, astfel încât schimbarea echipei să nu însemne și pierderea contextului.', ['Evidență tehnică pentru fiecare proprietate', 'Context comun pentru echipe și furnizori', 'Continuitate la schimbarea administratorului']],
        ];
        return array_map(fn ($s) => [__($s[0]), __($s[1]), __($s[2]), __($s[3]), array_map(fn ($item) => __($item), $s[4])], $segments);
    }
}
