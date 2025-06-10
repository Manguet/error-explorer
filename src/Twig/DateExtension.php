<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('diffForHumans', function (\DateTimeInterface $dateTime) {
                $now = new \DateTime();
                $diff = $now->diff($dateTime);

                if ($diff->y > 0) {
                    return $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
                }

                if ($diff->m > 0) {
                    return $diff->m . ' mois';
                }

                if ($diff->d > 0) {
                    return $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
                }

                if ($diff->h > 0) {
                    return $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
                }

                if ($diff->i > 0) {
                    return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
                }

                return 'à l\'instant';
            }),
            new TwigFilter('date_diff', function (\DateTimeInterface $dateTime) {
                $now = new \DateTime();
                $diff = $now->diff($dateTime);

                return sprintf(
                    '%d an%s, %d mois, %d jour%s, %d heure%s, %d minute%s',
                    $diff->y,
                    $diff->y > 1 ? 's' : '',
                    $diff->m,
                    $diff->d,
                    $diff->d > 1 ? 's' : '',
                    $diff->h,
                    $diff->h > 1 ? 's' : '',
                    $diff->i,
                    $diff->i > 1 ? 's' : ''
                );
            }),
        ];
    }
}
