<?php

namespace App\Service\Email;

/**
 * Énumération des priorités d'email
 */
enum EmailPriority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
}
