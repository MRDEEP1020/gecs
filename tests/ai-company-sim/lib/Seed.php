<?php

namespace AiCompanySim;

// Déterministe par (pool, seed) — même seed = mêmes variantes choisies à
// chaque ré-exécution, mais des pools différents dans le même run paraissent
// indépendamment variés. Jamais de données "test123" : chaque pool est un
// tableau de chaînes réalistes écrites à la main (voir scenarios/*).
class Seed
{
    public static function generer(): int
    {
        return random_int(10000, 99999);
    }

    public static function pick(string $pool, int $seed, array $variants): mixed
    {
        $graine = crc32($pool.'-'.$seed);
        mt_srand($graine);
        $index = array_rand($variants);
        mt_srand(); // ne pas polluer tout autre tirage aléatoire du process

        return $variants[$index];
    }
}
