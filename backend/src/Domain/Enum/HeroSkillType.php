<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum HeroSkillType: string
{
    case FRANTIC = 'FRANTIC';
    case STALWART = 'STALWART';
    case VITALIC = 'VITALIC';
    case SAVAGE = 'SAVAGE';
    case VIRULENT = 'VIRULENT';
    case SEARING = 'SEARING';
    case WARDEN = 'WARDEN';
    case RESURGENT = 'RESURGENT';
    case SUNDERING = 'SUNDERING';
    case RELENTLESS = 'RELENTLESS';
}
