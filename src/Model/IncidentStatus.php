<?php

namespace App\Model;

enum IncidentStatus: string
{
    case Reported = 'reported';
    case Validated = 'validated';
    case FalseAlert = 'false_alert';
    case Resolved = 'resolved';
}